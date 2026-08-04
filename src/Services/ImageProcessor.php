<?php

namespace App\Services;

use RuntimeException;

/**
 * Image & Media Processing Safety.
 *
 * - Re-encodes images via GD to strip malicious EXIF metadata, embedded PHP, GPS coords.
 * - SVG sanitization via DOMDocument (strips <script>, onload, xlink:href).
 * - Thumbnail generation with configurable dimensions.
 *
 * Usage:
 *   ImageProcessor::sanitize('/path/to/image.jpg');
 *   ImageProcessor::thumbnail('/path/to/image.jpg', 300, 300);
 *   ImageProcessor::sanitizeSvg($svgContent);
 */
final class ImageProcessor
{
    /**
     * Re-encodes an image to strip all metadata (EXIF, GPS, embedded code).
     *
     * Creates a new clean image from the pixel data only — any
     * non-pixel payload (PHP tags, EXIF, GPS coordinates) is discarded.
     *
     * @param string $path    Absolute path to the image file.
     * @param int    $quality Output quality (1-100, default 85).
     * @return bool True if re-encoding succeeded.
     */
    public static function sanitize(string $path, int $quality = 85): bool
    {
        $info = @getimagesize($path);
        if ($info === false) {
            return false;
        }

        $mime = $info['mime'] ?? '';

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif'  => @imagecreatefromgif($path),
            default      => false,
        };

        if ($source === false) {
            return false;
        }

        // Preserve transparency for PNG and WebP.
        if (in_array($mime, ['image/png', 'image/webp'], true)) {
            imagesavealpha($source, true);
            imagealphablending($source, false);
        }

        $result = match ($mime) {
            'image/jpeg' => imagejpeg($source, $path, $quality),
            'image/png'  => imagepng($source, $path, min(9, (int) ((100 - $quality) / 11))),
            'image/webp' => imagewebp($source, $path, $quality),
            'image/gif'  => imagegif($source, $path),
            default      => false,
        };

        imagedestroy($source);

        return $result;
    }

    /**
     * Generates a thumbnail from an image.
     *
     * @param string $sourcePath   Path to the source image.
     * @param int    $maxWidth     Maximum thumbnail width.
     * @param int    $maxHeight    Maximum thumbnail height.
     * @param string|null $destPath Destination path (defaults to source_thumb.ext).
     * @return string|false The thumbnail path, or false on failure.
     */
    public static function thumbnail(
        string  $sourcePath,
        int     $maxWidth = 300,
        int     $maxHeight = 300,
        ?string $destPath = null,
    ): string|false {
        $info = @getimagesize($sourcePath);
        if ($info === false) {
            return false;
        }

        [$origWidth, $origHeight] = $info;
        $mime = $info['mime'] ?? '';

        // Calculate proportional dimensions.
        $ratio  = min($maxWidth / $origWidth, $maxHeight / $origHeight);
        $newW   = (int) round($origWidth * $ratio);
        $newH   = (int) round($origHeight * $ratio);

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png'  => @imagecreatefrompng($sourcePath),
            'image/webp' => @imagecreatefromwebp($sourcePath),
            default      => false,
        };

        if ($source === false) {
            return false;
        }

        $thumb = imagecreatetruecolor($newW, $newH);
        if ($thumb === false) {
            imagedestroy($source);
            return false;
        }

        // Preserve transparency.
        if (in_array($mime, ['image/png', 'image/webp'], true)) {
            imagesavealpha($thumb, true);
            imagealphablending($thumb, false);
        }

        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newW, $newH, $origWidth, $origHeight);

        $destPath = $destPath ?? self::thumbPath($sourcePath);
        $ext      = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));

        $result = match ($ext) {
            'jpg', 'jpeg' => imagejpeg($thumb, $destPath, 85),
            'png'         => imagepng($thumb, $destPath, 6),
            'webp'        => imagewebp($thumb, $destPath, 85),
            default       => imagejpeg($thumb, $destPath, 85),
        };

        imagedestroy($source);
        imagedestroy($thumb);

        return $result ? $destPath : false;
    }

    /**
     * Sanitizes SVG content by stripping dangerous elements and attributes.
     *
     * Removes <script>, event handlers (onload, onclick, etc.),
     * and external xlink:href references to prevent XSS.
     *
     * @param string $svgContent Raw SVG XML string.
     * @return string Sanitized SVG string.
     */
    public static function sanitizeSvg(string $svgContent): string
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadXML($svgContent, LIBXML_NONET | LIBXML_NOENT);

        // Remove <script> elements.
        $scripts = $dom->getElementsByTagName('script');
        while ($scripts->length > 0) {
            $scripts->item(0)->parentNode->removeChild($scripts->item(0));
        }

        // Remove all event handler attributes and dangerous attrs.
        $dangerousAttrs = [
            'onload', 'onerror', 'onclick', 'onmouseover', 'onfocus',
            'onblur', 'onmouseout', 'onsubmit', 'onchange', 'oninput',
        ];

        $xpath = new \DOMXPath($dom);
        $allElements = $xpath->query('//*');

        if ($allElements !== false) {
            foreach ($allElements as $element) {
                // Remove event handlers.
                foreach ($dangerousAttrs as $attr) {
                    if ($element->hasAttribute($attr)) {
                        $element->removeAttribute($attr);
                    }
                }

                // Remove xlink:href pointing to external resources or javascript.
                $xlinkHref = $element->getAttributeNS('http://www.w3.org/1999/xlink', 'href');
                if ($xlinkHref && (
                    str_starts_with($xlinkHref, 'javascript:') ||
                    str_starts_with($xlinkHref, 'data:') ||
                    str_starts_with($xlinkHref, 'http://') ||
                    str_starts_with($xlinkHref, 'https://')
                )) {
                    $element->removeAttributeNS('http://www.w3.org/1999/xlink', 'href');
                }

                // Remove href with javascript: protocol.
                $href = $element->getAttribute('href');
                if ($href && str_starts_with(strtolower(trim($href)), 'javascript:')) {
                    $element->removeAttribute('href');
                }
            }
        }

        libxml_clear_errors();

        return $dom->saveXML() ?: '';
    }

    /**
     * Generates a thumbnail file path from the source path.
     */
    private static function thumbPath(string $sourcePath): string
    {
        $info = pathinfo($sourcePath);
        return $info['dirname'] . '/' . $info['filename'] . '_thumb.' . ($info['extension'] ?? 'jpg');
    }
}

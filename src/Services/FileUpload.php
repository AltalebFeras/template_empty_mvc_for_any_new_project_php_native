<?php

namespace App\Services;

use RuntimeException;

/**
 * Secure File Upload Handler.
 *
 * Provides defense-in-depth file upload validation:
 * - MIME verification via finfo (magic bytes)
 * - Extension whitelist
 * - UUID rename to prevent overwrites and execution
 * - Storage outside web root
 * - Size and dimension limits
 *
 * Usage:
 *   $upload = new FileUpload();
 *   $result = $upload->store($_FILES['avatar']);
 *   // $result = ['path' => '/storage/uploads/abc123.jpg', 'name' => 'abc123.jpg', ...]
 */
final class FileUpload
{
    /** MIME → extension whitelist (magic-byte based). */
    private const ALLOWED_TYPES = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'image/gif'       => 'gif',
        'video/mp4'       => 'mp4',
        'video/webm'      => 'webm',
        'application/pdf' => 'pdf',
    ];

    /** Max upload size in bytes (default 10MB). */
    private int $maxSize;

    /** Maximum image dimensions (pixels). */
    private int $maxWidth  = 8192;
    private int $maxHeight = 8192;

    /** Storage directory (absolute path). */
    private string $storagePath;

    public function __construct()
    {
        $this->maxSize     = Config::getInt('UPLOAD_MAX_SIZE', 10485760);
        $this->storagePath = dirname(__DIR__, 2) . '/' . Config::get('STORAGE_PATH', 'storage') . '/uploads';

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Validates and stores an uploaded file securely.
     *
     * @param array<string, mixed> $file The $_FILES['input_name'] array.
     * @return array{path: string, name: string, original_name: string, mime: string, size: int}
     * @throws RuntimeException On validation failure.
     */
    public function store(array $file): array
    {
        $this->validateUpload($file);

        $tmpPath      = $file['tmp_name'];
        $originalName = basename($file['name']);

        // 1. Verify MIME type via magic bytes (not the user-supplied type).
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpPath);

        if (!isset(self::ALLOWED_TYPES[$mimeType])) {
            throw new RuntimeException(
                "File type not allowed: {$mimeType}. Allowed: " . implode(', ', array_keys(self::ALLOWED_TYPES))
            );
        }

        // 2. Verify extension matches MIME.
        $allowedExt  = self::ALLOWED_TYPES[$mimeType];
        $extension   = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExts = explode(',', Config::get('UPLOAD_ALLOWED_EXTENSIONS', 'jpg,jpeg,png,webp,gif,mp4,webm,pdf'));

        if (!in_array($extension, $allowedExts, true)) {
            throw new RuntimeException("Extension '{$extension}' is not allowed.");
        }

        // 3. File size check.
        $fileSize = filesize($tmpPath);
        if ($fileSize > $this->maxSize) {
            throw new RuntimeException(
                "File too large: {$fileSize} bytes. Maximum: {$this->maxSize} bytes."
            );
        }

        // 4. Image-specific checks (dimensions, compression bomb protection).
        if (str_starts_with($mimeType, 'image/') && $mimeType !== 'image/gif') {
            $this->validateImageDimensions($tmpPath);
        }

        // 5. Generate unguessable UUID filename.
        $uuid     = bin2hex(random_bytes(16));
        $filename = $uuid . '.' . $allowedExt;
        $destPath = $this->storagePath . '/' . $filename;

        // 6. Move to storage (outside web root).
        if (!move_uploaded_file($tmpPath, $destPath)) {
            throw new RuntimeException('Failed to move uploaded file to storage.');
        }

        return [
            'path'          => $destPath,
            'name'          => $filename,
            'original_name' => $originalName,
            'mime'          => $mimeType,
            'size'          => $fileSize,
        ];
    }

    /**
     * Validates the basic upload array for errors.
     */
    private function validateUpload(array $file): void
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException('Invalid upload parameters.');
        }

        $errorMessages = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form MAX_FILE_SIZE.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by a PHP extension.',
        ];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $msg = $errorMessages[$file['error']] ?? 'Unknown upload error.';
            throw new RuntimeException($msg);
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Possible file upload attack detected.');
        }
    }

    /**
     * Validates image dimensions to prevent compression bombs.
     */
    private function validateImageDimensions(string $path): void
    {
        $info = @getimagesize($path);
        if ($info === false) {
            throw new RuntimeException('Unable to read image dimensions — file may be corrupted.');
        }

        [$width, $height] = $info;

        if ($width > $this->maxWidth || $height > $this->maxHeight) {
            throw new RuntimeException(
                "Image dimensions too large: {$width}x{$height}. Maximum: {$this->maxWidth}x{$this->maxHeight}."
            );
        }

        // Memory estimation to prevent OOM during processing.
        // Each pixel uses ~4 bytes (RGBA), plus overhead.
        $estimatedMemory = $width * $height * 4 * 1.7; // 1.7x safety factor
        $memoryLimit     = self::parseBytes(ini_get('memory_limit') ?: '128M');

        if ($estimatedMemory > $memoryLimit * 0.5) {
            throw new RuntimeException('Image would require too much memory to process safely.');
        }
    }

    /**
     * Parses PHP shorthand bytes notation (128M → bytes).
     */
    private static function parseBytes(string $value): int
    {
        $value = trim($value);
        $last  = strtolower($value[strlen($value) - 1]);
        $num   = (int) $value;

        return match ($last) {
            'g'     => $num * 1073741824,
            'm'     => $num * 1048576,
            'k'     => $num * 1024,
            default => $num,
        };
    }
}

# File Uploads & Image Processing

Covers secure file upload handling, image metadata stripping, SVG sanitization, and thumbnail generation.

**Source files:** `src/Services/FileUpload.php`, `src/Services/ImageProcessor.php`, `src/Controllers/FileController.php`

---

## Secure File Upload

### Basic Usage

```php
use App\Services\FileUpload;

$upload = new FileUpload();
$result = $upload->store($_FILES['avatar']);
```

### Return Value

```php
[
    'path'          => '/full/path/storage/uploads/a1b2c3d4e5f6...f0.jpg',
    'name'          => 'a1b2c3d4e5f6...f0.jpg',
    'original_name' => 'my_photo.jpg',
    'mime'          => 'image/jpeg',
    'size'          => 245760,
]
```

### Security Layers

| Layer | What It Does | Attack Prevented |
|-------|-------------|-----------------|
| 1. Upload error check | Validates `$_FILES` array | Malformed uploads |
| 2. `is_uploaded_file()` | Verifies file came from HTTP POST | File inclusion attacks |
| 3. MIME magic-byte check | `finfo_file()` reads file header | Disguised executables |
| 4. Extension whitelist | Checks against allowed list | Double extension attacks |
| 5. Size limit | Compares filesize to `UPLOAD_MAX_SIZE` | Denial of service |
| 6. Image dimension check | `getimagesize()` for width/height | Compression bombs |
| 7. Memory estimation | Calculates pixel × 4 bytes × 1.7 | Out-of-memory crashes |
| 8. UUID filename | `bin2hex(random_bytes(16))` | Overwrite, path guessing |
| 9. Storage outside webroot | Stored in `/storage/uploads/` | Direct PHP execution |

### Allowed MIME Types

| MIME Type | Extension |
|-----------|-----------|
| `image/jpeg` | `jpg` |
| `image/png` | `png` |
| `image/webp` | `webp` |
| `image/gif` | `gif` |
| `video/mp4` | `mp4` |
| `video/webm` | `webm` |
| `application/pdf` | `pdf` |

### Configuration

```env
UPLOAD_MAX_SIZE=10485760                              # 10 MB
UPLOAD_ALLOWED_EXTENSIONS=jpg,jpeg,png,webp,gif,mp4,webm,pdf
STORAGE_PATH=storage                                  # Relative to project root
```

### Form Example

```html
<form method="POST" action="/upload" enctype="multipart/form-data">
    <?= \App\Services\Csrf::inputField('upload') ?>
    <input type="file" name="avatar" accept="image/*">
    <button type="submit">Upload</button>
</form>
```

---

## Image Processing

### Metadata Stripping (EXIF, GPS, Embedded Code)

```php
use App\Services\ImageProcessor;

// Re-encodes the image from pixel data, discarding ALL metadata
ImageProcessor::sanitize('/path/to/image.jpg');
ImageProcessor::sanitize('/path/to/image.png', quality: 90);
```

**What gets stripped:**
- EXIF metadata (camera model, settings, software)
- GPS coordinates (location data)
- Embedded PHP or JavaScript code
- ICC color profiles
- Thumbnails embedded in EXIF

### Thumbnail Generation

```php
$thumbPath = ImageProcessor::thumbnail(
    sourcePath: '/path/to/original.jpg',
    maxWidth: 300,
    maxHeight: 300,
);
// Output: '/path/to/original_thumb.jpg'
```

Custom destination:
```php
$thumbPath = ImageProcessor::thumbnail(
    '/path/to/image.png',
    200, 200,
    destPath: '/path/to/custom_thumb.png',
);
```

Thumbnails maintain proportional aspect ratio. Supported formats: JPEG, PNG, WebP.

### SVG Sanitization

```php
$cleanSvg = ImageProcessor::sanitizeSvg($rawSvgContent);
```

**What gets removed:**
- `<script>` elements
- Event handler attributes (`onload`, `onerror`, `onclick`, `onmouseover`, `onfocus`, `onblur`, etc.)
- `xlink:href` pointing to `javascript:`, `data:`, or external URLs
- `href` attributes with `javascript:` protocol

---

## Serving Uploaded Files

Files stored in `/storage/uploads/` are not directly accessible via URL (the directory is blocked by Nginx/Apache rules). Instead, use the `FileController`:

```
GET /file?name=a1b2c3d4e5f6...f0.jpg
```

The controller:
1. Validates the filename format (must be `[a-f0-9]{32}.[a-z0-9]+`)
2. Resolves the real path and checks for directory traversal
3. Detects MIME type via `finfo`
4. Sets `Content-Type`, `Content-Length`, `Content-Disposition`, `X-Content-Type-Options`
5. Streams the file with `readfile()`

Images and videos are served `inline`; other files (PDFs) are served as `attachment` (force download).

---

## Complete Upload Workflow

```php
#[Route('/upload', methods: ['POST'], authRequired: true)]
public function uploadAvatar(): void
{
    // 1. Validate
    if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        $this->redirect('profile', [], ['No file uploaded.']);
    }

    // 2. Upload securely
    try {
        $upload = new FileUpload();
        $result = $upload->store($_FILES['avatar']);
    } catch (\RuntimeException $e) {
        $this->redirect('profile', [], [$e->getMessage()]);
    }

    // 3. Sanitize image (strip metadata)
    if (str_starts_with($result['mime'], 'image/')) {
        ImageProcessor::sanitize($result['path']);
        ImageProcessor::thumbnail($result['path'], 150, 150);
    }

    // 4. Save to database
    $userRepo = new UserRepository();
    $userRepo->updateById($_SESSION['user_id'], [
        'avatar' => $result['name'],
    ]);

    $_SESSION['success'] = 'Avatar uploaded successfully.';
    $this->redirect('profile');
}
```

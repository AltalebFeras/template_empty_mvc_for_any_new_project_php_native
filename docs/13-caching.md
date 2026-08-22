# Caching & Output Optimization

Covers multi-tier caching, response compression, ETag caching, and OPcache tuning.

**Source files:** `src/Services/Cache.php`, `src/Services/ResponseCompressor.php`, `opcache.ini`

---

## Multi-Tier Cache (`Cache`)

The `Cache` service implements a 3-tier caching hierarchy:

```
Request → L1: Memory (Per-request array)
             │
             ▼ (if miss)
          L2: Redis (Cross-request, if available)
             │
             ▼ (if miss)
          L3: File Cache (/storage/cache/)
```

### Hierarchy Details

1. **L1 Memory Cache**: Per-request static array. Zero-latency lookups for repeated reads within a single HTTP request.
2. **L2 Redis Cache**: Cross-request store via Predis. High-speed, shared across workers.
3. **L3 File Fallback**: Stored in `/storage/cache/` as JSON files with expiration timestamps. Used automatically if Redis is unavailable or unconfigured.

---

## Cache API

### `Cache::get(string $key): mixed`

Retrieves a value from cache. Tries L1 → L2 → L3. Promotes retrieved values to higher tiers automatically.

```php
use App\Services\Cache;

$user = Cache::get('user:42');
if ($user !== null) {
    // Cache hit
}
```

### `Cache::set(string $key, mixed $value, int $ttl = 3600): void`

Stores a value in cache with a TTL (Time-To-Live in seconds, default 1 hour).

```php
Cache::set('user:42', $userData, 3600);
```

### `Cache::remember(string $key, int $ttl, callable $compute): mixed`

Gets a cached value or executes the callback, stores the result, and returns it.

```php
$stats = Cache::remember('dashboard:stats', 600, function() use ($db) {
    return $db->queryHeavyStatistics();
});
```

### `Cache::delete(string $key): void`

Removes a key from all cache tiers.

```php
Cache::delete('user:42');
```

### `Cache::flush(): void`

Clears memory cache, Redis keys prefixed with `cache:`, and file cache files.

```php
Cache::flush();
```

---

## Response Compressor (`ResponseCompressor`)

Combines gzip output compression and ETag conditional caching.

### HTTP Gzip Compression

```php
use App\Services\ResponseCompressor;

// Call early in request (e.g. init.php or controller)
ResponseCompressor::start();
```

If the client sends `Accept-Encoding: gzip`, output buffering is initialized with `ob_gzhandler`.

### ETag & 304 Not Modified

```php
// Call before output finishes
ResponseCompressor::finish(maxAge: 3600, isPublic: true);
```

1. Hashes the output buffer content with `md5()`.
2. Sends `ETag: "hash"`.
3. Compares with client's `If-None-Match` header.
4. If matched, discards body and returns `304 Not Modified`.

---

## Automated Static Asset Cache Busting (`asset_url()`)

To prevent browsers and CDN edge caches from serving stale CSS, JavaScript, logos, and images after deployments, all static resources are loaded using the `asset_url()` helper.

### How It Works

Located in `src/init.php`:
```php
function asset_url(string $path): string
{
    $baseUrl = Config::baseUrl();
    $cleanPath = '/' . ltrim($path, '/');
    $publicPath = dirname(__DIR__) . '/public' . $cleanPath;
    if (file_exists($publicPath)) {
        $hash = md5_file($publicPath);
        $version = $hash ? substr($hash, 0, 10) : (string)@filemtime($publicPath);
    } else {
        $version = (string)time();
    }
    return $baseUrl . $cleanPath . '?v=' . $version;
}
```

1. Resolves the real physical path of the asset in `public/`.
2. Computes the MD5 file content hash.
3. Appends `?v=<hash>` to the asset URL.
4. When any asset is updated, the hash changes automatically, bypassing stale client and CDN caches.

### Usage in Views

```php
<!-- Images and Logos -->
<img src="<?= asset_url('assets/imgs/logo.svg') ?>" alt="Logo">

<!-- Stylesheets and Scripts -->
<link rel="stylesheet" href="<?= asset_url('assets/css/global.css') ?>">
<script src="<?= asset_url('assets/js/main.js') ?>" defer></script>
```

---

## OPcache Configuration

Production-tuned bytecode caching configuration in `opcache.ini`:

```ini
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
opcache.save_comments=1
opcache.fast_shutdown=1
opcache.jit_buffer_size=64M
```

> ⚠️ Set `opcache.validate_timestamps=0` in immutable container environments (Docker). In non-container production where code changes without restarting PHP-FPM, set `opcache.validate_timestamps=1` and `opcache.revalidate_freq=2`.

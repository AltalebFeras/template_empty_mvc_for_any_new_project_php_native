<?php

namespace App\Controllers;

use App\Abstracts\AbstractController;
use App\Services\Authorization;
use App\Services\Config;
use App\Services\Route;

/**
 * File Controller — serves uploaded files securely from storage.
 *
 * Files are stored outside the web root (/storage/uploads/) and served
 * through this controller with proper headers and authorization checks.
 */
class FileController extends AbstractController
{
    /**
     * Serves a file from storage with security headers.
     *
     * GET /file/{filename}
     *
     * Authorization: only the file owner or an admin can access.
     * Sets Content-Type, Content-Disposition, and X-Content-Type-Options headers.
     */
    #[Route('/file', methods: ['GET'], authRequired: true)]
    public function serve(): void
    {
        $filename = $_GET['name'] ?? '';
        if ($filename === '' || !preg_match('/^[a-f0-9]{32}\.[a-z0-9]+$/', $filename)) {
            http_response_code(400);
            echo 'Invalid file request.';
            exit;
        }

        $storagePath = dirname(__DIR__, 2) . '/' . Config::get('STORAGE_PATH', 'storage') . '/uploads';
        $filePath    = $storagePath . '/' . $filename;

        // Prevent path traversal.
        $realPath = realpath($filePath);
        $realBase = realpath($storagePath);

        if ($realPath === false || $realBase === false || !str_starts_with($realPath, $realBase)) {
            http_response_code(404);
            echo 'File not found.';
            exit;
        }

        if (!file_exists($realPath)) {
            http_response_code(404);
            echo 'File not found.';
            exit;
        }

        // Detect MIME type from file content.
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($realPath);

        // Security headers for served files.
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($realPath));

        // Images/videos: inline. Documents: attachment (force download).
        if (str_starts_with($mimeType, 'image/') || str_starts_with($mimeType, 'video/')) {
            header('Content-Disposition: inline; filename="' . $filename . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }

        // Cache for 1 hour for authenticated users.
        header('Cache-Control: private, max-age=3600');

        readfile($realPath);
        exit;
    }
}

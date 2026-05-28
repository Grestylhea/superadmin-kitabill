<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    /**
     * Display a listing of backups.
     */
    public function index()
    {
        $disk = Storage::disk('local');
        $backupName = config('backup.backup.name', 'laravel-backup');
        $files = $disk->allFiles($backupName);

        $backups = [];
        foreach ($files as $file) {
            if (str_ends_with($file, '.zip')) {
                $backups[] = [
                    'filename' => basename($file),
                    'path' => $file,
                    'size' => $this->formatBytes($disk->size($file)),
                    'date' => date('Y-m-d H:i:s', $disk->lastModified($file)),
                ];
            }
        }

        // Sort by date descending
        usort($backups, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        return Inertia::render('SuperAdmin/Backups/Index', [
            'backups' => $backups,
        ]);
    }

    /**
     * Download a backup file.
     */
    public function download($filename)
    {
        $backupName = config('backup.backup.name', 'laravel-backup');
        $filePath = storage_path('app/private/' . $backupName . '/' . $filename);

        // Security check: ensure file exists, is a zip file, and prevent path traversal
        if (!file_exists($filePath) || !str_ends_with($filename, '.zip') || str_contains($filename, '..')) {
            abort(404, 'File not found or access denied.');
        }

        // Clear output buffers to prevent file corruption
        if (ob_get_level()) {
            ob_end_clean();
        }

        return response()->download($filePath, $filename, [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * Helper to format bytes to human-readable format.
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

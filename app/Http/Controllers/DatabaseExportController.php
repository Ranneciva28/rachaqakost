<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class DatabaseExportController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user()->isOwner(), 403);
        abort_unless(config('database.default') === 'pgsql', 422, 'Export SQL hanya tersedia untuk database PostgreSQL.');

        $connection = $this->connectionSettings();
        $directory = storage_path('app/private/database-exports');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            return back()->withErrors(['database_export'=>'Folder sementara export database tidak dapat dibuat.']);
        }

        $path = tempnam($directory, 'rachaqakost-');
        if ($path === false) {
            return back()->withErrors(['database_export'=>'File sementara export database tidak dapat dibuat.']);
        }

        try {
            $process = new Process([
                '/usr/bin/pg_dump',
                '--host='.$connection['host'],
                '--port='.$connection['port'],
                '--username='.$connection['username'],
                '--dbname='.$connection['database'],
                '--schema=public',
                '--format=plain',
                '--clean',
                '--if-exists',
                '--no-owner',
                '--no-privileges',
                '--quote-all-identifiers',
                '--encoding=UTF8',
                '--file='.$path,
            ], base_path(), [
                'PGPASSWORD'=>$connection['password'],
                'PGSSLMODE'=>$connection['sslmode'],
                'PGCONNECT_TIMEOUT'=>'15',
            ], null, 300);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($path) || filesize($path) === 0) {
                Log::error('Database SQL export failed.', [
                    'exit_code'=>$process->getExitCode(),
                    'error'=>mb_substr($process->getErrorOutput(), 0, 2000),
                ]);
                @unlink($path);

                return back()->withErrors(['database_export'=>'Export database gagal dibuat. Coba lagi atau periksa log server.']);
            }

            $filename = 'rachaqakost-database-'.now()->format('Y-m-d_H-i-s').'.sql';

            return response()->download($path, $filename, [
                'Content-Type'=>'application/sql; charset=UTF-8',
                'Cache-Control'=>'private, no-store, no-cache, must-revalidate, max-age=0',
                'Pragma'=>'no-cache',
                'X-Content-Type-Options'=>'nosniff',
            ])->deleteFileAfterSend(true);
        } catch (\Throwable $exception) {
            @unlink($path);
            Log::error('Database SQL export could not start.', [
                'exception'=>get_class($exception),
                'message'=>mb_substr($exception->getMessage(), 0, 2000),
            ]);

            return back()->withErrors(['database_export'=>'Export database belum dapat dijalankan. Coba lagi setelah beberapa saat.']);
        }
    }

    private function connectionSettings(): array
    {
        $config = config('database.connections.pgsql');
        $settings = [
            'host'=>(string) ($config['host'] ?? ''),
            'port'=>(string) ($config['port'] ?? '5432'),
            'database'=>(string) ($config['database'] ?? 'postgres'),
            'username'=>(string) ($config['username'] ?? 'postgres'),
            'password'=>(string) ($config['password'] ?? ''),
            'sslmode'=>(string) ($config['sslmode'] ?? 'require'),
        ];

        if ($url = trim((string) ($config['url'] ?? ''))) {
            $parts = parse_url($url);
            if (is_array($parts)) {
                $settings['host'] = rawurldecode((string) ($parts['host'] ?? $settings['host']));
                $settings['port'] = (string) ($parts['port'] ?? $settings['port']);
                $settings['database'] = rawurldecode(ltrim((string) ($parts['path'] ?? ''), '/')) ?: $settings['database'];
                $settings['username'] = rawurldecode((string) ($parts['user'] ?? $settings['username']));
                $settings['password'] = rawurldecode((string) ($parts['pass'] ?? $settings['password']));
                parse_str((string) ($parts['query'] ?? ''), $query);
                $settings['sslmode'] = (string) ($query['sslmode'] ?? $settings['sslmode']);
            }
        }

        abort_if($settings['host'] === '' || $settings['database'] === '' || $settings['username'] === '', 500, 'Konfigurasi database belum lengkap.');

        return $settings;
    }
}

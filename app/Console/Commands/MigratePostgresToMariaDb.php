<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class MigratePostgresToMariaDb extends Command
{
    protected $signature = 'rachaqakost:migrate-postgres-to-mariadb {--force : Replace all application data in the target MariaDB database}';

    protected $description = 'Copy and verify RachaqaKost application data from PostgreSQL to MariaDB';

    private const TABLES = [
        'users',
        'room_categories',
        'rooms',
        'expense_categories',
        'app_settings',
        'tenant_form_sections',
        'tenant_form_fields',
        'import_batches',
        'tenants',
        'payments',
        'expenses',
        'maintenances',
        'tenant_data_forms',
        'media_files',
        'import_rows',
    ];

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('Target/default database harus MariaDB/MySQL.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->error('Jalankan dengan --force setelah backup target tersedia.');

            return self::FAILURE;
        }

        DB::connection('source_pgsql')->getPdo();
        $this->info('Koneksi PostgreSQL sumber dan MariaDB target berhasil.');

        Schema::disableForeignKeyConstraints();
        try {
            foreach (array_reverse(self::TABLES) as $table) {
                DB::table($table)->truncate();
            }

            foreach (self::TABLES as $table) {
                $this->copyTable($table);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->verify();
        DB::table('sessions')->truncate();
        DB::table('cache')->truncate();
        DB::table('cache_locks')->truncate();

        $this->newLine();
        $this->info('Migrasi dan rekonsiliasi selesai. Session lama sengaja tidak dipindahkan.');

        return self::SUCCESS;
    }

    private function copyTable(string $table): void
    {
        $source = DB::connection('source_pgsql')->table($table)->orderBy('id')->get();
        $rows = $source->map(fn ($row) => $this->normalizeRow((array) $row))->all();

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table($table)->insert($chunk);
        }

        $this->line(sprintf('%-24s %d baris', $table, count($rows)));
    }

    private function normalizeRow(array $row): array
    {
        foreach ($row as $column => $value) {
            if (is_resource($value)) {
                $contents = stream_get_contents($value);
                if ($contents === false) {
                    throw new RuntimeException('Gagal membaca data binary kolom '.$column.'.');
                }
                $row[$column] = $contents;
                continue;
            }

            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}[ T]/', $value)) {
                $row[$column] = Carbon::parse($value)->utc()->format('Y-m-d H:i:s');
            }
        }

        return $row;
    }

    private function verify(): void
    {
        $failed = [];
        $this->newLine();
        $this->info('Rekonsiliasi jumlah baris:');

        foreach (self::TABLES as $table) {
            $source = DB::connection('source_pgsql')->table($table)->count();
            $target = DB::table($table)->count();
            $ok = $source === $target;
            $this->line(sprintf('%-24s sumber=%d target=%d %s', $table, $source, $target, $ok ? 'OK' : 'GAGAL'));
            if (! $ok) $failed[] = $table;
        }

        foreach (['payments', 'expenses'] as $table) {
            $source = number_format((float) DB::connection('source_pgsql')->table($table)->sum('amount'), 2, '.', '');
            $target = number_format((float) DB::table($table)->sum('amount'), 2, '.', '');
            if ($source !== $target) $failed[] = $table.'.amount';
        }

        $sourceBytes = (int) DB::connection('source_pgsql')->table('media_files')->selectRaw('COALESCE(SUM(OCTET_LENGTH(contents)), 0) AS bytes')->value('bytes');
        $targetBytes = (int) DB::table('media_files')->selectRaw('COALESCE(SUM(OCTET_LENGTH(contents)), 0) AS bytes')->value('bytes');
        if ($sourceBytes !== $targetBytes) $failed[] = 'media_files.contents';

        if ($failed) {
            throw new RuntimeException('Rekonsiliasi gagal: '.implode(', ', array_unique($failed)));
        }

        $this->info('Nominal pembayaran/pengeluaran dan ukuran seluruh media juga cocok.');
    }
}

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Console\Command\Command as CommandExit;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Safer than migrate:fresh on XAMPP: full DROP DATABASE avoids orphan InnoDB tablespaces (error 1813).
 * Lihat docs/XAMPP-DATABASE.md untuk pencegahan korupsi.
 */
Artisan::command('duniatex:fresh-local {--no-seed : Skip db:seed} {--backup : Simpan mysqldump ke storage/app/db-backups sebelum DROP}', function () {
    if (! app()->environment('local')) {
        if (! $this->confirm('APP_ENV is not local. Drop and recreate the configured database?', false)) {
            return CommandExit::INVALID;
        }
    }

    $name = (string) config('database.connections.mysql.database', '');
    if ($name === '' || ! preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        $this->error('Invalid or empty mysql.database (check DB_DATABASE in .env).');

        return CommandExit::INVALID;
    }

    try {
        DB::connection()->getPdo();
    } catch (\Throwable $e) {
        $this->error('Tidak bisa konek ke MySQL: '.$e->getMessage());
        $this->warn('Nyalakan MySQL di XAMPP dan cek DB_HOST / DB_PORT / DB_DATABASE di .env.');

        return CommandExit::FAILURE;
    }

    $findMysqldump = static function (): ?string {
        foreach ([
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:/xampp/mysql/bin/mysqldump.exe',
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    };

    if ($this->option('backup')) {
        $dump = $findMysqldump();
        if ($dump === null) {
            $this->error('Option --backup: mysqldump tidak ditemukan (biasanya C:\\xampp\\mysql\\bin\\mysqldump.exe). Jalankan tanpa --backup atau pasang XAMPP.');

            return CommandExit::FAILURE;
        }

        $dir = storage_path('app/db-backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $dir.DIRECTORY_SEPARATOR.date('Y-m-d_His').'_'.$name.'.sql';
        $cfg = config('database.connections.mysql');
        $cmd = array_merge(
            [$dump, '-h', (string) $cfg['host'], '-P', (string) $cfg['port'], '-u', (string) $cfg['username']],
            $cfg['password'] !== '' && $cfg['password'] !== null ? ['-p'.(string) $cfg['password']] : [],
            ['--single-transaction', '--routines', '--no-tablespaces', $name]
        );
        $result = Process::timeout(600)->run($cmd);
        if (! $result->successful()) {
            $this->error('Backup mysqldump gagal: '.$result->errorOutput());

            return CommandExit::FAILURE;
        }
        file_put_contents($file, $result->output());
        $this->info('Backup disimpan: '.$file);
    }

    $this->info("DROP + CREATE `{$name}` lewat schema `mysql`…");

    try {
        DB::statement('USE `mysql`');
        DB::statement("DROP DATABASE IF EXISTS `{$name}`");
    } catch (\Throwable $e) {
        $this->error($e->getMessage());
        $this->newLine();
        $this->warn('Jika DROP gagal (errno 1010 / folder tidak kosong): hentikan MySQL XAMPP, hapus folder data DB ini, nyalakan lagi, ulangi perintah.');
        $this->line('  Biasanya: C:\\xampp\\mysql\\data\\'.$name);

        return CommandExit::FAILURE;
    }

    DB::statement("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    Config::set('database.connections.mysql.database', $name);
    DB::purge('mysql');
    DB::reconnect('mysql');

    $this->call('migrate', ['--force' => true]);

    if (! $this->option('no-seed')) {
        $this->call('db:seed', ['--force' => true]);
    }

    $this->info('Database selesai dibuat ulang.');
    $this->newLine();
    $this->comment('Stabilitas: matikan MySQL lewat XAMPP Stop; hindari migrate:fresh jika pernah error 1813; baca docs/XAMPP-DATABASE.md.');

    return CommandExit::SUCCESS;
})->purpose('Drop + recreate MySQL database (XAMPP-safe), migrate, and seed');

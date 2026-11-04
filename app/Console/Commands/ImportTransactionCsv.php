<?php

namespace App\Console\Commands;

use App\Models\DataSource;
use App\Services\TransactionCsvImporter;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportTransactionCsv extends Command
{
    protected $signature = 'transactions:import {path : Path to validated transaction CSV}';
    protected $description = 'Import/update transaction records from CSV without treating missing observations as zero';

    public function handle(TransactionCsvImporter $importer): int
    {
        $path = $this->argument('path');
        if (! is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'csv') {
            $this->components->error('File tidak ditemukan atau bukan CSV.');
            return self::FAILURE;
        }
        $source = DataSource::query()->first();
        if (! $source) {
            $this->components->error('Sumber data belum ada. Jalankan php artisan db:seed terlebih dahulu.');
            return self::FAILURE;
        }
        try {
            $count = $importer->import($path, $source);
            $source->forceFill(['imported_at' => now()])->save();
            $this->components->info("{$count} baris diimpor/diperbarui dari CSV.");
            return self::SUCCESS;
        } catch (ValidationException $e) {
            foreach ($e->errors()['file'] ?? ['Format CSV tidak valid.'] as $message) {
                $this->components->error($message);
            }
            return self::FAILURE;
        }
    }
}

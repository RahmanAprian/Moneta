<?php

namespace App\Services;

use App\Models\DataSource;
use App\Models\TransactionCategory;
use App\Models\TransactionStatistic;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use SplFileObject;

class TransactionCsvImporter
{
    private const HEADERS = [
        'period_start', 'granularity', 'category', 'transaction_value', 'transaction_volume',
        'value_unit', 'volume_unit', 'source_reference',
    ];

    public function import(string $path, DataSource $source): int
    {
        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl(',', '"', '');
        $header = $file->fgetcsv();
        if (! is_array($header)) {
            throw ValidationException::withMessages(['file' => 'CSV kosong atau tidak bisa dibaca.']);
        }
        $header[0] = trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]), '"');
        if ($header !== self::HEADERS) {
            throw ValidationException::withMessages(['file' => 'Header CSV tidak sesuai. Gunakan kolom: '.implode(', ', self::HEADERS).'.']);
        }

        $categories = TransactionCategory::query()->pluck('id', 'slug');
        $seen = [];
        $rows = [];
        $line = 1;
        while (! $file->eof()) {
            $columns = $file->fgetcsv();
            $line++;
            if (! is_array($columns) || count($columns) === 1 && ($columns[0] === null || $columns[0] === '')) {
                continue;
            }
            if (count($columns) !== count(self::HEADERS)) {
                throw ValidationException::withMessages(['file' => "Baris {$line}: jumlah kolom salah."]);
            }
            $row = array_combine(self::HEADERS, array_map(static fn ($v) => is_string($v) ? trim($v) : $v, $columns));
            $validator = Validator::make($row, [
                'period_start' => ['required', 'date_format:Y-m-d'],
                'granularity' => ['required', 'in:annual,monthly'],
                'category' => ['required', 'in:purchase,top_up,transfer'],
                'transaction_value' => ['nullable', 'numeric', 'min:0'],
                'transaction_volume' => ['nullable', 'numeric', 'min:0'],
                'value_unit' => ['required', 'string', 'max:60'],
                'volume_unit' => ['required', 'string', 'max:60'],
                'source_reference' => ['nullable', 'string', 'max:255'],
            ]);
            if ($validator->fails()) {
                throw ValidationException::withMessages(['file' => "Baris {$line}: ".implode(' ', $validator->errors()->all())]);
            }
            if ($row['transaction_value'] === null && $row['transaction_volume'] === null) {
                throw ValidationException::withMessages(['file' => "Baris {$line}: nilai dan volume sama-sama kosong; periode tanpa data tidak perlu dimasukkan."]);
            }
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $row['period_start']);
            if ($row['granularity'] === 'annual' && ($date->month !== 1 || $date->day !== 1)) {
                throw ValidationException::withMessages(['file' => "Baris {$line}: data tahunan harus memakai tanggal 1 Januari pada kolom period_start."]);
            }
            $key = $row['period_start'].'|'.$row['granularity'].'|'.$row['category'];
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(['file' => "Baris {$line}: data duplikat untuk periode dan kategori yang sama."]);
            }
            $seen[$key] = true;
            $rows[] = [
                'period_start' => $row['period_start'],
                'granularity' => $row['granularity'],
                'transaction_category_id' => $categories[$row['category']],
                'data_source_id' => $source->id,
                'transaction_value' => $row['transaction_value'] === '' ? null : $row['transaction_value'],
                'transaction_volume' => $row['transaction_volume'] === '' ? null : $row['transaction_volume'],
                'value_unit' => $row['value_unit'],
                'volume_unit' => $row['volume_unit'],
                'source_reference' => $row['source_reference'] ?: null,
            ];
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'CSV tidak berisi baris data.']);
        }

        return DB::transaction(function () use ($rows): int {
            $now = now();
            $rows = array_map(static fn (array $row) => array_merge($row, [
                'created_at' => $now,
                'updated_at' => $now,
            ]), $rows);
            TransactionStatistic::upsert(
                $rows,
                ['period_start', 'granularity', 'transaction_category_id'],
                ['data_source_id', 'transaction_value', 'transaction_volume', 'value_unit', 'volume_unit', 'source_reference', 'updated_at'],
            );
            return count($rows);
        });
    }
}

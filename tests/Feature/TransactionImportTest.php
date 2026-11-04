<?php

namespace Tests\Feature;

use App\Models\DataSource;
use App\Models\TransactionCategory;
use App\Models\TransactionStatistic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TransactionImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_csv_import_is_idempotent_and_keeps_units(): void
    {
        $this->seed(\Database\Seeders\TransactionCategorySeeder::class);
        $source = DataSource::create([
            'name' => 'Bank Indonesia — Tabel 5e',
            'source_file' => 'TABEL_5e.xls',
            'value_unit' => 'Rp Miliar',
            'volume_unit' => 'Ribu Transaksi',
        ]);
        $path = database_path('seeders/data/bi_tabel5e_transactions.csv');

        $this->assertSame(0, Artisan::call('transactions:import', ['path' => $path]), Artisan::output());
        $this->assertSame(78, TransactionStatistic::count());
        $this->assertSame(18, TransactionStatistic::query()->where('granularity', 'annual')->count());
        $this->assertSame(60, TransactionStatistic::query()->where('granularity', 'monthly')->count());
        $this->assertSame(36, TransactionStatistic::query()->where('granularity', 'monthly')->whereYear('period_start', 2025)->count());
        $this->assertSame(3, TransactionStatistic::query()->where('granularity', 'monthly')->whereDate('period_start', '2025-01-01')->count());
        $january2025Purchase = TransactionStatistic::query()->where('granularity', 'monthly')->whereDate('period_start', '2025-01-01')
            ->where('transaction_category_id', TransactionCategory::where('slug', 'purchase')->value('id'))->firstOrFail();
        $this->assertEqualsWithDelta(65534.58160919998, (float) $january2025Purchase->transaction_value, 0.00001);
        $this->assertEqualsWithDelta(1257736.5250000001, (float) $january2025Purchase->transaction_volume, 0.00001);
        $this->assertSame(24, TransactionStatistic::query()->where('granularity', 'monthly')->whereYear('period_start', 2026)->count());
        $this->assertEqualsWithDelta(88112.5336074941, (float) TransactionStatistic::query()->where('granularity', 'monthly')->whereDate('period_start', '2025-08-01')->where('transaction_category_id', TransactionCategory::where('slug', 'purchase')->value('id'))->value('transaction_value'), 0.00001);
        $this->assertEqualsWithDelta(1751876.265, (float) TransactionStatistic::query()->where('granularity', 'monthly')->whereDate('period_start', '2025-08-01')->where('transaction_category_id', TransactionCategory::where('slug', 'purchase')->value('id'))->value('transaction_volume'), 0.0001);
        $this->assertSame(0, Artisan::call('transactions:import', ['path' => $path]), Artisan::output());
        $this->assertSame(78, TransactionStatistic::count());
        $this->assertSame(60, TransactionStatistic::query()->where('granularity', 'monthly')->count());
        $this->assertSame(3, TransactionStatistic::query()->where('granularity', 'annual')->whereYear('period_start', 2020)->count());
        $this->assertSame(21, TransactionStatistic::query()->where('granularity', 'monthly')->whereYear('period_start', 2025)->whereMonth('period_start', '<', 8)->count());
        $this->assertSame(3, TransactionStatistic::query()->where('granularity', 'monthly')->whereDate('period_start', '2026-08-01')->count());
        $august2026Purchase = TransactionStatistic::query()->whereDate('period_start', '2026-08-01')
            ->where('transaction_category_id', TransactionCategory::where('slug', 'purchase')->value('id'))->firstOrFail();
        $this->assertEqualsWithDelta(113442.35874187885, (float) $august2026Purchase->transaction_value, 0.00001);
        $this->assertEqualsWithDelta(2416473.1109999996, (float) $august2026Purchase->transaction_volume, 0.0001);

        $augustPurchase = TransactionStatistic::query()->whereDate('period_start', '2025-08-01')
            ->where('transaction_category_id', \App\Models\TransactionCategory::where('slug', 'purchase')->value('id'))->firstOrFail();
        $this->assertEqualsWithDelta(88112.53360749413, (float) $augustPurchase->transaction_value, 0.00001);
        $this->assertEqualsWithDelta(1751876.2650000027, (float) $augustPurchase->transaction_volume, 0.0001);
        $this->assertSame('Rp Miliar', $augustPurchase->value_unit);
        $this->assertSame('Ribu Transaksi', $augustPurchase->volume_unit);
        $this->assertSame(3, TransactionStatistic::query()->where('granularity', 'monthly')->whereYear('period_start', 2025)->whereMonth('period_start', 1)->count());
        $this->assertDatabaseCount('transaction_statistics', 78);
    }

    public function test_import_rejects_duplicate_period_category_records(): void
    {
        $this->seed(\Database\Seeders\TransactionCategorySeeder::class);
        $source = DataSource::create(['name' => 'Test source', 'value_unit' => 'Rp Miliar', 'volume_unit' => 'Ribu Transaksi']);
        $path = storage_path('app/testing-duplicate.csv');
        if (! is_dir(dirname($path))) mkdir(dirname($path), 0777, true);
        file_put_contents($path, implode("\n", [
            'period_start,granularity,category,transaction_value,transaction_volume,value_unit,volume_unit,source_reference',
            '2025-08-01,monthly,purchase,1,1,Rp Miliar,Ribu Transaksi,test',
            '2025-08-01,monthly,purchase,2,2,Rp Miliar,Ribu Transaksi,test',
        ]));
        try {
            $this->expectException(\Illuminate\Validation\ValidationException::class);
            app(\App\Services\TransactionCsvImporter::class)->import($path, $source);
        } finally {
            @unlink($path);
        }
    }
}

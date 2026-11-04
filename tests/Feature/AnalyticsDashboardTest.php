<?php

namespace Tests\Feature;

use App\Models\DataSource;
use App\Services\TransactionCsvImporter;
use Database\Seeders\TransactionCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_page_loads_with_real_dataset_context(): void
    {
        $this->get('/')->assertOk()->assertSee('Dasbor Analitik Uang Elektronik');
    }

    public function test_api_returns_correct_kpis_and_respects_filters(): void
    {
        $this->seed(\Database\Seeders\TransactionCategorySeeder::class);
        $sourceId = \App\Models\DataSource::create([
            'name' => 'Bank Indonesia — Tabel 5e',
            'source_file' => 'TABEL_5e.xls',
            'value_unit' => 'Rp Miliar',
            'volume_unit' => 'Ribu Transaksi',
            'retrieved_at' => null,
        ])->id;
        $purchase = \App\Models\TransactionCategory::where('slug', 'purchase')->firstOrFail();
        \App\Models\TransactionStatistic::create([
            'period_start' => '2025-08-01', 'granularity' => 'monthly',
            'transaction_category_id' => $purchase->id, 'data_source_id' => $sourceId,
            'transaction_value' => 88113, 'transaction_volume' => 1751876,
            'value_unit' => 'Rp Miliar', 'volume_unit' => 'Ribu Transaksi',
        ]);
        \App\Models\TransactionStatistic::create([
            'period_start' => '2025-08-01', 'granularity' => 'monthly',
            'transaction_category_id' => \App\Models\TransactionCategory::where('slug', 'top_up')->firstOrFail()->id,
            'data_source_id' => $sourceId, 'transaction_value' => 156950,
            'transaction_volume' => 596045, 'value_unit' => 'Rp Miliar',
            'volume_unit' => 'Ribu Transaksi',
        ]);

        $this->getJson('/dashboard/data?year=2025&month=8&granularity=monthly&category=purchase')
            ->assertOk()
            ->assertJsonPath('summary.total_value', 88113)
            ->assertJsonPath('summary.total_volume', 1751876)
            ->assertJsonPath('summary.average_transaction_rupiah', 50296.37)
            ->assertJsonPath('summary.top_category', 'Belanja')
            ->assertJsonCount(1, 'table.data');
    }

    public function test_empty_filter_returns_null_average_and_no_fake_zero_rows(): void
    {
        $this->getJson('/dashboard/data?year=2022&granularity=monthly')
            ->assertOk()
            ->assertJsonPath('summary.total_value', null)
            ->assertJsonPath('summary.average_transaction_rupiah', null)
            ->assertJsonCount(0, 'table.data');
    }

    public function test_annual_2020_to_2025_and_monthly_2025_to_2026_are_separate(): void
    {
        $this->seed(TransactionCategorySeeder::class);
        $source = DataSource::create([
            'name' => 'Bank Indonesia — Tabel 5e',
            'source_file' => 'TABEL_5e.xls',
            'value_unit' => 'Rp Miliar',
            'volume_unit' => 'Ribu Transaksi',
        ]);
        app(TransactionCsvImporter::class)->import(database_path('seeders/data/bi_tabel5e_transactions.csv'), $source);

        $annual = $this->getJson('/dashboard/data?granularity=annual&year=2020&category=top_up')->assertOk();
        $this->assertEqualsWithDelta(252579.320903986, $annual->json('summary.total_value'), 0.00001);
        $this->assertEqualsWithDelta(10050717.631, $annual->json('summary.total_volume'), 0.00001);
        $annual->assertJsonPath('summary.top_category', 'Isi ulang')
            ->assertJsonPath('metadata.years.annual', [2020, 2021, 2022, 2023, 2024, 2025]);

        $monthly = $this->getJson('/dashboard/data?granularity=monthly&year=2026&month=8&category=transfer&metric=volume')->assertOk();
        $this->assertEqualsWithDelta(65274.302233379, $monthly->json('summary.total_value'), 0.00001);
        $this->assertEqualsWithDelta(457000.308, $monthly->json('summary.total_volume'), 0.00001);
        $monthly->assertJsonPath('filters.metric', 'volume')
            ->assertJsonPath('metadata.years.monthly', [2025, 2026])
            ->assertJsonPath('metadata.coverage.monthly.first', '2025-01-01')
            ->assertJsonPath('metadata.coverage.monthly.last', '2026-08-01')
            ->assertJsonPath('table.data.0.period', 'Agustus 2026');

        $comparison = $this->getJson('/dashboard/data?granularity=monthly')->assertOk();
        $comparison->assertJsonPath('year_comparison.months', [1, 2, 3, 4, 5, 6, 7, 8])
            ->assertJsonPath('year_comparison.month_names.0', 'Januari')
            ->assertJsonPath('year_comparison.month_names.7', 'Agustus')
            ->assertJsonPath('year_comparison.note', 'Perbandingan memakai Januari–Agustus pada kedua tahun dan menggunakan nilai sumber berpresisi penuh.');
        $this->assertEqualsWithDelta(613001.3641876542, $comparison->json('year_comparison.categories.0.data_2025.value'), 0.001);
        $this->assertEqualsWithDelta(888924.6147646792, $comparison->json('year_comparison.categories.0.data_2026.value'), 0.001);

        $january = $this->getJson('/dashboard/data?granularity=monthly&year=2025&month=1&category=purchase')->assertOk();
        $this->assertEqualsWithDelta(65534.5816092, $january->json('summary.total_value'), 0.00001);
        $this->assertEqualsWithDelta(1257736.525, $january->json('summary.total_volume'), 0.00001);
        $this->assertEqualsWithDelta(52105.17, $january->json('summary.average_transaction_rupiah'), 0.01);
        $january->assertJsonCount(1, 'table.data')
            ->assertJsonPath('table.data.0.period', 'Januari 2025');
    }
}

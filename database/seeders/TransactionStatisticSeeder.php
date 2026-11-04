<?php

namespace Database\Seeders;

use App\Models\DataSource;
use App\Services\TransactionCsvImporter;
use Illuminate\Database\Seeder;

class TransactionStatisticSeeder extends Seeder
{
    public function run(): void
    {
        $csv = database_path('seeders/data/bi_tabel5e_transactions.csv');
        if (! is_file($csv)) {
            throw new \RuntimeException('CSV data Tabel 5e tidak ditemukan.');
        }
        $this->call(TransactionCategorySeeder::class);
        $source = DataSource::updateOrCreate(
            ['name' => 'Bank Indonesia — Tabel 5e Uang Elektronik'],
            [
                'source_file' => 'TABEL_5e.xls (lembar 5e; PDF pendamping TABEL_5e.pdf)',
                'source_checksum' => '29d99fddc935e2dfc801539f185198ee6da6a08838648df9775a63a2171c7b6e',
                'value_unit' => 'Rp Miliar',
                'volume_unit' => 'Ribu Transaksi',
                'retrieved_at' => null,
                'imported_at' => now(),
                'notes' => 'Sumber: TABEL_5e.xls, lembar 5e; diverifikasi terhadap PDF pendamping. Pemetaan baris: Belanja nilai/volume 27/17; Isi ulang nilai/volume 32/22; Transfer nilai/volume 30/20. Data tahunan 2020–2025: 6 observasi per kategori. Data bulanan Januari–Desember 2025 dan Januari–Agustus 2026: 20 bulan, 60 observasi. Nilai mentah dimuat hingga presisi 12 desimal; format tampilan mengikuti pembulatan tabel sumber. Satuan: Rp Miliar dan Ribu Transaksi. Waktu impor aplikasi tidak sama dengan tanggal pengambilan sumber yang tidak tercantum/terverifikasi.',
            ],
        );
        app(TransactionCsvImporter::class)->import($csv, $source);
    }
}

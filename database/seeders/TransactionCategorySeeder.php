<?php

namespace Database\Seeders;

use App\Models\TransactionCategory;
use Illuminate\Database\Seeder;

class TransactionCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['slug' => 'purchase', 'name' => 'Belanja', 'source_label' => 'Volume/Nilai Transaksi Belanja', 'sort_order' => 1],
            ['slug' => 'top_up', 'name' => 'Isi ulang', 'source_label' => 'Volume/Nilai Transaksi Reload/Top Up', 'sort_order' => 2],
            ['slug' => 'transfer', 'name' => 'Transfer', 'source_label' => 'Volume/Nilai Transaksi Transfer Antar Uang Elektronik', 'sort_order' => 3],
        ] as $category) {
            TransactionCategory::updateOrCreate(['slug' => $category['slug']], $category);
        }
    }
}

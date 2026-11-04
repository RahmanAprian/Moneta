<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_statistics', function (Blueprint $table): void {
            $table->decimal('transaction_value', 30, 12)->nullable()->change();
            $table->decimal('transaction_volume', 30, 12)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Keep the expanded scale. Reverting to DECIMAL(20,3) would destroy imported source precision.
    }
};

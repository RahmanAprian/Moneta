<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('source_file')->nullable();
            $table->string('source_checksum', 64)->nullable();
            $table->string('value_unit');
            $table->string('volume_unit');
            $table->timestamp('retrieved_at')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('transaction_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('source_label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('transaction_statistics', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->enum('granularity', ['annual', 'monthly']);
            $table->foreignId('transaction_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('data_source_id')->constrained()->restrictOnDelete();
            $table->decimal('transaction_value', 30, 12)->nullable();
            $table->decimal('transaction_volume', 30, 12)->nullable();
            $table->string('value_unit');
            $table->string('volume_unit');
            $table->string('source_reference')->nullable();
            $table->timestamps();

            $table->unique(['period_start', 'granularity', 'transaction_category_id'], 'stats_period_gran_cat_unique');
            $table->index(['granularity', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_statistics');
        Schema::dropIfExists('transaction_categories');
        Schema::dropIfExists('data_sources');
    }
};

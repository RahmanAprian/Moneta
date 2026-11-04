<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionStatistic extends Model
{
    protected $fillable = [
        'period_start', 'granularity', 'transaction_category_id', 'data_source_id',
        'transaction_value', 'transaction_volume', 'value_unit', 'volume_unit', 'source_reference',
    ];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'transaction_value' => 'decimal:12', 'transaction_volume' => 'decimal:12'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class, 'transaction_category_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'data_source_id');
    }

    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['year'] ?? null, fn (Builder $q, $year) => $q->whereYear('period_start', $year))
            ->when(($filters['month'] ?? null) && ($filters['granularity'] ?? 'annual') === 'monthly',
                fn (Builder $q) => $q->whereMonth('period_start', $filters['month']))
            ->when($filters['granularity'] ?? 'annual', fn (Builder $q, $value) => $q->where('granularity', $value))
            ->when($filters['category'] ?? null, fn (Builder $q, $slug) => $q->whereHas('category', fn (Builder $cat) => $cat->where('slug', $slug)));
    }
}

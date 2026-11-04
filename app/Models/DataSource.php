<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataSource extends Model
{
    protected $fillable = ['name', 'source_file', 'source_checksum', 'value_unit', 'volume_unit', 'retrieved_at', 'imported_at', 'notes'];

    protected function casts(): array
    {
        return ['retrieved_at' => 'datetime', 'imported_at' => 'datetime'];
    }

    public function statistics(): HasMany
    {
        return $this->hasMany(TransactionStatistic::class);
    }
}

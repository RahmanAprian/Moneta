<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionCategory extends Model
{
    protected $fillable = ['slug', 'name', 'source_label', 'sort_order'];

    public function statistics(): HasMany
    {
        return $this->hasMany(TransactionStatistic::class);
    }
}

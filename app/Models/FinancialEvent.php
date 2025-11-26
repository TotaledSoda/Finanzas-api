<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialEvent extends Model
{
    protected $fillable = [
        'user_id',
        'eventable_id',
        'eventable_type',
        'title',
        'date',
        'amount',
        'category',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function eventable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

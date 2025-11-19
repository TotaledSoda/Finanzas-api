<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TandaMember extends Model
{
    protected $fillable = [
        'tanda_id',
        'user_id',
        'position',
        'is_owner',
    ];

    public function tanda(): BelongsTo
    {
        return $this->belongsTo(Tanda::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

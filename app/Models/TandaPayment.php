<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TandaPayment extends Model
{
    protected $fillable = [
        'tanda_id',
        'user_id',
        'due_date',
        'paid_at',
        'amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_at'  => 'date',
        'amount'   => 'decimal:2',
    ];

    public function tanda()
    {
        return $this->belongsTo(Tanda::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

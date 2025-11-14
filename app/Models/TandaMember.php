<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TandaMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'tanda_id',
        'user_id',
        'name',
        'email',
        'phone',
        'round_number',
        'has_collected',
    ];

    protected $casts = [
        'has_collected' => 'boolean',
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

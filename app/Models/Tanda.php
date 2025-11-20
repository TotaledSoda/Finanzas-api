<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tanda extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',             // dueño / organizador
        'name',
        'contribution_amount',
        'total_amount',
        'start_date',
        'frequency',
        'current_round',
        'status',
        'next_payment_date',
    ];

    protected $casts = [
        'contribution_amount' => 'decimal:2',
        'total_amount'        => 'decimal:2',
        'start_date'          => 'date',
        'next_payment_date'   => 'date',
    ];

    // Dueño / organizador (ahora usamos user_id)
    public function organizer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Registros de la tabla tanda_members
    public function members()
    {
        return $this->hasMany(TandaMember::class);
    }

    // Usuarios participantes a través de tanda_members
    public function participants()
    {
        return $this->belongsToMany(User::class, 'tanda_members')
            // 👇 AQUÍ QUITAMOS position Y SOLO USAMOS is_owner (y lo que realmente exista)
            ->withPivot(['is_owner'])
            ->withTimestamps();
    }
}

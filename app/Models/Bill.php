<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Bill extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'provider',
        'description',
        'amount',
        'due_date',
        'paid_at',
        'status',
        'category',
        'auto_debit',
    ];

    protected $casts = [
        'amount'    => 'decimal:2',
        'due_date'  => 'date',
        'paid_at'   => 'datetime',
        'auto_debit'=> 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ¿Está pagado?
    public function getIsPaidAttribute(): bool
    {
        return $this->status === 'paid' || ! is_null($this->paid_at);
    }

    // ¿Está vencido? (no pagado y fecha ya pasó)
    public function getIsOverdueAttribute(): bool
    {
        if ($this->is_paid || ! $this->due_date) {
            return false;
        }

        return $this->due_date->lt(Carbon::today());
    }

    // Días restantes (puede ser negativo si ya se venció)
    public function getDaysUntilDueAttribute(): ?int
    {
        if (! $this->due_date) {
            return null;
        }

        return Carbon::today()->diffInDays($this->due_date, false);
    }

    // Texto amigable para el estado (usado en el calendario)
    public function getStatusTextAttribute(): ?string
    {
        $days = $this->days_until_due;

        if ($this->is_paid) {
            return 'Pagado';
        }

        if ($this->is_overdue) {
            return 'Vencido';
        }

        if ($days === null) {
            return null;
        }

        if ($days === 0) {
            return 'Vence hoy';
        }

        if ($days === 1) {
            return 'Vence en 1 día';
        }

        if ($days > 1) {
            return "Vence en {$days} días";
        }

        return 'Vencido';
    }
      public function events()
    {
        return $this->morphMany(FinancialEvent::class, 'eventable');
    }
}

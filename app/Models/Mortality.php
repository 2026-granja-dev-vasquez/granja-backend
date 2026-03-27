<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mortality extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'quantity',
        'date',
        'reason',
    ];

    protected $casts = [
        'date' => 'date',
        'quantity' => 'integer',
    ];

    /**
     * Relación: Una mortalidad pertenece a un lote.
     */
    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * Boot: Lógica para actualizar la cantidad actual del lote al registrar una muerte.
     */
    protected static function booted()
    {
        static::created(function ($mortality) {
            $batch = $mortality->batch;
            $batch->decrement('current_quantity', $mortality->quantity);
        });

        static::deleted(function ($mortality) {
            $batch = $mortality->batch;
            $batch->increment('current_quantity', $mortality->quantity);
        });
    }
}

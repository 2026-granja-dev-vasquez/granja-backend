<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BatchAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'quantity',
        'reason',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
        'quantity' => 'integer',
    ];

    /**
     * Relación: Un ajuste pertenece a un lote.
     */
    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * Boot: Lógica para actualizar la cantidad actual del lote al registrar un ajuste.
     */
    protected static function booted()
    {
        static::created(function ($adjustment) {
            $batch = $adjustment->batch;
            
            // Increment current bird count in DB
            $batch->increment('current_quantity', $adjustment->quantity);
            
            // Refresh batch to get the updated current_quantity from DB
            $batch = $batch->fresh();
            
            // Only update initial_quantity if the NEW current count exceeds initial
            if ($batch->current_quantity > $batch->initial_quantity) {
                $batch->update(['initial_quantity' => $batch->current_quantity]);
            }
        });

        static::deleted(function ($adjustment) {
            $batch = $adjustment->batch;
            // Best effort reversal: decrement current_quantity
            $batch->decrement('current_quantity', $adjustment->quantity);
        });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BatchCollection extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'quantity',
        'date',
        'type',
    ];

    protected $casts = [
        'date' => 'datetime',
    ];

    /**
     * Relación con el lote de gallinas.
     */
    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'initial_quantity',
        'current_quantity',
        'acquisition_date',
        'status',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'initial_quantity' => 'integer',
        'current_quantity' => 'integer',
    ];

    /**
     * Relación: Un lote tiene muchas mortalidades.
     */
    public function mortalities()
    {
        return $this->hasMany(Mortality::class);
    }
}

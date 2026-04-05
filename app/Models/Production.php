<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Production extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_size_id',
        'useful_quantity',
        'damaged_quantity',
        'date',
        'origin',
    ];

    protected $casts = [
        'date' => 'datetime',
    ];

    /**
     * Relación con el tamaño de huevo.
     */
    public function productSize()
    {
        return $this->belongsTo(ProductSize::class);
    }
}

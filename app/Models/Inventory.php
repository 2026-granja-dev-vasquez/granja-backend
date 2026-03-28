<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_size_id',
        'units_available',
    ];

    /**
     * Relación con el tamaño de huevo.
     */
    public function productSize()
    {
        return $this->belongsTo(ProductSize::class);
    }
}

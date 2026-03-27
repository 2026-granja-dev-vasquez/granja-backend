<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSize extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'unit_price',
        'carton_price',
        'box_price',
        'is_active',
    ];

    protected $casts = [
        'unit_price'   => 'decimal:2',
        'carton_price' => 'decimal:2',
        'box_price'    => 'decimal:2',
        'is_active'    => 'boolean',
    ];
}

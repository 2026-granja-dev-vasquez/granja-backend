<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TableEgg extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'product_size_id',
        'quantity',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function productSize()
    {
        return $this->belongsTo(ProductSize::class);
    }
}

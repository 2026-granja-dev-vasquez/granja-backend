<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryAdjustment extends Model
{
    protected $fillable = [
        'product_size_id',
        'type', // 'in' or 'out'
        'quantity',
        'reason',
        'user_id',
    ];

    public function productSize()
    {
        return $this->belongsTo(ProductSize::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

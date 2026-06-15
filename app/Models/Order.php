<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'customer_id',
        'delivery_date',
        'status',
        'notes',
        'total_amount',
        'paid_amount'
    ];

    protected $casts = [
        'delivery_date' => 'datetime:Y-m-d H:i:s',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function transactions()
    {
        return $this->morphMany(CashTransaction::class, 'reference');
    }
}

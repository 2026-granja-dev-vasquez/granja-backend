<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashTransaction extends Model
{
    protected $fillable = [
        'cash_box_id',
        'type',
        'amount',
        'category',
        'description',
        'reference_id',
        'reference_type',
        'status',
        'void_reason',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function cashBox()
    {
        return $this->belongsTo(CashBox::class);
    }
}

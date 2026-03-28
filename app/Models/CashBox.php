<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashBox extends Model
{
    protected $fillable = [
        'name',
        'user_id',
        'opening_balance',
        'closing_balance',
        'total_income',
        'total_expense',
        'status',
        'opened_at',
        'closed_at'
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(CashTransaction::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'remind_at',
        'frequency',
        'interval_days',
        'is_done',
        'completed_at',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_done' => 'boolean',
    ];
}

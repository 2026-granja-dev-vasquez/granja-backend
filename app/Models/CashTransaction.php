<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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

    protected $appends = [
        'effective_at',
    ];

    public function cashBox()
    {
        return $this->belongsTo(CashBox::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public function getEffectiveAtAttribute(): string
    {
        $referenceDate = null;

        if ($this->relationLoaded('reference') && $this->reference) {
            if ($this->reference instanceof Sale && $this->reference->date) {
                $referenceDate = $this->reference->date;
            } elseif ($this->reference instanceof Order && $this->reference->created_at) {
                $referenceDate = $this->reference->created_at;
            }
        }

        $date = $referenceDate ?? $this->created_at ?? now();

        return Carbon::parse($date)->format('Y-m-d H:i:s');
    }
}

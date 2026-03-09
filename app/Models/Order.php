<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'tenant_id',
        'tracking_id',
        'customer_name',
        'customer_address',
        'customer_numbers',
        'governorate',
        'delivery_fees',
        'items',
        'total_amount',
        'status',
        'payment_method',
        'shipping_data',
        'seen_at',
    ];

    protected function casts(): array
    {
        return [
            'customer_numbers' => 'array',
            'items' => 'array',
            'shipping_data' => 'array',
            'delivery_fees' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'seen_at' => 'datetime',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}

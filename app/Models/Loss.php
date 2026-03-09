<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Loss extends Model
{
    protected $fillable = [
        'tenant_id',
        'product_id',
        'size',
        'color',
        'quantity',
        'cost_price_at_loss',
        'total_loss_value',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'cost_price_at_loss' => 'decimal:2',
            'total_loss_value' => 'decimal:2',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

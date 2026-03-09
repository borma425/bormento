<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'tenant_id',
        'product_id',
        'size',
        'color',
        'quantity',
        'selling_price',
        'cost_price_at_sale',
        'profit',
        'governorate',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'cost_price_at_sale' => 'decimal:2',
            'profit' => 'decimal:2',
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

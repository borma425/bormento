<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'sku',
        'description',
        'price',
        'discounted_price',
        'currency',
        'is_active',
        'media',
        'attributes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discounted_price' => 'decimal:2',
            'is_active' => 'boolean',
            'media' => 'array',
            'attributes' => 'array',
            'metadata' => 'array',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}

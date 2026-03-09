<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'business_type',
        'industry',
        'fb_page_id',
        'fb_page_token',
        'ig_account_id',
        'ig_access_token',
        'openai_api_key',
        'ecommerce_config',
        'reply_only_mode',
        'shipping_zones',
        'payment_config',
        'ai_config',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'reply_only_mode' => 'boolean',
        'shipping_zones' => 'array',
        'ecommerce_config' => 'array',
        'payment_config' => 'array',
        'ai_config' => 'array',
    ];
}

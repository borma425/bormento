<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Gravoni Clothing Store
        Tenant::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Gravoni',
                'business_type' => 'ecommerce',
                'industry' => 'retail',
                'fb_page_id' => '123456789012345', // Dummy Clothing Page ID
                'fb_page_token' => 'EAA_DUMMY_GRAVONI_TOKEN',
                'openai_api_key' => config('agent.openrouter_api_key'), // Fallback to local config for testing
                'ecommerce_config' => [
                    'provider' => 'gravoni',
                    'api_url' => config('agent.gravoni_api_url'),
                    'secret_key' => config('agent.api_secret_key')
                ],
                'payment_config' => [
                    'provider' => 'cashup',
                    'api_url' => config('agent.cashup_api_url'),
                    'api_key' => config('agent.cashup_api_key'),
                    'app_id' => config('agent.cashup_app_id')
                ],
                'ai_config' => [
                    'tone' => 'friendly',
                    'custom_rules' => [
                        'No physical branches. We deliver exclusively through Mylerz.',
                        'Sizes range from S to 3XL.'
                    ]
                ]
            ]
        );

        // 2. TechWave Electronics Store
        Tenant::updateOrCreate(
            ['id' => 2],
            [
                'name' => 'TechWave Electronics',
                'business_type' => 'ecommerce',
                'industry' => 'electronics',
                'fb_page_id' => '987654321098765', // Dummy Electronics Page ID
                'fb_page_token' => 'EAA_DUMMY_TECHWAVE_TOKEN',
                'openai_api_key' => config('agent.openrouter_api_key'),
                'ecommerce_config' => [
                    'provider' => 'gravoni', // Using same mocked API for testing
                    'api_url' => config('agent.gravoni_api_url'),
                    'secret_key' => config('agent.api_secret_key')
                ],
                'payment_config' => null, // TechWave has NO payment integration
                'ai_config' => [
                    'tone' => 'professional',
                    'custom_rules' => [
                        'Offer a 1-year warranty on all laptops and phones.',
                        'Highlight technical specifications like RAM, storage, and processor speed.'
                    ]
                ]
            ]
        );
    }
}

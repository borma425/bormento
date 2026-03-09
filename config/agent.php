<?php

return [
    'mode' => env('MODE', 'DEV'),
    'port' => env('PORT', 8000),

    // OpenRouter LLM
    'openrouter_api_key' => env('OPENROUTER_API_KEY'),
    'openrouter_model' => env('OPENROUTER_MODEL'),

    // Pinecone
    'pinecone_api_key' => env('PINECONE_API_KEY'),
    'pinecone_index_name' => env('PINECONE_INDEX_NAME', 'clothing-store-index'),

    // Facebook
    'fb_verify_token' => env('FB_VERIFY_TOKEN'),
    'fb_page_access_token' => env('FB_PAGE_ACCESS_TOKEN'),
    'fb_app_id' => env('FB_APP_ID'),

    // CashUp
    'cashup_api_url' => env('CASHUP_API_URL'),
    'cashup_api_key' => env('CASHUP_API_KEY'),
    'cashup_app_id' => env('CASHUP_APP_ID'),

    // Gravoni
    'gravoni_api_url' => env('GRAVONI_API_URL', 'https://app.gravoni.com'),
    'api_secret_key' => env('API_SECRET_KEY'),

    // Testers
    'testers_facebook_ids' => array_filter(explode(',', env('TESTERS_FACEBOOK_IDS', ''))),

    // Auto-reply comments
    'auto_reply_comments_enabled' => env('AUTO_REPLY_COMMENTS_ENABLED', false),

    // Webhook base URL
    'webhook_base_url' => env('WEBHOOK_BASE_URL', ''),

    // Instagram
    'ig_access_token' => env('IG_ACCESS_TOKEN', ''),
];

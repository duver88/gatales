<?php

return [

    /*
    |--------------------------------------------------------------------------
    | DeepSeek API Key
    |--------------------------------------------------------------------------
    |
    | Here you may specify your DeepSeek API Key. This will be used to
    | authenticate with the DeepSeek API. You can find your API key
    | on your DeepSeek dashboard at https://platform.deepseek.com
    */

    'api_key' => env('DEEPSEEK_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | DeepSeek Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for DeepSeek API requests. The default is the official
    | DeepSeek API endpoint.
    */

    'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout may be used to specify the maximum number of seconds to wait
    | for a response. By default, the client will time out after 120 seconds.
    */

    'request_timeout' => env('DEEPSEEK_REQUEST_TIMEOUT', 120),
];

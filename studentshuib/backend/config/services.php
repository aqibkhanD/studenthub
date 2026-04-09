<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // SSL Wireless SMS Gateway (Bangladesh)
    'sms' => [
        'url'       => env('SMS_GATEWAY_URL'),
        'key'       => env('SMS_GATEWAY_KEY'),
        'sender_id' => env('SMS_SENDER_ID', 'DIUSMS'),
    ],

    // FastAPI AI Service (Phase 4)
    'ai' => [
        'url'     => env('AI_SERVICE_URL', 'http://ai:8000'),
        'timeout' => env('AI_SERVICE_TIMEOUT', 30),
    ],

];

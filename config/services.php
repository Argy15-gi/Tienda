<?php

return [

    'frontend' => [
        'origin' => env('FRONTEND_ORIGIN', 'http://localhost:3000'),
    ],

    'demo' => [
        'enabled' => (bool) env('DEMO_LOGIN_ENABLED', true),
        'password' => env('DEMO_USER_PASSWORD', 'TiendaDev2026!'),
    ],

    // Detalles de la tienda que aparecen en la factura / comprobante
    'tienda' => [
        'nombre' => env('TIENDA_NOMBRE', 'La Tiendita'),
        'direccion' => env('TIENDA_DIRECCION', 'Av. del Parque 123, La Paz, Bolivia'),
        'telefono' => env('TIENDA_TELEFONO', '+591 700 00000'),
        'nit' => env('TIENDA_NIT', '1020304015'),
        'moneda' => 'Bs',
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', 'http://localhost:8000/auth/google/callback'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];

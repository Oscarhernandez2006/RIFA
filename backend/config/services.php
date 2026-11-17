<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

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

    'invoices' => [
        'url' => env('INVOICE_API_URL', 'https://apiconsulta.grupo-santacruz.com/ventas/facturas-pos'),
        'token' => env('INVOICE_API_QUERY_TOKEN'),
        'company' => (int) env('INVOICE_COMPANY', 4),
        'start_date' => env('INVOICE_CAMPAIGN_START', '2026-10-01'),
        'end_date' => env('INVOICE_CAMPAIGN_END', '2026-12-31'),
        // Pesos de compra (valor neto) necesarios por cada boleta; el residuo no cuenta.
        'ticket_value' => (int) env('INVOICE_TICKET_VALUE', 70000),
        // Puntos de venta por centro de operación (id_co). El consecutivo de la factura
        // se repite entre tiendas, por eso el cliente debe indicar dónde compró.
        'points_of_sale' => [
            402 => 'Malambo',
            403 => 'La Granja',
            405 => 'La 43',
            406 => 'Simón',
            407 => 'La 70',
            411 => 'Pereira Dosquebradas',
            412 => 'Bucaramanga',
            413 => 'La 93',
            414 => 'Centro',
            415 => 'Cartagena',
        ],
    ],

];

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
        'start_date' => env('INVOICE_CAMPAIGN_START', '2026-10-01'),
        'end_date' => env('INVOICE_CAMPAIGN_END', '2026-12-31'),
        // Pesos de compra (valor neto) necesarios por cada boleta; el residuo no cuenta.
        'ticket_value' => (int) env('INVOICE_TICKET_VALUE', 70000),
        // Puntos de venta por centro de operación (id_co), con la compañía (cia) a la que
        // pertenecen. El consecutivo se repite entre tiendas, por eso el cliente indica
        // dónde compró y la consulta a la API se hace con la cia de esa tienda.
        'points_of_sale' => [
            // 4 - CARNES SANTACRUZ S.A.S
            402 => ['name' => 'Malambo', 'company' => 4],
            403 => ['name' => 'La Granja', 'company' => 4],
            405 => ['name' => 'La 43', 'company' => 4],
            406 => ['name' => 'Simón', 'company' => 4],
            407 => ['name' => 'La 70', 'company' => 4],
            411 => ['name' => 'Pereira Dosquebradas', 'company' => 4],
            412 => ['name' => 'Bucaramanga', 'company' => 4],
            413 => ['name' => 'La 93', 'company' => 4],
            414 => ['name' => 'Centro', 'company' => 4],
            415 => ['name' => 'Cartagena', 'company' => 4],
            // 6 - CRISTIAN FABIAN SERRANO MILLAN
            603 => ['name' => 'Concord', 'company' => 6],
            605 => ['name' => 'Alameda 1', 'company' => 6],
            606 => ['name' => 'Alameda 2', 'company' => 6],
            // 7 - INVERSIONES SERUEDA S.A.S
            701 => ['name' => 'San Felipe', 'company' => 7],
            702 => ['name' => 'Olaya', 'company' => 7],
        ],
    ],

];

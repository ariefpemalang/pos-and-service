<?php

return [
    'defaults' => [
        'app_name' => env('APP_BRAND_NAME', 'AR Service'),
        'app_tagline' => env('APP_BRAND_TAGLINE', 'Service Smartphone & Laptop'),
        'app_description' => 'Sistem manajemen service smartphone dan laptop untuk transaksi, stok sparepart, dan layanan pelanggan.',
        'company_address' => '',
        'company_whatsapp' => '',
        'company_logo' => '',
        'hiwa_enabled' => false,
        'hiwa_token_device' => env('HIWA_TOKEN_DEVICE', ''),
    ],

    'encrypted_keys' => [
        'hiwa_token_device',
    ],

    'json_array_keys' => [
    ],
];

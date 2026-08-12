<?php

return [
    'device_types' => [
        'smartphone' => 'Smartphone',
        'laptop' => 'Laptop',
    ],

    'statuses' => [
        'received' => 'Diterima',
        'diagnosing' => 'Sedang dicek',
        'waiting_parts' => 'Menunggu sparepart',
        'in_progress' => 'Sedang dikerjakan',
        'ready' => 'Siap diambil',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ],

    'status_colors' => [
        'received' => 'secondary',
        'diagnosing' => 'info',
        'waiting_parts' => 'warning',
        'in_progress' => 'primary',
        'ready' => 'success',
        'completed' => 'success',
        'cancelled' => 'danger',
    ],

    'closed_statuses' => ['completed', 'cancelled'],

    'warranty_presets' => [
        0 => 'Tanpa garansi',
        7 => '7 hari',
        14 => '14 hari',
        30 => '30 hari',
        90 => '90 hari',
    ],

    'warranty_default_days' => 30,

    'warranty_max_days' => 365,

    'dp_refund_category' => 'Pengembalian Uang Muka',
];

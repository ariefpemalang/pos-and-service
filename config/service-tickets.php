<?php

return [
    'device_types' => [
        'smartphone' => 'Smartphone',
        'laptop' => 'Laptop',
    ],

    /*
     * All valid statuses (used for label resolution and backend validation).
     * Legacy statuses are kept so old DB rows still resolve labels and colours.
     */
    'statuses' => [
        'received'      => 'Proses Servis',
        'ready'         => 'Bisa diambil',
        'completed'     => 'Sudah diambil',
        'cancelled'     => 'Dibatalkan dan bisa diambil',
        // legacy — kept for label resolution; no longer selectable via UI
        'diagnosing'    => 'Sedang dicek',
        'waiting_parts' => 'Menunggu sparepart',
        'in_progress'   => 'Sedang dikerjakan',
    ],

    /*
     * Statuses technician/kasir can set manually from ticket UI.
     * "completed" (Sudah diambil) hanya lewat Nota Bayar — jangan di sini.
     */
    'selectable_statuses' => ['received', 'ready', 'cancelled'],

    /*
     * Status chips on ticket index / mobile list filters.
     */
    'filter_statuses' => ['received', 'ready', 'completed', 'cancelled'],

    'status_colors' => [
        'received'      => 'primary',
        'ready'         => 'success',
        'completed'     => 'success',
        'diagnosing'    => 'info',
        'waiting_parts' => 'warning',
        'in_progress'   => 'primary',
        'cancelled'     => 'danger',
    ],

    // Statuses that lock a ticket from further edits.
    'closed_statuses' => ['completed', 'cancelled'],

    'warranty_presets' => [
        0  => 'Tanpa garansi',
        7  => '7 hari',
        14 => '14 hari',
        30 => '30 hari',
        90 => '90 hari',
    ],

    'warranty_default_days' => 30,

    'warranty_max_days' => 365,

    'dp_refund_category' => 'Pengembalian Uang Muka',
];

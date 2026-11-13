<?php

return [
    [
        'group' => 'Dashboard',
        'access' => [
            'dashboard view',
            'dashboard finance view',
            'dashboard low stock view',
            'dashboard items view',
            'dashboard customers view',
            'dashboard technicians view',
            'dashboard services view',
            'dashboard commission view',
        ],
    ],
    [
        'group' => 'User',
        'access' => ['user view', 'user create', 'user edit', 'user delete'],
    ],
    [
        'group' => 'Role',
        'access' => ['role view', 'role create', 'role edit', 'role delete'],
    ],
    [
        'group' => 'Pelanggan',
        'access' => ['customer view', 'customer create', 'customer edit', 'customer delete'],
    ],
    [
        'group' => 'Supplier',
        'access' => ['supplier view', 'supplier create', 'supplier edit', 'supplier delete'],
    ],
    [
        'group' => 'Teknisi',
        'access' => ['technician view', 'technician create', 'technician edit', 'technician delete'],
    ],
    [
        'group' => 'Kategori Barang',
        'access' => ['item category view', 'item category create', 'item category edit', 'item category delete'],
    ],
    [
        'group' => 'Satuan Barang',
        'access' => ['item unit view', 'item unit create', 'item unit edit', 'item unit delete'],
    ],
    [
        'group' => 'Barang / Inventory',
        'access' => ['item view', 'item create', 'item edit', 'item delete'],
    ],
    [
        'group' => 'Stok Masuk',
        'access' => ['stock in view', 'stock in create'],
    ],
    [
        'group' => 'Stok Keluar',
        'access' => ['stock out view', 'stock out create'],
    ],
    [
        'group' => 'Laporan Stok',
        'access' => ['stock report view'],
    ],
    [
        'group' => 'Stock Opname',
        'access' => [
            'stock opname view',
            'stock opname create',
            'stock opname adjust',
        ],
    ],
    [
        'group' => 'Master Jasa',
        'access' => ['workshop service view', 'workshop service create', 'workshop service edit', 'workshop service delete'],
    ],
    [
        'group' => 'Tiket Servis',
        'access' => ['service ticket view', 'service ticket create', 'service ticket edit', 'service ticket delete'],
    ],
    [
        'group' => 'Transaksi',
        'access' => ['transaction view', 'transaction create', 'transaction edit', 'transaction delete'],
    ],
    [
        'group' => 'Pembelian',
        'access' => ['purchase view', 'purchase create', 'purchase edit', 'purchase delete'],
    ],
    [
        'group' => 'Laporan Keuangan',
        'access' => ['financial report view'],
    ],
    [
        'group' => 'Laporan Servis',
        'access' => ['service report view'],
    ],
    [
        'group' => 'Laporan Teknisi',
        'access' => ['technician report view'],
    ],
    [
        'group' => 'Laporan Aset Kategori',
        'access' => ['asset report view'],
    ],
    [
        'group' => 'Pemasukan Manual',
        'access' => ['manual income view', 'manual income create', 'manual income cancel'],
    ],
    [
        'group' => 'Pengeluaran Manual',
        'access' => ['manual expense view', 'manual expense create', 'manual expense cancel'],
    ],
    [
        'group' => 'Akun Bank',
        'access' => ['bank account view', 'bank account create', 'bank account edit', 'bank account delete'],
    ],
    [
        'group' => 'Pengaturan Aplikasi',
        'access' => ['settings view', 'settings edit'],
    ],
];

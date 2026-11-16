<?php
// Katalog narasi dan alias tampilan saja. Tidak mengubah aturan game atau data lama.
// Muat dengan $world = require __DIR__ . '/world.php'; tanpa koneksi database.
return [
    'title' => 'Lifevuww: Lima Poros',
    'intro' => 'Di Nawasela, lima wilayah terhubung oleh Poros yang merawat kehidupan sehari-hari. Kabut Kusut membuat jalurnya sulit terbaca. Kamu menjadi Perawat Poros: memilih satu tujuan, menata langkah kecil, dan menyambung kembali wilayah sesuai kemampuanmu. Jeda adalah bagian dari perjalanan.',
    'regions' => [
        'aksarimba' => [
            'label' => 'Arsip Aksarimba',
            'attribute' => 'Intelligence',
            'purpose' => 'Menjernihkan pemahaman melalui belajar, membaca, dan memecahkan masalah.',
        ],
        'lintasura' => [
            'label' => 'Punggung Lintasura',
            'attribute' => 'Physical',
            'purpose' => 'Membuka jalur gerak dan kebugaran sesuai kemampuan tubuh. Wilayah ini opsional.',
        ],
        'teduhara' => [
            'label' => 'Mata Air Teduhara',
            'attribute' => 'Health',
            'purpose' => 'Menjaga kebutuhan tidur, nutrisi, perawatan diri, dan pemulihan.',
        ],
        'bekalara' => [
            'label' => 'Lumbung Bekalara',
            'attribute' => 'Wealth',
            'purpose' => 'Menata bekal melalui pencatatan keuangan, pekerjaan, dan rencana pengeluaran.',
        ],
        'temuraya' => [
            'label' => 'Jembatan Temuraya',
            'attribute' => 'Social',
            'purpose' => 'Merawat hubungan, komunikasi, dan kontribusi pada komunitas.',
        ],
    ],
    'terms' => [
        'habit' => 'Kebiasaan',
        'quest' => 'Misi',
        'goal' => 'Tujuan',
        'milestone' => 'Langkah Tujuan',
        'focus_rift' => 'Ruang Fokus',
        'dungeon' => 'Ekspedisi Poros',
        'boss' => 'Simpul Penghalang',
        'companion' => 'Sahabat Poros',
        'recovery' => 'Kembali Bertahap',
        'planned_break' => 'Jeda Terencana',
    ],
    'ranks' => [
        'F-Rank Novice' => 'Penyemai Poros',
        'E-Rank Rookie' => 'Penjaga Poros',
        'D-Rank Explorer' => 'Perawat Poros',
        'C-Rank Vanguard' => 'Perintis Poros',
        'B-Rank Elite' => 'Penyambung Poros',
        'A-Rank Master' => 'Perangkai Poros',
        'S-Rank Sovereign' => 'Penata Poros',
    ],
    'specializations' => [
        'Obsidian Vanguard' => 'Perawat Serba',
        'Arcane Scholar' => 'Penjaga Aksara',
        'Crimson Striker' => 'Penuntun Gerak',
        'Solar Sovereign' => 'Perangkai Bekal',
        'Abyssal Archon' => 'Pengurai Simpul',
    ],
    'companions' => [
        'obsidian_wolf' => 'Ramu si Serigala',
        'chrono_owl' => 'Tilik si Burung Hantu',
        'crimson_drake' => 'Bara si Naga Kecil',
        'auric_griffin' => 'Pundi si Grifon',
        'aegis_tortoise' => 'Tedu si Kura-Kura',
    ],
    'companion_stages' => [
        'Hatchling' => 'Tunas',
        'Awakened' => 'Tumbuh',
        'Sovereign' => 'Berakar',
    ],
    'bosses' => [
        'gate_e_crawler' => 'Simpul Kusut',
        'gate_c_lich' => 'Penimbun Detik',
        'gate_b_colossus' => 'Dinding Tunda',
        'gate_a_valkyrie' => 'Pusaran Beban',
        'gate_s_monarch' => 'Jalinan Buntu',
    ],
    'focus_rifts' => [
        'alpha' => 'Ruang Awal',
        'beta' => 'Ruang Tekun',
        'crimson' => 'Ruang Mendalam',
    ],
];

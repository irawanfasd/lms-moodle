<?php
// Probe kelayakan Moodle di Vercel (vercel-php). HAPUS setelah selesai dites.
header('Content-Type: text/html; charset=utf-8');

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function row($label, $ok, $note = '') {
    $mark = $ok === null ? '–' : ($ok ? 'OK' : 'GAGAL');
    $color = $ok === null ? '#888' : ($ok ? '#0a7a3d' : '#b3261e');
    echo '<tr><td>' . h($label) . '</td><td style="color:' . $color . ';font-weight:600">' . $mark
       . '</td><td>' . h($note) . '</td></tr>';
}

$required    = ['ctype','curl','dom','gd','iconv','intl','json','mbstring','pcre','simplexml',
                'spl','xml','zip','pgsql','openssl','soap','sodium','tokenizer','fileinfo',
                'xmlreader','xmlwriter','zlib'];
$recommended = ['exif','opcache','pdo_pgsql','xmlrpc'];

echo '<!doctype html><meta charset="utf-8"><title>Moodle probe</title>';
echo '<style>body{font:15px system-ui;max-width:860px;margin:2rem auto;padding:0 1rem}
table{border-collapse:collapse;width:100%;margin-bottom:1.5rem}
td,th{border:1px solid #ccc;padding:6px 10px;text-align:left}</style>';
echo '<h1>Probe Moodle di vercel-php</h1>';

// 1. PHP
echo '<h2>1. PHP</h2><table><tr><th>Cek</th><th>Hasil</th><th>Catatan</th></tr>';
row('Versi PHP (Moodle 5.2 butuh 8.3 - 8.4)', version_compare(PHP_VERSION, '8.3.0', '>='), PHP_VERSION);
row('64-bit', PHP_INT_SIZE === 8, 'PHP_INT_SIZE=' . PHP_INT_SIZE);
row('memory_limit (min 128M)', null, ini_get('memory_limit'));
row('max_input_vars (min 5000)', (int)ini_get('max_input_vars') >= 5000, ini_get('max_input_vars'));
row('SAPI', null, php_sapi_name());
echo '</table>';

// 2. Ekstensi
echo '<h2>2. Ekstensi wajib</h2><table><tr><th>Ekstensi</th><th>Hasil</th><th></th></tr>';
$missing = [];
foreach ($required as $e) {
    $ok = extension_loaded($e);
    if (!$ok) { $missing[] = $e; }
    row($e, $ok);
}
echo '</table><h2>Ekstensi disarankan</h2><table><tr><th>Ekstensi</th><th>Hasil</th><th></th></tr>';
foreach ($recommended as $e) { row($e, extension_loaded($e)); }
echo '</table>';

// 3. Filesystem
echo '<h2>3. Filesystem</h2><table><tr><th>Cek</th><th>Hasil</th><th>Catatan</th></tr>';
$tmp = sys_get_temp_dir();
$tmpOk = @file_put_contents($tmp . '/probe.txt', 'x') !== false;
row('Tulis ke ' . $tmp, $tmpOk, 'ruang kosong: ' . round((@disk_free_space($tmp) ?: 0) / 1048576) . ' MB (sementara, hilang saat instance dibuang)');
$dirOk = @file_put_contents(__DIR__ . '/probe.txt', 'x') !== false;
row('Tulis ke folder kode', $dirOk, 'harusnya GAGAL (read-only), itulah kenapa moodledata tidak bisa di sini');
echo '</table>';

// 4. Database
echo '<h2>4. Koneksi Supabase (opsional)</h2><table><tr><th>Cek</th><th>Hasil</th><th>Catatan</th></tr>';
$host = getenv('PGHOST');
if (!$host || !function_exists('pg_connect')) {
    row('pg_connect', null, 'Lewati: isi env PGHOST, PGPORT, PGUSER, PGPASSWORD, PGDATABASE dan pastikan ekstensi pgsql ada');
} else {
    $dsn = sprintf('host=%s port=%s dbname=%s user=%s password=%s sslmode=require connect_timeout=8',
        $host, getenv('PGPORT') ?: '5432', getenv('PGDATABASE') ?: 'postgres',
        getenv('PGUSER'), getenv('PGPASSWORD'));
    $t = microtime(true);
    $c = @pg_connect($dsn);
    $ms = round((microtime(true) - $t) * 1000);
    if ($c) {
        $v = pg_fetch_result(pg_query($c, 'select current_setting(\'server_version\')'), 0, 0);
        row('Terhubung', true, "Postgres $v, $ms ms");
        pg_close($c);
    } else {
        row('Terhubung', false, "gagal setelah $ms ms (cek host/port/user, pakai session pooler)");
    }
}
echo '</table>';

// 5. Waktu
echo '<h2>5. Waktu</h2><table><tr><th>Cek</th><th>Hasil</th><th>Catatan</th></tr>';
row('Waktu eksekusi halaman ini', null, round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000) . ' ms');
echo '</table>';

echo '<h2>Ringkasan</h2><p>' . (empty($missing)
    ? 'Semua ekstensi wajib ada. Lanjut cek ukuran paket dan koneksi database.'
    : 'Ekstensi wajib yang belum ada: <b>' . h(implode(', ', $missing)) . '</b>. Moodle tidak akan bisa jalan di runtime ini.')
    . '</p>';

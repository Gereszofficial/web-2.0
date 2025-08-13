<?php
// cars.php – képfeltöltős automata JSON a „Autóink” részhez (index.html mellett)

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$root = __DIR__ . '/images/cars';
$baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); // pl. ""
$imgBase = $baseUrl . '/images/cars';

$exts = ['jpg','jpeg','png','webp','avif'];
$out = [];

function titleCase($slug){
  $s = preg_replace('/[-_]+/', ' ', $slug);
  return mb_convert_case($s, MB_CASE_TITLE, "UTF-8");
}
function labelFor($fname){
  $f = mb_strtolower($fname, 'UTF-8');
  if (str_contains($f, 'int') || str_contains($f, 'belso') || str_contains($f, 'belső') ) return 'Belső';
  return 'Külső';
}

if (is_dir($root)) {
  foreach (scandir($root) as $dir) {
    if ($dir === '.' || $dir === '..') continue;
    $full = $root . '/' . $dir;
    if (!is_dir($full)) continue;

    // képek
    $files = [];
    foreach (scandir($full) as $f) {
      if ($f === '.' || $f === '..') continue;
      $pi = pathinfo($f);
      $ext = strtolower($pi['extension'] ?? '');
      if (in_array($ext, $exts, true)) $files[] = $f;
    }
    if (empty($files)) continue;

    // cover
    $cover = null;
    $candidates = ['cover', '00-cover', '0-cover', '00_borito', 'borito', 'borító'];
    foreach ($candidates as $c) {
      foreach ($exts as $e) {
        $test = $c . '.' . $e;
        if (in_array($test, $files, true)) { $cover = $test; break 2; }
      }
    }
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    if ($cover === null) $cover = $files[0];

    // meta.json (title/subtitle/specs)
    $title = titleCase($dir);
    $subtitle = '';
    $specs = [];
    $metaPath = $full . '/meta.json';
    if (is_file($metaPath)) {
      $meta = json_decode(@file_get_contents($metaPath), true);
      if (is_array($meta)) {
        if (!empty($meta['title'])) $title = $meta['title'];
        if (!empty($meta['subtitle'])) $subtitle = $meta['subtitle'];
        if (!empty($meta['specs']) && is_array($meta['specs'])) $specs = $meta['specs'];
      }
    }

    // galéria
    $gallery = [];
    foreach ($files as $f) {
      $gallery[] = [
        'src'   => $imgBase . '/' . rawurlencode($dir) . '/' . rawurlencode($f),
        'label' => labelFor($f)
      ];
    }

    $out[] = [
      'id'       => $dir,
      'title'    => $title,
      'subtitle' => $subtitle,
      'cover'    => $imgBase . '/' . rawurlencode($dir) . '/' . rawurlencode($cover),
      'gallery'  => $gallery,
      'specs'    => $specs, // <<< EZ KELL AZ INDEXNEK
    ];
  }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

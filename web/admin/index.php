<?php
// /admin/index.php — BondiCars egyfelhasználós admin (feltöltés csak tulajnak)
session_start();

const ADMIN_PASS_HASH = '$2y$10$DgE07D2LoO9drdI0LVQcNey5leq2XqoJw2BnZdf7yBpsCdMAtlzI2'; // <-- a saját hash-ed

$IMG_ROOT = __DIR__ . '/../images/cars';
if (!is_dir($IMG_ROOT)) { @mkdir($IMG_ROOT, 0777, true); }
define('IMG_ROOT', $IMG_ROOT);

const MAX_FILE_SIZE = 16 * 1024 * 1024; // 16 MB
$OK_EXT = ['jpg','jpeg','png','webp','avif'];
$OK_MIME = ['image/jpeg','image/png','image/webp','image/avif'];

function logged_in(){ return !empty($_SESSION['auth']); }
function require_login(){ if (!logged_in()) { header('Location: ?login'); exit; } }
function slugify($s){ $s=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s); $s=strtolower(preg_replace('/[^a-z0-9]+/','-',$s)); return trim(preg_replace('/-+/','-',$s),'-'); }
function json_response($ok,$msg,$data=null){ header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>$ok,'msg'=>$msg,'data'=>$data], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function is_valid_image($tmp,$name){
  global $OK_EXT,$OK_MIME;
  $fi=finfo_open(FILEINFO_MIME_TYPE); $mime=finfo_file($fi,$tmp); finfo_close($fi);
  $ext=strtolower(pathinfo($name, PATHINFO_EXTENSION));
  return in_array($mime,$OK_MIME,true) && in_array($ext,$OK_EXT,true);
}

// ================== LOGIN ==================
if (isset($_GET['logout'])) {
  $_SESSION = [];
  session_destroy();
  header('Location: ./');
  exit;
}

if (isset($_GET['login'])) {
  $err = '';
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = $_POST['pw'] ?? '';
    if (password_verify($pw, ADMIN_PASS_HASH)) {
      $_SESSION['auth'] = true;
      header('Location: ./');
      exit;
    } else {
      $err = 'Hibás jelszó.';
    }
  }
  ?>
  <!doctype html>
  <html lang="hu">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BondiCars | Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
      :root{--bg:#0b0f16;--panel:#111827;--text:#e6edf3;--muted:#9aa4b2;--border:rgba(255,255,255,.12)}
      html,body{height:100%}
      body{margin:0;display:grid;place-items:center;background:var(--bg);color:var(--text);
           font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
      .card{background:var(--panel);border:1px solid var(--border);border-radius:14px;
            padding:22px;min-width:280px;box-shadow:0 30px 60px rgba(0,0,0,.45)}
      label{display:block;margin:10px 0 6px;color:var(--muted)}
      input[type=password]{width:100%;padding:12px 14px;border-radius:10px;border:1px solid var(--border);
            background:#0f1624;color:var(--text)}
      button{margin-top:12px;padding:12px 16px;border-radius:999px;border:1px solid var(--border);
             background:#e6edf3;color:#0b0f16;font-weight:700;cursor:pointer}
      .err{color:#ff8a8a;margin-top:8px}

      /* === Admin előnézetek: egységes arány === */
      .coverPrev{
        width:160px;
        aspect-ratio:16/9;
        height:auto;
        border-radius:12px;border:1px solid var(--border);
        background:#0f1624 center/cover no-repeat;
      }
      .t img{
        width:100%;
        aspect-ratio:16/9;
        height:auto;
        object-fit:cover;
        display:block;
      }
    </style>
  </head>
  <body>
    <form class="card" method="post" action="?login=1">
      <h2 style="margin:0 0 10px">BondiCars — Admin</h2>
      <label>Jelszó</label>
      <input type="password" name="pw" placeholder="••••••••" autofocus required>
      <?php if ($err !== ''): ?><div class="err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
      <button type="submit">Belépés</button>
    </form>
  </body>
  </html>
  <?php
  exit;
}
// ================== /LOGIN ==================
require_login();

// ================== API ==================
if (isset($_GET['api'])) {
  $a = $_GET['api'];

  // Lista (kiegészítve cover fájlnévvel)
  if ($a==='list') {
    global $OK_EXT;
    $cars=[];
    foreach (scandir(IMG_ROOT) as $dir) {
      if ($dir==='.'||$dir==='..') continue;
      $full=IMG_ROOT."/$dir"; if(!is_dir($full)) continue;

      $title=$dir; $subtitle='';
      if (is_file("$full/meta.json")) {
        $m=json_decode(@file_get_contents("$full/meta.json"),true);
        if(is_array($m)){ $title=$m['title']??$title; $subtitle=$m['subtitle']??''; }
      }

      // cover tényleges kiterjesztés
      $coverFile = null;
      foreach ($OK_EXT as $e) {
        if (is_file("$full/cover.$e")) { $coverFile = "cover.$e"; break; }
      }

      $cars[]=['id'=>$dir,'title'=>$title,'subtitle'=>$subtitle,'cover'=>$coverFile];
    }
    json_response(true,'ok',$cars);
  }

  // meta lekérése (specs-szel) egy autóra
  if ($a==='get' && $_SERVER['REQUEST_METHOD']==='POST') {
    $id = slugify($_POST['id']??''); if(!$id) json_response(false,'Hiányzó azonosító.');
    $dir=IMG_ROOT."/$id"; if(!is_dir($dir)) json_response(false,'Nem létezik.');
    $meta = ['title'=>$id,'subtitle'=>'','specs'=>[]];
    if (is_file("$dir/meta.json")) {
      $m=json_decode(@file_get_contents("$dir/meta.json"),true);
      if(is_array($m)) $meta=array_merge($meta,$m);
    }
    json_response(true,'ok',$meta);
  }

  // Létrehozás / meta frissítés  (SPEC-ek mentése)
  if ($a==='create' && $_SERVER['REQUEST_METHOD']==='POST') {
    $title=trim($_POST['title']??''); $subtitle=trim($_POST['subtitle']??''); $id=trim($_POST['id']??'');
    if(!$title) json_response(false,'Hiányzó cím.');
    $id=$id?slugify($id):slugify($title); $dir=IMG_ROOT."/$id";
    if(!is_dir($dir) && !@mkdir($dir,0755,true)) json_response(false,'Könyvtár nem hozható létre.');

    $specs = [];
    if (isset($_POST['specs'])) {
      $tmp = json_decode($_POST['specs'], true);
      if (is_array($tmp)) $specs = $tmp;
    }

    file_put_contents("$dir/meta.json", json_encode([
      'title'=>$title,
      'subtitle'=>$subtitle,
      'specs'=>$specs
    ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));

    json_response(true,'Létrehozva.',['id'=>$id]);
  }

  // Feltöltés (borító + tetszőleges számú galériakép)
  if ($a==='upload' && $_SERVER['REQUEST_METHOD']==='POST') {
    $id=slugify($_POST['id']??''); if(!$id) json_response(false,'Hiányzó azonosító.');
    $dir=IMG_ROOT."/$id"; if(!is_dir($dir)) json_response(false,'Autó nem létezik.');

    // Borító
    if (!empty($_FILES['cover']['tmp_name'])) {
      if($_FILES['cover']['size']>MAX_FILE_SIZE) json_response(false,'Borítókép túl nagy.');
      if(!is_valid_image($_FILES['cover']['tmp_name'], $_FILES['cover']['name'])) json_response(false,'Borítókép típusa tiltott.');
      $ext=strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
      foreach(glob("$dir/cover.*") as $old) @unlink($old);
      move_uploaded_file($_FILES['cover']['tmp_name'], "$dir/cover.$ext");
    }

    // Galéria (több fájl)
    if (!empty($_FILES['gallery'])) {
      $f=$_FILES['gallery']; $n=is_array($f['name'])?count($f['name']):0;
      $idx=1; foreach (glob("$dir/img-*.{jpg,jpeg,png,webp,avif}", GLOB_BRACE) as $p){
        $m = [];
        if (preg_match('/img-(\d+)\./i', basename($p), $m)) $idx=max($idx, intval($m[1])+1);
      }
      for($i=0;$i<$n;$i++){
        if(($f['error'][$i]??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK) continue;
        if(($f['size'][$i]??0)>MAX_FILE_SIZE) continue;
        $tmp=$f['tmp_name'][$i]; $name=$f['name'][$i];
        if(!is_valid_image($tmp,$name)) continue;
        $ext=strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $num=str_pad((string)$idx,3,'0',STR_PAD_LEFT);
        move_uploaded_file($tmp, "$dir/img-$num.$ext");
        $idx++;
      }
    }

    json_response(true,'Feltöltve.');
  }

  // Törlés → trash mappába
  if ($a==='delete' && $_SERVER['REQUEST_METHOD']==='POST') {
    $id=slugify($_POST['id']??''); if(!$id) json_response(false,'Hiányzó azonosító.');
    $src=IMG_ROOT."/$id"; if(!is_dir($src)) json_response(false,'Nem létezik.');
    $trash=realpath(__DIR__ . '/../images').'/trash'; if(!is_dir($trash)) @mkdir($trash,0755,true);
    $dst=$trash."/{$id}-".date('Ymd-His'); @rename($src,$dst);
    json_response(true,'Kukába helyezve.',['trash'=>$dst]);
  }

  json_response(false,'Ismeretlen művelet.');
}
?>
<!doctype html><html lang="hu"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>BondiCars – Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--bg:#0b0f16;--panel:rgba(14,18,28,.6);--text:#e6edf3;--muted:#9aa4b2;--accent:#58a6ff;--border:rgba(255,255,255,.12);--radius:18px}
body{margin:0;font-family:Inter,system-ui;background:radial-gradient(1200px 600px at 10% -10%, #0f1525 0%, var(--bg) 50%, #090c12 100%);color:var(--text)}
.wrap{max-width:1200px;margin:0 auto;padding:18px}
header{display:flex;justify-content:space-between;align-items:center}
.btn,button{border:1px solid var(--border);background:rgba(255,255,255,.04);color:var(--text);padding:10px 14px;border-radius:999px;font-weight:700;cursor:pointer}
.grid{display:grid;grid-template-columns:360px 1fr;gap:16px}
@media(max-width:980px){.grid{grid-template-columns:1fr}}
.panel{background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);box-shadow:0 30px 60px rgba(0,0,0,.45);padding:12px}
.list{display:grid;gap:10px;max-height:70vh;overflow:auto}
.row{display:flex;gap:10px;align-items:center;border:1px solid var(--border);border-radius:14px;padding:10px;background:rgba(255,255,255,.03)}
.row:hover{background:rgba(255,255,255,.06)}
.thumb{width:56px;height:42px;border-radius:10px;border:1px solid var(--border);background:#0f1624 center/cover}
.meta .id{color:var(--muted);font-size:12px}
.form{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.form .full{grid-column:1/-1}
label{display:grid;gap:6px;font-size:13px;color:var(--muted)}
input[type=text], select, input[type=number]{padding:10px 12px;border-radius:12px;border:1px solid var(--border);background:rgba(255,255,255,.04);color:var(--text)}
.coverPrev{width:160px;height:120px;border-radius:12px;border:1px solid var(--border);background:#0f1624 center/cover}
.drop{border:1px dashed var(--border);border-radius:14px;padding:12px;min-height:120px;background:rgba(255,255,255,.02)}
.thumbs{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-top:10px}
.t{position:relative;border:1px solid var(--border);border-radius:12px;overflow:hidden;background:#0f1624}
.t img{width:100%;height:92px;object-fit:cover}
.t .del{position:absolute;right:6px;top:6px;border:1px solid var(--border);background:rgba(0,0,0,.45);border-radius:999px;padding:4px 8px;cursor:pointer}
.hint{color:var(--muted);font-size:12px}
.bar{display:flex;gap:8px;justify-content:flex-end;padding-top:10px}

/* Spec blokk elrendezés */
.specs legend{font-weight:700;color:var(--muted)}
.spec-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media (max-width:700px){.spec-grid{grid-template-columns:1fr}}
</style>
<div class="wrap">
  <header>
    <h1>BondiCars – Admin</h1>
    <div><a href="../" target="_blank" class="btn">Nyit oldal</a> <a href="?logout" class="btn">Kijelentkezés</a></div>
  </header>

  <div class="grid">
    <div class="panel">
      <div style="display:flex;gap:8px"><input id="q" placeholder="Keresés…" style="flex:1;padding:10px 12px;border-radius:12px;border:1px solid var(--border);background:rgba(255,255,255,.04);color:var(--text)"><button id="add">+ Új autó</button></div>
      <div id="list" class="list"></div>
    </div>

    <div class="panel">
      <form class="form" id="frm" onsubmit="return false;">
        <div class="full hint">Add meg a címet/alcímet, majd specifikációk + borító + galéria → Mentés.</div>
        <label>Cím <input id="title" type="text" placeholder="Audi RS6"></label>
        <label>Alcím <input id="subtitle" type="text" placeholder="2021 · 4.0 TFSI"></label>
        <label class="full">Azonosító (mappa) <input id="carid" type="text" placeholder="audi-rs6"><span class="hint">Ha üres, a címből készül.</span></label>

        <!-- SPECIFIKÁCIÓK -->
        <fieldset class="full specs">
          <legend>Specifikációk</legend>
          <div class="spec-grid">
            <label>Üzemanyag
              <select id="spec_fuel">
                <option value="">–</option>
                <option>Benzin</option>
                <option>Dízel</option>
                <option>Hibrid</option>
                <option>Plug-in hibrid</option>
                <option>Elektromos</option>
                <option>LPG</option>
                <option>CNG</option>
              </select>
            </label>
            <label>Váltó
              <select id="spec_trans">
                <option value="">–</option>
                <option>Automata</option>
                <option>Kézi</option>
              </select>
            </label>
            <label>Hajtás
              <select id="spec_drive">
                <option value="">–</option>
                <option>FWD</option>
                <option>RWD</option>
                <option>AWD</option>
                <option>4x4</option>
              </select>
            </label>
            <label>Motor (típus)
              <input id="spec_engine" type="text" list="engine_list" placeholder="pl. 2.0 TFSI">
              <datalist id="engine_list">
                <option value="1.0 TSI"><option value="1.2 TSI"><option value="1.4 TSI">
                <option value="1.5 TSI"><option value="1.8 TFSI"><option value="2.0 TFSI">
                <option value="2.5 TFSI"><option value="3.0 TFSI"><option value="4.0 TFSI">
                <option value="2.0 TDI"><option value="3.0 TDI"><option value="1.5 dCi">
                <option value="2.0 dCi"><option value="2.0 EcoBoost"><option value="3.0 V6">
                <option value="4.0 V8"><option value="5.0 V10"><option value="Elektromos">
              </datalist>
            </label>
            <label>Teljesítmény (LE) <input id="spec_hp" type="number" min="1" step="1"></label>
            <label>Évjárat    <input id="spec_year" type="number" min="1950" max="2100"></label>
            <label>Futásteljesítmény (km) <input id="spec_km" type="number" min="0" step="1000"></label>
            <label>Szín       <input id="spec_color" type="text"></label>
            <label>Ajtók      <input id="spec_doors" type="number" min="2" max="6"></label>
            <label>Ülések     <input id="spec_seats" type="number" min="2" max="9"></label>
            <label>Ár (HUF)   <input id="spec_price" type="number" min="0" step="10000"></label>
          </div>
        </fieldset>
        <!-- /SPECIFIKÁCIÓK -->

        <div class="full" style="display:grid;grid-template-columns:160px 1fr;gap:12px;align-items:center">
          <div id="coverPrev" class="coverPrev"></div>
          <div><label class="btn"><input id="cover" type="file" accept="image/*" hidden> Borítókép kiválasztása</label><div class="hint">Ajánlott 1600px+ szélesség.</div></div>
        </div>
        <div class="full">
          <div class="drop" id="drop" style="display:flex;justify-content:space-between;align-items:center;gap:8px">
            <div class="hint">Húzd ide a galéria képeket, vagy kattints →</div>
            <label class="btn"><input id="gallery" type="file" accept="image/*" multiple hidden> Képek hozzáadása</label>
          </div>
          <div id="thumbs" class="thumbs"></div>
        </div>
      </form>
      <div class="bar"><button id="del">Kukába</button><button id="save">Mentés / Feltöltés</button></div>
    </div>
  </div>
</div>
<script>
// ===== util + API
const $=s=>document.querySelector(s);

// több fájl támogatás FormData-ban
const api = (n, d = {}, files = null) => {
  const fd = new FormData();
  Object.entries(d).forEach(([k, v]) => fd.append(k, v));
  if (files) {
    Object.entries(files).forEach(([k, v]) => {
      if (Array.isArray(v)) v.forEach(file => fd.append(k, file)); // pl. 'gallery[]'
      else if (v) fd.append(k, v);
    });
  }
  return fetch('?api=' + n, { method: 'POST', body: fd }).then(r => r.json());
};

const slug=s=>(s||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').replace(/--+/g,'-');
const state={list:[],current:null,coverFile:null,galleryFiles:[]};

// SPEC-ek olvasása / kitöltése
function readSpecs(){
  return {
    fuel: ($('#spec_fuel')?.value||'').trim(),
    engine: ($('#spec_engine')?.value||'').trim(),
    hp: parseInt($('#spec_hp')?.value)||null,
    transmission: ($('#spec_trans')?.value||'').trim(),
    year: parseInt($('#spec_year')?.value)||null,
    km: parseInt($('#spec_km')?.value)||null,
    color: ($('#spec_color')?.value||'').trim(),
    drivetrain: ($('#spec_drive')?.value||'').trim(),
    doors: parseInt($('#spec_doors')?.value)||null,
    seats: parseInt($('#spec_seats')?.value)||null,
    price_huf: parseInt($('#spec_price')?.value)||null
  };
}
function fillSpecs(sp){
  sp = sp || {};
  if ($('#spec_fuel'))  $('#spec_fuel').value  = sp.fuel||'';
  if ($('#spec_engine'))$('#spec_engine').value= sp.engine||'';
  if ($('#spec_hp'))    $('#spec_hp').value    = sp.hp??'';
  if ($('#spec_trans')) $('#spec_trans').value = sp.transmission||'';
  if ($('#spec_year'))  $('#spec_year').value  = sp.year??'';
  if ($('#spec_km'))    $('#spec_km').value    = sp.km??'';
  if ($('#spec_color')) $('#spec_color').value = sp.color||'';
  if ($('#spec_drive')) $('#spec_drive').value = sp.drivetrain||'';
  if ($('#spec_doors')) $('#spec_doors').value = sp.doors??'';
  if ($('#spec_seats')) $('#spec_seats').value = sp.seats??'';
  if ($('#spec_price')) $('#spec_price').value = sp.price_huf??'';
}

async function refresh(){
  const r=await fetch('?api=list'); const j=await r.json();
  if(j.ok){ state.list=j.data||[]; render(); }
}

function render(){
  const box=$('#list'); const q=$('#q').value?.toLowerCase()||'';
  box.innerHTML='';
  state.list
    .filter(it=>!q||(`${it.title} ${it.subtitle} ${it.id}`).toLowerCase().includes(q))
    .forEach(it=>{
      const row=document.createElement('div'); row.className='row';
      const th=document.createElement('div'); th.className='thumb';
      const coverPath = it.cover ? `../images/cars/${it.id}/${it.cover}` : `../images/cars/${it.id}/cover.jpg`;
      th.style.backgroundImage=`url('${coverPath}')`;
      const meta=document.createElement('div'); meta.className='meta';
      meta.innerHTML=`<div><b>${it.title}</b></div><div class="id">${it.id}</div>`;
      const sp=document.createElement('div'); sp.style.flex='1';
      const del=document.createElement('button'); del.textContent='Kukába';
      del.onclick=async(e)=>{e.stopPropagation(); if(!confirm('Biztosan törlöd?'))return; const res=await api('delete',{id:it.id}); alert(res.msg||'OK'); refresh();};
      row.append(th,meta,sp,del);
      row.onclick=()=>select(it);
      box.appendChild(row);
    });
}

function select(it){
  state.current=it; state.coverFile=null; state.galleryFiles=[];
  $('#title').value=it?.title||''; $('#subtitle').value=it?.subtitle||''; $('#carid').value=it?.id||'';
  const coverPath = it?.cover ? `../images/cars/${it.id}/${it.cover}` : (it?`../images/cars/${it.id}/cover.jpg`:'none');
  $('#coverPrev').style.backgroundImage= it ? `url('${coverPath}')` : 'none';
  $('#thumbs').innerHTML='';
  if (it?.id){
    api('get',{id: it.id}).then(r=>{
      if(r.ok && r.data) fillSpecs(r.data.specs||{});
    });
  } else {
    fillSpecs({});
  }
  window.scrollTo({top:0,behavior:'smooth'});
}

$('#add').onclick=()=>select({id:'',title:'',subtitle:''});
$('#q').oninput=render;

$('#title').oninput=()=>{ if(!$('#carid').value) $('#carid').value=slug($('#title').value); };

$('#cover').onchange=()=>{
  const f=$('#cover').files[0]; state.coverFile=f||null;
  if(f){ const r=new FileReader(); r.onload=()=>$('#coverPrev').style.backgroundImage=`url('${r.result}')`; r.readAsDataURL(f); }
};

$('#gallery').onchange=()=>{ addFiles(Array.from($('#gallery').files||[])); $('#gallery').value=''; };

$('#drop').addEventListener('dragover',e=>{e.preventDefault(); $('#drop').style.background='rgba(88,166,255,.10)';});
$('#drop').addEventListener('dragleave',()=>$('#drop').style.background='');
$('#drop').addEventListener('drop',e=>{
  e.preventDefault(); $('#drop').style.background='';
  addFiles(Array.from(e.dataTransfer.files||[]).filter(f=>f.type.startsWith('image/')));
});

function addFiles(fs){
  fs.forEach(f=>{
    state.galleryFiles.push(f);
    const t=document.createElement('div'); t.className='t';
    const img=document.createElement('img');
    const b=document.createElement('button'); b.className='del'; b.textContent='✕';
    b.onclick=()=>{ const i=state.galleryFiles.indexOf(f); if(i>=0){ state.galleryFiles.splice(i,1); t.remove(); } };
    const r=new FileReader(); r.onload=()=>img.src=r.result; r.readAsDataURL(f);
    t.append(img,b); $('#thumbs').appendChild(t);
  });
}

$('#save').onclick=async()=>{
  const title=$('#title').value.trim(); const subtitle=$('#subtitle').value.trim(); let id=$('#carid').value.trim();
  if(!title){alert('Cím kötelező');return;}

  const specs = readSpecs();

  const c=await api('create',{title,subtitle,id,specs: JSON.stringify(specs)});
  if(!c.ok){alert(c.msg||'Hiba a létrehozásnál');return;}
  id=c.data.id;

  const files={};
  if(state.coverFile) files['cover']=state.coverFile;
  if(state.galleryFiles.length) files['gallery[]']=state.galleryFiles;

  if(Object.keys(files).length){
    const up=await api('upload',{id},files);
    if(!up.ok){alert(up.msg||'Hiba a feltöltésnél');return;}
  }

  alert('Kész. A főoldal automatikusan megjeleníti.');
  state.galleryFiles=[]; $('#thumbs').innerHTML=''; $('#cover').value=''; $('#gallery').value='';
  refresh();
};

$('#del').onclick=async()=>{
  const id=$('#carid').value.trim(); if(!id){alert('Nincs kiválasztott autó');return;}
  if(!confirm('Biztosan törlöd? (Kukába kerül)'))return;
  const r=await api('delete',{id}); alert(r.msg||'OK'); select({id:'',title:'',subtitle:''}); refresh();
};

refresh();
</script>

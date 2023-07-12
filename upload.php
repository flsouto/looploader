<?php
use FlSouto\Sampler;
$conf = require(__DIR__."/config.php");
require_once($conf['smp_path']);

$uploaded = array_flip(file(__DIR__."/uploaded.log"));
$file = '';
$hash = '';

foreach(glob($conf['queue_path']) as $f){
    $h = str_replace('.mp3','',basename($f));
    if(!isset($uploaded[$h])){
        $hash = $h;
        $file = $f;
        break;
    }
}

if(!$file){
    die("All files from queue uploaded\n");
}

$s = new Sampler($file);
$bpm = 'XXX'; // todo resize and calc BPM

if(strstr($file,'.mp3')){
    $s->save($file = "/dev/shm/$hash.wav");
}

$ch = curl_init("https://www.looperman.com/loops/admin");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_COOKIEFILE, $conf['cookies_path']);
$output = curl_exec($ch);

preg_match("/name=\"csrftoken\" value=\"([^\"]+)\"/",$output,$m);

if(empty($m[1])){
    die("Oops: missing csrftoken. Looks like a login problem...\n");
}

$csrf = $m[1];
$cookies = "loop_csrfc=".$csrf;

$fields['csrftoken'] = $csrf;
$fields['loop_title'] = $hash;
$fields['loop_desc'] = pick($conf['desc']);
$fields['loop_wav'] = new CurlFile($file, 'audio/wav');
$fields['loop_cat_id'] = pick($conf['cat_id']);
$fields['loop_genre_id'] = pick($conf['genre_id']);
$fields['loop_meta_daw_id'] = '1';
$fields['loop_tempo'] = $bpm;
$fields['loop_key'] = '';
$fields['loop_disclaimer'] = '1';
$fields['submit'] = 'Insert';

$ch = curl_init("https://www.looperman.com/loops/admin");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_HEADER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: multipart/form-data']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

curl_setopt($ch, CURLOPT_COOKIE, $cookies);
curl_setopt($ch, CURLOPT_COOKIEFILE, $conf['smp_path']);
//curl_setopt($ch, CURLOPT_VERBOSE, true);

$out = curl_exec($ch);

file_put_contents(__DIR__."/uploaded.log","$hash\n",FILE_APPEND);
shell_exec("git add uploaded.log; git commit -m 'uploaded'; git push origin main");

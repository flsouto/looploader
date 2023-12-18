<?php
parse_str(implode('&',$argv),$params);

use FlSouto\Sampler;
require_once(__DIR__."/utils.php");

$uploaded = array_flip(array_map('trim',file(__DIR__."/uploaded.log")));
$file = '';
$hash = '';
$remain = 0;
//$files = glob($conf['queue_path']);
$files = glob("queue/05e*a63.wav");
shuffle($files);
foreach($files as $f){
    $h = str_replace(['.mp3','.wav'],'',basename($f));
    if(!isset($uploaded[$h])){
        if(isset($params['--info'])){
            $remain++;
            continue;
        }
        $hash = $h;
        $file = $f;
        break;
    }
}
if(isset($params['--info'])){
    echo "Remain: $remain\n";
    die();
}

if(!$file){
    die("All files from queue uploaded\n");
}

$s = sampler($file);
$mb = filesize($file) / (1024 * 1024);

if(filesize($file) > 8.5){
    $s->cut(0,'1/2');
}

$bpm = fixBPM($s);

if(strstr($file,'.mp3')){
    $s->save($file = "/dev/shm/$hash.wav");
    $s = sampler($file);
}

$info = shell_exec("soxi $s->file");
if(!stristr($info,'16-bit')){
    $tmp = "/dev/shm/".uniqid().".wav";
    shell_exec("sox $s->file -b16 $tmp");
    $file = $tmp;
    $s = sampler($file);
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
$fields['loop_desc'] = pick($conf['description']);
$fields['loop_wav'] = new CurlFile($file, 'audio/wav');

$fields['loop_cat_id'] = match(true){
    !!stristr($hash,'sy') => '4',
    default => pick($conf['cat_id'])
};

//$fields['loop_cat_id'] = stristr($hash,'sy') ? '4' : pick($conf['cat_id']);

$fields['loop_genre_id'] = match(true){
    !!stristr($hash,'brk') => '37',
    !!stristr($hash,'amb') => '2',
    default => pick($conf['genre_id'])
};

$fields['loop_genre_id'] = stristr($hash,'brk') ? '37' : pick($conf['genre_id']);


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
curl_setopt($ch, CURLOPT_COOKIEFILE, $conf['cookies_path']);
//curl_setopt($ch, CURLOPT_VERBOSE, true);

$out = curl_exec($ch);
file_put_contents(__DIR__."/output.html", $out);

file_put_contents(__DIR__."/uploaded.log","$hash\n",FILE_APPEND);
shell_exec("git add uploaded.log; git commit -m 'uploaded'; git push origin master");

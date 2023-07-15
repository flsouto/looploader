<?php

$conf = require(__DIR__."/config.php");
require_once($conf['smp_path']);

function sampler($f){
    return new FlSouto\Sampler($f);
}

function pick($array){
    return $array[array_rand($array)];
}


function getRefLen($sample){
	$len = $sample->len();
	if($len <= 4){
		return 4;
	} else if($len <= 8){
		return 8;
	} else {
		return 16;
	}
}

function calcBPM($sample){
	return 120 / $sample->len() * getRefLen($sample);
}

function fixBPM($sample){
	$bpm = calcBPM($sample);
	if(strstr(strval($bpm),".")){
		$bpm = round($bpm);
		$sample->resize(120 * getRefLen($sample) / $bpm);
	}
	return $bpm;
}

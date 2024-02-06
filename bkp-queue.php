<?php

$config = require(__DIR__."/config.php");

foreach($config['backup_paths'] as $to){
    $from = dirname($config['queue_path']);
    passthru("rsync -avhr --ignore-existing $from $to");
}

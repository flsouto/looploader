<?php

$config = require(__DIR__."/config.php");

foreach($config['backup_paths'] as $path){
    passthru("rsync -avh $config[queue_path] $path");
}

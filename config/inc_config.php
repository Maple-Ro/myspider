<?php
$GLOBALS['config']['db'] = array(
    'host'  => '192.168.56.101',
    'port'  => 3306,
    'user'  => 'root',
    'pass'  => '',
    'name'  => 'demo',
);

$GLOBALS['config']['redis'] = array(
    'host'      => '192.168.56.101',
    'port'      => 6379,
    'pass'      => '',
    'prefix'    => 'phpspider',
    'timeout'   => 30,
);

include "inc_mimetype.php";

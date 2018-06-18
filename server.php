<?php
error_reporting(-1);
ini_set("display_errors", true);

require __DIR__ . '/vendor/autoload.php';

$server = new \DDZ\Server(new \Hoa\Socket\Server("ws://0.0.0.0:34568")); 
// 34567 for eth , 34568 for neb
$server->start();

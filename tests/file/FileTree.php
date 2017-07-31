<?php

use hrm\file\FileServer;

require_once dirname(__FILE__) . '/../../inc/bootstrap.php';


// just in case...
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    return;
}

if (isset($_REQUEST['dir'])) {
    $dir = $_REQUEST['dir'];
} else {
    $dir = '/data/images/felix/src';
}

$server = new FileServer($dir);
$tree1 = $server->scan();
$tree2 = $server->scan(null, true, true);

echo "<html><body>";
echo "<h1>Directory tree</h1>" . FileServer::json2html($server->getDirectoryTree());
echo "<h1>Entire file dictionary</h1>" . FileServer::json2html($tree1);
echo "<h1>File dictionary with collapsed time series</h1>" . FileServer::json2html($tree2);
echo "<h1>File list</h1>" . FileServer::json2html($server->getFileList("/data/images/felix/src/time-series"));
echo "</body></html>";

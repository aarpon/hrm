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

$server = new FileServer($dir, false, false);
$tree = $server->getDirectoryTree();
$dir1 = $server->getFileDictionary();

$server = new FileServer($dir, true, false);
$dir3 = $server->getFileDictionary();
$fl1 = $server->getFileList($dir . '/real');
$fl2 = $server->getFileList($dir . '/time-series');

$server->explodeImageTimeSeries();
$dir4 = $server->getFileDictionary();


echo "<html><body>";
echo "<h1>Directory tree</h1>" . FileServer::array2html($tree);
echo "<h1>File dictionary</h1>" . FileServer::array2html($dir1);
echo "<h1>File dictionary including imploded time series </h1>" . FileServer::array2html($dir3);
echo "<h1>File list of the real directory</h1>" . FileServer::array2html($fl1);
echo "<h1>File list of the time-series directory</h1>" . FileServer::array2html($fl2);
echo "<h1>Directory tree (re-)exploded</h1>" . FileServer::array2html($dir4);
echo "</body></html>";

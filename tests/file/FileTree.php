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
$dict1 = $server->getFileDictionary();

$server = new FileServer($dir);
$dict2 = $server->getFileDictionary();

echo "<html><body>";
echo "<h1>Directory tree</h1>" . FileServer::array2html($server->getDirectoryTree());
echo "<h1>Entire file dictionary</h1>" . FileServer::array2html($dict1);
echo "<h1>File dictionary with collapsed time series</h1>" . FileServer::array2html($dict2);
echo "<h1>File list</h1>" . FileServer::array2html($server->getFileList($dir));
echo "</body></html>";

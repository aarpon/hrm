<?php

use hrm\file\FileServer;

require_once dirname(__FILE__) . '/../../inc/bootstrap.php';

/**
 * This test works once you are logged in to HRM and there is a session...
 */


session_start();
// just in case...
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    return;
}

if (!isset($_SESSION['user'])) {
    return;
}

$username = $_SESSION['user']->name();
$server = new FileServer($username, true, false);
$tree = $server->getDirectoryTree('src');
$fl3 = $server->getRelativeFileDirectory();

$server = new FileServer($username, true, true);
$fl0 = $server->getFileDictionary();

$dir = $server->getAbsolutePath('src/real');
$ms = $server->getImageMultiSeries($server->getAbsolutePath('src/real/cells.lif'));
//$ms = $server->getImageMultiSeries($server->getAbsolutePath('src/real/cells.lif'));
$fl1 = $server->getFileList($dir);
$dir = $server->getAbsolutePath('src/time-series');
$fl2 = $server->getFileList($dir);

$server->explodeImageTimeSeries();
$dir4 = $server->getFileDictionary();


echo "<html><body>";
echo "<h1>Directory tree</h1>" . FileServer::array2html($tree);
echo "<h1>File dictionary (paths relative to user root, filter hidden stuff)</h1>" . FileServer::array2html($fl3);
echo "<h1>File dictionary including all multi-series </h1>" . FileServer::array2html($fl0);
echo "<h1>File list of the real directory</h1>" . FileServer::array2html($fl1);
echo "<h1>Get Multi-Series</h1>" . FileServer::array2html($ms);
echo "<h1>File list of the time-series directory</h1>" . FileServer::array2html($fl2);
echo "<h1>Directory tree (re-)exploded</h1>" . FileServer::array2html($dir4);
echo "</body></html>";

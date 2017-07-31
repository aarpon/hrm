<?php
/**
 * FileServer
 *
 * asynchronous queries on the file system.
 *
 * The URL of type 'ajax/FileServer.php?dir=/some/dir/on/filesystem'
 *      returns the directory tree of that root folder
 * The URL of the following types
 *      'ajax/FileServer.php?dir=/some/dir/on/fs/&ext=tif
 *      'ajax/FileServer.php?dir=/some/dir/on/fs/&ext=tif&collapse=true
 *      return the file list of the given directory
 *
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

require_once dirname(__FILE__) . '/../inc/bootstrap.php';

use hrm\user\UserV2;
use hrm\file\FileServer;


session_start();

// If the user is not logged on, we return without doing anything
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
        print_r("not authenticated");
        return;
    }
}

if (!isset($_SESSION['fileserver'])) {
    print_r("no fileserver");
    return;
}

$fs = $_SESSION['fileserver'];
assert($fs instanceof FileServer);

if (!isset($_REQUEST['dir'])) {
    return;
}

$dir = rtrim($_REQUEST['dir'], '/');
$collapse = isset($_REQUEST['collapse']) && strtolower($_REQUEST['collapse']) == "true";

if ($collapse) {
    $fs->collapseImageTimeSeries();
} else {
    $fs->explodeImageTimeSeries();
}

if (isset($_REQUEST['ext'])) {
    $ext = $_REQUEST['ext'];
    echo json_encode($fs->getFileList($dir, $ext), JSON_FORCE_OBJECT);
} else if ($fs->isRootDirectory($dir)) {
    echo json_encode($fs->getDirectoryTree(), JSON_FORCE_OBJECT);
}

$_SESSION['fileserver'] = $fs;

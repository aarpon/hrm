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
 * TODO: delete before merging to devel
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

require_once dirname(__FILE__) . '/../inc/bootstrap.php';

use hrm\file\FileServer;


// Handle session
session_start();
/*
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
        return;
    }
}*/

if (!isset($_SESSION[FileServer::$SESSION_KEY])) {
    return;
}

$fs = $_SESSION[FileServer::$SESSION_KEY];
assert($fs instanceof FileServer);


// Parse request
if (!isset($_REQUEST['dir'])) {
    return;
}

$dir = rtrim($_REQUEST['dir'], '/');
$collapse = isset($_REQUEST['collapse']) && strtolower($_REQUEST['collapse']) == "true";
$ext = $_REQUEST['ext'];


// Process request
if ($collapse) {
    if (!$fs->hasImplodedTimeSeries()) {
        $fs->implodeImageTimeSeries();
    }
} else {
    if ($fs->hasImplodedTimeSeries()) {
        $fs->explodeImageTimeSeries();
    }
}

if (isset($ext)) {
    echo json_encode($fs->getFileList($dir, $ext), JSON_FORCE_OBJECT);
} else if ($fs->isRootDirectory($dir)) {
    echo json_encode($fs->getDirectoryTree(), JSON_FORCE_OBJECT);
}

$_SESSION[FileServer::$SESSION_KEY] = $fs;

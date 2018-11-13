<?php
/**
 * filesystem
 *
 * asynchronous queries on the file system.
 *
 * Base URL
 *    [domain]/hrm/ajax/filesystem.php?
 *
 * Requests:
 *    dirs
 *          -> returns nested array with the directory tree as json
 *    files=/some/dir
 *          -> returns an array containing arrays for each entry in the directory with the following information
 *             (file name, modification time, multi-series flag, time-series flag, series/file count)
 *    files=/some/dir&ext=[file extension]$collapse=[true|false]
 *          -> returns an array containing arrays for each entry in the directory with collapsed/imploded time-series
 *    multi-series=/some/file.tif
 *          -> returns an array of series names
 *    time-series=/some/file.tif
 *          -> returns an array of series names
 *
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

if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
        return;
    }
}

if (!isset($_SESSION[FileServer::$SESSION_KEY])) {
    return;
}

$fs = $_SESSION[FileServer::$SESSION_KEY];
assert($fs instanceof FileServer);


// Determine request type
if (isset($_REQUEST['dirs'])) {
    $queryType = 'dirs';
} else if (isset($_REQUEST['files'])) {
    $queryType = 'files';
} else if (isset($_REQUEST['multi-series'])) {
    $queryType = 'multi-series';
} else if (isset($_REQUEST['time-series'])) {
    $queryType = 'time-series';
} else if (isset($_REQUEST['ls'])) {
    $queryType = 'ls';
}


// Create response
switch ($queryType) {
    case 'dirs':
        $path = rtrim($_REQUEST['ls'], '/');
        $response = $fs->getDirectoryTree();
        break;

    case 'files':
        $path = rtrim($_REQUEST['files'], '/');
        $collapse = isset($_REQUEST['collapse']) && strtolower($_REQUEST['collapse']) == "true";
        if ($collapse) {
            $fs->implodeImageTimeSeries();
        } else {
            $fs->explodeImageTimeSeries();
        }
        $response = $fs->getFileDictionary();
        break;

    case 'ls':
        $path = rtrim($_REQUEST['ls'], '/');
        $ext = $_REQUEST['ext'];
        $response = $fs->getFileList($path, $ext);
        break;

    case 'multi-series':
        $path = rtrim($_REQUEST['multi-series'], '/');
        $response = $fs->getImageFileSeries($path);
        break;

    case 'time-series':
        $path = rtrim($_REQUEST['time-series'], '/');
        $response = $fs->getImageTimeSeries($path);
        break;

    default:
        $response = null;
        break;
}

// TODO test some more
if ($response == null) {
    $code = 500;
} else {
    $code = 200;
}

// Update the sessions FileServer object
$_SESSION[FileServer::$SESSION_KEY] = $fs;

// Post the response
//echo FileServer::array2html($response);
header("Content-Type: application/json", true, $code);
echo json_encode($response, JSON_FORCE_OBJECT);

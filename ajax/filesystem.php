<?php
/**
 * filesystem
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

require_once dirname(__FILE__) . '/../inc/bootstrap.php';

use hrm\file\FileServer;

/**
 * Asynchronous queries on the file system.
 *
 * Base URL
 *    [domain]/hrm/ajax/filesystem.php?
 *
 * Requests:
 *    dirs
 *          -> returns nested array with the directory tree as json (relative to the user root)
 *
 *    files&[implode|explode]
 *          -> returns an array of directories containing each an array with all the file entries
 *
 *    ls=/some/dir&ext=[file extension]
 *          -> users relative paths to the user content root!
 *             returns an array containing arrays for each entry in the directory with collapsed/imploded time-series
 *             and without the multi-series displayed. These have to be queried separately using the multi-series and
 *             the time-series argument.
 *             Response array content:
 *             (file name, modification time, multi-series flag, time-series flag, series/file count)
 *
 *    multi-series=/some/file.tif
 *          -> users relative paths to the user content root!
 *             returns an array of series names
 *
 *    time-series=/some/file.tif
 *          -> users relative paths to the user content root!
 *             returns an array of file names
 */


// Handle session
session_start();

if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
        return;
    }
}

if (isset($_SESSION[FileServer::$SESSION_KEY])) {
    $fs = $_SESSION[FileServer::$SESSION_KEY];
} else {
    $fs = new FileServer($_SESSION['user']->name());
}

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
        $path = rtrim($_REQUEST['dirs'], '/');
        $response = $fs->getDirectoryTree();
        break;

    case 'files':
        if (isset($_REQUEST['implode']) ||
            (!isset($_REQUEST['implode']) && !isset($_REQUEST['explode']))) {
            $fs->implodeImageTimeSeries();
        } else if (isset($_REQUEST['explode'])) {
            $fs->explodeImageTimeSeries();
        }
        $response = $fs->getRelativeFileDirectory();
        break;

    case 'ls':
        $path = rtrim($_REQUEST['ls'], '/');
        $path_abs = $fs->getAbsolutePath($path);
        $ext = null;
        if (isset($_REQUEST['ext'])) {
            $ext = $_REQUEST['ext'];
        }
        $response = $fs->getFileList($path_abs, $ext);
        break;

    case 'multi-series':
        $path = rtrim($_REQUEST['multi-series'], '/');
        $path_abs = $fs->getAbsolutePath($path);
        $response = $fs->getImageFileSeries($path_abs);
        break;

    case 'time-series':
        $path = rtrim($_REQUEST['time-series'], '/');
        $path_abs = $fs->getAbsolutePath($path);
        $response = $fs->getImageTimeSeries($path_abs);
        break;

    default:
        $response = null;
        break;
}

// TODO test some more
if ($response === null) {
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

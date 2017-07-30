<?php
/**
 * FileServer
 *
 * asynchronous queries on the file system.
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

if (!isset($_REQUEST['dir'])) {
    return;
}

$dir = rtrim($_REQUEST['dir'], '/');
$format = $_REQUEST['ext'];


if ($fs->isRootDirectory($dir)) {
    if ($format == null) {
        echo json_encode($fs->getDirectories(), JSON_FORCE_OBJECT);
    } else {
        echo json_encode($fs->getFiles($dir, $format));
    }
} else {
    echo json_encode($fs->getFiles($dir, $format), JSON_FORCE_OBJECT);
}

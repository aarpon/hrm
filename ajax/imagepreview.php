<?php
/**
 * Class ImagePreviews
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

use hrm\file\ImagePreviews;

require_once dirname(__FILE__) . '/../inc/bootstrap.php';

/**
 * Base URL
 * [domain]/hrm/ajax/imagepreview.php?
 *
 * Requests:
 *     info=/path/to/some/image.file
 *          -> returns a json object with information about the image previews
 *
 *     thumbnail=/path/to/image.file
 *          -> send the image preview XY
 *     thumbnail=/path/to/image.file&view=[preview_xy|preview_xz|preview_xy]
 *          -> returns the image preview in the requested perspective
 *
 *     result=/path/to/image.file&size=[preview|integer]&$view[xy|xz|yz|...]&orig=[true|false]
 *          -> To see the possible values of the view argument @see ImagePreviews::getViewConstants()
 *             potentially all the arguments can be empty, but if no single result file can be identified,
 *             the default @see ImagePreviews::DEFAULT_OUTPUT will be returned.
 *
 *     movie=/path/to/image.file
 *          -> movie download
 *             the stack movie is part of the result files. For convenience however, it has its own url
 */


// Handle session
session_start();

if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
        return;
    }
}


// Determine the request type
if (isset($_REQUEST['info'])) {
    $action = 'info';
} else if (isset($_REQUEST['thumbnail'])) {
    $action = 'thumbnail';
} else if (isset($_REQUEST['result'])) {
    $action = 'result';
} else if (isset($_REQUEST['movie'])) {
    $action = 'movie';
} else {
    return;
}

// Instantiate previews object, get url arguments
$username = $_SESSION['user']->name();
$previews = new ImagePreviews($username);


switch ($action) {

    case 'thumbnail':
        $img_path = $_REQUEST['thumbnail'];
        if (isset($_REQUEST['view'])) {
            $view = $_REQUEST['view'];
            $thumb_path = $previews->getThumbnailPath($img_path, $view);
        } else {
            $thumb_path = $previews->getThumbnailPath($img_path);
        }

        header("Content-Type: image/jpeg");
        readfile($thumb_path);
        break;

    case 'info':
        $img_path = $_REQUEST['info'];
        $info = $previews->getInfo($img_path);

        header("Content-Type: application/json", true, 200);
        echo json_encode($info, JSON_FORCE_OBJECT);
        break;

    case 'result':
        $img_path = $_REQUEST['result'];

        if (isset($_REQUEST['size'])) {
            $size = $_REQUEST['size'];
        } else {
            $size = '';
        }

        if (isset($_REQUEST['view'])) {
            $view = $_REQUEST['view'];
        } else {
            $view = $previews::VIEW_XY;
        }

        if (isset($_REQUEST['orig'])) {
            $orig = filter_var($_REQUEST['orig'], FILTER_VALIDATE_BOOLEAN);
        } else {
            $orig = '';
        }

        $res_path = $previews->getResultPath($img_path, $size, $view, $orig);

        header("Content-Type: image/jpeg");
        readfile($res_path);
        break;

    case 'movie':
        $img_path = $_REQUEST['movie'];
        $res_path = $previews->getResultPath($img_path, '', 'stack', false, 'avi');
        $size = filesize($res_path);

        if (preg_match('/.*jpg$/', $res_path)) {
            header("Content-Type: image/jpeg");
            readfile($res_path);
        } else if ($size) {
            $type = "video/x-msvideo";
            $avi_filename = basename($res_path);

            header("Accept-Ranges: bytes");
            header("Connection: close");
            header("Content-Disposition-type: attachment");
            header("Content-Disposition: attachment; filename=\"$avi_filename\"");
            header("Content-Length: $size");
            header("Content-Type: $type; name=\"$avi_filename\"");
            readfile($res_path);
        }

        break;
}

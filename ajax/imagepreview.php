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
}

<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\DatabaseConnection;
use hrm\Nav;
use hrm\param\base\Parameter;
use hrm\param\CCDCaptorSizeX;
use hrm\param\ImageFileFormat;
use hrm\param\NumericalAperture;
use hrm\param\PinholeSize;
use hrm\param\PinholeSpacing;
use hrm\param\TimeInterval;
use hrm\param\ZStepSize;
use hrm\Util;

require_once dirname(__FILE__) . '/inc/bootstrap.php';


/* *****************************************************************************
 *
 * START SESSION, CHECK LOGIN STATE, INITIALIZE WHAT NEEDED
 *
 **************************************************************************** */

session_start();

$message = "";

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit();
}

if (isset($_GET['home'])) {
    header("Location: " . "home.php");
    exit();
}

/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */
$saveToDB = true;
$pageToGo = 'select_parameter_settings.php';
if ($_SESSION['setting']->checkPostedCapturingParameters($_POST)) {
    if ($saveToDB) {
        $saved = $_SESSION['setting']->save();
        $message = $_SESSION['setting']->message();
        if ($saved) {
            header("Location: " . $pageToGo);
            exit();
        }
    }
    header("Location: " . $pageToGo);
    exit();
} else {
    $message = $_SESSION['setting']->message();
}

// The Twig handler.
$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader);

echo $twig->render('capturing_parameter.twig',
    array('chanCnt' => $_SESSION['setting']->numberOfChannels(),
          'message' => $message));
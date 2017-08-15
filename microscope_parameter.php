<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\DatabaseConnection;
use hrm\Nav;
use hrm\param\base\Parameter;
use hrm\setting\ParameterSetting;
use hrm\Util;

require_once dirname(__FILE__) . '/inc/bootstrap.php';


/* *****************************************************************************
 *
 * START SESSION, CHECK LOGIN STATE, INITIALIZE WHAT NEEDED
 *
 **************************************************************************** */

session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit();
}

if (isset($_GET['home'])) {
    header("Location: " . "home.php");
    exit();
}

$message = "";

/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */
if ($_SESSION['setting']->checkPostedMicroscopyParameters($_POST)) {
    // Continue to the next page
    header("Location: " . "capturing_parameter.php");
    exit();
} else {
    $message = $_SESSION['setting']->message();
}

// The Twig handler.
$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader);

echo $twig->render('microscope_parameter.twig',
                   array('chanCnt' => $_SESSION['setting']->numberOfChannels()));
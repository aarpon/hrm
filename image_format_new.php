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

$message = "";

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit();
}

if (isset($_GET['home'])) {
    header("Location: " . "home.php");
    exit();
}

if (!isset($_SESSION['setting'])) {
    $_SESSION['setting'] = new ParameterSetting();
}

/* *****************************************************************************
 *
 * SET THE CONFIDENCES FOR THE RELEVANT PARAMETERS
 *
 **************************************************************************** */

/*
   In this page, all parameters are required and independent of the
   file format chosen!
*/
$parameterNames = $_SESSION['setting']->imageParameterNames();
$db = new DatabaseConnection();
foreach ($parameterNames as $name) {
    $parameter = $_SESSION['setting']->parameter($name);
    $confidenceLevel = $db->getParameterConfidenceLevel('', $name);
    $parameter->setConfidenceLevel($confidenceLevel);
    $_SESSION['setting']->set($parameter);
}

/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */

if ($_SESSION['setting']->checkPostedImageParameters($_POST)) {

    // Now we force all variable channel parameters to have the correct number
    // of channels
    $_SESSION['setting']->setNumberOfChannels(
        $_SESSION['setting']->numberOfChannels());

    // Continue to the next page
    header("Location: " . "microscope_parameter.php");
    exit();
} else {
    $message = $_SESSION['setting']->message();
}

/* *****************************************************************************
 *
 * THE HTML PAGE VIA TWIG
 *
 **************************************************************************** */

$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader);
echo $twig->render('image_parameters.twig',
                   array('NumberOfChannels' => $_SESSION['setting']->numberOfChannels()));

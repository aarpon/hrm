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

/* *****************************************************************************
 *
 * SET THE CONFIDENCES FOR THE RELEVANT PARAMETERS
 *
 **************************************************************************** */

/** @var ImageFileFormat $fileFormat */
$fileFormat = $_SESSION['setting']->parameter("ImageFileFormat");
$parameterNames = $_SESSION['setting']->microscopeParameterNames();
$db = new DatabaseConnection();
foreach ($parameterNames as $name) {
    $parameter = $_SESSION['setting']->parameter($name);
    $confidenceLevel = $db->getParameterConfidenceLevel(
        $fileFormat->value(), $name);
    /** @var Parameter $parameter */
    $parameter->setConfidenceLevel($confidenceLevel);
    $_SESSION['setting']->set($parameter);
}

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
$twig   = new Twig_Environment($loader);

$microscopeType = array(
    'title'      => 'Microscope Type',
    'varName'    => 'MicroscopeType',
    'label'      => 'Microscope Type: ',
    'confidence' => $_SESSION['setting']->parameter("MicroscopeType")->confidenceLevel(),
    'options'    => $_SESSION['setting']->parameter("MicroscopeType")->possibleValues());

$numericalAperture = array(
    'title'      => 'Numerical Aperture',
    'varName'    => 'NumericalAperture',
    'label'      => 'NA: ',
    'min'        => 0.2, /* TODO: add NA boundary values to the DB. */
    'max'        => 1.5,
    'step'       => 0.1,
    'confidence' => $_SESSION['setting']->parameter("NumericalAperture")->confidenceLevel());

$objectiveType = array(
    'title'      => 'Objective Type',
    'varName'    => 'ObjectiveType',
    'confidence' => $_SESSION['setting']->parameter("ObjectiveType")->confidenceLevel(),
    'options'    => $_SESSION['setting']->parameter("ObjectiveType")->possibleValues(),
    'values'     => $_SESSION['setting']->parameter("ObjectiveType")->translatedValues());

$sampleMedium = array(
    'title'      => 'Sample Medium',
    'varName'    => 'SampleMedium',
    'confidence' => $_SESSION['setting']->parameter("SampleMedium")->confidenceLevel(),
    'options'    => $_SESSION['setting']->parameter("SampleMedium")->possibleValues(),
    'values'     => $_SESSION['setting']->parameter("SampleMedium")->translatedValues());

echo $twig->render('microscope_parameter.twig',
                   array('chanCnt'           => $_SESSION['setting']->numberOfChannels(),
                         'MicroscopeType'    => $microscopeType,
                         'NumericalAperture' => $numericalAperture,
                         'ObjectiveType'     => $objectiveType,
                         'SampleMedium'      => $sampleMedium,
                         'message'           => $message));
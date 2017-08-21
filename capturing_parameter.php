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

$voxelSizeXY = array(
    'title'      => 'Voxel Size',
    'varName'    => 'CCDCaptorSizeX',
    'label'      => 'Pixel size (nm): ',
    'value'      => $_SESSION['setting']->parameter("CCDCaptorSizeX")->value(),
    'confidence' => $_SESSION['setting']->parameter("CCDCaptorSizeX")->confidenceLevel(),
    'min'        => 0.2, /* TODO: add CCDCaptorSizeX boundary values to the DB. */
    'max'        => 1.5,
    'step'       => 0.1);

$voxelSizeZ = array(
    'title'      => 'Voxel Size',
    'varName'    => 'ZStepSize',
    'label'      => 'Z step (nm): ',
    'value'      => $_SESSION['setting']->parameter("ZStepSize")->value(),
    'confidence' => $_SESSION['setting']->parameter("ZStepSize")->confidenceLevel(),
    'min'        => 0.2, /* TODO: add ZStepSize boundary values to the DB. */
    'max'        => 1.5,
    'step'       => 0.1);

$timeInterval = array(
    'title'      => 'Time Interval',
    'varName'    => 'TimeInterval',
    'label'      => 'Time Interval (s): ',
    'value'      => $_SESSION['setting']->parameter("TimeInterval")->value(),
    'confidence' => $_SESSION['setting']->parameter("TimeInterval")->confidenceLevel(),
    'min'        => 0., /* TODO: add TimeInterval boundary values to the DB. */
    'max'        => 10000,
    'step'       => 0.01);

$pinholeSize = array(
    'title'      => 'Pinhole Radius',
    'varName'    => 'PinholeSize',
    'chanCnt'    => $_SESSION['setting']->numberOfChannels(),
    'values'     => $_SESSION['setting']->parameter("PinholeSize")->value(),
    'confidence' => $_SESSION['setting']->parameter("PinholeSize")->confidenceLevel(),
    'min'        => 25, /* TODO: add pinholeSize boundary values to the DB. */
    'max'        => 5000,
    'step'       => 1);

$pinholeSpacing = array(
    'title'      => 'Pinhole Spacing',
    'varName'    => 'PinholeSpacing',
    'label'      => 'Backprojected Pinhole Spacing (nm): ',
    'value'      => $_SESSION['setting']->parameter("PinholeSpacing")->value(),
    'confidence' => $_SESSION['setting']->parameter("PinholeSpacing")->confidenceLevel(),
    'min'        => 250, /* TODO: add PinholeSpacing boundary values to the DB. */
    'max'        => 50000,
    'step'       => 1);


echo $twig->render('capturing_parameter.twig',
    array('chanCnt'         => $_SESSION['setting']->numberOfChannels(),
          'VoxelSizeXY'     => $voxelSizeXY,
          'ZStepSize'       => $voxelSizeZ,
          'TimeInterval'    => $timeInterval,
          'PinholeSize'     => $pinholeSize,
          'PinholeSpacing'  => $pinholeSpacing,
          'message'         => $message));

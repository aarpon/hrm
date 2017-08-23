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
 * WHICH IS THE NEXT PAGE?
 *
 **************************************************************************** */

// Here we try to figure out whether we have to continue after this page or not.
// If the user chose to use a measured PSF or if there is a refractive index
// mismatch, there will be more pages after this. Otherwise, we save the
// settings to the database and go back to select_parameter_settings.php.
// Besides the destination page, also the control buttons will need to be
// adapted.

$saveToDB = false;

$psf  = $_SESSION['setting']->parameter('PointSpreadFunction')->value();
$micr = $_SESSION['setting']->parameter("MicroscopeType")->value();

if ($micr == "STED" || $micr == 'STED 3D') {
    $pageToGo = 'sted_parameters.php';
} elseif ($micr == "SPIM") {
    $pageToGo = 'spim_parameters.php';
} elseif ($psf == 'measured') {
    $pageToGo = 'select_psf.php';
    // Make sure to turn off the correction
    $_SESSION['setting']->parameter('AberrationCorrectionNecessary')->setValue('0');
    $_SESSION['setting']->parameter('PerformAberrationCorrection')->setValue('0');
} else {
    // Get the refractive indices: if they are not set, the floatval conversion
    // will change them into 0s
    $sampleRI = floatval($_SESSION['setting']->parameter(
        'SampleMedium')->translatedValue());
    $objectiveRI = floatval($_SESSION['setting']->parameter(
        'ObjectiveType')->translatedValue());
    // Calculate the deviation
    if (($sampleRI == 0) || ($objectiveRI == 0)) {
        // If at least one of the refractive indices is not known, we cannot
        // calculate whether an aberration correction is necessary and we leave
        // the decision to the user in the aberration_correction.php page.
        $pageToGo = 'aberration_correction.php';
        $_SESSION['setting']->parameter('AberrationCorrectionNecessary')->setValue('1');
    } else {
        // If we know both the refractive indices we can calculate the deviation
        // and skip the aberration correction page in case the deviation is smaller
        // than 1%.
        $deviation = abs($sampleRI - $objectiveRI) / $objectiveRI;

        // Do we need to go to the aberration correction page?
        if ($deviation < 0.01) {
            // We can save the parameters
            $saveToDB = true;
            $pageToGo = 'select_parameter_settings.php';
            // Make sure to turn off the correction
            $_SESSION['setting']->parameter('AberrationCorrectionNecessary')->setValue('0');
            $_SESSION['setting']->parameter('PerformAberrationCorrection')->setValue('0');
        } else {
            $pageToGo = 'aberration_correction.php';
            $_SESSION['setting']->parameter('AberrationCorrectionNecessary')->setValue('1');
        }
    }
}

/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */
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

/* *****************************************************************************
 *
 * CREATE THE PAGE
 *
 **************************************************************************** */

// The Twig handler.
$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader);

$voxelSizeXY = array(
    'title'      => 'Voxel Size',
    'varName'    => 'CCDCaptorSizeX',
    'label'      => 'Pixel size (nm): ',
    'value'      => $_SESSION['setting']->parameter("CCDCaptorSizeX")->value(),
    'confidence' => $_SESSION['setting']->parameter("CCDCaptorSizeX")->confidenceLevel(),
    'min'        => 20, /* TODO: add CCDCaptorSizeX boundary values to the DB. */
    'max'        => 1000,
    'step'       => 1);

$voxelSizeZ = array(
    'title'      => 'Voxel Size',
    'varName'    => 'ZStepSize',
    'label'      => 'Z step (nm): ',
    'value'      => $_SESSION['setting']->parameter("ZStepSize")->value(),
    'confidence' => $_SESSION['setting']->parameter("ZStepSize")->confidenceLevel(),
    'min'        => 20, /* TODO: add ZStepSize boundary values to the DB. */
    'max'        => 1000,
    'step'       => 1);

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

?>
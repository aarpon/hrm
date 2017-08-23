<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\DatabaseConnection;
use hrm\Nav;
use hrm\param\base\Parameter;
use hrm\param\ImageFileFormat;
use hrm\param\SpimCenterOffset;
use hrm\param\SpimDir;
use hrm\param\SpimExcMode;
use hrm\param\SpimFill;
use hrm\param\SpimFocusOffset;
use hrm\param\SpimGaussWidth;
use hrm\param\SpimNA;

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
$message = "";

$chanCnt = $_SESSION['setting']->numberOfChannels();

/* *****************************************************************************
 *
 * SET THE CONFIDENCES FOR THE RELEVANT PARAMETERS
 *
 **************************************************************************** */

/** @var ImageFileFormat $fileFormat */
$fileFormat = $_SESSION['setting']->parameter("ImageFileFormat");
$parameterNames = $_SESSION['setting']->spimParameterNames();
$db = new DatabaseConnection();
foreach ($parameterNames as $name) {
    /** @var Parameter $parameter */
    $parameter = $_SESSION['setting']->parameter($name);
    $confidenceLevel =
        $db->getParameterConfidenceLevel($fileFormat->value(), $name);
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

$psf = $_SESSION['setting']->parameter('PointSpreadFunction')->value();

if ($psf == 'measured') {
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

if ($_SESSION['setting']->checkPostedSpimParameters($_POST)) {
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

$spimExcMode = array(
    'title'      => 'SPIM Excitation Mode',
    'varName'    => 'SpimExcMode',
    'value'      => $_SESSION['setting']->parameter("SpimExcMode")->value(),
    'confidence' => $_SESSION['setting']->parameter("SpimExcMode")->confidenceLevel(),
    'options'    => $_SESSION['setting']->parameter("SpimExcMode")->possibleValues(),
    'chanCnt'    => $_SESSION['setting']->numberOfChannels());


echo $twig->render('spim_parameters.twig',
    array('SPIMExcMode'   => $spimExcMode,
          'message'       => $message));
?>
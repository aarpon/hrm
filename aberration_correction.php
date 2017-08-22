<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\DatabaseConnection;
use hrm\Nav;
use hrm\param\AberrationCorrectionMode;
use hrm\param\AdvancedCorrectionOptions;
use hrm\param\base\Parameter;
use hrm\param\CoverslipRelativePosition;
use hrm\param\ImageFileFormat;
use hrm\param\PerformAberrationCorrection;
use hrm\param\PSFGenerationDepth;

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

/* *****************************************************************************
 *
 * SET THE CONFIDENCES FOR THE RELEVANT PARAMETERS
 *
 **************************************************************************** */

/* In this page, all parameters are required! */
$parameterNames = $_SESSION['setting']->correctionParameterNames();
$db = new DatabaseConnection();
foreach ($parameterNames as $name) {
    /** @var Parameter $parameter */
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

if ($_SESSION['setting']->checkPostedAberrationCorrectionParameters($_POST)) {
    $saved = $_SESSION['setting']->save();
    $message = $_SESSION['setting']->message();
    if ($saved) {
        header("Location: select_parameter_settings.php");
        exit();
    }
} else {
    $message = $_SESSION['setting']->message();
}


/* *****************************************************************************
 *
 * PREVIOUS PAGE
 *
 **************************************************************************** */

if ($_SESSION['setting']->isSted() || $_SESSION['setting']->isSted3D()) {
    $back = "sted_parameters.php";
} else if ($_SESSION['setting']->isSpim()) {
    $back = "spim_parameters.php";
} else {
    $back = "capturing_parameter.php";
}

/* *****************************************************************************
 *
 * CREATE THE PAGE
 *
 **************************************************************************** */

// The Twig handler.
$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader);

$performAberrationCorrection = array(
    'title'      => 'Enable depth-dependent PSF correction? ',
    'varName'    => 'PerformAberrationCorrection',
    'label'      => 'Correct for Spherical Aberration: ',
    'value'      => $_SESSION['setting']->parameter("PerformAberrationCorrection")->value(),
    'confidence' => $_SESSION['setting']->parameter("PerformAberrationCorrection")->confidenceLevel(),
    'options'    => $_SESSION['setting']->parameter("PerformAberrationCorrection")->possibleValues());

$coverslipRelativePosition = array(
    'title'      => 'Specify Sample Orientation ',
    'varName'    => 'CoverslipRelativePosition',
    'label'      => 'Position of the Coverslip w.r.t sample: ',
    'value'      => $_SESSION['setting']->parameter("CoverslipRelativePosition")->value(),
    'confidence' => $_SESSION['setting']->parameter("CoverslipRelativePosition")->confidenceLevel(),
    'options'    => $_SESSION['setting']->parameter("CoverslipRelativePosition")->possibleValues());

$aberrationCorrectionMode = array(
    'title'      => 'Aberration Correction Mode ',
    'varName'    => 'AberrationCorrectionMode',
    'label'      => 'Correction Mode ',
    'value'      => $_SESSION['setting']->parameter("AberrationCorrectionMode")->value(),
    'confidence' => $_SESSION['setting']->parameter("AberrationCorrectionMode")->confidenceLevel(),
    'options'    => $_SESSION['setting']->parameter("AberrationCorrectionMode")->possibleValues());

$advancedCorrectionOptions = array(
    'title'      => 'Advanced Correction Scheme ',
    'varName'    => 'AdvancedCorrectionOptions',
    'label'      => 'How many PSFs per image ',
    'value'      => $_SESSION['setting']->parameter("AdvancedCorrectionOptions")->value(),
    'confidence' => $_SESSION['setting']->parameter("AdvancedCorrectionOptions")->confidenceLevel(),
    'options'    => $_SESSION['setting']->parameter("AdvancedCorrectionOptions")->possibleValues());

$psfGenerationDepth = array(
    'title'      => 'Depth for PSF Generation ',
    'label'      => 'Depth (microns): ',
    'varName'    => 'PSFGenerationDepth',
    'value'      => $_SESSION['setting']->parameter("PSFGenerationDepth")->value(),
    'confidence' => $_SESSION['setting']->parameter("PSFGenerationDepth")->confidenceLevel(),
    'min'        => 0,
    'max'        => 200,
    'step'       => 0.2);


echo $twig->render('aberration_correction.twig',
    array('PerformAberrationCorrection'     => $performAberrationCorrection,
          'CoverslipRelativePosition'       => $coverslipRelativePosition,
          'AberrationCorrectionMode'        => $aberrationCorrectionMode,
          'AdvancedCorrectionOptions'       => $advancedCorrectionOptions,
          'PSFGenerationDepth'              => $psfGenerationDepth,
          'message'                         => $message));


?>

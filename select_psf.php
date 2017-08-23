<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Fileserver;
use hrm\Nav;
use hrm\param\PSF;

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

// fileserver related code
if (!isset($_SESSION['fileserver'])) {
    $name = $_SESSION['user']->name();
    $_SESSION['fileserver'] = new Fileserver($name);
}

$message = "";

/* *****************************************************************************
 *
 * MANAGE THE MULTI-CHANNEL PSF FILE NAMES
 *
 **************************************************************************** */

/** @var PSF $psfParam */
$psfParam = $_SESSION['setting']->parameter("PSF");
$psfParam->setNumberOfChannels($_SESSION['setting']->numberOfChannels());
$psf = $psfParam->value();
for ($i = 0; $i < $_SESSION['setting']->numberOfChannels(); $i++) {
    $psfKey = "psf{$i}";
    if (isset($_POST[$psfKey])) {
        $psf[$i] = $_POST[$psfKey];
    }
}
// get rid of extra values in case the number of channels is changed
$psfParam->setValue($psf);
$_SESSION['setting']->set($psfParam);

/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 * In this case, we do not need to check the confidence level of the PSF
 * Parameter (although it is set to Provide), since there is no other
 * meaningful alternative to having to provide the file names.
 *
 **************************************************************************** */

if (count($_POST) > 0) {
    if ($psfParam->check()) {
        // Make sure to turn off the aberration correction since we use a measured PSF
        $_SESSION['setting']->parameter('AberrationCorrectionNecessary')->setValue('0');
        $_SESSION['setting']->parameter('PerformAberrationCorrection')->setValue('0');
        $saved = $_SESSION['setting']->save();
        $message = $_SESSION['setting']->message();
        if ($saved) {
            header("Location: select_parameter_settings.php");
            exit();
        }
    } else {
        $message = $psfParam->message();
    }
}


/* *****************************************************************************
 *
 * CREATE THE PAGE
 *
 **************************************************************************** */

// The Twig handler.
$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader);

$psfSelect = array(
    'title'      => 'Distilled PSF File Selection',
    'varName'    => 'psf',
    'value'      => $_SESSION['setting']->parameter("PSF")->value(),
    'chanCnt'    => $_SESSION['setting']->numberOfChannels());



echo $twig->render('psf_select.twig',
    array('PSF'      => $psfSelect,
          'message'  => $message));


?>
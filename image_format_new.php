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

if (!isset($_SESSION['setting'])) {
    $_SESSION['setting'] = new ParameterSetting();
}

$message = "";

// The Twig handler.
$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader);

/* Render the HTML code. */
//echo $twig->render('home.twig', array('isAdmin' => $_SESSION['user']->isAdmin()));

echo $twig->render('image_parameters.twig');
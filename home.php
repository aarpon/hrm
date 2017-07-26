<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Log;
use hrm\Nav;
use hrm\user\UserManager;
use hrm\user\proxy\ProxyFactory;
use hrm\Util;

require_once dirname(__FILE__) . '/inc/bootstrap.php';

global $email_admin;
global $authenticateAgainst;

session_start();

if (isset($_GET['exited'])) {
    if (session_id() && isset($_SESSION['user'])) {
        Log::info("User " . $_SESSION['user']->name() . " logged off.");
        $_SESSION['user']->logout();
        $_SESSION = array();
        session_unset();
        session_destroy();
    }
    header("Location: " . "login.php");
    exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit();
}

$message = "";

// The Twig handler.
$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader);

/* Render the HTML code. */
echo $twig->render('home.twig', array('isAdmin' => $_SESSION['user']->isAdmin()));

?>


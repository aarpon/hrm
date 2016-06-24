<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Log;
use hrm\Nav;
use hrm\Util;

require_once dirname(__FILE__) . '/../inc/bootstrap.php';

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

$script = array("ajax_utils.js", "json-rpc-client.js");

$loader = new Twig_Loader_Filesystem('template');
$twig = new Twig_Environment($loader);

echo $twig->render('index.html', array('error_message' => $message, 'username' => $_SESSION['user']));

?>
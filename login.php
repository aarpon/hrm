<?php

use hrm\Fileserver;
use hrm\Log;
use hrm\System;
use hrm\user\UserManager;
use hrm\user\UserV2;
use hrm\Validator;
use hrm\DatabaseConnection;

require_once dirname(__FILE__) . '/./inc/bootstrap.php';

global $email_admin;

// The Twig handler.
$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader);

/*
 *
 * SANITIZE INPUT
 *   We check the relevant contents of $_POST for validity and store them in
 *   a new array $clean that we will use in the rest of the code.
 *
 *   After this step, only the $clean array and no longer the $_POST array
 *   should be used!
 *
 */

// Here we store the cleaned variables
$clean = array(
    "username" => "",
    "password" => "");

// Username
if (isset($_POST["username"])) {
    if (Validator::isUserNameValid($_POST["username"])) {
        $clean["username"] = $_POST["username"];
    }
}

// Password
if (isset($_POST["password"])) {
    if (Validator::isPasswordValid($_POST["password"])) {
        $clean["password"] = $_POST["password"];
    }
}

// TODO Clean $_POST['request']

/*
 *
 * END OF SANITIZE INPUT
 *
 */

$message = "";

session_start();
if (isset($_SESSION['request'])) {
    $req = $_SESSION['request'];
} else {
    $req = "";
}
if (isset($_POST['request'])) {
    $req = $_POST['request'];
}

/* Reset all! */
session_unset();
session_destroy();

session_start();

$db = new DatabaseConnection();
if (!$db->isReachable()) {
    $message = "The database is not reachable! " .
               "Please contact your administrator. " .
               "You will not be allowed to login " .
               "until this issue has been fixed.";
    echo $twig->render('base.twig', array('status'  => 'danger',
                                                'message' => $message));
    return;
}

// Check that the hucore version is known.
if (System::getHuCoreVersionAsInteger() == 0) {
    $message = "Unknown HuCore version! " .
               "Please ask the administrator to start the queue manager. " .
               "You are now allowed to login until this issue has been fixed.";
    echo $twig->render('base.twig', array('status'  => 'danger',
                                                'message' => $message));
    return;
}

// Check that hucore is recent enough to run this version of the HRM.
if (System::isMinHuCoreVersion() == false) {
    $message = "Your HuCore version is " .
               System::getHucoreVersionAsString() .
               ", you need at least HuCore version " .
               System::getMinHuCoreVersionAsString() .
               " for HRM " .
               System::getHRMVersionAsString() .
               "! Please contact the administrator.";
    echo $twig->render('base.twig', array('status'  => 'danger',
                                                'message' => $message));
    return;
}

// Check that the database is up-to-date.
if (System::isDBUpToDate() == false) {
    $message = "The database is not up-to-date! ";
               "This happens if HRM was recently updated but the " .
               "database was not. You are not allowed to login " .
               "until this issue has been fixed. " .
               "Only the administrator can login.";
    echo $twig->render('base.twig', array('status'  => 'danger',
                                                'message' => $message));
    return;
}

// Check that HuCore has a valid license, unless this is a development setup.
if (file_exists('.hrm_devel_version') == false) {
    if (System::hucoreHasValidLicense() == false) {
        $message = "No valid HuCore license found! " .
                   "Please contact the administrator.";
        echo $twig->render('base.twig', array('status'  => 'danger',
                                                    'message' => $message));
        return;
    }
}

if (isset($_POST['password']) && isset($_POST['username'])) {
    if ($clean['password'] != "" && $clean['username'] != "") {

        // Create a user
        $tentativeUser = new UserV2($clean['username']);

        if ($tentativeUser->logIn($clean['password'])) {

            // If the user does not exist yet in the system, we add it
            if (!UserManager::existsUser($tentativeUser)) {
                UserManager::addUser($tentativeUser, $clean['password']);
            }

            // Register the user in the session
            $_SESSION['user'] = $tentativeUser;

            // Make sure that the user source and destination folders exist
            $fileServer = new Fileserver($tentativeUser->name());
            if (!$fileServer->isReachable()) {
                UserManager::createUserFolders($tentativeUser->name());
            }

            // Log successful logon
            Log::info("User " . $_SESSION['user']->name() . " (" .
                $_SESSION['user']->emailAddress() . ") logged on.");

            // If the database is not up-to-date go straight to the
            // database update page
            if (!System::isDBUpToDate()) {
                if ($_SESSION['user']->isAdmin()) {
                    header("Location: update.php");
                    exit();
                } else {
                    $message = "Only the administrator is allowed to login " .
                               "to perform maintenance";
                }
            } else {
                // Is there a requested redirection?
                if ($req != "") {
                    header("Location: " . $req);
                    exit();
                } else {
                    // Proceed to home
                    header("Location: " . "home.php");
                    exit();
                }
            }

        } else if (UserManager::isLoginRestrictedToAdmin()) {
            if ($tentativeUser->isAdmin()) {
                $message = "Wrong password";
            } else {
                $message = "Only the administrator is allowed to login " .
                           "to perform maintenance";
            }
        } else {
            if ($tentativeUser->isDisabled()) {
                $message = "Your account has been suspended, please " .
                           "contact the administrator";
            } else {
                $message = "Sorry, wrong user name or password, or not authorized.";
            }
        }
    } else {
        $message = "Sorry, invalid user name or password";
    }
}

/* Render the HTML code. */
echo $twig->render('login.twig', array('version' => '4.0',
                                             'error' => $message));

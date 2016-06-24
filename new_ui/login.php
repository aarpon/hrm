<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Fileserver;
use hrm\Log;
use hrm\Nav;
use hrm\user\mngm\UserManagerFactory;
use hrm\Validator;
use hrm\DatabaseConnection;
use hrm\user\User;
use hrm\System;

require_once dirname(__FILE__) . '/../inc/bootstrap.php';

global $email_admin;
global $authenticateAgainst;

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

error_log("Start");

// Check that the database is reachable
$db = new DatabaseConnection();
if (!$db->isReachable()) {
    $message = "The database is not reachable! <br/>" .
        "Please contact your administrator." .
        "You will not be allowed to login " .
        "until this issue has been fixed.";
    error_log("DB not reachable");
// Check that the hucore version is known
} elseif (System::getHuCoreVersionAsInteger() == 0) {
    $message = "Unknown HuCore version! <br/>" .
        "Please ask the administrator to start the queue manager." .
        "You are now allowed to login until this issue has been " .
        "fixed.";
    error_log("Unknown HuCore");
// Check that hucore is recent enough to run this version of the HRM
} elseif (System::isMinHuCoreVersion() == false) {
    $message = "Your HuCore version is " .
        System::getHucoreVersionAsString() . ", you need at least HuCore " .
        "version " . System::getMinHuCoreVersionAsString() . " for HRM " .
        System::getHRMVersionAsString() . "!<br/>";
        "Please contact the administrator.";
    error_log("Old HuCore");
// Check that the database is up-to-date
} elseif (System::isDBUpToDate() == false) {
    $message = "<The database is not up-to-date! <br/>" .
        "This happens if HRM was recently updated but the " .
        "database was not. You are not allowed to login " .
        "until this issue has been fixed.<br/>" .
        "Only the administrator can login.</p>";
    error_log("Old DB");
}

// Check that HuCore has a valid license, unless this is a development setup
elseif (file_exists('.hrm_devel_version') == false  && System::hucoreHasValidLicense() == false) {
      $message = "No valid HuCore license found!<br/>" .
      "Please contact the administrator.";
      error_log("HuCore invalid, message is " . $message);
} else {

  error_log("past pre-checks");

  if (isset($_POST['password']) && isset($_POST['username'])) {
      if ($clean['password'] != "" && $clean['username'] != "") {

          error_log("password provided");
          // Get the UserManager
          $userManager = UserManagerFactory::getUserManager(false);

          // Create a user
          $tentativeUser = new User();
          $tentativeUser->setName($clean['username']);
          $tentativeUser->logOut(); // TODO

          error_log("user: " . $clean['username']);
          error_log("password: " . $clean['password']);
          if ($tentativeUser->logIn($clean['username'], $clean['password'])) {
          
              error_log("logIn returned true");

              if ($tentativeUser->isLoggedIn()) {

                  // Register the user in the session
                  $_SESSION['user'] = $tentativeUser;

                  // Make sure that the user source and destination folders exist
                  $fileServer = new Fileserver($tentativeUser->name());
                  if (!$fileServer->isReachable()) {
                      $userManager->createUserFolders($tentativeUser->name());
                  }

                  // Update the user data and the access date in the database
                  $userManager->storeUser($_SESSION['user']);

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
                          header("Location: " . "index.php");
                          exit();
                      }
                  }
              }
          } else if ($userManager->isLoginRestrictedToAdmin()) {
              if ($tentativeUser->isAdmin()) {
                  $message = "Wrong password";
              } else {
                  $message = "Only the administrator is allowed to login " .
                      "to perform maintenance";
              }
          } else {
              if ($userManager->isSuspended($tentativeUser)) {
                  $message = "Your account has been suspended, please " .
                      "contact the administrator";
              } else {
                  $message = "Sorry, wrong username or password, or not authorized.";
              }
          }
      } else {
          $message = "Sorry, invalid user name or password";
      }
  }

}


error_log('Test log');
error_log('Message is ' . $message);
    
$loader = new Twig_Loader_Filesystem('template');
$twig = new Twig_Environment($loader);

echo $twig->render('login.html', array('error_message' => $message));

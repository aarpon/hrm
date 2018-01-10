<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Mail;
use hrm\user\proxy\ProxyFactory;
use hrm\user\UserConstants;
use hrm\user\UserManager;
use hrm\Validator;

// Settings
global $hrm_url, $image_folder, $image_host, $email_sender, $userManagerScript;

require_once dirname(__FILE__) . '/inc/bootstrap.php';

session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit();
}

$message = "";

$added = False;

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
    "email" => '',
    "group" => "",
    "authMode" => "",
    "informUserOnCreation" => false);

// Username
if (isset($_POST["username"])) {
    if (Validator::isUserNameValid($_POST["username"])) {
        $clean["username"] = $_POST["username"];
    }
}

// Email
if (isset($_POST["email"])) {
    if (Validator::isEmailValid($_POST["email"])) {
        $clean["email"] = $_POST["email"];
    }
}

// Group name
if (isset($_POST["group"])) {
    if (Validator::isGroupNameValid($_POST["group"])) {
        $clean["group"] = $_POST["group"];
    }
}

// Authentication mode
$clean["authMode"] = ProxyFactory::getDefaultAuthenticationMode();
if (isset($_POST["authMode"])) {
    $clean["authMode"] = $_POST["authMode"];
}

// Inform the user on creation?
$clean["informUserOnCreation"] = false;
if (isset($_POST["inform"]) && $_POST["inform"] == "Yes") {
    $clean["informUserOnCreation"] = true;
}

/*
 *
 * END OF SANITIZE INPUT
 *
 */

// Add the user
if (isset($_POST['add'])) {

    if ($clean["username"] == "") {
        $message = "Please provide a user name!";
    } else if ($clean["email"] == "") {
        $message = "Please provide a valid email address!";
    } else if ($clean['group'] == "") {
        $message = "Please a group!";
    } else {

        // Make sure that there is no user with same name
        if (UserManager::existsUserWithName($clean['username'])) {
            $name = $clean['username'];
            $message = "Sorry, a user with name $name exists already!";
        } else {

            // Add the user
            // TODO: add institution and authentication mode!
            $institution_id = 1;
            $password = UserManager::generateRandomPlainPassword();
            $result = UserManager::createUser($clean["username"],
                $password, $clean["email"], $clean["group"], $institution_id,
                ProxyFactory::getDefaultAuthenticationMode(),
                UserConstants::ROLE_USER, UserConstants::STATUS_ACTIVE);

            // TODO refactor
            if ($result) {
                if ($clean["informUserOnCreation"]) {
                    $text = "Your account has been activated:\n\n";
                    $text .= "\t      Username: " . $clean["username"] . "\n";
                    if ($clean["authMode"] == "integrated") {
                        $text .= "\t      Password: " . $password . "\n\n";
                    } else {
                        $text .= "\t      Password: Use your " . ProxyFactory::getDefaultProxy()->friendlyName() .
                            " password to login.\n\n";
                    }
                    $text .= "Login here\n";
                    $text .= $hrm_url . "\n\n";
                    $folder = $image_folder . "/" . $clean["username"];
                    $text .= "Source and destination folders for your images are " .
                        "located on server " . $image_host . " under " . $folder . ".";
                    $mail = new Mail($email_sender);
                    $mail->setReceiver($clean['email']);
                    $mail->setSubject('Account activated');
                    $mail->setMessage($text);
                    $mail->send();
                }
                $message = "New user successfully added to the system.";
                shell_exec("$userManagerScript create \"" . $clean["username"] . "\"");
                $added = True;
            } else {
                $message = "Sorry, the user could not be registered. Please contact your administrator.";
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Huygens Remote Manager</title>
    <script type="text/javascript">
        <!--
        <?php

        if ($added) echo "        var added = true;\n";
        else echo "        var added = false;\n";

        ?>
        -->
    </script>
    <style type="text/css">
        @import "css/default.css";
    </style>
</head>

<body<?php if ($added) echo " onload=\"parent.report()\"" ?>>

<div>

    <form method="post" action="">

            <fieldset>

                <legend>New account details</legend>

                <div id="adduser">

                    <div id="notice">
                        <?php echo "$message"; ?>
                    </div>

                    <p>Default authentication mechanism
                        is <?php echo(ProxyFactory::getDefaultProxy()->friendlyName()); ?>.</p>

                    <label for="username">Username: </label>
                    <input type="text"
                           name="username"
                           id="username"
                           value=""
                           class=""/>

                    <br/>

                    <label for="email">E-mail address: </label>
                    <input type="text"
                           name="email"
                           id="email"
                           value=""
                           class=""/>

                    <br/>

                    <label for="group">Research group: </label>
                    <input type="text"
                           name="group"
                           id="group"
                           value=""
                           class=""/>

                    <br/>


                    <br/>

                    <select name="authMode"
                            id="authMode"
                            title="Authentication mode"
                            class="selection">

                        <?php

                        // Retrieve all configured authentication modes
                        $allAuthMap = ProxyFactory::getAllConfiguredAuthenticationModes();

                        // Get default authentication mode
                        $defaultAuthMode = ProxyFactory::getDefaultAuthenticationMode();

                        $auth_keys = array_keys($allAuthMap);
                        for ($i = 0; $i < count($allAuthMap); $i++) {
                            $value = $auth_keys[$i];
                            $text = $allAuthMap[$value];
                            if ($defaultAuthMode == $value) {
                                $selected = "selected";
                            } else {
                                $selected = "";
                            }
                            echo("<option value='$value' $selected>$text</option>");
                        }

                        ?>
                    </select>

                </div>

                <p>

                    <input title="Send an e-mail to the user"
                           type="checkbox"
                           name="inform"
                           id="inform"
                           value='Yes'/>Send an e-mail to the user on creation?
                </p>

                <input name="add"
                       type="submit"
                       value="add"
                       class="button"/>

            </fieldset>

            <br/>

            <div>
                <input type="button"
                       value="close"
                       onclick="window.close();"/>
            </div>

    </form>

</div>

</body>

</html>

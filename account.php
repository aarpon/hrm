<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Nav;
use hrm\Util;
use hrm\Validator;
use hrm\user\UserManager;

require_once dirname(__FILE__) . '/inc/bootstrap.php';


global $email_sender;

/* *****************************************************************************
 *
 * SANITIZE INPUT
 *   We check the relevant contents of $_POST for validity and store them in
 *   a new array $clean that we will use in the rest of the code.
 *
 *   After this step, only the $clean array and no longer the $_POST array
 *   should be used!
 *
 **************************************************************************** */

// Here we store the cleaned variables
$clean = array(
    "email" => "",
    "group" => "",
    "pass1" => "",
    "pass2" => "");

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

// Passwords
if (isset($_POST["pass1"])) {
    if (Validator::isPasswordValid($_POST["pass1"])) {
        $clean["pass1"] = $_POST["pass1"];
    }
}
if (isset($_POST["pass2"])) {
    if (Validator::isPasswordValid($_POST["pass2"])) {
        $clean["pass2"] = $_POST["pass2"];
    }
}
if (isset($_POST["authMode"])) {
    $clean["authMode"] = $_POST["authMode"];
}

/* *****************************************************************************
 *
 * START SESSION, CHECK LOGIN STATE, INITIALIZE WHAT NEEDED
 *
 **************************************************************************** */

session_start();

if (isset($_GET['home'])) {
    header("Location: " . "home.php");
    exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit();
}

if (isset($_SERVER['HTTP_REFERER']) &&
    !strstr($_SERVER['HTTP_REFERER'], 'account')
) {
    $_SESSION['referer'] = $_SERVER['HTTP_REFERER'];
}

if (isset($_SESSION['account_user'])) {

    // Make sure the User is properly loaded
    $edit_user = $_SESSION['account_user'];

} else {
    $edit_user = $_SESSION['user'];
}

$message = "";

/* *****************************************************************************
 *
 * UPDATE USER SETTINGS
 *
 **************************************************************************** */

if (isset($_POST['modify'])) {

    // Initialize the result to True
    $result = True;

    // E-mail address
    if (UserManager::canModifyEmailAddress($edit_user)) {

        // Check that a valid e-mail address was provided
        if ($clean['email'] == "") {
            $result = False;
            $message = "Please fill in the email field with a valid address";
        } else {
            $emailToUse = $clean['email'];
        }

    } else {

        // Use current e-mail address
        $emailToUse = $edit_user->emailAddress();

    }

    // User group
    if (UserManager::canModifyGroup($edit_user)) {

        // Check that a valid group was provided
        if ($clean['group'] == "") {
            $result = False;
            $message = "Please fill in the group field";
        } else {
            $groupToUse = $clean['group'];
        }

    } else {

        // Use current group
        $groupToUse = $edit_user->userGroup();

    }

    // Passwords
    if ($clean['pass1'] == "" || $clean['pass2'] == "") {
        $result = False;
        $message = "Please fill in both password fields";
    } else {
        if ($clean['pass1'] != $clean['pass2']) {
            $result = False;
            $message = "Passwords do not match";
        } else {
            $passToUse = $clean['pass1'];
        }
    }

    // Update the information in the database
    if ($result == true) {

        // Update the User information
        if (UserManager::canModifyEmailAddress($edit_user)) {
            $edit_user->SetEmailAddress($emailToUse);
        }
        if (UserManager::canModifyGroup($edit_user)) {
            $edit_user->SetGroup($groupToUse);
        }
        $success = UserManager::storeUser($edit_user, true);

        if ($success == true) {

            // Now we need to update the password (and update the success
            // status).
            $success &= UserManager::changeUserPassword($edit_user->name(),
                $passToUse);

        }

        if (!$success) {

            $message = "Sorry, an error occurred and the user data could " .
                "not be updated!";

        } else {

            // If updating some other User setting, remove the modified
            // User from the session and return to the user management page.
            if (isset($_SESSION['account_user'])) {
                unset($_SESSION['account_user']);
                $_SESSION['account_update_message'] =
                    "Account details successfully modified";
                header("Location: " . "user_management.php");
                exit();
            } else {
                $message = "Account details successfully modified";
                $_SESSION['user'] = $edit_user;
                header("Location: " . $_SESSION['referer']);
                exit();
            }
        }
    }
}

/* *****************************************************************************
 *
 * DISPLAY PAGE
 *
 **************************************************************************** */

include("header.inc.php");
?>

<!--
  Tooltips
-->
<span class="toolTip" id="ttSpanCancel">Discard changes and go back to your home page.</span>
<span class="toolTip" id="ttSpanSave">Save the changes.</span>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
            echo(Nav::linkWikiPage('HuygensRemoteManagerHelpAccount'));
            ?>
        </ul>
    </div>
    <div id="navright">
        <ul>
            <?php
            echo(Nav::textUser($_SESSION['user']->name()));
            echo(Nav::linkHome(Util::getThisPageName()));
            ?>
        </ul>
    </div>
    <div class="clear"></div>
</div>

<div id="content">

    <h3><img alt="Account" src="./images/account_title.png"
             width="40"/>&nbsp;Your account</h3>

    <form method="post" action="" id="useraccount">

        <div id="account">
            <?php

            $somethingToChange = false;

            if (UserManager::canModifyEmailAddress($edit_user)) {

                $emailForForm = "";
                if ($clean['email'] != "") {
                    $emailForForm = $clean['email'];
                } else {
                    $emailForForm = $edit_user->emailAddress();
                }

                $somethingToChange = true;
                ?>

                <label for="email">E-mail address: </label>
                <input name="email"
                       id="email"
                       type="text"
                       value="<?php echo $emailForForm; ?>"/>

                <?php
            }
            ?>
            <br/>
            <?php

            if (UserManager::canModifyGroup($edit_user)) {

                $emailForForm = "";
                if ($clean['group'] != "") {
                    $groupForForm = $clean['group'];
                } else {
                    $groupForForm = $edit_user->group();
                }

                $somethingToChange = true;

                ?>

                <label for="group">Research group: </label>
                <input name="group"
                       id="group"
                       type="text"
                       value="<?php echo $groupForForm; ?>"/>

                <?php
            }

            ?>

            <br/>
            <?php
            if (UserManager::canModifyPassword($edit_user)) {
                ?>
                <label for="pass1">New password: </label>
                <input name="pass1" id="pass1" type="password"/>
                <br/>
                <label for="pass2">Verify password: </label>
                <input name="pass2" id="pass2" type="password"/>
                <input name="modify" type="hidden" value="modify"/>

                <?php

                $somethingToChange = true;
            }
            ?>
            <p/>

            <?php
            $referer = $_SESSION['referer'];
            ?>
        </div>

        <?php
        if ($somethingToChange == true) {
            ?>
            <div id="controls">
                <input type="button" name="cancel" value=""
                       class="icon cancel"
                       onmouseover="TagToTip('ttSpanCancel' )"
                       onmouseout="UnTip()"
                       onclick="document.location.href='<?php echo $referer ?>'"/>
                <input type="button" name="save" value=""
                       class="icon save"
                       onmouseover="TagToTip('ttSpanSave' )"
                       onmouseout="UnTip()"
                       onclick="document.forms['useraccount'].submit()"/>
            </div>
            <?php
        } else {
            ?>
            <p>The authentication backend does not allow HRM to make any change!</p>
            <div id="controls">
                <input type="button" name="cancel" value=""
                       class="icon cancel"
                       onmouseover="TagToTip('ttSpanCancel' )"
                       onmouseout="UnTip()"
                       onclick="document.location.href='<?php echo $referer ?>'"/>
            </div>

        <?php
        }
        ?>
    </form>

</div> <!-- content -->

<div id="rightpanel">

    <div id="info">

        <h3>Quick help</h3>

        <p>Please update the account information.</p>

    </div>

    <div id="message">
        <?php
        echo "<p>$message</p>";
        ?>
    </div>

</div> <!-- rightpanel -->

<?php

include("footer.inc.php");

?>

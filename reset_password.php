<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Mail;
use hrm\Nav;
use hrm\user\proxy\ProxyFactory;
use hrm\user\UserConstants;
use hrm\Util;
use hrm\Validator;
use hrm\user\UserManager;

require_once dirname(__FILE__) . '/inc/bootstrap.php';

global $email_sender;
global $hrm_url;

/* *****************************************************************************
 *
 * START SESSION, CHECK LOGIN STATE, INITIALIZE WHAT NEEDED
 *
 **************************************************************************** */

session_start();

$message = "";

// Exit?
if (isset($_GET["exited"])) {
    header("Location: " . "login.php");
    exit();
}

// Clean array where to store sanitized input
if (isset($_SESSION['sanizited_input'])) {
    $clean = $_SESSION['sanizited_input'];
} else {
    $clean = array();
}

/* *****************************************************************************
 *
 * Is there some request in $_GET?
 *
 **************************************************************************** */

// Default action
$actionMode = "askForUserName";

if (!isset($_POST['modifypassword']) && isset($_GET['user']) && isset($_GET['seed'])) {

    // Username
    if (isset($_GET["user"])) {
        if (Validator::isUserNameValid($_GET['user'])) {
            $clean['username'] = $_GET['user'];
        }
    }

    // Seed
    if (isset($_GET["seed"])) {
        if (Validator::isStringSanitized($_GET['seed'])) {
            $clean['seed'] = $_GET['seed'];
        }
    }

    // Is there a matching user with a password request?
    if (UserManager::existsUserPasswordResetRequestWithSeed($clean['username'], $clean['seed'])) {

        // Store the parameters
        $_SESSION['sanizited_input'] = $clean;

        // Action
        $actionMode = "askForNewPassword";
    } else {

        $actionMode = "requestFromEmailFailed";
    }
}

// Check that the user exists and can change his password, and e-mail the link
if (isset($_POST['requestusername']) && isset($_POST['username'])) {

    // Username
    if (Validator::isUserNameValid($_POST['username'])) {
        $clean['username'] = $_POST['username'];
    }

    // Is there such a user?
    if (UserManager::existsUserWithName($clean['username'])) {

        // Retrieve the authentication mechanism for the user
        $authProxy = ProxyFactory::getProxy($clean['username']);

        if (!$authProxy->canModifyPassword()) {
            $message = "The " . $authProxy->friendlyName() . " authentication does not " .
            "allow changing the password for user " . $clean['username'] . "!";
        } else {

            // Retrieve the user e-mail
            $email = $authProxy->getEmailAddress($clean['username']);

            if ($email == "") {
                $message = "Cannot retrieve e-mail address for user " . $clean['username'] . "!";
            } else {

                # Mark the
                $seed = UserManager::generateAndSetSeed($clean['username']);
                if ($seed == "") {
                    $message = "Could not mark the user to password reset!";
                } else {

                    # Email the user with instructions
                    $mail = new Mail($email_sender);
                    $tmp_username = $clean['username'];
                    $text = "Please reset your password here: $hrm_url/reset_password.php?user=$tmp_username&seed=$seed";
                    $mail->setReceiver($email);
                    $mail->setSubject("HRM's password reset");
                    $mail->setMessage($text);
                    if (!$mail->send()) {
                        $notice = "Could not send an email to user $tmp_username! " .
                            "Please contact your administrator.";
                        $actionMode = "genericErrorWithMessage";
                    } else {
                        $actionMode = "emailSentSuccessfully";
                    }
                }
            }
        }
    } else {

        $message = "Sorry, could not find user " . $clean['username'] . ".";
    }
}

// If the new password has been submitted already, check it and set it
if (isset($_POST['modifypassword']) && isset($_POST['pass1']) &&
    isset($_POST['pass2']) && isset($_SESSION['sanizited_input'])) {

    // Retrieve the sanitized input
    $clean = $_SESSION['sanizited_input'];

    // Passwords
    if (Validator::isPasswordValid($_POST["pass1"])) {
        $clean["pass1"] = $_POST["pass1"];
    }

    if (Validator::isPasswordValid($_POST["pass2"])) {
        $clean["pass2"] = $_POST["pass2"];
    }

    if ($clean["pass1"] != $clean["pass2"]) {
        $message = "Passwords do not match!";

        # Ask again
        $actionMode = "askForNewPassword";

    }  else {

        // Update the user
        if (UserManager::changeUserPassword($clean['username'], $clean['pass1'])) {

            // Make the user active
            UserManager::setUserStatus($clean['username'], UserConstants::STATUS_ACTIVE);

            $actionMode = "passwordUpdateSuccessful";

        } else {

            $actionMode = "passwordUpdateFailed";
        }

        // In any case, we reset the seed
        UserManager::resetSeed($clean['username']);

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
<span class="toolTip" id="ttSpanContinue">Query the user.</span>
<span class="toolTip" id="ttSpanSave">Update the password.</span>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
            echo(Nav::linkWikiPage('HuygensRemoteManagerHelpLogin'));
            ?>
        </ul>
    </div>
    <div id="navright">
        <ul>
            <li>
                <?php
                echo(Nav::exitToLogin());
                ?>
            </li>
        </ul>
    </div>
    <div class="clear"></div>
</div>

<div id="content">

    <h3><img alt="Reset your password" src="./images/forgot_pwd.png"
             width="40"/>&nbspReset your password</h3>

    <?php

    /* ======================================================================
     *
     * DISPLAY FORM TO ASK FOR USERNAME
     *
     * ====================================================================== */

    if ($actionMode == "askForUserName") {

        ?>

        <form method="post" action="" id="askforusername">

            <div id="password_reset">
                <label for="username">Enter your user name:</label>
                <input name="username" id="username" type="text" value=""/>
                <input name="requestusername" type="hidden" value="requestusername"/>
            </div>

            <div id="controls">
                <input type="button" name="cancel" value=""
                       class="icon cancel"
                       onmouseover="TagToTip('ttSpanCancel')"
                       onmouseout="UnTip()"
                       onclick="document.location.href='login.php'"/>
                <input type="button" name="search" value=""
                       class="icon next"
                       onmouseover="TagToTip('ttSpanContinue')"
                       onmouseout="UnTip()"
                       onclick="document.forms['askforusername'].submit()"/>
            </div>

        </form>

        <?php

    } else if ($actionMode == "askForNewPassword") {
        ?>

        <form method="post" action="" id="resetpassword">

            <div id="adduser">
                <p>You can change the password for user '<?php echo($clean['username']); ?>'.</p>
                <label for="pass1">New password:</label>
                <input name="pass1" id="pass1" type="password"/>
                <br/>
                <label for="pass2">(verify) New password: </label>
                <input name="pass2" id="pass2" type="password"/>
                <input name="modifypassword" type="hidden" value="modifypassword"/>
            </div>

            <div id="controls">
                <input type="button" name="cancel" value=""
                       class="icon cancel"
                       onmouseover="TagToTip('ttSpanCancel')"
                       onmouseout="UnTip()"
                       onclick="document.location.href='login.php'"/>
                <input type="button" name="save" value=""
                       class="icon save"
                       onmouseover="TagToTip('ttSpanSave')"
                       onmouseout="UnTip()"
                       onclick="document.forms['resetpassword'].submit()"/>
            </div>

        </form>

        <?php
    } else if ($actionMode == "emailSentSuccessfully") {
        ?>
        <p><b>Congratulations!</b></p>
        <p>Instructions have been sent to the e-mail associated to the user!</p>
        <?php
        } else if ($actionMode == "requestFromEmailFailed") {
        ?>
        <p><b>Sorry!</b></p>
        <p>There is no matching password reset request!<br />
        Have you already changed your password?</p>
        <?php
    } else if ($actionMode == "genericErrorWithMessage") {
        ?>
        <p><b>Sorry!</b></p>
        <p>Could not complete the request!<br />
        Please contact your administrator.</p>
        <?php
    } else if ($actionMode == "passwordUpdateSuccessful") {
        ?>
        <p><b>Congratulations!</b></p>
        <p>The password was updated successfully!</p>
        <?php
    } else if ($actionMode == "passwordUpdateFailed") {
        ?>
        <p><b>Sorry!</b></p>
        <p>Could not update the password!<br />
        Please contact your administrator.</p>
        <?php
    } else {
    ?>
        <p><b>Oooops!</b></p>
        <p>Something went wrong. Please submit a bug report!</p>
    <?php
    }
    ?>

</div> <!-- content -->

<div id="rightpanel">

    <div id="info">

        <h3>Quick help</h3>

        <p>Here you can change your password.</p>
        <p>Please notice that this is possible only if the
            authentication method assigned to you allows the
            HRM to change the password.</p>
        <p>If it is not possible, you will be informed.
            In that case, please contact your institution's
            IT or Human Resources department.</p>

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

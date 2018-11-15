<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Mail;
use hrm\Nav;
use hrm\Settings;
use hrm\user\UserConstants;
use hrm\user\UserManager;
use hrm\Validator;

require_once dirname(__FILE__) . '/inc/bootstrap.php';

$instanceSettings = Settings::getInstance();
$hrm_url = $instanceSettings->get('hrm_url');
$email_sender = $instanceSettings->get('email_sender');
$email_admin = $instanceSettings->get('email_admin');

$processed = False;

$message = "";

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
    "pass1" => "",
    "pass2" => "",
    "note" => "");

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

// Note
if (isset($_POST["note"])) {
    if (Validator::isNoteValid($_POST["note"])) {
        $clean["note"] = $_POST["note"];
    }
}

/*
 *
 * END OF SANITIZE INPUT
 *
 */

if (isset($_POST["OK"])) {

    // Check whether all fields have been correctly filled and whether the user
    // already exists
    if ($clean["username"] == "") {
        $message = "Please provide a valid user name.";
    } else if ($clean["email"] == "") {
        $message = "Please provide a valid e-mail address.";
    } else if ($clean["group"] == "") {
        $message = "Please provide a group.";
    } else if ($clean["pass1"] == "" || $clean["pass2"] == "") {
        $message = "Please provide your password (twice).";
    } else if ($clean["pass1"] == $clean["pass2"]) {

        // Make sure that there is no user with same name
        if (UserManager::existsUserWithName($clean['username'])) {

            $name = $clean['username'];
            $message = "Sorry, a user with name $name exists already!";

        } else {

            // Create the new user
            $institution_id = 1;
            $uniqueId = UserManager::generateUniqueId();
            $result = UserManager::createUser($clean["username"],
                $clean["pass1"], $clean["email"], $clean["group"],
                $institution_id, "integrated",
                UserConstants::ROLE_USER,
                UserConstants::STATUS_ACTIVE,
                $uniqueId);

            // TODO refactor
            if ($result) {
                $text = "New user registration:\n\n";
                $text .= "\t       Username: " . $clean["username"] . "\n";
                $text .= "\t E-mail address: " . $clean["email"] . "\n";
                $text .= "\t          Group: " . $clean["group"] . "\n";
                $text .= "\tRequest message: " . $clean["note"] . "\n\n";
                $text .= "Accept or reject this user here (login required)\n";
                $text .= $hrm_url . "/user_management.php?seed=" . $uniqueId;
                $mail = new Mail($email_sender);
                $mail->setReceiver($email_admin);
                $mail->setSubject("New HRM user registration");
                $mail->setMessage($text);
                if ($mail->send()) {
                    $notice = "Application successfully sent!\n" .
                        "Your application will be processed by the " .
                        "administrator and you will receive a confirmation " .
                        "by e-mail.";
                } else {
                    $notice = "Your application was successfully stored, " .
                        "but there was an error e-mailing the administrator! " .
                        "Please contact him directly.";
                }
                $processed = True;
            } else {
                $message = "Could not create user! Please contact your administrator.";
            }
        }

    } else {

        $message = "Passwords do not match!";

    }

}

include("header.inc.php");

?>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
            echo(Nav::linkWikiPage('HuygensRemoteManagerHelpRegistrationPage'));
            ?>
        </ul>
    </div>
    <div id="navright">
        <ul>
            <?php
            echo(Nav::exitToLogin());
            ?>
        </ul>
    </div>
    <div class="clear"></div>
</div>


<div id="content">

    <h3>Registration</h3>

    <?php

    if (!$processed) {

        ?>
        <form method="post" action="">

            <div id="adduser">

                <div>
                    <label for="username">* Username: </label>
                    <input type="text"
                           name="username"
                           id="username"
                           maxlength="30"
                           value="<?php echo $clean["username"] ?>"/>

                </div>

                <div>
                    <label for="email">* E-mail address: </label>
                    <input type="text"
                           name="email"
                           id="email"
                           maxlength="80"
                           value="<?php echo $clean["email"] ?>"/>
                </div>

                <div>
                    <label for="group">* Research group: </label>
                    <input type="text"
                           name="group"
                           id="group"
                           maxlength="30"
                           value="<?php echo $clean["group"] ?>"/>

                </div>

                <div>
                    <label for="pass1">* Password: </label>
                    <input type="password"
                           name="pass1"
                           id="pass1"/>

                </div>

                <div>
                    <label for="pass2">* (verify) Password: </label>
                    <input type="password"
                           name="pass2"
                           id="pass2"/>

                </div>

                <div>
                <textarea name="note"
                          placeholder="Request message (optional)..."
                          id="note"
                          rows="3"
                          class="selection"
                          cols="30"><?php if ($clean["note"] != "") { echo($clean["note"]); } ?>
                </textarea>
                </div>

                <div>
                    <input name="OK"
                           type="submit"
                           value="register"/>

                </div>
            </div>
        </form>
        <?php

    } else {

        ?>
        <div id="notice"><?php echo "<p>$notice</p>"; ?></div>
        <?php

    }

    ?>
</div> <!-- content -->

<div id="rightpanel">

    <?php

    if (!$processed) {

        ?>
        <div id="info">

            <h3>Quick help</h3>

            <p>* Required fields.</p>

        </div>
        <?php

    }

    ?>

    <div id="message">
        <?php

        echo "<p>$message</p>";

        ?>
    </div>

</div>  <!-- rightpanel -->

<?php

include("footer.inc.php");

?>

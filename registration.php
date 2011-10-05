<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Database.inc.php");
require_once("./inc/hrm_config.inc.php");
require_once("./inc/Mail.inc.php");
require_once("./inc/Util.inc.php");
require_once("./inc/Validator.inc.php");

global $hrm_url;
global $email_sender;
global $email_admin;

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
    "email"    => '',
    "group"    => "",
    "pass1"    => "",
    "pass2"    => "",
    "note"     => "" );

  // Username
  if ( isset( $_POST["username"] ) ) {
    if ( Validator::isUsernameValid( $_POST["username"] ) ) {
      $clean["username"] = $_POST["username"];
    }
  }

  // Email
  if ( isset( $_POST["email"] ) ) {
    if ( Validator::isEmailValid( $_POST["email"] ) ) {
      $clean["email"] = $_POST["email"];
    }
  }

  // Group name
  if ( isset( $_POST["group"] ) ) {
    if ( Validator::isGroupNameValid( $_POST["group"] ) ) {
      $clean["group"] = $_POST["group"];
    }
  }

  // Passwords
  if ( isset( $_POST["pass1"] ) ) {
    if ( Validator::isPasswordValid( $_POST["pass1"] ) ) {
      $clean["pass1"] = $_POST["pass1"];
    }
  }
  if ( isset( $_POST["pass2"] ) ) {
    if ( Validator::isPasswordValid( $_POST["pass2"] ) ) {
      $clean["pass2"] = $_POST["pass2"];
    }
  }

  // Note
  if ( isset( $_POST["note"] ) ) {
    if ( Validator::isNoteValid( $_POST["note"] ) ) {
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
  if ( $clean["username"] != "" ) {
    if ( $clean["email"] != "") {
      if ( $clean["group"] != "") {
        if ($clean["pass1"] != "" && $clean["pass2"] != "" ) {
          if ( $clean["pass1"] == $clean["pass2"] ) {

            // Store the new user into the database
            $db = new DatabaseConnection();
            if ( $db->emailAddress( $clean["username"] ) == "" ) {
              $id = get_rand_id(10);
              $result = $db->addNewUser( $clean["username"],
                  $clean["pass1"], $clean["email"], $clean["group"], $id );

              // TODO refactor
              if ($result) {
                $text = "New user registration:\n\n";
                $text .= "\t       Username: " . $clean["username"] ."\n";
                $text .= "\t E-mail address: " . $clean["email"] ."\n";
                $text .= "\t          Group: " . $clean["group"] . "\n";
                $text .= "\tRequest message: " . $clean["note"] ."\n\n";
                $text .= "Accept or reject this user here (login required)\n";
                $text .= $hrm_url."/user_management.php?seed=" . $id;
                $mail = new Mail($email_sender);
                $mail->setReceiver($email_admin);
                $mail->setSubject("New HRM user registration");
                $mail->setMessage($text);
                if ( $mail->send() ) {
                  $notice = "Application successfully sent!\n" .
                    "Your application will be processed by the " .
                    "administrator and you will receive a confirmation " .
                    "by e-mail.";
                } else {
                  $notice = "Your application was successfully stored, " .
                    "but there was an error e-mailing the administrator! " .
                    "Please contact the administrator yourself!";
                }
                $processed = True;
              }
                else $message = "Database error, please inform the person " .
                    "in charge";
              }
              else $message = "This user name is already in use. Please " .
                  "enter another one";
            }
            else $message = "Passwords do not match";
          }
          else $message = "Please fill in both password fields";
        }
        else $message = "Please fill in the group field";
      }
      else $message = "Please fill in the email field with a valid address";
    }
    else $message = "Please fill in the name field";
}

include("header.inc.php");

?>

    <div id="nav">
        <ul>
            <li>
                <a href="login.php">
                    <img src="images/exit.png" alt="exit" />&nbsp;Exit
                </a>
            </li>
            <li>
                <a href="javascript:openWindow(
                   'http://www.svi.nl/HuygensRemoteManagerHelpRegistrationPage')">
                    <img src="images/help.png" alt="help" />
                    &nbsp;Help
                </a>
            </li>
        </ul>
    </div>

    <div id="content">

        <h3>Registration</h3>

<?php

if (!$processed) {

?>
        <form method="post" action="">

            <div id="adduser">

                <label for="username">*Username: </label>
                <input type="text" 
                       name="username"
                       id="username"
                       maxlength="30"
                       value="<?php echo $clean["username"] ?>" />

                <br />

                <label for="email">*E-mail address: </label>
                <input type="text" 
                       name="email"
                       id="email"
                       maxlength="80"
                       value="<?php echo $clean["email"] ?>" />

                <br />

                <label for="group">*Research group: </label>
                <input type="text" 
                       name="group"
                       id="group"
                       maxlength="30"
                       value="<?php echo $clean["group"] ?>" />

                <br />

                <label for="pass1">*Password: </label>
                <input type="password" 
                       name="pass1"
                       id="pass1" />

                <br />

                <label for="pass2">*(verify) Password: </label>
                <input type="password" 
                       name="pass2"
                       id="pass2" />

                <br />

                <label for="note">Request message:</label>
                <textarea name="note" 
                          id="note"
                          rows="3"
                          cols="30"><?php echo $clean["note"] ?>
                </textarea>

                <div>
                    <input name="OK"
                           type="submit"
                           value="register"
                           class="button" />
                </div>

            </div>
        </form>
<?php

}
else {

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

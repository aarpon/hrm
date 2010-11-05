<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc");
require_once("./inc/Database.inc");
require_once("./inc/hrm_config.inc");
require_once("./inc/Mail.inc");
require_once("./inc/Util.inc");

global $hrm_url;
global $email_sender;
global $email_admin;

$processed = False;

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

if (isset($_POST['OK'])) {
  
  $user = new User();
  $user->setName(strtolower($_POST['username']));
  $user->setEmail($_POST['email']);
  $user->setGroup($_POST['group']);

  if (strtolower($_POST['username']) != "") {
    if ($_POST['email'] != "" && strstr($_POST['email'], "@") && strstr(strstr($_POST['email'], "@"), ".")) {
      if ($_POST['group'] != "") {
        if ($_POST['pass1'] != "" && $_POST['pass2'] != "") {
          if ($_POST['pass1'] == $_POST['pass2']) {
            $db = new DatabaseConnection();
            if ($db->emailAddress(strtolower($_POST['username'])) == "") {
              $id = get_rand_id(10);
              $query = "INSERT INTO username (name, password, email, research_group, status) ".
                        "VALUES ('".strtolower($_POST['username'])."', ".
                                "'".md5($_POST['pass1'])."', ".
                                "'".$_POST['email']."', ".
                                "'".$_POST['group']."', ".
                                "'".$id."')";
              $result = $db->execute($query);
              // TODO refactor
              if ($result) {
                $text = "New user registration:\n\n";
                $text .= "\t       Username: ".strtolower($_POST['username'])."\n";
                $text .= "\t E-mail address: ".$_POST['email']."\n";
                $text .= "\t          Group: ".stripslashes($_POST['group'])."\n";
                $text .= "\tRequest message: ".stripslashes($_POST['note'])."\n\n";
                $text .= "Accept or reject this user here\n";
                $text .= $hrm_url."/user_management.php?seed=" . $id;
                $mail = new Mail($email_sender);
                $mail->setReceiver($email_admin);
                $mail->setSubject("New user registration");
                $mail->setMessage($text);
                if ( $mail->send() ) {
                  $notice = "            <p class=\"info\">Application successfully sent!</p>\n";
                  $notice .= "            <p>Your application will be processed by the administrator. You will receive a confirmation by e-mail.</p>\n";
                } else {
                  $notice = "            <p class=\"info\">Your application was successfully stored, but there was an error e-mailing the administrator!</p>\n";
                  $notice .= "            <p>Please contact the reponsible person.</p>\n";
                }
                $processed = True;
              }
                else $message = "            <p class=\"warning\">Database error, please inform the person in charge</p>\n";
              }
              else $message = "            <p class=\"warning\">This user name is already in use. Please enter another one.</p>\n";
            }
            else $message = "            <p class=\"warning\">Passwords do not match</p>\n";
          }
          else $message = "            <p class=\"warning\">Please fill in both password fields</p>\n";
        }
        else $message = "            <p class=\"warning\">Please fill in the group field</p>\n";
      }
      else $message = "            <p class=\"warning\">Please fill in the email field with a valid address</p>\n";
    }
    else $message = "            <p class=\"warning\">Please fill in the name field</p>\n";
}

include("header.inc.php");

?>

    <div id="nav">
        <ul>
            <li><a href="login.php"><img src="images/exit.png" alt="exit" />&nbsp;Exit</a></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpRegistrationPage')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
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
                <input type="text" name="username" id="username" maxlength="30" value="<?php if (isset($user)) echo $user->name() ?>" />
                
                <br />
                
                <label for="email">*E-mail address: </label>
                <input type="text" name="email" id="email"  maxlength="80" value="<?php if (isset($user)) echo $user->email() ?>" />
                
                <br />
                
                <label for="group">*Research group: </label>
                <input type="text" name="group" id="group" maxlength="30" value="<?php if (isset($user)) echo stripslashes($user->group()) ?>" />
                
                <br />
                
                <label for="pass1">*Password: </label>
                <input type="password" name="pass1" id="pass1" />
                
                <br />
                
                <label for="pass2">*(verify) Password: </label>
                <input type="password" name="pass2" id="pass2" />
                
                <br />
                
                <label for="note">Request message:</label>
                <textarea name="note" id="note" rows="3" cols="30"><?php if (isset($_POST['note']))  echo stripslashes($_POST['note']) ?></textarea>
                
                <div>
                    <input name="OK" type="submit" value="register" class="button" />
                </div>
                
            </div>
        </form>
<?php

}
else {

?>
        <div id="notice"><?php echo $notice ?></div>
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

  print $message;

?>
        </div>
        
    </div>  <!-- rightpanel -->
    
<?php

include("footer.inc.php");

?>

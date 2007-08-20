<?php

// php page: registration.php 

// This file is part of huygens remote manager.

// Copyright: Montpellier RIO Imaging (CNRS)

// contributors :
// 	     Pierre Travo	(concept)
// 	     Volker Baecker	(concept, implementation)

// email:
// 	pierre.travo@crbm.cnrs.fr
// 	volker.baecker@crbm.cnrs.fr

// Web:     www.mri.cnrs.fr

// huygens remote manager is a software that has been developed at 
// Montpellier Rio Imaging (mri) in 2004 by Pierre Travo and Volker 
// Baecker. It allows running image restoration jobs that are processed 
// by 'Huygens professional' from SVI. Users can create and manage parameter 
// settings, apply them to multiple images and start image processing 
// jobs from a web interface. A queue manager component is responsible for 
// the creation and the distribution of the jobs and for informing the user 
// when jobs finished.

// This software is governed by the CeCILL license under French law and 
// abiding by the rules of distribution of free software. You can use, 
// modify and/ or redistribute the software under the terms of the CeCILL 
// license as circulated by CEA, CNRS and INRIA at the following URL 
// "http://www.cecill.info".

// As a counterpart to the access to the source code and  rights to copy, 
// modify and redistribute granted by the license, users are provided only 
// with a limited warranty and the software's author, the holder of the 
// economic rights, and the successive licensors  have only limited 
// liability.

// In this respect, the user's attention is drawn to the risks associated 
// with loading, using, modifying and/or developing or reproducing the 
// software by the user in light of its specific status of free software, 
// that may mean that it is complicated to manipulate, and that also 
// therefore means that it is reserved for developers and experienced 
// professionals having in-depth IT knowledge. Users are therefore encouraged 
// to load and test the software's suitability as regards their requirements 
// in conditions enabling the security of their systems and/or data to be 
// ensured and, more generally, to use and operate it in the same conditions 
// as regards security.

// The fact that you are presently reading this means that you have had 
// knowledge of the CeCILL license and that you accept its terms.

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
  if (!isset($_SESSION['note'])) {
    session_register("note");
  }
  
  $user = new User();
  $user->setName(strtolower($_POST['username']));
  $user->setEmail($_POST['email']);
  $user->setGroup($_POST['group']);
  
  $_SESSION['note'] = $_POST['note'];
  
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
                $text .= "\t          Group: ".$_POST['group']."\n";
                $text .= "\tRequest message: ".$_POST['note']."\n\n";
                $text .= "Accept or reject this user here\n";
                $text .= $hrm_url."/user_management.php?seed=" . $id;
                $mail = new Mail($email_sender);
                $mail->setReceiver($email_admin);
                $mail->setSubject("New user registration");
                $mail->setMessage($text);
                $mail->send();
                $notice = "            <p class=\"info\">Registration successful!</p>\n";
                $notice .= "            <p>Your registration will be processed and your account activated soon. You will receive a confirmation by e-mail.</p>\n";
                unset($_SESSION['note']);
                $processed = True;
              }
                else $message = "            <p class=\"warning\">Database error, please inform the person in charge</p>\n";
              }
              else $message = "            <p class=\"warning\">This user name is already in use</p>\n";
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
            <li><a href="login.php">back</a></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpRegistrationPage')">help</a></li>
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
                <input type="text" name="username" id="username" value="<?php if (isset($user)) echo $user->name() ?>" />
                
                <br />
                
                <label for="email">*E-mail address: </label>
                <input type="text" name="email" id="email" value="<?php if (isset($user)) echo $user->email() ?>" />
                
                <br />
                
                <label for="group">*Research group: </label>
                <input type="text" name="group" id="group" value="<?php if (isset($user)) echo $user->group() ?>" />
                
                <br />
                
                <label for="pass1">*Password: </label>
                <input type="password" name="pass1" id="pass1" />
                
                <br />
                
                <label for="pass2">*(verify) Password: </label>
                <input type="password" name="pass2" id="pass2" />
                
                <br />
                
                <label for="note">Request message:</label>
                <textarea name="note" id="note" rows="3" cols="30"></textarea>
                
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
    
    <div id="stuff">
    
<?php

if (!$processed) {

?>
        <div id="info">
        
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
        
    </div>  <!-- stuff -->
    
<?php

include("footer.inc.php");

?>

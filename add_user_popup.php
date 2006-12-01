<?php

// php page: add_user_popup.php

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

session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

$added = False;

// TODO refactor from here
if (isset($_POST['add'])) {
  $user = new User();
  $user->setName(strtolower($_POST['username']));
  $user->setEmail($_POST['email']);
  $user->setGroup($_POST['group']);
  if (strtolower($_POST['username']) != "") {
    if ($_POST['email'] != "" && strstr($_POST['email'], "@") && strstr(strstr($_POST['email'], "@"), ".")) {
      if ($_POST['group'] != "") {
        $db = new DatabaseConnection();
        if ($db->emailAddress(strtolower($_POST['username'])) == "") {
          $password = get_rand_id(8);
          $query = "INSERT INTO user (name, password, email, research_group, status) ".
                    "VALUES ('".strtolower($_POST['username'])."', ".
                            "'".md5($password)."', ".
                            "'".$_POST['email']."', ".
                            "'".$_POST['group']."', ".
                            "'a')";
          $result = $db->execute($query);
          // TODO refactor
          if ($result) {
            $text = "Your account has been activated:\n\n";
            $text .= "\t      Username: ".strtolower($_POST['username'])."\n";
            $text .= "\t      Password: ".$password."\n\n";
            $text .= "Login here\n";
            $text .= $hrm_url."\n\n";
            $folder = $image_folder . "/" . strtolower($_POST['username']);
            $text .= "Source and destination folders for your images are located on server ".$image_host." under ".$folder.".";
            $mail = new Mail($email_sender);
            $mail->setReceiver($_POST['email']);
            $mail->setSubject('Account activated');
            $mail->setMessage($text);
            $mail->send();
            $user->setName('');
            $user->setEmail('');
            $user->setGroup('');
            $message = "            <p class=\"warning\">New user successfully added to the system</p>";
            shell_exec("hrm2share create " . strtolower($_POST['username']));
            $added = True;
          }
          else $message = "            <p class=\"warning\">Database error, please inform the person in charge</p>";
        }
        else $message = "            <p class=\"warning\">This user name is already in use</p>";
      }
      else $message = "            <p class=\"warning\">Please fill in group field</p>";
    }
    else $message = "            <p class=\"warning\">Please fill in email field with a valid address</p>";
  }
  else $message = "            <p class=\"warning\">Please fill in name field</p>";
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";

?>

<!DOCTYPE html 
    PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" 
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">

<head>
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
        @import "stylesheets/default.css";
    </style>
</head>

<body<?php if ($added) echo " onload=\"parent.report()\"" ?>>

<div>

  <form method="post" action="">

    <div id="box">
    
      <fieldset>
      
        <legend>account details</legend>
        
        <div id="adduser">
          
          <label for="username">Username: </label>
          <input type="text" name="username" id="username" value="<?php if (isset($user)) echo $user->name() ?>" class="texfield" />
          
          <br />
          
          <label for="email">E-mail address: </label>
          <input type="text" name="email" id="email" value="<?php if (isset($user)) echo $user->email() ?>" class="texfield" />
          
          <br />
          
          <label for="group">Research group: </label>
          <input type="text" name="group" id="group" value="<?php if (isset($user)) echo $user->group() ?>" class="texfield" />
          
          <br />
          
          <input name="add" type="submit" value="add" class="button" />
          
        </div>
        
      </fieldset>
      
      <div>
        <input type="button" value="close" onclick="window.close()" />
      </div>
      
    </div> <!-- box -->
    
    <div id="notice">
<?php

  print $message;

?>
    </div>
    
  </form>
    
</div>
  
</body>

</html>
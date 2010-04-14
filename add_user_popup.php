<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

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
          $query = "INSERT INTO username (name, password, email, research_group, status) ".
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
            shell_exec("$userManager create \"" . strtolower($_POST['username']) . "\"" );
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

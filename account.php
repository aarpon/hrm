<?php

// php page: account.php

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

global $email_sender;

session_start();

$db = new DatabaseConnection();

if (isset($_GET['exited'])) {
  $_SESSION['user']->logout();
  session_unset();
  session_destroy();
  header("Location: " . "login.php"); exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

if (isset($_SERVER['HTTP_REFERER']) && !strstr($_SERVER['HTTP_REFERER'], 'account')) {
  $_SESSION['referer'] = $_SERVER['HTTP_REFERER'];
}

if (isset($_SESSION['account_user'])) {
  $user = $_SESSION['account_user'];
}
else {
  $user = $_SESSION['user'];
}

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

if (isset($_POST['modify'])) {
  $result = True;
  $query = "UPDATE user SET ";
  if ($_POST['email'] != "" && strstr($_POST['email'], "@") && strstr(strstr($_POST['email'], "@"), ".")) {
    $query .= " email ='".$_POST['email']."'";
  }
  else {
    $result = False;
    $message = "            <p class=\"warning\">Please fill email field in with a valid address</p>";
  }
  if ($_POST['group'] != "") {
    $query .= ", research_group ='".$_POST['group']."'";
  }
  else {
    $result = False;
    $message .= "\n            <p class=\"warning\">Please fill group field in</p>";
  }
  if ($_POST['pass1'] != "" || $_POST['pass2'] != "") {
    if ($_POST['pass1'] == "" || $_POST['pass2'] == "") {
      $result = False;
      $message .= "\n            <p class=\"warning\">Please fill both password fields in</p>";
    }
    else {
      if ($_POST['pass1'] == $_POST['pass2']) {
        $query .= ", password = '".md5($_POST['pass1'])."'";
      }
      else {
        $result = False;
        $message .= "\n            <p class=\"warning\">Passwords do not match</p>";
      }
    }
  }
  if ($result) {
    $query .= " WHERE name = '".$user->name()."'";
    $result = $db->execute($query);
    // TODO refactor
    if ($result) {
      if (isset($_SESSION['account_user'])) {
        $_SESSION['account_user'] = "Account details successfully modified";
        header("Location: " . "user_management.php"); exit();
      }
      else {
        $user->setEmail($_POST['email']);
        $user->setGroup($_POST['group']);
        $_SESSION['user'] = $user;
        $message = "            <p class=\"warning\">Account details successfully modified</p>";
        header("Location: " . $_SESSION['referer']); exit();
      }
    }
    else $message = "            <p class=\"warning\">Database error, please inform the person in charge</p>";
  }
}

include("header.inc.php");

?>

    <div id="nav">
        <ul>
            <li><a href="select_images.php?exited=exited">exit</a></li>
            <li><a href="javascript:openWindow('help/helpSelectParameterSettingPage.html')">help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
        <h3>Account Management</h3>
        
        <form method="post" action="" id="useraccount">
        
            <div id="adduser">
                <label for="email">E-mail address: </label>
                <input name="email" id="email" type="text" value="<?php echo $user->email() ?>" />
                <br />
                <label for="group">Research group: </label>
                <input name="group" id="group" type="text" value="<?php echo $user->group() ?>" />
                <br />
                <br />
                <label for="pass1">New password: </label>
                <input name="pass1" id="pass1" type="password" />
                <br />
                <label for="pass2">(verify) New password: </label>
                <input name="pass2" id="pass2" type="password" />
                <br />
                (leave blank if you do not wish to change your password)
                <input name="modify" type="hidden" value="modify" />
            </div>
        </form>
        
    </div> <!-- content -->
    
    <div id="stuff">
    
        <div id="info">
        
            <p>
                Click the modify button to change e-mail address and/or 
                password. Click the back link to return without changing 
                anything.
            </p>
            
<?php

$referer = $_SESSION['referer'];

?>

          <input type="button" value="cancel" onclick="document.location.href='<?php echo $referer ?>'" class="icon cancel" />
          <input type="button" value="apply" onclick="document.forms['useraccount'].submit()" class="icon apply" />
            
        </div>
        
        <div id="message">
<?php

echo $message;

?>
        </div>
        
    </div> <!-- stuff -->
    
<?php

include("footer.inc.php");

?>

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
require_once("./inc/hrm_config.inc");

global $email_sender;


session_start();

$db = new DatabaseConnection();

if (isset($_GET['home'])) {
  header("Location: " . "home.php"); exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

if (isset($_SERVER['HTTP_REFERER']) && !strstr($_SERVER['HTTP_REFERER'], 'account')) {
  $_SESSION['referer'] = $_SERVER['HTTP_REFERER'];
}

if (isset($_SESSION['account_user'])) {
  $edit_user = $_SESSION['account_user'];
}
else {
  $edit_user = $_SESSION['user'];
}

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";


if (isset($_POST['modify'])) {
  $result = True;
  $baseQuery = "UPDATE username SET ";
  $query = $baseQuery;
  if ( isset( $_POST['email'] ) ) {
    if ( $_POST['email'] != "" && strstr($_POST['email'], "@") && strstr(strstr($_POST['email'], "@"), ".")) {
      $query .= " email ='".$_POST['email']."'";
    }
    else {
      $result = False;
      $message = "            <p class=\"warning\">Please fill in the email field with a valid address<br />&nbsp;</p>";
    }
  }
  if (isset($_POST['group'])) {
    if ($_POST['group'] != "") {
      if ( strcmp( $query, $baseQuery ) == 0 ) {
        $query .= "research_group ='".$_POST['group']."'";
      } else {
        $query .= ", research_group ='".$_POST['group']."'";
      }
    }
    else {
      $result = False;
      $message = "\n            <p class=\"warning\">Please fill in the group field<br />&nbsp;</p>";
    }
  }
  if ($_POST['pass1'] != "" || $_POST['pass2'] != "") {
    if ($_POST['pass1'] == "" || $_POST['pass2'] == "") {
      $result = False;
      $message = "\n            <p class=\"warning\">Please fill in both password fields</p>";
    }
    else {
      if ($_POST['pass1'] == $_POST['pass2']) {
        if ( strcmp( $query, $baseQuery ) == 0 ) {
          $query .= "password = '".md5($_POST['pass1'])."'";
        } else {
          $query .= ", password = '".md5($_POST['pass1'])."'";
        }
      }
      else {
        $result = False;
        $message = "\n            <p class=\"warning\">Passwords do not match</p>";
      }
    }
  }
  if ($result) {
    $query .= " WHERE name = '".$edit_user->name()."'";
    $result = $db->execute($query);
    // TODO refactor
    if ($result) {
      if (isset($_SESSION['account_user'])) {
        $_SESSION['account_user'] = "Account details successfully modified";
        header("Location: " . "user_management.php"); exit();
      }
      else {
        if ( isset( $_POST['email'] ) ) {
          $edit_user->setEmail($_POST['email']);
        }
        if ( isset( $_POST['group'] ) ) {
          $edit_user->setGroup($_POST['group']);
        }
        #$_SESSION['user'] = $user;
        $message = "            <p class=\"warning\">Account details successfully modified</p>";
        header("Location: " . $_SESSION['referer']); exit();
      }
    }
    else $message = "            <p class=\"warning\">Database error, please inform the person in charge</p>\n";
  }
}

include("header.inc.php");

?>
    <!--
      Tooltips
    -->
    <span id="ttSpanCancel">Discard changes and go back to your home page.</span>  
    <span id="ttSpanSave">Save the changes.</span>

    <div id="nav">
        <ul>
            <li><?php echo $_SESSION['user']->name(); ?></li>
            <li><a href="<?php echo getThisPageName();?>?home=home"><img src="images/restart_help.png" alt="home" />&nbsp;Home</a></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpAccount')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
    </div>
    
    <div id="content">

        <h3>Your account</h3>
        
        <form method="post" action="" id="useraccount">
        
            <div id="adduser">
<?php

if (isset($_SESSION['account_user']) || $_SESSION['user']->name() != "admin") {

?>
                <label for="email">E-mail address: </label>
                <input name="email" id="email" type="text" value="<?php echo $edit_user->email() ?>" />
                <br />
<?php

}

// add user management
// TODO refactor
if (isset($_SESSION['account_user'])/* && $_SESSION['user']->name() == "admin"*/) {

?>
                <label for="group">Research group: </label>
                <input name="group" id="group" type="text" value="<?php echo $edit_user->userGroup() ?>" />
                <br />
<?php

}

?>
                <br />
                <label for="pass1">New password: </label>
                <input name="pass1" id="pass1" type="password" />
                <br />
                <label for="pass2">(verify) New password: </label>
                <input name="pass2" id="pass2" type="password" />
                <input name="modify" type="hidden" value="modify" />

                <p />

                <?php
                  $referer = $_SESSION['referer'];
                ?>                

                <div id="controls">
                  <input type="button" name="cancel" value=""
                    class="icon cancel"
                    onmouseover="TagToTip('ttSpanCancel' )"
                    onmouseout="UnTip()"
                    onclick="document.location.href='<?php echo $referer ?>'" />
                  <input type="button" name="save" value="" 
                    class="icon save"
                    onmouseover="TagToTip('ttSpanSave' )"
                    onmouseout="UnTip()"
                    onclick="document.forms['useraccount'].submit()" />
                </div>
            
            </div>
        </form>
        
    </div> <!-- content -->
    
    <div id="rightpanel">
    
        <div id="info">

          <h3>Quick help</h3>
          
          <p>Leave the password fields blank if you do not wish to change
          your password.</p>
          
       </div>
        
        <div id="message">
<?php

echo $message;

?>
        </div>
        
    </div> <!-- rightpanel -->
    
<?php

include("footer.inc.php");

?>

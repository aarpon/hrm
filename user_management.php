<?php

// php page: user_management.php

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
global $image_host;
global $image_folder;
global $image_source;

session_start();

$db = new DatabaseConnection();

if (isset($_GET['exited'])) {
  $_SESSION['user']->logout();
  session_unset();
  session_destroy();
  header("Location: " . "login.php"); exit();
}

if (isset($_GET['seed'])) {
  $query = "SELECT status FROM user WHERE status = '".$_GET['seed']."'";
  if ($db->queryLastValue($query) != $_GET['seed']) {
    header("Location: " . "login.php"); exit();
  }
  else {
    $admin = new User();
    $admin->isLoggedIn = True;
    $admin->lastActivity = time();
    $admin->name = "admin";
    if (isset($_SERVER['REMOTE_ADDR'])) $admin->ip = $_SERVER["REMOTE_ADDR"];
    else $admin->ip = $HTTP_SERVER_VARS["REMOTE_ADDR"];
    session_register("user");
    $_SESSION['user'] = $admin;
  }
}

else if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn() || $_SESSION['user']->name() != "admin") {
  header("Location: " . "login.php"); exit();
}

if (isset($_SERVER['HTTP_REFERER']) && !strstr($_SERVER['HTTP_REFERER'], 'admin')  && !strstr($_SERVER['HTTP_REFERER'], 'account')) {
  $_SESSION['referer'] = $_SERVER['HTTP_REFERER'];
}

// TODO refactor
if (isset($_SESSION['admin_referer'])) {
  $_SESSION['referer'] = $_SESSION['admin_referer'];
  unset($_SESSION['admin_referer']);
}

if (isset($_SESSION['account_user']) && gettype($_SESSION['account_user']) != "object") {
  $message = $_SESSION['account_user'];
  unset($_SESSION['account_user']);
}

if (!isset($_SESSION['index'])) {
  session_register("index");
  $_SESSION['index'] = "";
}
else if (isset($_GET['index'])) {
  $_SESSION['index'] = $_GET['index'];
}

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

if (isset($_POST['accept'])) {
  $query = "UPDATE user SET status = 'a' WHERE name = '".$_POST['username']."'";
  $result = $db->execute($query);
  // TODO refactor
  if ($result) {
    $email = $db->emailAddress($_POST['username']);
    $text = "Your account has been activated:\n\n";
    $text .= "\t      Username: ".$_POST['username']."\n";
    $text .= "\tE-mail address: ".$email."\n\n";
    $text .= "Login here\n";
    $text .= $hrm_url."\n\n";
    $folder = $image_folder . "/" . $_POST['username'];
    $text .= "Source and destination folders for your images are located on server ".$image_host." under ".$folder.".";
    $mail = new Mail($email_sender);
    $mail->setReceiver($email);
    $mail->setSubject("Account activated");
    $mail->setMessage($text);
    $mail->send();
    shell_exec("bin/hrm create " . $_POST['username']);
  }
  else $message = "            <p class=\"warning\">Database error, please inform the person in charge</p>";
}
else if (isset($_POST['reject'])) {
  $email = $db->emailAddress($_POST['username']);
  $query = "DELETE FROM user WHERE name = '".$_POST['username']."'";
  $result = $db->execute($query);
  // TODO refactor
  if (!$result) $message = "            <p class=\"warning\">Database error, please inform the person in charge</p>";
  $text = "Your request has been rejected. Please contact ".$email_admin." for any enquiries.\n";
  $mail = new Mail($email_sender);
  $mail->setReceiver($email);
  $mail->setSubject("Request rejected");
  $mail->setMessage($text);
  $mail->send();
}
else if (isset($_POST['annihilate']) && $_POST['annihilate'] == "yes") {
  if ($_POST['username'] != "admin") {
    $query = "DELETE FROM user WHERE name = '".$_POST['username']."'";
    $result = $db->execute($query);
    if ($result) {
      // delete user's settings
      $query = "DELETE FROM parameter WHERE owner = '".$_POST['username']."'";
      $db->execute($query);
      $query = "DELETE FROM parameter_setting WHERE owner = '".$_POST['username']."'";
      $db->execute($query);
      $query = "DELETE FROM task_parameter WHERE owner = '".$_POST['username']."'";
      $db->execute($query);
      $query = "DELETE FROM task_setting WHERE owner = '".$_POST['username']."'";
      $db->execute($query);
      // TODO refactor
      if ($result) {
        shell_exec("bin/hrm delete " . $_POST['username']);
      }
      else {
        $message = "            <p class=\"warning\">Database error, please inform the person in charge</p>";
      }
    }
    else {
      $message = "            <p class=\"warning\">Database error, please inform the person in charge</p>";
    }
  }
}
else if (isset($_POST['edit'])) {
  $account_user = new User();
  $account_user->setName($_POST['username']);
  $account_user->setEmail($_POST['email']);
  $account_user->setGroup($_POST['group']);
  session_register("account_user");
  $_SESSION['account_user'] = $account_user;
  if (isset($c) || isset($_GET['c']) || isset($_POST['c'])) {
    session_register("c");
    if (isset($_GET['c'])) $_SESSION['c'] = $_GET['c'];
    else if (isset($_POST['c'])) $_SESSION['c'] = $_POST['c'];
  }
  header("Location: " . "account.php"); exit();
}
else if (isset($_POST['enable'])) {
  $query = "UPDATE user SET status = 'a' WHERE name = '".$_POST['username']."'";
  $result = $db->execute($query);
}
else if (isset($_POST['disable'])) {
  $query = "UPDATE user SET status = 'd' WHERE name = '".$_POST['username']."'";
  $result = $db->execute($query);
}
else if (isset($_POST['action'])) {
  if ($_POST['action'] == "disable") {
    $query = "UPDATE user SET status = 'd' WHERE name NOT LIKE 'admin'";
    $result = $db->execute($query);
  }
  else if ($_POST['action'] == "enable") {
    $query = "UPDATE user SET status = 'a' WHERE name NOT LIKE 'admin'";
    $result = $db->execute($query);
  }
}
// TODO refactor to here

$script = "admin.js";

include("header.inc.php");

?>

    <div id="nav">
        <ul>
            <li><a href="select_images.php?exited=exited" onclick="clean()">exit</a></li>
            <li>users</li>
            <li><a href="select_parameter_settings.php" onclick="clean()">parameters</a></li>
            <li><a href="select_task_settings.php" onclick="clean()">tasks</a></li>
            <li><a href="account.php">account</a></li>
            <li><a href="job_queue.php" onclick="clean()">queue</a></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpUserManagement')">help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
        <h3>User Management</h3>
        
        <form method="post" action="">
            <div id="registeruser">
                <fieldset>
                    <legend>registrations</legend>
                    <table>
<?php

$rows = $db->query("SELECT * FROM user");
sort($rows);
$i = 0;
foreach ($rows as $row) {
  $name = $row["name"];
  $email = $row["email"];
  $group = $row["research_group"];
  $creation_date = date("j M Y, G:i", strtotime($row["creation_date"]));
  $status = $row["status"];
  if ($status != "a" && $status != "d") {
    if ($i > 0) echo "                    <tr><td colspan=\"3\" class=\"hr\">&nbsp;</td></tr>\n";

?>
                        <tr class="upline">
                            <td class="name"><span class="title"><?php echo $name ?></span></td>
                            <td class="group"><?php echo $group ?></td>
                            <td class="email"><a href="mailto:<?php echo $email ?>" class="normal"><?php echo $email ?></a></td>
                        </tr>
                        <tr class="bottomline">
                            <td colspan="2" class="date">
                                request date: <?php echo $creation_date."\n" ?>
                            </td>
                            <td class="operations">
                                <div>
                                    <input type="hidden" name="username" value="<?php echo $name ?>" />
                                    <input type="submit" name="accept" value="accept" />
                                    <input type="submit" name="reject" value="reject" />
                                </div>
                            </td>
                        </tr>
<?php

    $i++;
  }
}

if (!$i) {

?>
                        <tr><td>no pending requests</td></tr>
<?php

}

?>
                    </table>
                </fieldset>
            </div>
        </form>
        
        <div id="listusers">
            <fieldset>
<?php

$count = $db->queryLastValue("SELECT count(*) FROM user WHERE status = 'a' OR status = 'd'");
$rows = $db->query("SELECT email FROM user WHERE status = 'a'");
$emails = array();
foreach ($rows as $row) {
  array_push($emails, $row['email']);
}
$emails = array_unique($emails);
sort($emails);

?>
                <legend>existing users (<?php echo $count - 1 ?>)</legend>
                <p class="menu">
                    <a href="javascript:openPopup('add_user')">add new user</a>&nbsp;|&nbsp;<a href="mailto:<?php echo implode(";", $emails) ?>">distribution list</a>
                    <br />
                    <a href="javascript:disableUsers()">disable</a>/<a href="javascript:enableUsers()">enable</a> all users
                    <form method="post" action="" name="user_management">
                      <input type="hidden" name="action" />
                    </form>
                </p>
                <table>
                    <tr>
                        <td colspan="3" class="menu">
<?php

$i = 0;
echo "                            <div class=\"line\">";
$style = " class=\"filled\"";
if ($_SESSION['index'] == "all") $style = " class=\"selected\"";
echo "[<a href=\"?index=all\"".$style.">&nbsp;all&nbsp;</a>]&nbsp;[";
while (True) {
  $c = chr(97 + $i);
  $style = "";
  $result = $db->queryLastValue("SELECT * FROM user WHERE name LIKE '".$c."%' AND name NOT LIKE 'admin' AND (status = 'a' OR status = 'd')");
  if ($_SESSION['index'] == $c) $style = " class=\"selected\"";
  else if (!$result) $style = " class=\"empty\"";
  else $style = " class=\"filled\"";
  echo "<a href=\"?index=".chr(97 + $i)."\"".$style.">&nbsp;".strtoupper($c)."&nbsp;</a>";
  if ($i == 25) {
    echo "]</div>\n";
    break;
  }
  $i++;
}

?>
                        </td>
                    </tr>
                    <tr><td>&nbsp;</td></tr>
<?php

if ($_SESSION['index'] != "") {
  $condition = "";
  if ($_SESSION['index'] != "all") $condition = " WHERE name LIKE '".$_SESSION['index']."%'";
  $rows = $db->query("SELECT * FROM user".$condition);
  sort($rows);
  $i = 0;
  foreach ($rows as $row) {
    if ($row['name'] != "admin") {
      $name = $row['name'];
      $email = $row['email'];
      $group = $row['research_group'];
      $last_access_date = date("j M Y, G:i", strtotime($row['last_access_date']));
      if ($last_access_date == "30 Nov 1999, 0:00") {
        $last_access_date = "never";
      }
      $status = $row['status'];
      if ($status == "a" || $status == "d") {
        if ($i > 0) echo "                    <tr><td colspan=\"3\" class=\"hr\">&nbsp;</td></tr>\n";

?>
                    <tr  class="upline<?php if ($status == "d") echo " disabled" ?>">
                        <td class="name"><span class="title"><?php echo $name ?></span></td>
                        <td class="group"><?php echo $group ?></td>
                        <td class="email"><a href="mailto:<?php echo $email ?>" class="normal"><?php echo $email ?></a></td>
                    </tr>
                    <tr class="bottomline<?php if ($status == "d") echo " disabled" ?>">
                        <td colspan="2" class="date">
                            last access: <?php echo $last_access_date."\n" ?>
                        </td>
                        <td class="operations">
                            <form method="post" action="">
                                <div>
                                    <input type="hidden" name="username" value="<?php echo $name ?>" />
                                    <input type="hidden" name="email" value="<?php echo $email ?>" />
                                    <input type="hidden" name="group" value="<?php echo $group ?>" />
                                    <input type="submit" name="edit" value="edit" class="submit" />
<?php

        if ($name != "admin") {
          if ($status == "d") {

?>
                                    <input type="submit" name="enable" value="enable" class="submit" />
<?php

          }
          else {

?>
                                    <input type="submit" name="disable" value="disable" class="submit" />
<?php

          }

?>

                                    <input type="hidden" name="annihilate" />
                                    <input type="button" name="delete" value="delete" onclick="warn(this.form)" class="submit" />
<?php

        }

?>
                                </div>
                            </form>
                        </td>
                    </tr>
<?php

        $i++;
      }
    }
  }
  if (!$i) {

?>
                    <tr><td colspan="3" class="notice">n/a</td></tr>
<?php

  }
}

?>
                </table>
            </fieldset>
        </div>
        
    </div> <!-- content -->
    
    <div id="stuff">
        <div id="info">
            <p>
                You can add new users, accept or reject pending registration 
                requests, and manage existing users.
            </p>
        </div>
        <div id="message">
<?php

print $message;

?>
        </div>
    </div>  <!-- stuff -->
    
<?php

include("footer.inc.php");

?>


<?php

// php page: select_task_settings.php

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
require_once("./inc/Parameter.inc");
require_once("./inc/Setting.inc");
require_once("./inc/SettingEditor.inc");

global $enableUserAdmin;

session_start();

if (isset($_GET['exited'])) {
  $_SESSION['user']->logout();
  session_unset();
  session_destroy();
  header("Location: " . "login.php"); exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

if (!isset($_SESSION['taskeditor'])) {
  session_register('taskeditor');
  $_SESSION['taskeditor'] = new TaskSettingEditor($_SESSION['user']);
}

if (isset($_SESSION['task_setting'])) {
  session_register("task_setting");
}

// add public setting support
if ($_SESSION['user']->name() != "admin") {
  $admin = new User();
  $admin->name = "admin";
  $admin_editor = new TaskSettingEditor($admin);
}

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

if (isset($_POST['task_setting'])) {
  $_SESSION['taskeditor']->setSelected($_POST['task_setting']);
}

if (isset($_POST['copy_public'])) {
  if (isset($_POST["public_setting"])) {
    if (!$_SESSION['taskeditor']->copyPublicSetting($admin_editor->setting($_POST['public_setting']))) {
      $message = "            <p class=\"warning\">".$_SESSION['editor']->message()."</p>\n";
    }
  }
  else $message = "            <p class=\"warning\">Please select a setting to copy</p>\n";
}
else if (isset($_POST['create'])) {
  $task_setting = $_SESSION['taskeditor']->createNewSetting($_POST['new_setting']);
  if ($task_setting != NULL) {
    $_SESSION['task_setting'] = $task_setting;
    header("Location: " . "task_parameter.php"); exit();
  }
  $message = "            <p class=\"warning\">".$_SESSION['taskeditor']->message()."</p>\n";
}
else if (isset($_POST['copy'])) {
  $_SESSION['taskeditor']->copySelectedSetting($_POST['new_setting']);
  $message = "            <p class=\"warning\">".$_SESSION['taskeditor']->message()."</p>\n";
}
else if (isset($_POST['edit'])) {
  $task_setting = $_SESSION['taskeditor']->loadSelectedSetting();
  if ($task_setting) {
    $_SESSION['task_setting'] = $task_setting;
    header("Location: " . "task_parameter.php"); exit();
  }
  $message = "            <p class=\"warning\">".$_SESSION['taskeditor']->message()."</p>\n";
}
else if (isset($_POST['make_default'])) {
  $_SESSION['taskeditor']->makeSelectedSettingDefault();
  $message = "            <p class=\"warning\">".$_SESSION['taskeditor']->message()."</p>\n";
}
else if (isset($_POST['delete'])) {
  $_SESSION['taskeditor']->deleteSelectedSetting();
  $message = "            <p class=\"warning\">".$_SESSION['taskeditor']->message()."</p>\n";
}
else if (isset($_POST['OK'])) {
  if (!isset($_POST['task_setting'])) {
    $message = "            <p class=\"warning\">Please select a parameter setting</p>\n";
  }
  else {
    $_SESSION['task_setting'] = $_SESSION['taskeditor']->loadSelectedSetting();
    $_SESSION['task_setting']->setNumberOfChannels($_SESSION['setting']->numberOfChannels());
    header("Location: " . "select_images.php"); exit();
  }
}

$script = "settings.js";

include("header.inc.php");

?>

    <div id="nav">
        <ul>
            <li><a href="select_task_settings.php?exited=exited">exit</a></li>
<?php

// add user management
if ($_SESSION['user']->name() == "admin") {
  if ($enableUserAdmin) {

?>
            <li><a href="user_management.php">users</a></li>
<?php

  }

?>
            <li><a href="select_parameter_settings.php">parameters</a></li>
            <li>tasks</li>
<?php

}

if ($enableUserAdmin || $_SESSION['user']->name() == "admin") {

?>
            <li><a href="account.php">account</a></li>
<?php

}

?>
            <li><a href="job_queue.php">queue</a></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpSelectTaskSettings')">help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
<?php

// add user management
if ($_SESSION['user']->name() == "admin") {

?>
        <h3>Task Settings</h3>
<?php

}
else {

?>
        <h3>Step 2 - Select Task Setting</h3>
<?php

}

// display public settings
if ($_SESSION['user']->name() != "admin") {

?>
        <form method="post" action="">
        
            <fieldset>
                <legend>template parameter settings</legend>
                <div id="templates">
<?php

  $settings = $admin_editor->settings();
  $flag = "";
  if (sizeof($settings) == 0) $flag = " disabled=\"disabled\"";

?>
                    <select name="public_setting" size="5"<?php echo $flag ?>>
<?php

  if (sizeof($settings) == 0) {
    echo "                        <option>&nbsp;</option>\n";
  }
  else {
    foreach ($settings as $set) {
      echo "                        <option>".$set->name()."</option>\n";
    }
  }

?>
                    </select>
                </div>
            </fieldset>
            
            <div id="selection">
                <input name="copy_public" type="submit" value="copy_public" class="icon copy" />
            </div>
            
        </form>
        
<?php

}

?>

        <form method="post" action="" id="select">
        
            <fieldset>
            
                <legend>your parameter settings</legend>
                
                <div id="settings">
<?php

$settings = $_SESSION['taskeditor']->settings();
$size = "8";
if ($_SESSION['user']->name() == "admin") $size = "12";
$flag = "";
if (sizeof($settings) == 0) $flag = " disabled=\"disabled\"";

?>
                    <select name="task_setting" size="<?php echo $size ?>"<?php echo $flag ?>>
<?php

if (sizeof($settings) == 0) {
  echo "                        <option>&nbsp;</option>\n";
}
else {
  foreach ($settings as $set) {
    echo "                        <option";
    if ($set->isDefault()) {
      echo " style=\"color: #078d1b\"";
    }
    if ($_SESSION['taskeditor']->selected() == $set->name()) {
      echo " selected=\"selected\"";
    }
    echo ">".$set->name()."</option>\n";
  }
}

?>
                    </select>
                </div>
                
            </fieldset>
            
            <div id="actions" class="taskselection">
                <input name="create" type="submit" value="create" class="icon create" />
                <input name="edit" type="submit" value="edit" class="icon edit" />
                <input name="copy" type="submit" value="copy" class="icon clone" />
<?php

if ($_SESSION['user']->name() != "admin") {

?>
                <input name="make_default" type="submit" value="make_default" class="icon mark" />
<?php

}

?>
                <input name="delete" type="submit" value="delete" class="icon delete" />
                <label>new/clone setting name: <input name="new_setting" type="text" class="textfield" /></label>
                <input name="OK" type="hidden" />
            </div>
            
        </form> <!-- select -->
        
    </div> <!-- content -->
    
    <div id="stuff">
    
        <div id="info">
        
<?php

// add user management
if ($_SESSION['user']->name() != "admin") {

?>
            <input type="button" class="icon previous" onclick="document.location.href='select_parameter_settings.php'" />
            <input type="submit" class="icon next" onclick="process()" />
<?php

}

?>

            <p>
                Select a task setting and press <br>
                the <img src="images/next_help.png" name="Forward" width="22" height="22"> <b>forward</b>
                button to go to the <br> next step.
            </p>

            <p>
                You can <img src="images/create_help.png" name="Create" width="22" height="22"> <b>create</b>
                new settings or <br><img src="images/edit_help.png" name="Create" width="22" height="22"><b>edit</b>
                existing ones.
            </p>

           <p>
                <img src="images/clone_help.png" name="Copy" width="22" height="22"> <b>Copy</b>
                creates a clone of the selected setting with the specified name.
            </p>

            <p>
                You can set the current selection as the
                <img src="images/mark_help.png" name="Default" width="22" height="22"> <b>default</b> setting.
            </p>

            <p>
                You can permanently destroy the current selection by pressing <br>on the
                <img src="images/delete_help.png" name="Delete" width="22" height="22"> <b>delete</b> button.
            </p>

            <p>
                For more detailed explanations please follow the help link in the navigation bar.
            </p>

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

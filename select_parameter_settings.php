<?php

// php page: select_parameter_settings.php

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
require_once("./inc/Fileserver.inc");

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

if (!isset($_SESSION['editor'])) {
  session_register("editor");
  $_SESSION['editor'] = new SettingEditor($_SESSION['user']);
}

if (isset($_SESSION['setting'])) {
  session_register("setting");
}

// add public setting support
if ($_SESSION['user']->name() != "admin") {
  $admin = new User();
  $admin->setName("admin");
  $admin_editor = new SettingEditor($admin);
}

// fileserver related code (for measured PSF files check)
if (!isset($_SESSION['fileserver'])) {
  session_register("fileserver");
  $name = $_SESSION['user']->name();
  $_SESSION['fileserver'] = new Fileserver($name);
}

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

if (isset($_POST['setting'])) {
  $_SESSION['editor']->setSelected($_POST['setting']);
}

if (isset($_POST['copy_public'])) {
  if (isset($_POST['public_setting'])) {
    if (!$_SESSION['editor']->copyPublicSetting($admin_editor->setting($_POST['public_setting']))) {
      $message = "            <p class=\"warning\">".$_SESSION['editor']->message()."</p>\n";
    }
  }
  else $message = "            <p class=\"warning\">Please select a setting to copy</p>\n";
}
else if (isset($_POST['create'])) {
  $setting = $_SESSION['editor']->createNewSetting($_POST['new_setting']);
  if ($setting != NULL) {
    $_SESSION['setting'] = $setting;
    header("Location: " . "image_format.php"); exit();
  }
  $message = "            <p class=\"warning\">".$_SESSION['editor']->message()."</p>\n";
}
else if (isset($_POST['copy'])) {
  $_SESSION['editor']->copySelectedSetting($_POST['new_setting']);
  $message = "            <p class=\"warning\">".$_SESSION['editor']->message()."</p>\n";
}
else if (isset($_POST['edit'])) {
  $setting = $_SESSION['editor']->loadSelectedSetting();
  if ($setting) {
    $_SESSION['setting'] = $setting;
    header("Location: " . "image_format.php"); exit();
  }
  $message = "            <p class=\"warning\">".$_SESSION['editor']->message()."</p>\n";
}
else if (isset($_POST['make_default'])) {
  $_SESSION['editor']->makeSelectedSettingDefault();
  $message = "            <p class=\"warning\">".$_SESSION['editor']->message()."</p>\n";
}
else if (isset($_POST['delete'])) {
  $_SESSION['editor']->deleteSelectedSetting();
  $message = "            <p class=\"warning\">".$_SESSION['editor']->message()."</p>\n";
}
else if (isset($_POST['OK'])) {
  if (!isset($_POST['setting'])) {
    $message = "            <p class=\"warning\">Please select a parameter setting</p>\n";
  }
  else {
    $_SESSION['setting'] = $_SESSION['editor']->loadSelectedSetting();
    // if measured PSF, check files availability
    $ok = True;
    $psfParam = $_SESSION['setting']->parameter("PointSpreadFunction");
    if ($psfParam->value() == "measured") {
      $psf = $_SESSION['setting']->parameter("PSF");
      $value = $psf->value();
      $files = $_SESSION['fileserver']->files("ics");
      if ($files != null) {
        for ($i=1; $i <= $_SESSION['setting']->numberOfChannels(); $i++) {
          if (!in_array($value[$i], $files)) {
            $message = "            <p class=\"warning\">Please verify selected setting, as some PSF files appear to be missing</p>\n";
            $ok = False;
            break;
          }
        }
      }
      else {
        $message = "            <p class=\"warning\">Source image folder not found! Make sure path ".$_SESSION['fileserver']->sourceFolder()." exists.</p>\n";
        $ok = False;
      }
    }
    if ($ok) {header("Location: " . "select_task_settings.php"); exit();}
  }
}

$script = "settings.js";

include("header.inc.php");

?>

    <div id="nav">
        <ul>
            <li><a href="select_images.php?exited=exited">exit</a></li>
<?php

// add user management
if ($_SESSION['user']->name() == "admin") {

?>
            <li><a href="user_management.php">users</a></li>
            <li>parameters</li>
            <li><a href="select_task_settings.php">tasks</a></li>
<?php

}

if ($_SESSION['user']->name() != "admin") {

?>
            <li><a href="account.php">account</a></li>
<?php

}

?>
            <li><a href="job_queue.php">queue</a></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/HuygensRemoteManagerHelpSelectParameterSettings')">help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
<?php

// add user management
if ($_SESSION['user']->name() == "admin") {

?>
        <h3>Parameter Settings</h3>
<?php

}
else {

?>
        <h3>Step 1 - Select Parameter Setting</h3>
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
    foreach ($settings as $setting) {
      echo "                        <option>".$setting->name()."</option>\n";
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

$settings = $_SESSION['editor']->settings();
$size = "8";
if ($_SESSION['user']->name() == "admin") $size = "12";
$flag = "";
if (sizeof($settings) == 0) $flag = " disabled=\"disabled\"";

?>
                    <select name="setting" size="<?php echo $size ?>"<?php echo $flag ?>>
<?php

if (sizeof($settings) == 0) {
  echo "                        <option>&nbsp;</option>\n";
}
else {
  foreach ($settings as $setting) {
    echo "                        <option";
    if ($setting->isDefault()) {
      echo " class=\"default\"";
    }
    if ($_SESSION['editor']->selected() == $setting->name()) {
      echo " selected=\"selected\"";
    }
    echo ">".$setting->name()."</option>\n";
  }
}

?>
                    </select>
                </div>
                
            </fieldset>
            
            <div id="actions" class="parameterselection">
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
            <input type="submit" class="icon empty" disabled="disabled" />
            <input type="submit" class="icon next" onclick="process()" />
<?php

}

?>

            <p>
                Select a parameter setting and press OK to go to the next step.
            </p>
            
            <p>
                Before selecting a parameter setting you can create a new one 
                or edit an existing one.
            </p>
            
            <p>
                To create a new parameter setting enter its name and press the 
                "create new setting" button.
            </p>
            
            <p>
                "Copy selected setting" creates a new setting with the name you 
                entered that has the same parameter values set as the selected 
                setting.
            </p>
            
            <p>
                "Edit selected setting" allows you to change the parameter 
                values of the selected setting.
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

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

global $enableUserAdmin;

session_start();

if (isset($_GET['home'])) {
  header("Location: " . "home.php"); exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

if (!isset($_SESSION['editor'])) {
  # session_register("editor");
  $_SESSION['editor'] = new SettingEditor($_SESSION['user']);
}

if (isset($_SESSION['setting'])) {
  # session_register("setting");
}

// add public setting support
if ($_SESSION['user']->name() != "admin") {
  $admin = new User();
  $admin->setName("admin");
  $admin_editor = new SettingEditor($admin);
}

// fileserver related code (for measured PSF files check)
if (!isset($_SESSION['fileserver'])) {
  # session_register("fileserver");
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
      $files = $_SESSION['fileserver']->allFiles();
      if ($files != null) {
        for ($i=0; $i < $_SESSION['setting']->numberOfChannels(); $i++) {
          if (!in_array($value[$i], $files)) {
            $message = "            <p class=\"warning\">Please verify selected setting, as some PSF files appear to be missing</p>\n";
            $ok = False;
            break;
          }
        }
        // If a parameter setting with measured PSF was created before HRM 1.1,
        // all microscope parameters would be empty. This behavior was changed
        // in HRM 1.1, thus meaning that existing settings must be checked for
        // completeness.
        // We will check just a couple of parameters -- we do not neet to check
        // them all, since in case of measured PSF even existing parameters were
        // purged.
        $microscopeType    = $_SESSION['setting']->parameter("MicroscopeType")->value( );
        $numericalAperture = $_SESSION['setting']->parameter("NumericalAperture")->value( );
        $ccdCaptorSize     = $_SESSION['setting']->parameter("CCDCaptorSizeX")->value( );
        if ( empty( $microscopeType ) ||
             empty( $numericalAperture ) ||
             empty( $ccdCaptorSize ) ) {
                $message = "            <p class=\"warning\">Please check this setting for completeness! Current version of HRM requires that all parameters are set even if a measured PSF is chosen.</p>\n";
                $ok = False;
        }          
      } else {
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
            <li><a href="<?php echo getThisPageName();?>?home=home"><img src="images/restart_help.png" alt="home" />&nbsp;Home</a></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpSelectParameterSettings')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
    </div>
    
    <div id="content">

    <!--
      Tooltips
    -->
    <span id="ttSpanCreate">Create a new setting with the specified name.</span>  
    <span id="ttSpanEdit">Edit the selected setting.</span>
    <span id="ttSpanClone">Copy the selected setting to a new one with the
      specified name.</span>
    <span id="ttSpanDelete">Delete the selected setting.</span>
    <?php
      if ($_SESSION['user']->name() != "admin") {
        ?>
        <span id="ttSpanDefault">Sets the selected setting as the default one.</span>
        <span id="ttSpanCopyTemplate">Copy a template.</span>
    <?php
      }
    ?>
    
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
                <input name="copy_public" type="submit" value=""
                    class="icon copy"
                    onmouseover="TagToTip('ttSpanCopyTemplate' )"
                    onmouseout="UnTip()" />
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
                <input name="create" type="submit" value="" class="icon create"
                       onmouseover="TagToTip('ttSpanCreate' )"
                       onmouseout="UnTip()" />
                <input name="edit" type="submit" value="" class="icon edit"
                      onmouseover="TagToTip('ttSpanEdit' )"
                      onmouseout="UnTip()" />
                <input name="copy" type="submit" value="" class="icon clone"
                       onmouseover="TagToTip('ttSpanClone' )"
                       onmouseout="UnTip()" />
<?php

if ($_SESSION['user']->name() != "admin") {

?>
                <input name="make_default" type="submit" value=""
                      class="icon mark"
                      onmouseover="TagToTip('ttSpanDefault' )"
                      onmouseout="UnTip()" />
<?php

}

?>
                <input name="delete" type="submit" value="" class="icon delete"
                      onmouseover="TagToTip('ttSpanDelete' )"
                      onmouseout="UnTip()" />
                <label>new/clone setting name: <input name="new_setting" type="text" class="textfield" /></label>
                <input name="OK" type="hidden" />
        </div>                
<?php

if ($_SESSION['user']->name() != "admin") {

?>
                <div id="controls">      
                  <input type="submit" value="" class="icon empty" disabled="disabled" />
                  <input type="submit" value="" class="icon next" onclick="process()" />
                </div>
<?php

}

?>
            
        </form> <!-- select -->
        
    </div> <!-- content -->
    
    <div id="rightpanel">
    
        <div id="info">
          
          <h3>Quick help</h3>
        
<?php

// add user management
if ($_SESSION['user']->name() != "admin") {

?>

<?php

}

	if ($_SESSION['user']->name() != "admin") {
	?>
	    <p>
		Select a parameter setting and press  
                the <img src="images/next_help.png" alt="Forward" width="22" height="22" /> <b>forward</b>
                button to go to the next step.
	   </p>
	<?php
	} else {
	?>
		<p>&nbsp;</p>
	<?php
	}
	?>
            <p>
		You can <img src="images/create_help.png" alt="Create" width="22" height="22" /> <b>create</b> 
                new settings or <img src="images/edit_help.png" alt="Create" width="22" height="22" /><b>edit</b> 
                existing ones.
            </p>
            
           <p>
		<img src="images/clone_help.png" alt="Copy" width="22" height="22" /> <b>Copy</b> 
                creates a clone of the selected setting with the specified name.
            </p>
            
            <p>
		You can set the current selection as the 
		<img src="images/mark_help.png" alt="Default" width="22" height="22" /> <b>default</b> setting.
	    </p>

            <p>
                You can permanently destroy the current selection by pressing on the 
                <img src="images/delete_help.png" alt="Delete" width="22" height="22" /> <b>delete</b> button.
            </p>

            <p>
                For more etailed explanations please follow the
                <img src="images/help.png" alt="Help" width="22" height="22" /> <b>help</b> 
                link in the navigation bar.
            </p>


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

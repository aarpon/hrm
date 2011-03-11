<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

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
  $_SESSION['editor'] = new SettingEditor($_SESSION['user']);
}

// add public setting support
if (!$_SESSION['user']->isAdmin()) {
  $admin = new User();
  $admin->setName("admin");
  $admin_editor = new SettingEditor($admin);
}

// fileserver related code (for measured PSF files check)
if (!isset($_SESSION['fileserver'])) {
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
  if (isset($_POST['annihilate']) && $_POST['annihilate'] == "yes") {
    $_SESSION['editor']->deleteSelectedSetting();
    $message = "            <p class=\"warning\">".$_SESSION['editor']->message()."</p>\n";
  }
}
else if (isset($_POST['OK'])) {
  if (!isset($_POST['setting'])) {
    $message = "            <p class=\"warning\">Please select some image parameters.</p>\n";
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
    <!--
      Tooltips
    -->
    <span id="ttSpanCreate">Create a new setting with the specified name.</span>  
    <span id="ttSpanEdit">Edit the selected setting.</span>
    <span id="ttSpanClone">Copy the selected setting to a new one with the
      specified name.</span>
    <span id="ttSpanDelete">Delete the selected setting.</span>
    <?php
      if (!$_SESSION['user']->isAdmin()) {
        ?>
        <span id="ttSpanDefault">Sets the selected setting as the default one.</span>
        <span id="ttSpanCopyTemplate">Copy a template.</span>
        <span id="ttSpanForward">Continue to step 2/4 - Restoration parameters.</span>
    <?php
      }
    ?>

    <div id="nav">
        <ul>
            <li><?php echo $_SESSION['user']->name(); ?></li>
            <li><a href="<?php echo getThisPageName();?>?home=home"><img src="images/home.png" alt="home" />&nbsp;Home</a></li>
            <li><a href="javascript:openWindow('http://www.svi.nl/HuygensRemoteManagerHelpSelectParameterSettings')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
    </div>
    
    <div id="content">
 
<?php

if ($_SESSION['user']->isAdmin()) {

?>
        <h3>Image parameters</h3>
<?php

}
else {

?>
        <h3>Step 1/4 - Image parameters</h3>
<?php

}

// display public settings
if (!$_SESSION['user']->isAdmin()) {

?>
        <form method="post" action="">
        
            <fieldset>
                <legend>Template image parameters</legend>
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
            
            <?php
            if ($_SESSION['user']->isAdmin()) {
              echo "<legend>Template image parameters</legend>";
            } else {
              echo "<legend>Your image parameters</legend>";
            }
            ?>
        
             <div id="settings">
<?php

$settings = $_SESSION['editor']->settings();
$size = "8";
if ($_SESSION['user']->isAdmin()) $size = "12";
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

if (!$_SESSION['user']->isAdmin()) {

?>
                <input name="make_default" type="submit" value=""
                      class="icon mark"
                      onmouseover="TagToTip('ttSpanDefault' )"
                      onmouseout="UnTip()" />
<?php

}

?>
                <input type="hidden" name="annihilate" />
                <input name="delete" type="submit" value="" class="icon delete"
                      onclick="warn(this.form, 'Do you really want to delete this parameter set?')"
                      onmouseover="TagToTip('ttSpanDelete' )"
                      onmouseout="UnTip()" />
                <label>New/clone setting name: <input name="new_setting" type="text" class="textfield" /></label>
                <input name="OK" type="hidden" />
        </div>                
<?php

if (!$_SESSION['user']->isAdmin()) {

?>
                <div id="controls">      
                  <input type="submit" value="" class="icon empty" disabled="disabled" />
                  <input type="submit" value="" class="icon next"
                    onclick="process()"
                    onmouseover="TagToTip('ttSpanForward' )"
                    onmouseout="UnTip()" />
                </div>
<?php

}

?>
            
        </form> <!-- select -->
        
    </div> <!-- content -->
    
    <div id="rightpanel">
    
        <div id="info">
          
          <h3>Quick help</h3>

          <p><strong>Placing the mouse pointer over the various icons will display a
          tooltip with explanations.</strong></p>
    
          <p><strong>For a more detailed explanation on the possible actions, please follow the
          <img src="images/help.png" alt="Help" width="22" height="22" /> <b>Help</b> 
          link in the navigation bar.</strong></p>

          <p />
<?php

// add user management
if (!$_SESSION['user']->isAdmin()) {

?>

<?php

}

	if (!$_SESSION['user']->isAdmin()) {
      echo "<p>In the first step, you are asked to specify all parameters relative
        to the images you want to restore.</p>";
	} else {
	  echo "<p>Here, you can create template parameters relative to the images
      to restore.</p>";
	}
	?>
      <p>These include: file information (format, geometry, voxel size);
      microscopic parameters (such as microscope type, numerical aperture of
      the objective, fluorophore wavelengths); whether a measured or a
      theoretical PSF should be used; whether depth-dependent correction
      on the PSF should be applied.</p>

    <?php        
	if (!$_SESSION['user']->isAdmin()) {
      echo "<p>'Template image parameters' created by your facility manager can
        be copied to the list of 'Your image parameters' and adapted to fit your
        specific experimental setup.</p>";
	} else {
	  echo "<p>The created templates will be visible for the users in an
      additional selection field from which they can be copied to the user's
      parameters.</p>";
	}
	?>

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

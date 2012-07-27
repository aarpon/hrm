<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Parameter.inc.php");
require_once("./inc/Setting.inc.php");
require_once("./inc/SettingEditor.inc.php");
require_once("./inc/Fileserver.inc.php");

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

$message = "";

if (isset($_POST['setting'])) {
  $_SESSION['editor']->setSelected($_POST['setting']);
}

if (isset($_POST['copy_public'])) {
  if (isset($_POST['public_setting'])) {
    if (!$_SESSION['editor']->copyPublicSetting(
        $admin_editor->setting($_POST['public_setting']))) {
      $message = $_SESSION['editor']->message();
    }
  }
  else $message = "Please select a setting to copy";
}
else if (isset($_POST['create'])) {

    // The file format is stored in a dummy setting created at stage 1.
    $fileFormat = $_SESSION['setting']->parameter("ImageFileFormat")->value();
    
    $setting = $_SESSION['editor']->createNewSetting($_POST['new_setting']);
    $setting->parameter("ImageFileFormat")->setValue($fileFormat);
    $_SESSION['setting'] = $setting;
    
    if ($setting != NULL) {
        header("Location: " . "image_format.php"); exit();
    }
    $message = $_SESSION['editor']->message();
}
else if (isset($_POST['copy'])) {
  $_SESSION['editor']->copySelectedSetting($_POST['new_setting']);
  $message = $_SESSION['editor']->message();
}
else if (isset($_POST['edit'])) {
  $setting = $_SESSION['editor']->loadSelectedSetting();
  if ($setting) {
    $_SESSION['setting'] = $setting;
    header("Location: " . "image_format.php"); exit();
  }
  $message = $_SESSION['editor']->message();
}
else if (isset($_POST['make_default'])) {
  $_SESSION['editor']->makeSelectedSettingDefault();
  $message = $_SESSION['editor']->message();
}
else if ( isset($_POST['annihilate']) &&
    strcmp( $_POST['annihilate'], "yes") == 0 ) {
        $_SESSION['editor']->deleteSelectedSetting();
        $message = $_SESSION['editor']->message();
}
else if (isset($_POST['OK']) && $_POST['OK']=="OK" ) {

  if (!isset($_POST['setting'])) {
    $message = "Please select some image parameters";
  } else {
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
            $message = "Please verify selected setting, as some PSF " .
              "files appear to be missing";
            $ok = False;
            break;
          }
        }    
      } else {
        $message = "Source image folder not found! Make sure path " .
          $_SESSION['fileserver']->sourceFolder()." exists";
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
    <span id="ttSpanCreate">
        Create a new parameter set with the specified name.
    </span>
    <span id="ttSpanEdit">
        Edit the selected parameter set.
    </span>
    <span id="ttSpanClone">
        Copy the selected parameter set to a new one with the
      specified name.</span>
    <span id="ttSpanDelete">
        Delete the selected parameter set.
    </span>
    <?php
      if (!$_SESSION['user']->isAdmin()) {
        ?>
        <span id="ttSpanDefault">
            Sets (or resets) the selected parameter set as the default one
            .</span>
        <span id="ttSpanCopyTemplate">Copy a template.
        </span>
        <span id="ttSpanBack">
            Go back to step 1/4 - Select images.
        </span>
        <span id="ttSpanForward">
            Continue to step 3/4 - Restoration parameters.
        </span>
    <?php
      }
    ?>

    <div id="nav">
        <ul>
            <li>
                <img src="images/user.png" alt="user" />
                &nbsp;<?php echo $_SESSION['user']->name(); ?>
            </li>
            <?php
            if ( !$_SESSION['user']->isAdmin()) {
            ?>
            <li><a href="file_manager.php">
                    <img src="images/filemanager_small.png" alt="file manager" />
                    &nbsp;File manager
                </a>
            </li>
            <?php
            }
            ?>
            <li>
                <a href="<?php echo getThisPageName();?>?home=home">
                    <img src="images/home.png" alt="home" />
                    &nbsp;Home
                </a>
            </li>
            <li>
                <a href="javascript:openWindow(
                   'http://www.svi.nl/HuygensRemoteManagerHelpSelectParameterSettings')">
                    <img src="images/help.png" alt="help" />
                    &nbsp;Help
                </a>
            </li>
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
        <h3>Step 2/4 - Image parameters</h3>
<?php

}

// display public settings
if (!$_SESSION['user']->isAdmin()) {

?>
        <form method="post" action="">
        
            <fieldset>
                <legend>Template image parameters</legend>
                <p class="message_small">
                    These are the parameter sets prepared by your administrator.
                </p>
                <div id="templates">
<?php

  $settings = $admin_editor->settings();
  $flag = "";
  if (sizeof($settings) == 0) {
      $flag = " disabled=\"disabled\"";
  }

?>
                    <select name="public_setting"
                            size="5"<?php echo $flag ?>>
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
                <input name="copy_public" 
                       type="submit"
                       value=""
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
              echo "<p class=\"message_small\">Create template parameter " .
                "sets visible to all users.</p>";
            } else {
              echo "<legend>Your image parameters</legend>";
              echo "<p class=\"message_small\">These are your (private) " .
                "parameter sets.</p>";
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
                    <select name="setting"
                            size="<?php echo $size ?>"<?php echo $flag ?>>
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
                <input name="create"
                       type="submit"
                       value=""
                       class="icon create"
                       onmouseover="TagToTip('ttSpanCreate' )"
                       onmouseout="UnTip()" />
                <input name="edit"
                       type="submit"
                       value=""
                       class="icon edit"
                       onmouseover="TagToTip('ttSpanEdit' )"
                       onmouseout="UnTip()" />
                <input name="copy" type="submit" 
                       value=""
                       class="icon clone"
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
                <input name="delete"
                       type="submit"
                       value=""
                       class="icon delete"
                       onclick="warn(this.form,
                        'Do you really want to delete this parameter set?',
                        this.form['setting'].selectedIndex )"
                       onmouseover="TagToTip('ttSpanDelete' )"
                       onmouseout="UnTip()" />
                <label>New/clone parameter set name:
                    <input name="new_setting"
                           type="text"
                           class="textfield" />
                </label>
                <input name="OK" type="hidden" />
        </div>                
<?php

if (!$_SESSION['user']->isAdmin()) {

?>
                <div id="controls">      
                  <input type="submit"
                         value=""
                         class="icon empty"
                         disabled="disabled" />
                  <input type="button"
                         value=""
                         class="icon previous"
                         onclick="document.location.href='select_images.php'"
                        onmouseover="TagToTip('ttSpanBack' )"
                        onmouseout="UnTip()" />
                  <input type="submit"
                         value=""
                         class="icon next"
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

          <p><strong>Placing the mouse pointer over the various icons will 
          display a tooltip with explanations.</strong></p>
    
          <p><strong>For a more detailed explanation on the possible actions, 
            please follow the
            <img src="images/help.png" alt="Help" width="22" height="22" />
            <b>Help</b> link in the navigation bar.</strong></p>

          <p />
<?php

// add user management
if (!$_SESSION['user']->isAdmin()) {

?>

<?php

}

	if (!$_SESSION['user']->isAdmin()) {
      echo "<p>In the first step, you are asked to specify all parameters
          relative to the images you want to restore.</p>";
	} else {
	  echo "<p>Here, you can create template parameters relative to the images
      to restore.</p>";
	}
	?>
      <p>These include: file information (geometry, voxel size);
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

echo "<p>$message</p>";


?>
        </div>
        
    </div> <!-- rightpanel -->
    
<?php

include("footer.inc.php");

?>

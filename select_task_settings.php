<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Parameter.inc.php");
require_once("./inc/Setting.inc.php");
require_once("./inc/SettingEditor.inc.php");
require_once("./inc/System.inc.php");
require_once("./inc/wiki_help.inc.php");

global $enableUserAdmin;

/* *****************************************************************************
 *
 * START SESSION, CHECK LOGIN STATE, INITIALIZE WHAT NEEDED
 *
 **************************************************************************** */

session_start();

if (isset($_GET['home'])) {
  header("Location: " . "home.php"); exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

if (!isset($_SESSION['taskeditor'])) {
  $_SESSION['taskeditor'] = new TaskSettingEditor($_SESSION['user']);
}

if (System::hasLicense("coloc")) {
    $numberSteps   = 5;
    $goNextMessage = " - Analysis parameters.";
} else {
    $numberSteps = 4;
    $goNextMessage = " - Create job.";
}

$currentStep  = 3;
$previousStep = $currentStep - 1;
$nextStep     = $currentStep + 1;

$goBackMessage  = " - Image parameters.";
$goBackMessage  = "Go back to step $previousStep/$numberSteps" . $goBackMessage;

$goNextMessage  = "Continue to step $nextStep/$numberSteps" . $goNextMessage;

// add public setting support
if (!$_SESSION['user']->isAdmin()) {
  $admin = new User();
  $admin->setName( "admin" );
  $admin_editor = new TaskSettingEditor($admin);
  $_SESSION['admin_taskeditor'] = $admin_editor;
}

$message = "";

/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */

if (isset($_POST['task_setting'])) {
  $_SESSION['taskeditor']->setSelected($_POST['task_setting']);
}

if (isset($_POST['copy_public'])) {
  if (isset($_POST["public_setting"])) {
    if (!$_SESSION['taskeditor']->copyPublicSetting(
        $admin_editor->setting($_POST['public_setting']))) {
      $message = $_SESSION['editor']->message();
    }
  }
  else $message = "Please select a setting to copy";
}
else if (isset($_POST['create'])) {
  $task_setting = $_SESSION['taskeditor']->createNewSetting(
    $_POST['new_setting']);
  if ($task_setting != NULL) {
    $_SESSION['task_setting'] = $task_setting;
    header("Location: " . "task_parameter.php"); exit();
  }
  $message = $_SESSION['taskeditor']->message();
}
else if (isset($_POST['copy'])) {
  $_SESSION['taskeditor']->copySelectedSetting($_POST['new_setting']);
  $message = $_SESSION['taskeditor']->message();
}
else if (isset($_POST['edit'])) {
  $task_setting = $_SESSION['taskeditor']->loadSelectedSetting();
  if ($task_setting) {
    $_SESSION['task_setting'] = $task_setting;
    header("Location: " . "task_parameter.php"); exit();
  }
  $message = $_SESSION['taskeditor']->message();
}
else if (isset($_POST['make_default'])) {
  $_SESSION['taskeditor']->makeSelectedSettingDefault();
  $message = $_SESSION['taskeditor']->message();
}
else if ( isset($_POST['annihilate']) &&
    strcmp( $_POST['annihilate'], "yes") == 0 ) {
        $_SESSION['taskeditor']->deleteSelectedSetting();
        $message = $_SESSION['taskeditor']->message();
}
else if (isset($_POST['OK']) && $_POST['OK']=="OK" ) {
  if (!isset($_POST['task_setting'])) {
    $message = "Please select some restoration parameters";
  }
  else {
    $_SESSION['task_setting'] =
        $_SESSION['taskeditor']->loadSelectedSetting();
    $_SESSION['task_setting']->setNumberOfChannels(
        $_SESSION['setting']->numberOfChannels());
    
    /*
      Here we just check that the Parameters that have a variable of values per
      channel have all their values set properly.
    */
    $ok = True;
    $ok = $ok && $_SESSION['task_setting']->parameter(
        'SignalNoiseRatio' )->check();
    $ok = $ok && $_SESSION['task_setting']->parameter(
        'BackgroundOffsetPercent' )->check();

    // If there's no coloc license the analysis stage is skipped. A default
    // (switched-off coloc) analysis setting will be created.
    if ($ok) {
        if (System::hasLicense("coloc")) {
            header("Location: " . "select_analysis_settings.php"); exit();
        } else {
            $_SESSION['analysis_setting'] = new AnalysisSetting();            
            header("Location: " . "create_job.php"); exit();
        }
    }
    $message = "The number of channels in the selected restoration " .
      "parameters does not match the number of channels in the image " .
      "parameters. Please fix this!";
  }
}

$script = array( "settings.js", "common.js", "ajax_utils.js" );

include("header.inc.php");

?>
    <!--
      Tooltips
    -->
    <span class="toolTip" id="ttSpanCreate">
        Create a new parameter set with the specified name.
    </span>
    <span class="toolTip" id="ttSpanEdit">
        Edit the selected parameter set.
    </span>
    <span class="toolTip" id="ttSpanClone">
        Copy the selected parameter set to a new one with the
      specified name.
    </span>
    <span class="toolTip" id="ttSpanDelete">
        Delete the selected parameter set.
    </span>
    <?php
      if (!$_SESSION['user']->isAdmin()) {
        ?>
        <span class="toolTip" id="ttSpanDefault">
            Sets (or resets) the selected parameter set as the default one.
        </span>
        <span class="toolTip" id="ttSpanCopyTemplate">
            Copy a template.
        </span>
        <span class="toolTip" id="ttSpanBack">
        <?php echo $goBackMessage; ?>
        </span>
        <span class="toolTip" id="ttSpanForward">
        <?php echo $goNextMessage; ?>
        </span>
    <?php
      }
    ?>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
                wiki_link('HuygensRemoteManagerHelpSelectTaskSettings');
            ?>
        </ul>
    </div>
    <div id="navright">
        <ul>
            <?php
                include("./inc/nav/user.inc.php");
                include("./inc/nav/raw_images.inc.php");
                include("./inc/nav/home.inc.php");
            ?>
        </ul>
    </div>
    <div class="clear"></div>
</div>

    
    <div id="content">
    
<?php

if ($_SESSION['user']->isAdmin()) {

?>
        <h3>Restoration parameters</h3>
<?php

}
else {

?>
        <h3><img alt="Restoration" src="./images/restoration.png"
        width="40"/>&nbsp;&nbsp;Step
        <?php echo $currentStep . "/" . $numberSteps; ?>
         - Restoration parameters</h3>
<?php

}

// display public settings
if (!$_SESSION['user']->isAdmin()) {

?>
        <form method="post" action="">
        
            <fieldset>
              <legend>Template restoration parameters</legend>
              <p class="message_small">
                  These are the parameter sets prepared by your administrator.
              </p>
              <div id="templates">
<?php

  $settings = $admin_editor->settings();
  $flag = "";
  if (sizeof($settings) == 0) $flag = " disabled=\"disabled\"";

?>
<select name="public_setting"
     onclick="ajaxGetParameterListForSet('task_setting', $(this).val(), true);"
     onchange="ajaxGetParameterListForSet('task_setting', $(this).val(), true);"
     size="5"<?php echo $flag ?>>
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
                <input name="copy_public"
                       type="submit"
                       value=""
                       class="icon down"
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
                  echo "<legend>Template restoration parameters</legend>";
                  echo "<p class=\"message_small\">Create template parameter " .
                    "sets visible to all users.</p>";
                } else {
                  echo "<legend>Your restoration parameters</legend>";
                  echo "<p class=\"message_small\">These are your (private) " .
                    "parameter sets.</p>";
                }
              ?>
              <div id="settings">
<?php

$settings = $_SESSION['taskeditor']->settings();
$size = "8";
if ($_SESSION['user']->isAdmin()) $size = "12";
$flag = "";
if (sizeof($settings) == 0) $flag = " disabled=\"disabled\"";

?>
<select name="task_setting"
    onclick="ajaxGetParameterListForSet('task_setting', $(this).val(), false);"
    onchange="ajaxGetParameterListForSet('task_setting', $(this).val(), false);"
    size="<?php echo $size ?>"
                        <?php echo $flag ?>>
<?php

if (sizeof($settings) == 0) {
  echo "                        <option>&nbsp;</option>\n";
}
else {
  foreach ($settings as $set) {
    echo "                        <option";
    if ($set->isDefault()) {
      echo " class=\"default\"";
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
            
            <div id="actions"
                 class="taskselection">
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
                <input name="copy"
                       type="submit"
                       value=""
                       class="icon clone"
                       onmouseover="TagToTip('ttSpanClone' )"
                       onmouseout="UnTip()" />
<?php

if (!$_SESSION['user']->isAdmin()) {

?>
                <input name="make_default" 
                       type="submit"
                       value=""
                       class="icon mark"
                       onmouseover="TagToTip('ttSpanDefault' )"
                       onmouseout="UnTip()" />
<?php

}

?>
                <input type="hidden" name="annihilate" />
                <input name="delete"
                       type="button"
                       value=""
                       class="icon delete"
                       onclick="warn(this.form,
                         'Do you really want to delete this parameter set?',
                         this.form['task_setting'].selectedIndex )"
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
                  <input type="button"
                         value=""
                         class="icon previous"
                         onclick="document.location.href='select_parameter_settings.php'"
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
    
    <?php    
	if (!$_SESSION['user']->isAdmin()) {
      echo "<p>In this step, you are asked to specify all parameters relative
        to the restoration of your images.</p>";
	} else {
	  echo "<p>Here, you can create template parameters relative to the
      restoration procedure.</p>";
	}
	?>
        <p>These are the choice of the deconvolution algorithm and its options
        (signal-to-noise ratio, background estimation mode and stopping 
        criteria).</p>

    <?php        
	if (!$_SESSION['user']->isAdmin()) {
      echo "<p>'Template restoration parameters' created by your facility
        manager can be copied to the list of 'Your restoration parameters' and
        adapted to fit your restoration needs.</p>";
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

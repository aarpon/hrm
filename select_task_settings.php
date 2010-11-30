<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc");
require_once("./inc/Parameter.inc");
require_once("./inc/Setting.inc");
require_once("./inc/SettingEditor.inc");
require_once("./inc/Util.inc");

global $enableUserAdmin;

session_start();

if (isset($_GET['home'])) {
  header("Location: " . "home.php"); exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

if (!isset($_SESSION['taskeditor'])) {
  # session_register('taskeditor');
  $_SESSION['taskeditor'] = new TaskSettingEditor($_SESSION['user']);
}

if (isset($_SESSION['task_setting'])) {
  # session_register("task_setting");
}

// add public setting support
if ($_SESSION['user']->name() != "admin") {
  $admin = new User();
  $admin->setName( "admin" );
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
    $message = "            <p class=\"warning\">Please select some restoration parameters.</p>\n";
  }
  else {
    $_SESSION['task_setting'] = $_SESSION['taskeditor']->loadSelectedSetting();
    $_SESSION['task_setting']->setNumberOfChannels($_SESSION['setting']->numberOfChannels());
    $ok = $_SESSION['task_setting']->checkParameter();
    if ($ok) {
      header("Location: " . "select_images.php"); exit();
    }
    //$message = "            <p class=\"warning\">Values for some channels are missing, please edit the selected task setting</p>\n";
    $message = "            <p class=\"warning\">".$_SESSION['task_setting']->message()."</p>\n";
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
      if ($_SESSION['user']->name() != "admin") {
        ?>
        <span id="ttSpanDefault">Sets the selected setting as the default one.</span>
        <span id="ttSpanCopyTemplate">Copy a template.</span>
        <span id="ttSpanBack">Go back to step 1/4 - Image parameters.</span>
        <span id="ttSpanForward">Continue to step 3/4 - Select images.</span>  
    <?php
      }
    ?>

    <div id="nav">
        <ul>
            <li><?php echo $_SESSION['user']->name(); ?></li>
            <li><a href="<?php echo getThisPageName();?>?home=home"><img src="images/home.png" alt="home" />&nbsp;Home</a></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpSelectTaskSettings')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
<?php

if ($_SESSION['user']->name() == "admin") {

?>
        <h3>Restoration parameters</h3>
<?php

}
else {

?>
        <h3>Step 2/4 - Restoration parameters</h3>
<?php

}

// display public settings
if ($_SESSION['user']->name() != "admin") {

?>
        <form method="post" action="">
        
            <fieldset>
              <legend>Template restoration parameters</legend>
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
                <input name="copy_public" type="submit" value=""
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
                if ($_SESSION['user']->name() == "admin") {
                  echo "<legend>Template restoration parameters</legend>";
                } else {
                  echo "<legend>Your restoration parameters</legend>";
                }
              ?>
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
                <label>New/clone setting name: <input name="new_setting" type="text" class="textfield" /></label>
                <input name="OK" type="hidden" />
                
            </div>
<?php

if ($_SESSION['user']->name() != "admin") {

?>
                <div id="controls">      
                  <input type="button" value="" class="icon previous"
                    onclick="document.location.href='select_parameter_settings.php'"
                    onmouseover="TagToTip('ttSpanBack' )"
                    onmouseout="UnTip()" />
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
    
    <?php    
	if ($_SESSION['user']->name() != "admin") {
      echo "<p>In this step, you are asked to specify all parameters relative
        to the restoration of your images.</p>";
	} else {
	  echo "<p>Here, you can create template parameters relative to the
      restoration procedure.</p>";
	}
	?>
      <p>These are the choice of the deconvolution algorithm, the signal-to-noise
      ratio, the background estimation mode and the stopping criteria.</p>

    <?php        
	if ($_SESSION['user']->name() != "admin") {
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

echo $message;

?>
        </div>
        
    </div> <!-- rightpanel -->
    
<?php

include("footer.inc.php");

?>

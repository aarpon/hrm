<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Nav;
use hrm\setting\AnalysisSetting;
use hrm\setting\TaskSettingEditor;
use hrm\System;
use hrm\setting\TaskSetting;
use hrm\user\UserV2;
use hrm\Util;

require_once dirname(__FILE__) . '/inc/bootstrap.php';


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
    $goNextMessage = " - Select analysis template.";
} else {
    $numberSteps = 4;
    $goNextMessage = " - Create job.";
}

$currentStep  = 3;
$previousStep = $currentStep - 1;
$nextStep     = $currentStep + 1;

$goBackMessage  = " - Select image template.";
$goBackMessage  = "Go back to step $previousStep/$numberSteps" . $goBackMessage;

$goNextMessage  = "Continue to step $nextStep/$numberSteps" . $goNextMessage;

// add public setting support
if (!$_SESSION['user']->isAdmin()) {
  $admin = new UserV2();
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
else if (!empty($_POST['new_setting_create'])) {
  $task_setting = $_SESSION['taskeditor']->createNewSetting(
    $_POST['new_setting_create']);
  if ($task_setting != NULL) {
    $_SESSION['task_setting'] = $task_setting;
    header("Location: " . "task_parameter.php"); exit();
  }
  $message = $_SESSION['taskeditor']->message();
}
else if (!empty($_POST['new_setting_copy'])) {
  $_SESSION['taskeditor']->copySelectedSetting($_POST['new_setting_copy']);
  $message = $_SESSION['taskeditor']->message();
}
else if (isset($_POST['huTotemplate'])) {
    $task_setting = NULL;
    $file = $_FILES["upfile"]["name"];
    $fileName = pathinfo($file[0], PATHINFO_BASENAME);
    $extension = pathinfo($file[0], PATHINFO_EXTENSION);

    if ($extension == "hgsd") {
        if($fileName != '') {
            $hrmTemplateName = 'From ' . $fileName;
            $task_setting =
                $_SESSION['taskeditor']->createNewSetting($hrmTemplateName);

            $tmpName = $_FILES["upfile"]["tmp_name"];
            // @todo This should react appropriately to the return status of huTemplate2hrmTemplate()
            $_SESSION['taskeditor']->huTemplate2hrmTemplate($task_setting,
                                                            $tmpName[0]);
            $message = $_SESSION['taskeditor']->message();
        } else {
            $message = "Please upload a valid Huygens deconvolution template " .
                "(extension .hgsd)";
        }

        if ($task_setting != NULL) {
            $_SESSION['task_setting'] = $task_setting;
            header("Location: " . "task_parameter.php"); exit();
        }
    } else {
        $message = "Please upload a valid Huygens deconvolution template " .
            "(extension .hgsd)";
    }
}
else if (isset($_POST['edit'])) {
  $task_setting = $_SESSION['taskeditor']->loadSelectedSetting();
  if ($task_setting) {
    $_SESSION['task_setting'] = $task_setting;
    header("Location: " . "task_parameter.php"); exit();
  }
  $message = $_SESSION['taskeditor']->message();
}
else if (isset($_POST['pickUser']) && isset($_POST["templateToShare"])) {
    if (isset($_POST["usernameselect"])) {
        $_SESSION['taskeditor']->shareSelectedSetting($_POST["templateToShare"],
            $_POST["usernameselect"]);
        $message = $_SESSION['taskeditor']->message();
    } else {
        $message = "Please pick one or more recipients.";
    }
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
    $message = "Please select a template from \"Your restoration templates\".";
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
    $ok = $ok && ($_SESSION['task_setting']->parameter(
        'Acuity' )->check() || $_SESSION['task_setting']->parameter(
        'AcuityMode' )->value() == 'off');
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

$script = array( "settings.js", "common.js",
                 "json-rpc-client.js", "shared.js", "ajax_utils.js" );

include("header.inc.php");

?>
<!--
  Tooltips
-->
<span class="toolTip" id="ttSpanCreate">
        Create a new restoration<br />template with the specified name.
</span>
<span class="toolTip" id="ttSpanHuygens">
        Import a Huygens restoration template (extension "hgsd").
</span>
<span class="toolTip" id="ttSpanEdit">
        Edit the selected restoration template.
</span>
<span class="toolTip" id="ttSpanClone">
        Copy the selected restoration template to a new one with the
      specified name.
</span>
<span class="toolTip" id="ttSpanShare">
        Share the selected restoration template with one or more HRM users.
</span>
<span class="toolTip" id="ttSpanDelete">
        Delete the selected restoration template.
</span>
<span class="toolTip" id="ttSpanAcceptTemplate">
        Accept the template.
</span>
<span class="toolTip" id="ttSpanRejectTemplate">
        Reject the template.
</span>
<span class="toolTip" id="ttSpanPreviewTemplate">
        Preview the template.
</span>
    <?php
      if (!$_SESSION['user']->isAdmin()) {
        ?>
        <span class="toolTip" id="ttSpanDefault">
            Sets (or resets) the selected restoration template as the default one.
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
                echo(Nav::linkWikiPage('HuygensRemoteManagerHelpSelectTaskSettings'));

            if ( ! $_SESSION["user"]->isAdmin()) {
            ?>

                <li>
                    <img src="images/share_small.png" alt="shared_templates" />&nbsp;
                    <!-- This is where the template sharing notification is shown -->
                    <span id="templateSharingNotifier">&nbsp;</span>
                </li>

            <?php
            }
            ?>
        </ul>
    </div>
    <div id="navright">
        <ul>
            <?php
                echo(Nav::textUser($_SESSION['user']->name()));
                if ( !$_SESSION['user']->isAdmin()) {
                    echo(Nav::linkRawImages());
                }
                echo(Nav::linkHome(Util::getThisPageName()));
            ?>
        </ul>
    </div>
    <div class="clear"></div>
</div>


    <div id="content">

    <!-- This is where the shared templates are shown with action buttons
    to accept, reject, and preview them. -->

    <div id="sharedTemplatePicker">
        <div id="shareTemplatePickerHeader">
            <p>These are your shared templates:</p>
            <div id="shareTemplatePickerHeaderClose" title="Close"
                 onclick="closeSharedTemplatesDiv();">
                X
            </div>
        </div>
        <div id="shareTemplatePickerBody">
            <p class="tableTitle">Templates shared <b>with</b> you:</p>
            <table id="sharedWithTemplatePickerTable">
                <tbody>
                </tbody>
            </table>
            <p class="tableTitle">Templates shared <b>by</b> you:</p>
            <table id="sharedByTemplatePickerTable">
                <tbody>
                </tbody>
            </table>
        </div>
        <div id="shareTemplatePickerFooter">
            <p>Mouse over template names for more information.</p>
        </div>
    </div>

<?php

if ($_SESSION['user']->isAdmin()) {

?>
        <h3><img alt="Restoration" src="./images/restoration.png"
                 width="40"/>&nbsp;&nbsp;Create restoration template</h3>
<?php

}
else {

?>
        <h3><img alt="Restoration" src="./images/restoration.png"
        width="40"/>&nbsp;&nbsp;Step
        <?php echo $currentStep . "/" . $numberSteps; ?>
         - Select restoration template</h3>
<?php

}

// display public settings
if (!$_SESSION['user']->isAdmin()) {

?>
        <form id="formTemplateTypeParameters" method="post" action="">

            <fieldset>
              <legend>Admin restoration templates</legend>
              <p class="message_small">
                  These are the restoration templates prepared by your administrator.
              </p>
              <div id="templates">
<?php

  $settings = $admin_editor->settings();
  $flag = "";
  if (sizeof($settings) == 0) {
      $flag = " disabled=\"disabled\"";
  }

?>
<select name="public_setting" title="Admin templates"
     class="selection"
     onclick="ajaxGetParameterListForSet('task_setting', $(this).val(), true);"
     onchange="ajaxGetParameterListForSet('task_setting', $(this).val(), true);"
     size="5"<?php echo $flag ?>>
<?php

  if (sizeof($settings) == 0) {
    echo "                        <option>&nbsp;</option>\n";
  }
  else {
      /** @var TaskSetting $set */
      foreach ($settings as $set) {
          /** @var TaskSetting $set */
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

        <form method="post" action="" enctype="multipart/form-data" id="select">

            <fieldset>

              <?php
                if ($_SESSION['user']->isAdmin()) {
                  echo "<legend>Admin template restoration templates</legend>";
                  echo "<p class=\"message_small\">Create restoration templates " .
                    "visible to all users.</p>";
                } else {
                  echo "<legend>Your restoration templates</legend>";
                  echo "<p class=\"message_small\">These are your (private) " .
                    "restoration templates.</p>";
                }
              ?>
              <div id="settings">
<?php

/** @var TaskSetting $settings */
$settings = $_SESSION['taskeditor']->settings();
$size = "8";
if ($_SESSION['user']->isAdmin()) $size = "12";
$flag = "";
if (sizeof($settings) == 0) $flag = " disabled=\"disabled\"";

?>
<select name="task_setting" id="setting" title="Your templates"
    class="selection"
    onclick="ajaxGetParameterListForSet('task_setting', $(this).val(), false);"
    onchange="ajaxGetParameterListForSet('task_setting', $(this).val(), false);"
    size="<?php echo $size ?>"
                        <?php echo $flag ?>>
<?php

if (sizeof($settings) == 0) {
  echo "                        <option>&nbsp;</option>\n";
}
else {
    /** @var TaskSetting $set */
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
            <div id="upMsg"></div>
            <div id="actions"
                 class="taskselection">
                 <label id="actions">
                    Template actions:
                 </label>
                 <table id="actions">
                  <tr>
                    <td class="button">
                      <input name="create"
                       type="button"
                       value=""
                       class="icon create"
                       onmouseover="TagToTip('ttSpanCreate' )"
                       onmouseout="UnTip()"
                       onclick="hide('copyTemplateDiv'); changeVisibility('newTemplateDiv')"/> 
                    </td>
                    <td class="button">
                      <input name="edit"
                       type="submit"
                       value=""
                       class="icon edit"
                       onmouseover="TagToTip('ttSpanEdit' )"
                       onmouseout="UnTip()" />
                    </td>
                    <td class="button">
                      <input name="copy"
                       type="button"
                       value=""
                       class="icon clone"
                       onmouseover="TagToTip('ttSpanClone' )"
                       onmouseout="UnTip()"
                       onclick="hide('newTemplateDiv'); changeVisibility('copyTemplateDiv')"/>
                    </td>
                    <td class="button">
                      <input name="huTotemplate"
                       type="button"
                       value=""
                       class="icon huygens"
                       onmouseover="TagToTip('ttSpanHuygens' )"
                       onmouseout="UnTip()"
                       onclick="UnTip(); hu2template('decon')" />
                    </td>
<?php

if (!$_SESSION['user']->isAdmin()) {

?>
                    <td class="button">
                      <input name="share"
                       type="button"
                       onclick="prepareUserSelectionForSharing('<?php echo $_SESSION['user']->name() ?>');"
                       value=""
                       class="icon share"
                       onmouseover="TagToTip('ttSpanShare' )"
                       onmouseout="UnTip()" />
                    </td>
                    <td class="button">
                      <input name="make_default"
                       type="submit"
                       value=""
                       class="icon mark"
                       onmouseover="TagToTip('ttSpanDefault' )"
                       onmouseout="UnTip()" />
                    </td>
<?php

}

?>
                    <td class="button">
                      <input type="hidden" name="annihilate" />
                      <input name="delete"
                       type="button"
                       value=""
                       class="icon delete"
                       onclick="warn(this.form,
                         'Do you really want to delete this restoration template?',
                         this.form['task_setting'].selectedIndex )"
                       onmouseover="TagToTip('ttSpanDelete' )"
                       onmouseout="UnTip()" />
                    </td>
                  </tr>
                  <tr>
                    <td class="label">
                     New
                    </td>
                    <td class="label">
                     Edit
                    </td>
                    <td class="label">
                     Duplicate
                    </td>
                    <td class="label">
                     Import<br />Huygens<br />template
                    </td>
<?php
if (!$_SESSION['user']->isAdmin()) {
?>       
                    <td class="label">
                     Share
                    </td>
                    <td class="label">
                     Mark as<br />favorite
                    </td>
<?php
}
?>      
                    <td class="label">
                     Remove
                    </td>
                  </tr>
                </table>                
                <input name="OK" type="hidden" />
            </div>

       <div id="newTemplateDiv">
           <label>Enter a name for the new template:
              <input name="new_setting_create"
                     type="text"
                     size="20"
                     class="textfield_20"/>
              <input name="input_submit"
                     type="submit"
                     value="Create"
                     class="submit_btn"/>
           </label>
        </div>
        <div id="copyTemplateDiv">
           <label>Enter a name for the new template:
              <input name="new_setting_copy"
                     type="text"
                     size="20"
                     class="textfield_20"/>
              <input name="input_submit"
                     type="submit"
                     value="Create"
                     class="submit_btn"/>
           </label>
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

    <!-- Form for picking users with whom to share templates, initially hidden -->
    <form id="formUserList" method="post" action="" hidden>

        <fieldset>
            <legend>Users you may share with</legend>
            <p class="message_small">
                This is the list of users you may share your template with.
            </p>
            <div id="users">

                <select id="usernameselect"
                        name="usernameselect[]"
                        class="selection"
                        size="5"
                        multiple="multiple"
                        title="Users">
                    <option>&nbsp;</option>
                </select>
            </div>
        </fieldset>

        <!-- Hidden input where to store the selected template -->
        <input hidden id="templateToShare" name="templateToShare" value=""
               title="Template to share">

        <div id="actions" class="userSelection">

            <input name="cancelUser"
                   type="submit"
                   value=""
                   class="icon cancel"
                   onmouseover="TagToTip('' )"
                   onmouseout="UnTip()" />

            <input name="pickUser"
                   type="submit"
                   value=""
                   class="icon apply"
                   onmouseover="TagToTip('' )"
                   onmouseout="UnTip()" />


        </div>

    </form> <!-- Form for picking users with whom to share templates -->

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
      echo "<p>'Admin restoration templates' created by your facility
        manager can be copied to the list of 'Your restoration templates' and
        adapted to fit your restoration needs.</p>";
	} else {
	  echo "<p>The created templates will be visible for the users in an
      additional selection field from which they can be copied to the user's
      templates.</p>";
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

<script type="text/javascript">

    // Prepare list of templates for sharing
    $(document).ready(function() {

        // Get the user name from the session
        var username;
        username = <?php echo("'" . $_SESSION['user']->name() . "'");?>;

        // Check that we have a user name
        if (null === username) {
            return;
        }

        // No templates can be shared with the admin
        <?php
        if ($_SESSION['user']->isAdmin()) {
            echo("var isAdmin = true;" . PHP_EOL);
        } else {
            echo("var isAdmin = false;" . PHP_EOL);
        }
        ?>
        if (isAdmin == true) {
            return;
        }

        // Retrieve the templates shared with current user
        retrieveSharedTemplates(username, 'task');


    });


</script>

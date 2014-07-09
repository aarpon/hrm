<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Parameter.inc.php");
require_once("./inc/Setting.inc.php");
require_once("./inc/SettingEditor.inc.php");
require_once("./inc/Fileserver.inc.php");
require_once("./inc/System.inc.php");
require_once("./inc/wiki_help.inc.php");

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

// Settings by the admin can be used with any file format, no specific confidence
// levels. Thus, we set the lowest confidence levels, which corresponds to the
// tiff format to force the admin to enter all the parameters.
if ($_SESSION['user']->isAdmin()) {
    $_SESSION[ 'parametersetting' ] = new ParameterSetting();
    $_SESSION[ 'parametersetting' ]->parameter("ImageFileFormat")->setValue("hdf5");
}

// add public setting support
if (!$_SESSION['user']->isAdmin()) {
  $admin = new User();
  $admin->setName("admin");
  $admin_editor = new SettingEditor($admin);
  $_SESSION['admin_editor'] = $admin_editor;
}

if (System::hasLicense("coloc")) {
    $numberSteps   = 5;
} else {
    $numberSteps = 4;
}

$currentStep  = 2;
$previousStep = $currentStep - 1;
$nextStep     = $currentStep + 1;

$goBackMessage  = " - Select images.";
$goBackMessage  = "Go back to step $previousStep/$numberSteps" . $goBackMessage;

$goNextMessage  = " - Restoration parameters.";
$goNextMessage  = "Continue to step $nextStep/$numberSteps" . $goNextMessage;

// fileserver related code (for measured PSF files check)
if (!isset($_SESSION['fileserver'])) {
  $name = $_SESSION['user']->name();
  $_SESSION['fileserver'] = new Fileserver($name);
}

$message = "";

if (isset($_POST['setting'])) {
  $_SESSION['editor']->setSelected($_POST['setting']);
}

// Except for the admin, the file format is selected at 'select_images'.
$fileFormat =
    $_SESSION['parametersetting']->parameter("ImageFileFormat")->value();

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
    $setting = $_SESSION['editor']->createNewSetting($_POST['new_setting']);

    if ($setting != NULL) {
        $setting->parameter("ImageFileFormat")->setValue($fileFormat);
        $_SESSION['setting'] = $setting;
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
      $setting->parameter("ImageFileFormat")->setValue($fileFormat);
      $_SESSION['setting'] = $setting;
      header("Location: " . "image_format.php"); exit();
  }
  $message = $_SESSION['editor']->message();
}
else if (isset($_POST['pickUser']) &&
        isset($_POST["usernameselect"]) &&
        isset($_POST["templateToShare"])) {
    $_SESSION['editor']->shareSelectedSetting($_POST["templateToShare"],
        $_POST["usernameselect"]);
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
    $_SESSION['setting']->parameter("ImageFileFormat")->setValue($fileFormat);

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

    if ( !$_SESSION['setting']->checkParameterSetting( ) ) {
        $message = $_SESSION['setting']->message();
        $ok = False;
    }

    if ($ok) {header("Location: " . "select_task_settings.php"); exit();}
  }
}

$script = array( "settings.js", "common.js", "json-rpc-client.js", "ajax_utils.js" );

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
      specified name.</span>
    <span class="toolTip" id="ttSpanShare">
        Share the selected parameter set with one or more HRM users.</span>
    <span class="toolTip" id="ttSpanDelete">
        Delete the selected parameter set.
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
            Sets (or resets) the selected parameter set as the default one
            .</span>
        <span class="toolTip" id="ttSpanCopyTemplate">Copy a template.
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
                wiki_link('HuygensRemoteManagerHelpSelectParameterSettings');
            ?>
            <li>
                <img src="images/share_small.png" alt="shared_templates" />&nbsp;
                <!-- This is where the template sharing notification is shown -->
                <span id="templateSharingNotifier">&nbsp;</span>
            </li>
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

    <!-- This is where the shared templates are shown with action buttons
    to accept, reject, and preview them. -->

    <div id="sharedTemplatePicker">
        <div id="shareTemplatePickerHeader">
            <p>These are the templates shared with you:</p>
        </div>
        <div id="shareTemplatePickerBody">
            <table id="sharedTemplatePickerTable">
                <tbody>
                </tbody>
            </table>
        </div>
        <div id="shareTemplatePickerFooter">
            <p>Mouseover template names for more information.</p>
        </div>
    </div>

    <?php

if ($_SESSION['user']->isAdmin()) {

?>
        <h3>Image parameters</h3>
<?php

}
else {

?>
        <h3><img alt="ImageParameters" src="./images/image_parameters.png"
        width="40"/>&nbsp;&nbsp;Step
        <?php echo $currentStep . "/" . $numberSteps; ?>
        - Image parameters</h3>
<?php

}

// display public settings
if (!$_SESSION['user']->isAdmin()) {

?>
        <form id="formTemplateImageParameters" method="post" action="">

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
           onclick="ajaxGetParameterListForSet('setting', $(this).val(), true);"
           onchange="ajaxGetParameterListForSet('setting', $(this).val(), true);"
           size="5"<?php echo $flag ?>>
<?php

  if (sizeof($settings) == 0) {
    echo "<option>&nbsp;</option>\n";
  }
  else {
    foreach ($settings as $setting) {
      echo "<option>".$setting->name()."</option>\n";
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

        <form id="formImageParameters" method="post" action="" id="select">

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
<select name="setting" id="setting"
    onclick="ajaxGetParameterListForSet('setting', $(this).val(), false);"
    onchange="ajaxGetParameterListForSet('setting', $(this).val(), false);"
    size="<?php echo $size ?>"<?php echo $flag ?>>
<?php

if (sizeof($settings) == 0) {
  echo "<option>&nbsp;</option>\n";
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
                <input name="share" type="button"
                       onclick="prepareUserSelectionForSharing('<?php echo $_SESSION['user']->name() ?>');"
                       value=""
                       class="icon share"
                       onmouseover="TagToTip('ttSpanShare' )"
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
                       type="button"
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

    <!-- Form for picking users with whom to share templates, initially hidden -->
    <form id="formUserList" method="post" action="" hidden>

        <fieldset>
            <legend>Users you may share with</legend>
            <p class="message_small">
                This is the list of users you may share your template with.
            </p>
            <div id="users">

                <select id="usernameselect" name="usernameselect[]"
                        size="5" multiple="multiple">
                    <option>&nbsp;</option>
                </select>
            </div>
        </fieldset>

        <!-- Hidden input where to store the selected template -->
        <input hidden id="templateToShare" name="templateToShare" value="">

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
      echo "<p>In this step, you are asked to specify all parameters
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

<script type="text/javascript">

    // Prepare list of templates for sharing
    $(document).ready(function() {

        // Get the user name from the session
        var username = <?php echo("'" . $_SESSION['user']->name() . "'");?>;

        if (null === username) {
            return;
        }

        // Query server for list of shared templates
        JSONRPCRequest({
            method : 'jsonGetSharedTemplateList',
            params: [username]
        }, function(response) {

            // Failure?
            if (response.success != "true") {

                $("#message").append("<p>Problem retrieving list of shared templates!</p>");
                return;

            }

            // Get the templates
            var sharedTemplates = response.sharedTemplates;

            // Sort them by previous owner
            sharedTemplates.sort(sort_by_previous_owner);

            // Get the number of templates
            var numSharedTemplates = sharedTemplates.length;

            // Write the notification
            if (numSharedTemplates == 0) {
                $("#templateSharingNotifier").html("You have no shared templates.");
            } else {
                $("#templateSharingNotifier").html("You have " +
                    "<a href='' onclick='toggleSharedTemplatesDiv(); return false;'><b>" +
                    String(numSharedTemplates) + " shared template" +
                    (numSharedTemplates > 1 ? "s" : "") + "</b></a>!");
            }

            // Now fill in the shared template table
            fillInSharedTemplatesTable(sharedTemplates);

        });
    });

    // Fill in the shared template table
    function fillInSharedTemplatesTable(sharedTemplates) {

        // Remove content from table
        var tbody = $("#sharedTemplatePickerTable tbody");
        tbody.empty();

        // Make sure to clear the template data
        tbody.data("shared_templates", null);

        if (sharedTemplates.length == 0) {
            tbody.append("<tr><td>No templates shared with you.</td>/<tr>");
            return;
        }

        // Store the shared templates
        tbody.data("shared_templates", sharedTemplates);

        // Now fill the table
        var lastUser = null;
        for (var i = 0; i < sharedTemplates.length; i++) {

            // Add 'header' row if needed
            if (sharedTemplates[i].previous_owner != lastUser) {

                // Add "From 'user'" header
                tbody.append("<tr><td colspan='4' class='from_template'>" +
                    "From <b>" + sharedTemplates[i].previous_owner +
                    "</b>:</td></tr>");

                lastUser = sharedTemplates[i].previous_owner;
            }

            // Add template with actions
            var tdAccept = "<td class='accept_template' " +
                "title='Accept the template.' " +
                "onclick=acceptTemplate(" + String(i) + ") >" +
                "<a href='#'>&nbsp;</a></td>";

            var tdReject = "<td class='reject_template' " +
                "title='Reject the template.' " +
                "onclick=rejectTemplate(" + String(i) + ") >" +
                "<a href='#'>&nbsp;</a></td>";

            var tdPreview = "<td class='preview_template'  " +
                "title='Preview the template.' " +
                "onclick=previewTemplate(" + String(i) + ") >" +
                "<a href='#'>&nbsp;</a></td>";

            var tdTemplate = "<td class='name_template' " +
                "title='Shared with you on " +
                sharedTemplates[i].sharing_date + ".' >" +
                sharedTemplates[i].name + "</td>";

            tbody.append("<tr>" + tdAccept + tdReject +
                tdPreview + tdTemplate + "</tr>");

        }

    }

    // Prepare the page for template sharing
    function prepareUserSelectionForSharing(username) {

        // Is there a selected template?
        var templateToShare = $("#setting").val();
        if (null === templateToShare) {
            // No template selected; inform and return
            $("#message").append("<p>Please pick a template to share!</p>");
            return;
        }

        // Query server for user list
        JSONRPCRequest({
            method : 'jsonGetUserList',
            params: [username]
        }, function(response) {

            // Failure?
            if (response.success != "true") {

                $("#message").append("<p>Could not retrieve user list!</p>");
                return;

            }

            // Add user names to the select widget
            $("#usernameselect").find('option').remove();
            for (var i = 0; i < response.users.length; i++ ) {
                var name = response.users[i]["name"];
                $("#usernameselect").append("<option value=" + name  + ">" + name + "</option>");
            }

            // Copy the shared template
            $("#templateToShare").val(templateToShare);

            // Hide the forms that are not relevant now
            $("#formTemplateImageParameters").hide();
            $("#formImageParameters").hide();

            // Display the user selection form
            $("#formUserList").show();

        });
    }

    // Accept the template with specified index
    function acceptTemplate(template_index) {

        // Get the shared templates content from table
        var tbody = $("#sharedTemplatePickerTable tbody");
        var sharedTemplates = tbody.data("shared_templates");

        if (sharedTemplates == null) {
            return;
        }

        // Send an asynchronous call to the server to accept the template
        JSONRPCRequest({
            method : 'jsonAcceptSharedTemplate',
            params: [sharedTemplates[template_index], 'parameter']
        }, function(response) {

            // Failure?
            if (response.success != "true") {

                $("#message").append("<p>Could not accept template!</p>");
                return;

            }

            // Now reload the page to udpate everything
            location.reload(true);
        });

    }

    // Delete the template with specified index
    function rejectTemplate(template_index) {

        // Get the shared templates content from table
        var tbody = $("#sharedTemplatePickerTable tbody");
        var sharedTemplates = tbody.data("shared_templates");

        if (sharedTemplates == null) {
            return;
        }

        // Ask the user for confirmation
        if (! confirm("Are you sure you want to discard this template?")) {
            return;
        }

        // Send an asynchronous call to the server to accept the template
        JSONRPCRequest({
            method : 'jsonDeleteSharedTemplate',
            params: [sharedTemplates[template_index], 'parameter']
        }, function(response) {

            // Failure?
            if (response.success != "true") {

                $("#message").append("<p>Could not delete template!</p>");
                return;

            }

            // Now reload the page to udpate everything
            location.reload(true);
        });

    }

    // Preview the template with specified index
    function previewTemplate(template_index) {

        alert("Not implemented yet!");

    }

    // Sort the templates by previous user
    function sort_by_previous_owner(template1, template2) {
        var a = template1['previous_owner'];
        var b = template2['previous_owner'];
        if (a == b) {
            return 0;
        }
        return (a < b) ? -1 : 1;
    }

    // Toggles visibility of the shared templates div.
    function toggleSharedTemplatesDiv() {
        $('#sharedTemplatePicker').toggle();
    }

</script>

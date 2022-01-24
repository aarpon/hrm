<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Fileserver;
use hrm\Nav;
use hrm\setting\ParameterSetting;
use hrm\setting\ParameterSettingEditor;
use hrm\System;
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
    header("Location: " . "home.php");
    exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit();
}

if (!isset($_SESSION['editor'])) {
    $_SESSION['editor'] = new ParameterSettingEditor($_SESSION['user']);
}

// The admin will be  the only user who gets the freedom to omit parameters
// systematically. Thus, we set the highest confidence levels, which
// corresponds to the hdf5 format. Hence, it'll be up to the admin to fill out
// the template fields for the users.
if ($_SESSION['user']->isAdmin()) {
    $_SESSION['parametersetting'] = new ParameterSetting();
    $_SESSION['parametersetting']->parameter("ImageFileFormat")->setValue("hdf5");
}

// add public setting support
if (!$_SESSION['user']->isAdmin()) {
    $admin = new UserV2();
    $admin->setName("admin");
    $admin_editor = new ParameterSettingEditor($admin);
    $_SESSION['admin_editor'] = $admin_editor;
}

if (System::hasLicense("coloc")) {
    $numberSteps = 5;
} else {
    $numberSteps = 4;
}

$currentStep = 2;
$previousStep = $currentStep - 1;
$nextStep = $currentStep + 1;

$goBackMessage = " - Select images.";
$goBackMessage = "Go back to step $previousStep/$numberSteps" . $goBackMessage;

$goNextMessage = " - Select restoration template.";
$goNextMessage = "Continue to step $nextStep/$numberSteps" . $goNextMessage;

// fileserver related code (for measured PSF files check)
if (!isset($_SESSION['fileserver'])) {
    $name = $_SESSION['user']->name();
    $_SESSION['fileserver'] = new Fileserver($name);
}

$message = "";


/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */

if (isset($_POST['setting'])) {
    $_SESSION['editor']->setSelected($_POST['setting']);
}

// Except for the admin, the file format is selected at 'select_images'.
$fileFormat =
    $_SESSION['parametersetting']->parameter("ImageFileFormat")->value();

if (isset($_POST['copy_public'])) {
    if (isset($_POST['public_setting'])) {
        if (!$_SESSION['editor']->copyPublicSetting($admin_editor->setting($_POST['public_setting']))) {
            $message = $_SESSION['editor']->message();
        }
    } else {
        $message = "Please select a setting to copy";
    }
} elseif (!empty($_POST['new_setting_create'])) {
      $setting = $_SESSION['editor']->createNewSetting($_POST['new_setting_create']);

    if ($setting != null) {
        $setting->parameter("ImageFileFormat")->setValue($fileFormat);
        $_SESSION['setting'] = $setting;
        header("Location: " . "image_format.php");
        exit();
    }
    $message = $_SESSION['editor']->message();

} elseif (!empty($_POST['new_setting_copy'])) {
    $_SESSION['editor']->copySelectedSetting($_POST['new_setting_copy']);
    $message = $_SESSION['editor']->message();
} elseif (isset($_POST['imageTotemplate']) && isset($_POST['fileselection'])) {
    $setting = null;

    if ($_POST['fileselection'] != 'Choose a file') {
        $filestring = $_POST['fileselection'];
        $path_parts = pathinfo($filestring);
        $hrmtemplatename = $_SESSION['editor']->getValidNewSettingName('Based on ' . $path_parts['filename']);
        $setting = $_SESSION['editor']->createNewSetting($hrmtemplatename);
        // @todo This should react appropriately to the return status of image2hrmTemplate()
        $result = $_SESSION['editor']->image2hrmTemplate(
                $setting,
                $_SESSION['fileserver']->sourceFolder(),
                $filestring
        );
        $message = $_SESSION['editor']->message();
    }

    if ($setting != null) {
        // Need to set ImageFileFormat here, as for the template creation above.
        $setting->parameter("ImageFileFormat")->setValue($fileFormat);
        $_SESSION['setting'] = $setting;
        header("Location: " . "image_format.php");
        exit();
    }
} elseif (isset($_POST['huTotemplate'])) {
    $setting = null;
    $file = $_FILES["upfile"]["name"];
    $fileName = pathinfo($file[0], PATHINFO_BASENAME);
    $extension = pathinfo($file[0], PATHINFO_EXTENSION);

    if ($extension == "hgsm") {
        if ($fileName != '') {
            $hrmTemplateName = 'From ' . $fileName;
            $setting = $_SESSION['editor']->createNewSetting($hrmTemplateName);

            $tmpName = $_FILES["upfile"]["tmp_name"];
            // @todo This should react appropriately to the return status of huTemplate2hrmTemplate()
            $_SESSION['editor']->huTemplate2hrmTemplate($setting, $tmpName[0]);
            $message = $_SESSION['editor']->message();
        } else {
            $message = "Please upload a valid Huygens microscopy template " .
                "(extension .hgsm)";
        }

        if ($setting != null) {
            $setting->parameter("ImageFileFormat")->setValue($fileFormat);
            $_SESSION['setting'] = $setting;
            header("Location: " . "image_format.php");
            exit();
        }
    } else {
        $message = "Please upload a valid Huygens microscopy template " .
            "(extension .hgsm)";
    }
} elseif (isset($_POST['edit'])) {
    /** @var ParameterSetting $setting */
    $setting = $_SESSION['editor']->loadSelectedSetting();
    if ($setting) {
        $setting->parameter("ImageFileFormat")->setValue($fileFormat);
        $_SESSION['setting'] = $setting;
        header("Location: " . "image_format.php");
        exit();
    }
    $message = $_SESSION['editor']->message();
} elseif (isset($_POST['pickUser']) && isset($_POST["templateToShare"])) {
    if (isset($_POST["usernameselect"])) {
        $_SESSION['editor']->shareSelectedSetting(
            $_POST["templateToShare"],
            $_POST["usernameselect"]
        );
        $message = $_SESSION['editor']->message();
    } else {
        $message = "Please pick one or more recipients.";
    }
} elseif (isset($_POST['make_default'])) {
    $_SESSION['editor']->makeSelectedSettingDefault();
    $message = $_SESSION['editor']->message();
} elseif (isset($_POST['annihilate']) && strcmp($_POST['annihilate'], "yes") == 0) {
    $_SESSION['editor']->deleteSelectedSetting();
    $message = $_SESSION['editor']->message();
} elseif (isset($_POST['OK']) && $_POST['OK'] == "OK") {
    if (!isset($_POST['setting'])) {
        $message  = "Please select a template from \"Your image templates\".";
    } else {
        $_SESSION['setting'] = $_SESSION['editor']->loadSelectedSetting();
        $_SESSION['setting']->parameter("ImageFileFormat")->setValue($fileFormat);

        // if measured PSF, check files availability
        $ok = true;
        $psfParam = $_SESSION['setting']->parameter("PointSpreadFunction");

        if ($psfParam->value() == "measured") {
            $psf = $_SESSION['setting']->parameter("PSF");
            $value = $psf->value();
            $files = $_SESSION['fileserver']->allFiles();
            if ($files != null) {
                for ($i = 0; $i < $_SESSION['setting']->numberOfChannels(); $i++) {
                    if (!in_array($value[$i], $files)) {
                        $message = "Please verify selected template, as some PSF " .
                            "files appear to be missing";
                        $ok = false;
                        break;
                    }
                }
            } else {
                $message = "Source image folder not found! Make sure path " .
                    $_SESSION['fileserver']->sourceFolder() . " exists";
                $ok = false;
            }
        }

        if (!$_SESSION['setting']->checkParameterSetting()) {
            $message = $_SESSION['setting']->message();
            $ok = false;
        }

        if ($ok) {
            header("Location: " . "select_task_settings.php");
            exit();
        }
    }
}

$script = array("settings.js", "common.js", "json-rpc-client.js", "shared.js", "ajax_utils.js");

include("header.inc.php");

?>
<!--
  Tooltips
-->
<span class="toolTip" id="ttSpanCreate">
        Create a new image template set with the specified name.
</span>
<span class="toolTip" id="ttSpanEdit">
        Edit the selected image template.
</span>
<span class="toolTip" id="ttSpanImageToTemplate">
        Generate template from image file.
</span>
<span class="toolTip" id="ttSpanHuygens">
        Import a Huygens microscopy template (extension "hgsm").
</span>
<span class="toolTip" id="ttSpanClone">
        Copy the selected image template to a new one with the specified name.
</span>
<span class="toolTip" id="ttSpanShare">
        Share the selected image template with one or more HRM users.
</span>
<span class="toolTip" id="ttSpanDelete">
        Delete the selected image template.
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
            Sets (or resets) the selected image template as the default one
            .</span>
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
            echo(Nav::linkWikiPage('HuygensRemoteManagerHelpSelectParameterSettings'));

            if (!$_SESSION["user"]->isAdmin()) {
                ?>

                <li>
                    <img src="images/share_small.png" alt="shared_templates"/>&nbsp;
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
            if (!$_SESSION['user']->isAdmin()) {
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
        <h3><img alt="ImageParameters" src="./images/image_parameters.png"
                 width="40"/>&nbsp;&nbsp;Create image template</h3>
        <?php

    } else {
        ?>
        <h3><img alt="ImageParameters" src="./images/image_parameters.png"
                 width="40"/>&nbsp;&nbsp;Step
            <?php echo $currentStep . "/" . $numberSteps; ?>
            - Select image template</h3>
        <h4></h4>
        <?php

    }

    // display public settings
    if (!$_SESSION['user']->isAdmin()) {
        ?>
        <form id="formTemplateTypeParameters" method="post" action="">

            <fieldset>
                <legend>Admin image templates</legend>
                <p class="message_small">
                    These are the image templates prepared by your
                    administrator.
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
                            title="Admin templates"
                            class="selection"
                            onclick="ajaxGetParameterListForSet('setting', $(this).val(), true);"
                            onchange="ajaxGetParameterListForSet('setting', $(this).val(), true);"
                            size="5"<?php echo $flag ?>>
                        <?php

                        if (sizeof($settings) == 0) {
                            echo "<option>&nbsp;</option>\n";
                        } else {
                            foreach ($settings as $setting) {
                                echo "<option>" . $setting->name() . "</option>\n";
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
                       onmouseout="UnTip()"/>
            </div>

        </form>

        <?php

    }

    ?>

    <form method="post" action="" enctype="multipart/form-data" id="select">

        <fieldset>

            <?php
            if ($_SESSION['user']->isAdmin()) {
                echo "<legend>Admin image templates</legend>";
                echo "<p class=\"message_small\">Create image templates " .
                    "visible to all users.</p>";
            } else {
                echo "<legend>Your image templates</legend>";
                echo "<p class=\"message_small\">These are your (private) " .
                    "image templates.</p>";
            }
            ?>

            <div id="settings">
                <?php

                $settings = $_SESSION['editor']->settings();
                $size = "8";
                if ($_SESSION['user']->isAdmin()) {
                    $size = "12";
                }
                $flag = "";
                if (sizeof($settings) == 0) {
                    $flag = " disabled=\"disabled\"";
                }
                ?>
                <select name="setting"
                        id="setting"
                        class="selection"
                        title="Your templates"
                        onclick="ajaxGetParameterListForSet('setting', $(this).val(), false);"
                        onchange="ajaxGetParameterListForSet('setting', $(this).val(), false);"
                        size="<?php echo $size ?>"<?php echo $flag ?>>
                    <?php

                    if (sizeof($settings) == 0) {
                        echo "<option>&nbsp;</option>\n";
                    } else {
                        foreach ($settings as $setting) {
                            echo "                        <option";
                            if ($setting->isDefault()) {
                                echo " class=\"default\"";
                            }
                            if ($_SESSION['editor']->selected() == $setting->name()) {
                                echo " selected=\"selected\"";
                            }
                            echo ">" . $setting->name() . "</option>\n";
                        }
                    }

                    //
                    ?>
                </select>
            </div>

        </fieldset>

        <?php
        if ($_SESSION['user']->isAdmin()) {
            $validFiles = $_SESSION['fileserver']->listFiles(true);
        } else {
            $validFiles = $_SESSION['fileserver']->selectedFiles();
        }
        ?>
        <div id="upMsg"></div>        
        <div id="actions" class="parameterselection">    
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
                   onmouseout="UnTip()"/>
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
                  <input name="imageTotemplate"
                   type="button"
                   value=""
                   class="icon imageTotemplate"
                   onmouseover="TagToTip('ttSpanImageToTemplate')"
                   onmouseout="UnTip()"
                   onclick='UnTip(); image2template(<?php echo json_encode($validFiles); ?>)'/>
                </td>
                <td class="button">
                  <input name="huTotemplate"
                   type="button"
                   value=""
                   class="icon huygens"
                   onmouseover="TagToTip('ttSpanHuygens' )"
                   onmouseout="UnTip()"
                   onclick="UnTip(); hu2template('micr');"/>
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
                       onmouseout="UnTip()"/>
                </td>
                <td class="button">
                  <input name="make_default" type="submit" value=""
                       class="icon mark"
                       onmouseover="TagToTip('ttSpanDefault' )"
                       onmouseout="UnTip()"/>
                </td>
    <?php
}
?>
                <td class="button">
                  <input type="hidden" name="annihilate"/>
                  <input name="delete"
                   type="button"
                   value=""
                   class="icon delete"
                   onclick="warn(this.form,
                        'Do you really want to delete this image template?',
                        this.form['setting'].selectedIndex )"
                   onmouseover="TagToTip('ttSpanDelete' )"
                   onmouseout="UnTip()"/>
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
                     From<br />image
                </td>
                <td class="label">
                     Huygens<br />template
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
              <tr>

              </tr>
            </table>                
            <input name="OK" type="hidden"/>
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
                       onclick="document.location.href='select_images.php'"
                       onmouseover="TagToTip('ttSpanBack' )"
                       onmouseout="UnTip()"/>
                <input type="submit"
                       value=""
                       class="icon next"
                       onclick="process()"
                       onmouseover="TagToTip('ttSpanForward' )"
                       onmouseout="UnTip()"/>
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
                        title="Users"
                        size="5" multiple="multiple">
                    <option>&nbsp;</option>
                </select>
            </div>
        </fieldset>

        <!-- Hidden input where to store the selected template -->
        <input hidden id="templateToShare" title="Template to share"
               name="templateToShare" value="">

        <div id="actions" class="userSelection">

            <input name="cancelUser"
                   type="submit"
                   value=""
                   class="icon cancel"
                   onmouseover="TagToTip('' )"
                   onmouseout="UnTip()"/>

            <input name="pickUser"
                   type="submit"
                   value=""
                   class="icon apply"
                   onmouseover="TagToTip('' )"
                   onmouseout="UnTip()"/>


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
                <img src="images/help.png" alt="Help" width="22" height="22"/>
                <b>Help</b> link in the navigation bar.</strong></p>

        <?php

        if (!$_SESSION['user']->isAdmin()) {
            echo "<p>In this step, you are asked to specify all parameters
          relative to the images you want to restore.</p>";
        } else {
            echo "<p>Here, you can create template parameters relative to the images
      to restore.</p>";
        }
        ?>
        <p>These include: file information (e.g. voxel size);
            microscopic parameters (such as microscope type, numerical aperture
            of
            the objective, fluorophore wavelengths); whether a measured or a
            theoretical PSF should be used; whether depth-dependent correction
            on the PSF should be applied.</p>

        <?php
        if (!$_SESSION['user']->isAdmin()) {
            echo "<p>'Admin image templates' created by your facility manager can
        be copied to the list of 'Your image templates' and adapted to fit your
        specific experimental setup.</p>";
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
    $(document).ready(function () {

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
        retrieveSharedTemplates(username, 'parameter');

    });

</script>

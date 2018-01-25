<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Nav;
use hrm\setting\AnalysisSetting;
use hrm\setting\AnalysisSettingEditor;
use hrm\setting\base\Setting;
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

if (!isset($_SESSION['analysiseditor'])) {
    $_SESSION['analysiseditor'] = new AnalysisSettingEditor($_SESSION['user']);
}

/* Initialize variables. */
$analysisEnabled = True;
$message = "";
$widgetState = "";
$divState = "";

/* The analysis stage contains no meaningful selections for single channel
 images. We'll gray out most parts of the page if there's only one channel.*/
if (!$_SESSION['user']->isAdmin()) {
    if ($_SESSION['setting']->numberOfChannels() <= 1) {
        $analysisEnabled = False;
    }
}

/* These checks should be removed when the analysis stage includes steps
 for single channel images. */
if (!$analysisEnabled) {
    $message = "Analysis only available for multichannel images.<br />";
    $message .= "Please continue.";

    $widgetState = "disabled=\"disabled\"";
    $divState = "_disabled";
} else {
    $message  = "Optional step.<br />Leave selection empty to skip analysis.";
}

// add public setting support
if (!$_SESSION['user']->isAdmin()) {
    $admin = new UserV2();
    $admin->setName("admin");
    $admin_editor = new AnalysisSettingEditor($admin);
    $_SESSION['admin_analysiseditor'] = $admin_editor;
}

/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */

if (isset($_POST['analysis_setting'])) {
    $_SESSION['analysiseditor']->setSelected($_POST['analysis_setting']);
}

if (isset($_POST['copy_public'])) {
    if (isset($_POST["public_setting"])) {
        if (!$_SESSION['analysiseditor']->copyPublicSetting(
            $admin_editor->setting($_POST['public_setting']))
        ) {
            $message = $_SESSION['editor']->message();
        }
    } else $message = "Please select a setting to copy";
} else if (!empty($_POST['new_setting_create'])) {
    $analysis_setting = $_SESSION['analysiseditor']->createNewSetting(
        $_POST['new_setting_create']);
    if ($analysis_setting != NULL) {
        $_SESSION['analysis_setting'] = $analysis_setting;
        header("Location: " . "coloc_analysis.php");
        exit();
    }
    $message = $_SESSION['analysiseditor']->message();
} else if (!empty($_POST['new_setting_copy'])) {
    $_SESSION['analysiseditor']->copySelectedSetting($_POST['new_setting_copy']);
    $message = $_SESSION['analysiseditor']->message();
} else if (isset($_POST['edit'])) {
    $analysis_setting = $_SESSION['analysiseditor']->loadSelectedSetting();
    if ($analysis_setting) {
        $_SESSION['analysis_setting'] = $analysis_setting;
        header("Location: " . "coloc_analysis.php");
        exit();
    }
    $message = $_SESSION['analysiseditor']->message();
} else if (isset($_POST['make_default'])) {
    $_SESSION['analysiseditor']->makeSelectedSettingDefault();
    $message = $_SESSION['analysiseditor']->message();
} else if (isset($_POST['pickUser']) && isset($_POST["templateToShare"])) {
    if (isset($_POST["usernameselect"])) {
        $_SESSION['analysiseditor']->shareSelectedSetting($_POST["templateToShare"],
            $_POST["usernameselect"]);
        $message = $_SESSION['analysiseditor']->message();
    } else {
        $message = "Please pick one or more recipients.";
    }
} else if (isset($_POST['annihilate']) &&
    strcmp($_POST['annihilate'], "yes") == 0
) {
    $_SESSION['analysiseditor']->deleteSelectedSetting();
    $message = $_SESSION['analysiseditor']->message();
} else if (isset($_POST['OK']) && $_POST['OK'] == "OK") {

    /* Create default analysis setting (coloc = no) if necessary. */
    if (!$analysisEnabled) {
        $_SESSION['analysis_setting'] = new AnalysisSetting();
        header("Location: " . "create_job.php");
        exit();
    } else {
        if (isset($_POST['analysis_setting'])) {

           /* Proceed to process the selected template. */
           $_SESSION['analysis_setting'] =
               $_SESSION['analysiseditor']->loadSelectedSetting();
           $_SESSION['analysis_setting']->setNumberOfChannels(
                $_SESSION['setting']->numberOfChannels());

        } else {

           $message = "Please continue to skip coloc.";
           
            /* Set a default temlate for skipping analysis. */
            $_SESSION['analysis_setting'] = new AnalysisSetting();
        }

        header("Location: " . "create_job.php");
        exit();
    }
}

/*******************************************************************************/


$script = array("settings.js", "common.js", "json-rpc-client.js", "shared.js", "ajax_utils.js");

include("header.inc.php");

?>
<!--
  Tooltips
-->
<span class="toolTip" id="ttSpanCreate">
        Create a new analysis template with the specified name.
    </span>
<span class="toolTip" id="ttSpanEdit">
        Edit the selected analysis template.
    </span>
<span class="toolTip" id="ttSpanClone">
        Copy the selected analysis template to a new one with the
      specified name.</span>
<span class="toolTip" id="ttSpanShare">
        Share the selected analysis template with one or more HRM users.</span>
<span class="toolTip" id="ttSpanDelete">
        Delete the selected analysis template.
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
            Sets (or resets) the selected analysis template as the default one
            .</span>
    <span class="toolTip" id="ttSpanCopyTemplate">Copy a template.
        </span>
    <span class="toolTip" id="ttSpanBack">
            Go back to step 3/5 - Select restoration template.
        </span>
    <span class="toolTip" id="ttSpanForward">
            Continue to step 5/5 - Create job.
        </span>
    <?php
}
?>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
            echo(Nav::linkWikiPage('HuygensRemoteManagerHelpSelectTaskSettings'));

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


<div id=<?php echo "content" . $divState; ?>>

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
        <h3><img alt="Analysis" src="./images/analysis.png" width="40"/>
            &nbsp;&nbsp;Create analysis template</h3>
        <?php

    } else {

        ?>
        <h3><img alt="Analysis" src="./images/analysis.png" width="40"/>
            &nbsp;&nbsp;Step 4/5 - Select analysis template</h3>
        <?php

    }

    // display public settings
    if (!$_SESSION['user']->isAdmin()) {

        ?>
        <form id="formTemplateTypeParameters" method="post" action="">

            <fieldset>
                <legend>Admin analysis templates</legend>
                <p class="message_small">
                    These are the analysis templates prepared by your
                    administrator.
                </p>
                <div id="templates">
                    <?php

                    $settings = $admin_editor->settings();
                    $flag = "";
                    if (sizeof($settings) == 0) {
                        $flag = " disabled=\"disabled\"";
                    } else {
                        $flag = $widgetState;
                    }

                    ?>
                    <select name="public_setting"
                            title="Admin templates"
                            class="selection"
                            onclick="ajaxGetParameterListForSet('analysis_setting', $(this).val(), true);"
                            onchange="ajaxGetParameterListForSet('analysis_setting', $(this).val(), true);"
                            size="5"<?php echo $flag ?>>

                        <?php

                        if (sizeof($settings) == 0) {
                            echo "<option>&nbsp;</option>\n";
                        } else {
                            foreach ($settings as $set) {
                                /** @var Setting $set */
                                echo "<option>" . $set->name() . "</option>\n";
                            }
                        }

                        ?>
                    </select>
                </div>
            </fieldset>
            <div id=<?php echo "selection" . $divState; ?>>
                <input name="copy_public"
                    <?php echo $widgetState; ?>
                       type="submit"
                       value=""
                       class="icon down"
                       id="controls_copyTemplate"/>
            </div>

        </form>

        <?php

    }

    ?>

    <form method="post" action="" id="select">

        <fieldset>

            <?php
            if ($_SESSION['user']->isAdmin()) {
                echo "<legend>Admin analysis templates</legend>";
                echo "<p class=\"message_small\">Create template " .
                    "visible to all users.</p>";
            } else {
                echo "<legend>Your analysis templates</legend>";
                echo "<p class=\"message_small\">These are your (private) " .
                    "analysis templates.</p>";
            }
            ?>
            <div id="settings">
                <?php

                $settings = $_SESSION['analysiseditor']->settings();
                $size = "8";
                if ($_SESSION['user']->isAdmin()) $size = "12";

                $flag = "";
                if (sizeof($settings) == 0) {
                    $flag = " disabled=\"disabled\"";
                } else {
                    $flag = $widgetState;
                }

                ?>
                <select name="analysis_setting" id="setting"
                        title="Your templates"
                        class="selection"
                        onclick="ajaxGetParameterListForSet('analysis_setting', $(this).val(), false);"
                        onchange="ajaxGetParameterListForSet('analysis_setting', $(this).val(), false);"
                        size="<?php echo $size ?>"
                    <?php echo $flag ?>>
                    <?php

                    if (sizeof($settings) == 0) {
                        echo "<option>&nbsp;</option>\n";
                    } else {
                        foreach ($settings as $set) {
                            echo "<option";
                            if ($set->isDefault()) {
                                echo " class=\"default\"";
                            }
                            if ($_SESSION['analysiseditor']->selected() == $set->name()) {
                                echo " selected=\"selected\"";
                            }
                            echo ">" . $set->name() . "</option>\n";
                        }
                    }

                    ?>
                </select>
            </div>

        </fieldset>

        <div id="<?php echo "actions" . $divState; ?>"
             class="taskselection">
          <table id="actions">
             <tr>
                <td class="button">
                  <input name="create" <?php echo $widgetState ?>
                   type="button"
                   value=""
                   class="icon create"
                   onmouseover="TagToTip('ttSpanCreate' )"
                   onmouseout="UnTip()"
                   onclick="hide('copyTemplateDiv'); changeVisibility('newTemplateDiv')"/>
                </td>
                <td class="button">
                  <input name="edit" <?php echo $widgetState ?>
                   type="submit"
                   value=""
                   class="icon edit"
                   onmouseover="TagToTip('ttSpanEdit' )"
                   onmouseout="UnTip()"/>
                </td>
                <td class="button">
                  <input name="copy" <?php echo $widgetState ?>
                   type="button"
                   value=""
                   class="icon clone"
                   onmouseover="TagToTip('ttSpanClone' )"
                   onmouseout="UnTip()"
                   onclick="hide('newTemplateDiv'); changeVisibility('copyTemplateDiv')"/>
                </td>
<?php
  if (!$_SESSION['user']->isAdmin()) {
?>
                <td class="button">
                  <input name="share" <?php echo $widgetState ?>
                       type="button"
                       onclick="prepareUserSelectionForSharing('<?php echo $_SESSION['user']->name() ?>');"
                       value=""
                       class="icon share"
                       onmouseover="TagToTip('ttSpanShare' )"
                       onmouseout="UnTip()"/>
                  </td>
                  <td class="button">
                    <input name="make_default" <?php echo $widgetState ?>
                       type="submit"
                       value=""
                       class="icon mark"
                       onmouseover="TagToTip('ttSpanDefault' )"
                       onmouseout="UnTip()"/>
                  </td>
<?php
 }
?>
                  <td class="button">
                    <input type="hidden" name="annihilate"/>
                    <input name="delete" <?php echo $widgetState ?>
                     type="button"
                     value=""
                    class="icon delete"
                    onclick="warn(this.form,
                         'Do you really want to delete this analysis template?',
                         this.form['analysis_setting'].selectedIndex )"
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
            <input name="OK" type="hidden"/>
        </div>
        
        <div id="newTemplateDiv">
           <label>Enter a name for the new template:
              <input name="new_setting_create"
                     type="text"
                     size="30"
                     class="textfield_30"/>
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
                     size="30"
                     class="textfield_30"/>
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
                       onclick="document.location.href='select_task_settings.php'"
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

                <select id="usernameselect" name="usernameselect[]"
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

        <?php
        if (!$_SESSION['user']->isAdmin()) {
            echo "<p>In this step, you are asked to specify all parameters relative
        to the analysis of your images.</p>";
        } else {
            echo "<p>Here, you can create templates relative to the
      analysis procedure.</p>";
        }
        ?>
        <p>These are the choices for colocalization analysis, colocalization
            coefficients and maps.</p>

        <?php
        if (!$_SESSION['user']->isAdmin()) {
            echo "<p>'Admin analysis templates' created by your facility
        manager can be copied to the list of 'Your analysis templates' and
        adapted to fit your analysis needs.</p>";
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
            echo("var isAdmin = false;". PHP_EOL);
        }
        ?>
        if (isAdmin == true) {
            return;
        }

        // Retrieve the templates shared with current user
        retrieveSharedTemplates(username, 'analysis');


    });

</script>


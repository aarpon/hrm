<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Fileserver;
use hrm\job\JobDescription;
use hrm\Nav;
use hrm\System;
use hrm\Util;

require_once dirname(__FILE__) . '/inc/bootstrap.php';

session_start();

if (isset($_GET['home'])) {
    header("Location: " . "home.php");
    exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit();
}

if (!isset($_SESSION['fileserver'])) {
    $name = $_SESSION['user']->name();
    $_SESSION['fileserver'] = new Fileserver($name);
}

if (System::hasLicense("coloc")) {
    $currentStep = 5;
    $goBackLink = "select_analysis_settings.php";
    $goBackMessage = "Analysis parameters.";
} else {
    $currentStep = 4;
    $goBackLink = "select_task_settings.php";
    $goBackMessage = "Processing parameters.";
}

$previousStep = $currentStep - 1;
$goBackMessage = "Go back to step $previousStep/$currentStep - " . $goBackMessage;


$message = "";

if (isset($_POST['create'])) {
    /** @var \hrm\param\OutputFileFormat $parameter */
    $parameter = $_SESSION['task_setting']->parameter("OutputFileFormat");
    $parameter->setValue($_POST['OutputFileFormat']);
    $_SESSION['task_setting']->set($parameter);
    // save preferred output file format
    if ($_SESSION['task_setting']->save()) {
        // TODO source/destination folder names should be given to JobDescription
        $job = new JobDescription();
        $job->setParameterSetting($_SESSION['setting']);
        $job->setTaskSetting($_SESSION['task_setting']);
        $job->setAnalysisSetting($_SESSION['analysis_setting']);
        $job->setFiles($_SESSION['fileserver']->selectedFiles(), $_SESSION['autoseries']);

        if ($job->addJob()) {
            $_SESSION['jobcreated'] = True;
            $_SESSION['numberjobadded'] = count($job->files());
            header("Location: " . "home.php");
            exit();
        } else {
            $message = $job->message();
        }
    } else $message = "An unknown error has occurred. " .
        "Please inform the administrator";
} else if (isset($_POST['OK'])) {
    header("Location: " . "select_parameter_settings.php");
    exit();
}

include("header.inc.php");

?>
<!--
  Tooltips
-->
<span class="toolTip" id="ttSpanBack">
    <?php echo $goBackMessage; ?>
    </span>
<span class="toolTip" id="ttSpanCreateJob">
        Create job, add it to the queue, and go back to your home page.
    </span>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
            echo(Nav::linkWikiPage('HuygensRemoteManagerHelpCreateJob'));
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
            echo(Nav::linkJobQueue());
            echo(Nav::linkHome(Util::getThisPageName()));
            ?>
        </ul>
    </div>
    <div class="clear"></div>
</div>

<div id="content">

    <h3><img alt="Launch" src="./images/launch.png" width="40"/>
        &nbsp;Step
        <?php echo $currentStep . "/" . $currentStep; ?>
        - Launch the job</h3>

    <form method="post" action="" id="createjob">

        <fieldset class="setting">

            <legend>
                <a href="javascript:openWindow(
                    'http://www.svi.nl/FileFormats')">
                    <img src="images/help.png" alt="?"/>
                </a>
                Output file format
            </legend>

            <?php

            /** @var \hrm\param\OutputFileFormat $parameter */
            $parameter = $_SESSION['task_setting']->parameter("OutputFileFormat");
            $value = $parameter->value();

            /** @var \hrm\param\TimeInterval $timeParameter */
            $timeParameter = $_SESSION['setting']->parameter("TimeInterval");
            $timeValue = $timeParameter->value();

            // Make sure that if we had TIFF (8 or 16 bit) as output file format and a
            // multichannel dataset, we reset the value to ics
            if (($value == 'TIFF 18-bit') || ($value == 'TIFF 16-bit')) {
                /** @var \hrm\param\NumberOfChannels $nChannelsParameter */
                $nChannelsParameter = $_SESSION['setting']->parameter("NumberOfChannels");
                $numberOfChannels = $nChannelsParameter->value();
                if ($numberOfChannels > 1) {
                    $parameter->setValue("ICS (Image Cytometry Standard)");
                    $_SESSION['first_visit'] = False;
                }
            }

            // Make sure that if we had RGB-TIFF 8 bit as output file format and a
            // single-channel dataset or more than 3 channels, we reset the value to ics
            if ($value == 'RGB TIFF 8-bit') {
                $nChannelsParameter = $_SESSION['setting']->parameter("NumberOfChannels");
                $numberOfChannels = $nChannelsParameter->value();
                if (($numberOfChannels == 1 || $numberOfChannels > 3)) {
                    $parameter->setValue("ICS (Image Cytometry Standard)");
                    $_SESSION['first_visit'] = False;
                }
            }

            // Make sure that if we had Imaris Classic, TIFF 8, or TIFF 16
            // as output file format and a time-series dataset, we reset
            // the value to ics
            if (($value == 'IMS (Imaris Classic)') ||
                ($value == 'TIFF 18-bit') || ($value == 'TIFF 16-bit')
            ) {
                if (($_SESSION['autoseries'] == "TRUE") || ($timeValue > 0)) {
                    $parameter->setValue("ICS (Image Cytometry Standard)");
                    $_SESSION['first_visit'] = False;
                }
            }

            ?>
            <select name="OutputFileFormat"
                    id="OutputFileFormat"
                    class="selection"
                    title="Output file format"
                    size="1">
                <?php

                // FILTER POSSIBLE OUTPUT FILE FORMATS

                // Extract possible values for OutputFileFormat
                $possibleValues = $parameter->possibleValues();
                sort($possibleValues);

                // If the dataset is multi-channel, we remove the TIFF-16 bit
                // options from the list

                /** @var \hrm\param\NumberOfChannels $nChannelsParameter */
                $nChannelsParameter = $_SESSION['setting']->parameter("NumberOfChannels");
                $numberOfChannels = $nChannelsParameter->value();
                if ($numberOfChannels > 1) {
                    $possibleValues = array_diff($possibleValues, array('TIFF 16-bit'));
                    $possibleValues = array_diff($possibleValues, array('TIFF 8-bit'));
                    $possibleValues = array_values($possibleValues);
                }

                // If the dataset is single-channel or has more than 3 channels, we remove
                // the RGB TIFF 8-bit option from the list
                $nChannelsParameter = $_SESSION['setting']->parameter("NumberOfChannels");
                $numberOfChannels = $nChannelsParameter->value();
                if (($numberOfChannels == 1) || ($numberOfChannels > 3)) {
                    $possibleValues = array_diff($possibleValues, array('RGB TIFF 8-bit'));
                    $possibleValues = array_values($possibleValues);
                }

                // If the dataset is a time series, we remove Imaris classic, TIFF 8,
                // TIFF RGB and TIFF 16 from the list
                if (($_SESSION['autoseries'] == "TRUE") || ($timeValue > 0)) {
                    $possibleValues =
                        array_diff($possibleValues, array('IMS (Imaris Classic)'));
                    $possibleValues = array_diff($possibleValues, array('TIFF 16-bit'));
                    $possibleValues = array_diff($possibleValues, array('TIFF 8-bit'));
                    $possibleValues = array_diff($possibleValues, array('RGB TIFF 8-bit'));
                    $possibleValues = array_values($possibleValues);
                }

                // if 'first visit' is not set, set the OutputFileFormat as ICS
                if (!isset($_SESSION['first_visit'])) {
                    $parameter->setValue("ICS (Image Cytometry Standard)");
                    $_SESSION['first_visit'] = False;
                }

                // Set the OutputFileFormat in the TaskSetting object
                $_SESSION['task_setting']->set($parameter);

                foreach ($possibleValues as $possibleValue) {
                    if ($possibleValue == $parameter->value()) {
                        $selected = "selected=\"selected\"";
                    } else {
                        $selected = "";
                    }

                    ?>
                    <option <?php echo $selected ?>>
                        <?php echo $possibleValue ?>
                    </option>
                    <?php

                }

                ?>
            </select>

            <input name="create" type="hidden" value="create"/>

        </fieldset>

    </form>

    <fieldset class="report">
        <legend>
            <a href="javascript:openWindow(
                'http://www.svi.nl/HuygensRemoteManagerHelpCreateJob')">
                <img src="images/help.png" alt="?"/>
            </a>
            <a href="select_parameter_settings.php">
                Image parameters
            </a>: <?php print $_SESSION['setting']->name() ?>
        </legend>
            <textarea name="parameter_settings_report"
                      title="Summary"
                      class="selection"
                      cols="50"
                      rows="5"
                      readonly="readonly">
<?php

echo $_SESSION['setting']->displayString();

?>
            </textarea>
    </fieldset>

    <fieldset class="report">
        <legend>
            <a href="javascript:openWindow(
                'http://www.svi.nl/HuygensRemoteManagerHelpCreateJob')">
                <img src="images/help.png" alt="?"/>
            </a>
            <a href="select_task_settings.php">
                Processing parameters
            </a>: <?php echo $_SESSION['task_setting']->name() ?>
        </legend>
            <textarea name="task_settings_report"
                      title="Summary"                      
                      class="selection"
                      cols="50"
                      rows="5"
                      readonly="readonly">
<?php

$numberOfChannels = $_SESSION['setting']->numberOfChannels();
$micrType = $_SESSION['setting']->microscopeType();
echo $_SESSION['task_setting']->displayString($numberOfChannels, $micrType);

?>
            </textarea>
    </fieldset>


    <fieldset class="report">
        <legend>
            <a href="javascript:openWindow(
                'http://www.svi.nl/HuygensRemoteManagerHelpCreateJob')">
                <img src="images/help.png" alt="?"/>
            </a>

            <?php if (System::hasLicense("coloc")) { ?>
            <a href="select_analysis_settings.php">
                <?php } else { ?>
                <a>
                    <?php } ?>
                    Analysis parameters
                </a>: <?php echo $_SESSION['analysis_setting']->name() ?>
        </legend>
            <textarea name="analysis_settings_report"
                      title="Summary"
                      class="selection"
                      cols="50"
                      rows="5"
                      readonly="readonly">
<?php

echo $_SESSION['analysis_setting']->displayString();

?>
            </textarea>

    </fieldset>


    <fieldset class="report">
        <legend>
            <a href="javascript:openWindow(
                'http://www.svi.nl/HuygensRemoteManagerHelpCreateJob')">
                <img src="images/help.png" alt="?"/>
            </a>
            <a href="select_images.php">
                Selected images
            </a>
        </legend>
            <textarea name="task_settings_report"
                      title="Summary"
                      class="selection"
                      cols="50"
                      rows="3"
                      readonly="readonly">
<?php

$files = $_SESSION['fileserver']->selectedFiles();
foreach ($files as $file) {
    echo " " . $file . "\n";
}

?>
            </textarea>

    </fieldset>


    <form method="post" action="">

        <div id="controls">

            <?php

            if (!isset($_SESSION['jobcreated'])) {

                ?>
                <input type="button" name="previous" value=""
                       class="icon previous"
                       onclick="goBack()"
                       onmouseover="TagToTip('ttSpanBack' )"
                       onmouseout="UnTip()"/>
                <input type="button" name="create job" value=""
                       class="icon launch_start"
                       onclick="submitJob()"
                       onmouseover="TagToTip('ttSpanCreateJob' )"
                       onmouseout="UnTip()"/>


                <?php

            }

            ?>
        </div>

    </form>

</div> <!-- content -->

<div id="rightpanel">

    <div id="info">

        <h3>Quick help</h3>

        <p>As a last step, please choose the output file format for your
            restored images.</p>

        <p>Please notice that some output file formats (specifically all TIFF options)
            are disabled if you set a time interval larger than 0 or you enabled
            the "When applicable, load file series automatically" option in Step 1 - Select images.</p>

        <p>Also, use this as summary to check your parameters. If you spot
            a mistake, use the links on the left to go back and fix it.</p>

        <p>Once you are okay with the parameters, press the
            <img src="images/launch_start.png" alt="Create job" width="22"
                 height="22"/> <b>launch job</b> button to add the job to the
            queue and go back to
            the home page.</p>

        <?php
        if ($numberOfChannels > 1) {
            echo "<p>Please notice that is not possible to save " .
                "multichannel datasets in TIFF-16 bit format.</p>";
        }
        ?>

    </div>

    <div id="message">
        <?php

        echo "<p>$message</p>";

        ?>
    </div>

</div> <!-- rightpanel -->

<!-- Short script to make sure the user does not navigate away until he has submitted.
 We just set a variable canLeave to true when we should not display the warning.
 The warning comes from the onbeforeunload function. This is hard-coded into Firefox so
 the custom message does not display. In other browsers, the message here should show. -->
<script type="text/javascript">
    var canLeave = false;
    function submitJob() {
        canLeave = true;
        document.forms['createjob'].submit();

    }
    function goBack() {
        canLeave = true;
        document.location.href = '<?php echo $goBackLink; ?>';
    }
    // Check if the user is quitting it
    window.onbeforeunload = function (e) {
        if (!canLeave) {
            document.forms['createjob'].is
            return 'You did not submit the job. Are you sure you want to exit?';
        }
    };

</script>
<?php

include("footer.inc.php");

?>

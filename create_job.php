<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Fileserver.inc.php");
require_once("./inc/Setting.inc.php");
require_once("./inc/JobDescription.inc.php");
require_once("./inc/System.inc.php");
require_once("./inc/wiki_help.inc.php");

session_start();

if (isset($_GET['home'])) {
  header("Location: " . "home.php"); exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

if (!isset($_SESSION['fileserver'])) {
  # session_register('fileserver');
  $name = $_SESSION['user']->name();
  $_SESSION['fileserver'] = new Fileserver($name);
}

if (System::hasLicense("coloc")) {
    $currentStep   = 5;
    $goBackLink    = "select_analysis_settings.php";
    $goBackMessage = "Analysis parameters.";
} else {
    $currentStep   = 4;
    $goBackLink    = "select_task_settings.php";
    $goBackMessage = "Processing parameters.";
}

$previousStep   = $currentStep - 1;
$goBackMessage  = "Go back to step $previousStep/$currentStep - " . $goBackMessage;



$message = "";

if (isset($_POST['create'])) {
  $parameter = $_SESSION['task_setting']->parameter("OutputFileFormat");
  $parameter->setValue($_POST['OutputFileFormat']);
  $_SESSION['task_setting']->set($parameter);
  // save preferred output file format
  if ($_SESSION['task_setting']->save()) {
    // TODO source/destination folder names should be given to JobDescription
    $jobDescription = new JobDescription();
    $jobDescription->setParameterSetting($_SESSION['setting']);
    $jobDescription->setTaskSetting($_SESSION['task_setting']);
    $jobDescription->setAnalysisSetting($_SESSION['analysis_setting']);
    $jobDescription->setFiles($_SESSION['fileserver']->selectedFiles(),
                              $_SESSION['autoseries']);

    if ($jobDescription->addJob()) {
      $_SESSION['jobcreated'] = True;
      $_SESSION['numberjobadded'] = count( $jobDescription->files() );

      $job = new Job($jobDescription);
      $job->createHuygensTemplate();
      $job->createSubJobsOrHuTemplate();
      
      header("Location: " . "home.php");
      exit();
    }
    else {
      $message = $jobDescription->message();
    }
  }
  else $message = "An unknown error has occured. " .
      "Please inform the administrator";
}
else if (isset($_POST['OK'])) {
  header("Location: " . "select_parameter_settings.php"); exit();
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
                wiki_link('HuygensRemoteManagerHelpCreateJob');
            ?>
        </ul>
    </div>
    <div id="navright">
        <ul>
            <?php
                include("./inc/nav/user.inc.php");
                include("./inc/nav/raw_images.inc.php");
                include("./inc/nav/job_queue.inc.php");
                include("./inc/nav/home.inc.php");
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
                <img src="images/help.png" alt="?" />
            </a>
              Output file format
            </legend>

<?php

$parameter = $_SESSION['task_setting']->parameter("OutputFileFormat");
$value = $parameter->value();

$timeParameter = $_SESSION['setting']->parameter("TimeInterval");
$timeValue = $timeParameter->value();

// Make sure that if we had TIFF (8 or 16 bit) as output file format and a
// multichannel dataset, we reset the value to ics
if ( ( $value == 'TIFF 18-bit' ) || ( $value == 'TIFF 16-bit' ) ) {
  $nChannelsParameter = $_SESSION['setting']->parameter("NumberOfChannels");
  $numberOfChannels = $nChannelsParameter->value( );
  if ( $numberOfChannels > 1 ) {
    $parameter->setValue("ICS (Image Cytometry Standard)");
    $_SESSION['first_visit'] = False;
  }
}

// Make sure that if we had RGB-TIFF 8 bit as output file format and a
// single-channel dataset or more than 3 channels, we reset the value to ics
if ( $value == 'RGB TIFF 8-bit' ) {
  $nChannelsParameter = $_SESSION['setting']->parameter("NumberOfChannels");
  $numberOfChannels = $nChannelsParameter->value( );
  if ( ( $numberOfChannels == 1 || $numberOfChannels > 3 ) ) {
    $parameter->setValue("ICS (Image Cytometry Standard)");
    $_SESSION['first_visit'] = False;
  }
}

// Make sure that if we had Imaris Classic, TIFF 8, or TIFF 16 
// as output file format and a time-series dataset, we reset 
// the value to ics
if (($value == 'IMS (Imaris Classic)') ||
        ($value == 'TIFF 18-bit') || ($value == 'TIFF 16-bit')) {
  if ( ($_SESSION['autoseries'] == "TRUE") || ($timeValue > 0) ) {
    $parameter->setValue("ICS (Image Cytometry Standard)");
    $_SESSION['first_visit'] = False;
  }
}

?>
                <select name="OutputFileFormat" id="OutputFileFormat" size="1">
<?php

// FILTER POSSIBLE OUTPUT FILE FORMATS

// Extract possible values for OutputFileFormat
$possibleValues = $parameter->possibleValues();
sort( $possibleValues );

// If the dataset is multi-channel, we remove the TIFF-16 bit
// options from the list
$nChannelsParameter = $_SESSION['setting']->parameter("NumberOfChannels");
$numberOfChannels = $nChannelsParameter->value( );
if ( $numberOfChannels > 1 ) {
  $possibleValues = array_diff($possibleValues, array( 'TIFF 16-bit' ) );
  $possibleValues = array_diff($possibleValues, array( 'TIFF 8-bit' ) );
  $possibleValues = array_values( $possibleValues );
}

// If the dataset is single-channel or has more than 3 channels, we remove 
// the RGB TIFF 8-bit option from the list
$nChannelsParameter = $_SESSION['setting']->parameter("NumberOfChannels");
$numberOfChannels = $nChannelsParameter->value( );
if ( ( $numberOfChannels == 1 ) || ( $numberOfChannels > 3) ) {
  $possibleValues = array_diff($possibleValues, array( 'RGB TIFF 8-bit' ) );
  $possibleValues = array_values( $possibleValues );
}

// If the dataset is a time series, we remove Imaris classic, TIFF 8,
// TIFF RGB and TIFF 16 from the list
if (($_SESSION['autoseries'] == "TRUE") || ($timeValue > 0) ) {
    $possibleValues =
        array_diff($possibleValues, array( 'IMS (Imaris Classic)' ) );
    $possibleValues = array_diff($possibleValues, array( 'TIFF 16-bit' ) );
    $possibleValues = array_diff($possibleValues, array( 'TIFF 8-bit' ) );
    $possibleValues = array_diff($possibleValues, array( 'RGB TIFF 8-bit' ) );
    $possibleValues = array_values( $possibleValues );
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
  }
  else {
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

                <input name="create" type="hidden" value="create" />

          </fieldset>

        </form>

        <fieldset class="report">
            <legend>
                <a href="javascript:openWindow('
                   http://www.svi.nl/HuygensRemoteManagerHelpCreateJob')">
                    <img src="images/help.png" alt="?" />
                </a>
                <a href="select_parameter_settings.php">
                    Image parameters
                </a>: <?php print $_SESSION['setting']->name() ?>
            </legend>
            <textarea name="parameter_settings_report"
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
                <a href="javascript:openWindow('
                   http://www.svi.nl/HuygensRemoteManagerHelpCreateJob')">
                    <img src="images/help.png" alt="?" />
                </a>
                <a href="select_task_settings.php">
                    Processing parameters
                </a>: <?php echo $_SESSION['task_setting']->name() ?>
            </legend>
            <textarea name="task_settings_report"
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
                    <img src="images/help.png" alt="?" />
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
                    <img src="images/help.png" alt="?" />
                </a>
                <a href="select_images.php">
                    Selected images
                </a>
            </legend>
            <textarea name="task_settings_report"
                      cols="50"
                      rows="3"
                      readonly="readonly">
<?php

$files = $_SESSION['fileserver']->selectedFiles();
foreach ($files as $file) {
  echo " ".$file."\n";
}

?>
            </textarea>

        </fieldset>
            

        <form method="post" action="">

          <div id="controls">

<?php

if (!isset($_SESSION['jobcreated'])) {

?>
            <input type="button" name="previous" value="" class="icon previous"
              onclick="document.location.href='<?php echo $goBackLink; ?>'"
              onmouseover="TagToTip('ttSpanBack' )"
              onmouseout="UnTip()" />
            <input type="button" name="create job" value=""
              class="icon launch_start"
              onclick="document.forms['createjob'].submit()"
              onmouseover="TagToTip('ttSpanCreateJob' )"
              onmouseout="UnTip()" />


<?php

}
else {

?>
            <input type="button"
                   value="restart"
                   onclick="document.location.href='home.php'"
                   class="icon restart" />
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

            <p>Also, use this as summary to check your parameters. If you spot
            a mistake, use the links on the left to go back and fix it.</p>

            <p>Once you are okay with the parameters, press the
            <img src="images/launch_start.png" alt="Create job" width="30"
                 height="22" /> <b>launch job</b> button to add the job to the
            queue and go back to
            the home page.</p>

            <?php
              if ( $numberOfChannels > 1 ) {
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

<?php

include("footer.inc.php");

?>

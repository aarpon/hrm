<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Fileserver.inc.php");
require_once("./inc/Setting.inc.php");
require_once("./inc/JobDescription.inc.php");
require_once ("./inc/System.inc.php");

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

$message = "";

if (isset($_POST['create'])) {
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
    $job->setFiles($_SESSION['fileserver']->selectedFiles());
    if ($job->addJob()) {
      $_SESSION['jobcreated'] = True;
      $_SESSION['numberjobadded'] = count( $job->files() );
      header("Location: " . "home.php");
      exit();
    }
    else {
      $message = $job->message();
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
                <a href="job_queue.php">
                <img src="images/queue_small.png" alt="queue" />
                &nbsp;Queue
                </a>
            </li>
            <li>
                <a href="<?php echo getThisPageName();?>?home=home">
                <img src="images/home.png" alt="home" />
                &nbsp;Home
                </a>
            </li>
            <li>
                <a href="javascript:openWindow(
                   'http://www.svi.nl/HuygensRemoteManagerHelpCreateJob')">
                    <img src="images/help.png" alt="help" />
                    &nbsp;Help
                </a>
            </li>
        </ul>
    </div>

    <div id="content">

        <h3><img alt="Launch" src="./images/launch.png" width="40"/>&nbsp;Step 5/5 - Launch the job</h3>

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

// Make sure that if we had Imaris Classic as output file format and a
// time-series dataset, we reset the value to ics
if ( $value == 'IMS (Imaris Classic)' ) {
  if ( $_SESSION['setting']->isTimeSeries() ) {
    $parameter->setValue("ICS (Image Cytometry Standard)");
    $_SESSION['first_visit'] = False;
  }
}
?>
                <select name="OutputFileFormat" id="OutputFileFormat" size="1">
<?php

// Extract possible values for OutputFileFormat
$possibleValues = $parameter->possibleValues();
sort( $possibleValues );

// If the dataset is multi-channel, we remove the TIFF-8 and TIFF-16 bit
// options from the list
$nChannelsParameter = $_SESSION['setting']->parameter("NumberOfChannels");
$numberOfChannels = $nChannelsParameter->value( );
if ( $numberOfChannels > 1 ) {
  $possibleValues = array_diff($possibleValues, array( 'TIFF 16-bit' ) );
  $possibleValues = array_diff($possibleValues, array( 'TIFF 8-bit' ) );
  $possibleValues = array_values( $possibleValues );
}

// If the dataset is a time series, we remove Imaris classic from the list
if ( $_SESSION['setting']->isTimeSeries() ) {
    $possibleValues =
        array_diff($possibleValues, array( 'IMS (Imaris Classic)' ) );
    $possibleValues = array_values( $possibleValues );
}

if (!isset($_SESSION['first_visit'])) { // if 'first visit' is not set, set
// the OutputFileFormat as ICS
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

$numberOfChannels =
    $_SESSION['setting']->parameter( "NumberOfChannels" )->value( );
echo $_SESSION['task_setting']->
    displayStringWithoutOutputFileFormat( $numberOfChannels );

?>
            </textarea>
        </fieldset>

            
   <fieldset class="report">
            <legend>
                <a href="javascript:openWindow(
                   'http://www.svi.nl/HuygensRemoteManagerHelpCreateJob')">
                    <img src="images/help.png" alt="?" />
                </a>
                <a href="select_analysis_settings.php">
                    Analysis parameters
    </a>: <?php echo $_SESSION['analysis_setting']->name() ?>
                </a>
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
              onclick="document.location.href='select_analysis_settings.php'"
              id="controls_back" />
            <input type="button" name="create job" value="" class="icon launch"
              onclick="document.forms['createjob'].submit()"
              id="controls_createJob" />

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

            <p>Also, use this is summary to check your parameters. If you spot
            a mistake, use the links on the left to go back and fix it.</p>

            <p>Once you are okay with the parameters, press the
            <img src="images/launch.png" alt="Create job" width="22"
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

/*
 * Tooltips. 
 * 
 * Define $tooltips array with object id as key and tooltip string as value.
 */
$tooltips = array(
    "controls_back" => "Go back to step 4/5 - Analysis parameters.",
    "controls_createJob" => "Create job, add it to the queue, and go back to your home page.",
);

include("footer.inc.php");

?>

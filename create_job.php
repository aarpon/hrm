<?php

// php page: create_job.php

// This file is part of huygens remote manager.

// Copyright: Montpellier RIO Imaging (CNRS)

// contributors :
// 	     Pierre Travo	(concept)
// 	     Volker Baecker	(concept, implementation)

// email:
// 	pierre.travo@crbm.cnrs.fr
// 	volker.baecker@crbm.cnrs.fr

// Web:     www.mri.cnrs.fr

// huygens remote manager is a software that has been developed at 
// Montpellier Rio Imaging (mri) in 2004 by Pierre Travo and Volker 
// Baecker. It allows running image restoration jobs that are processed 
// by 'Huygens professional' from SVI. Users can create and manage parameter 
// settings, apply them to multiple images and start image processing 
// jobs from a web interface. A queue manager component is responsible for 
// the creation and the distribution of the jobs and for informing the user 
// when jobs finished.

// This software is governed by the CeCILL license under French law and 
// abiding by the rules of distribution of free software. You can use, 
// modify and/ or redistribute the software under the terms of the CeCILL 
// license as circulated by CEA, CNRS and INRIA at the following URL 
// "http://www.cecill.info".

// As a counterpart to the access to the source code and  rights to copy, 
// modify and redistribute granted by the license, users are provided only 
// with a limited warranty and the software's author, the holder of the 
// economic rights, and the successive licensors  have only limited 
// liability.

// In this respect, the user's attention is drawn to the risks associated 
// with loading, using, modifying and/or developing or reproducing the 
// software by the user in light of its specific status of free software, 
// that may mean that it is complicated to manipulate, and that also 
// therefore means that it is reserved for developers and experienced 
// professionals having in-depth IT knowledge. Users are therefore encouraged 
// to load and test the software's suitability as regards their requirements 
// in conditions enabling the security of their systems and/or data to be 
// ensured and, more generally, to use and operate it in the same conditions 
// as regards security.

// The fact that you are presently reading this means that you have had 
// knowledge of the CeCILL license and that you accept its terms.

require_once("./inc/User.inc");
require_once("./inc/Fileserver.inc");
require_once("./inc/Setting.inc");
require_once("./inc/JobDescription.inc");

global $use_accounting_system;

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

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

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
    $job->setFiles($_SESSION['fileserver']->selectedFiles()); 
    if ($use_accounting_system) {
	 	$job->setCredit($_SESSION['credit']);
		$job->setGroup($_SESSION['group']);
	}
    if ($job->createJob()) {
      $_SESSION['jobcreated'] = True;
      header("Location: " . "home.php");
      exit();
    }
    else {
      $message = "            <p class=\"warning\">".$job->message()."</p>";
    }
  }
  else $message = "            <p class=\"warning\">An unknown error has occured. Please inform the person in charge.</p>";
}
else if (isset($_POST['OK'])) {
  header("Location: " . "select_parameter_settings.php"); exit();  
}

include("header.inc.php");

?>
    <!--
      Tooltips
    -->
    <span id="ttSpanBack">Go back to step 3/4 - Select images.</span>
    <span id="ttSpanCreateJob">Create job, add it to the queue, and go back to your home page.</span>  
 
     <div id="nav">
        <ul>
            <li><?php echo $_SESSION['user']->name(); ?></li>
            <li><a href="<?php echo getThisPageName();?>?home=home"><img src="images/restart_help.png" alt="home" />&nbsp;Home</a></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpCreateJob')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
    </div>
   
    <div id="content">
   
        <h3>Step 4/4 - Create job</h3>
        
        <form method="post" action="" id="createjob">
        
            <div id="selection">
            
                <label for="OutputFileFormat">Output file format:</label>
<?php

$parameter = $_SESSION['task_setting']->parameter("OutputFileFormat");
$value = $parameter->value();

// Make sure that if we had TIFF-16 bit as output file format and a multichannel
// dataset, we reset the value to ics
if ( $value == 'TIFF 16-bit' ) {
  $nChannelsParameter = $_SESSION['setting']->parameter("NumberOfChannels");
  $numberOfChannels = $nChannelsParameter->value( );
  if ( $numberOfChannels > 1 ) {
    $parameter->setValue("ICS (Image Cytometry Standard)");
    $_SESSION['first_visit'] = False;
  }
}

?>
                <select name="OutputFileFormat" id="OutputFileFormat" size="1">
<?php

$possibleValues = $parameter->possibleValues(); // extract possible values for OutputFileFormat

// If the dataset is multi-channel, we remove the TIFF-16 bit option from the list
$nChannelsParameter = $_SESSION['setting']->parameter("NumberOfChannels");
$numberOfChannels = $nChannelsParameter->value( );
if ( $numberOfChannels > 1 ) {
  $possibleValues = array_diff($possibleValues, array( 'TIFF 16-bit' ) );
  $possibleValues = array_values( $possibleValues );
}
  
if (!isset($_SESSION['first_visit'])) { // if 'first visit' is not set, set the OutputFileFormat as ICS
  $parameter->setValue("ICS (Image Cytometry Standard)");
  $_SESSION['first_visit'] = False;
}

$_SESSION['task_setting']->set($parameter); // set the OutputFileFormat in the TaskSetting object

foreach ($possibleValues as $possibleValue) {
  if ($possibleValue == $parameter->value()) {
    $selected = "selected=\"selected\"";
  }
  else {
    $selected = "";
  }

?>
                    <option <?php echo $selected ?>><?php echo $possibleValue ?></option>
<?php

}

?>
                </select>
                
                <input name="create" type="hidden" value="create" />
                
            </div>
            
        </form>
        
        <fieldset class="report">
            <legend>
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpCreateJob')"><img src="images/help.png" alt="?" /></a>
                <a href="select_parameter_settings.php">Image parameters</a>: <?php print $_SESSION['setting']->name() ?>
            </legend>
            <textarea name="parameter_settings_report" cols="50" rows="10" readonly="readonly">
<?php

echo $_SESSION['setting']->display();

?>
            </textarea>
        </fieldset>
        
        <fieldset class="report">
            <legend>
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpCreateJob')"><img src="images/help.png" alt="?" /></a>
                <a href="select_task_settings.php">Restoration parameters</a>: <?php echo $_SESSION['task_setting']->name() ?>
            </legend>
            <textarea name="task_settings_report" cols="50" rows="6" readonly="readonly">
<?php

$numberOfChannels = $_SESSION['setting']->parameter( "NumberOfChannels" )->value( );
echo $_SESSION['task_setting']->displayWithoutOutputFileFormat( $numberOfChannels );

?>
            </textarea>
        </fieldset>
        
        <fieldset class="report">
            <legend>
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpCreateJob')"><img src="images/help.png" alt="?" /></a>
                <a href="select_images.php">Selected images</a>
            </legend>
            <textarea name="task_settings_report" cols="50" rows="5" readonly="readonly">
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
            <input type="button" name="previous"   value="" class="icon previous"
              onclick="document.location.href='select_images.php'"
              onmouseover="TagToTip('ttSpanBack' )"
              onmouseout="UnTip()" />
            <input type="button" name="create job" value="" class="icon ok"
              onclick="document.forms['createjob'].submit()"
              onmouseover="TagToTip('ttSpanCreateJob' )"
              onmouseout="UnTip()" />
            
<?php

}
else {

?>
            <input type="button" value="restart" onclick="document.location.href='home.php'" class="icon restart" />
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
            <img src="images/ok_help.png" alt="Create job" width="22" height="22" />
		    <b>create job</b> button to add the job to the queue and go back to
            the home page.</p>
            
            <?php
              if ( $numberOfChannels > 1 ) {
                echo "<p>Please notice that is not possible to save multichannel datasets in TIFF-16 bit format.</p>";
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




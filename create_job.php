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

session_start();

if (isset($_GET['exited'])) {
  $_SESSION['user']->logout();
  session_unset();
  session_destroy();
  header("Location: " . "login.php"); exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

if (!isset($_SESSION['fileserver'])) {
  session_register('fileserver');
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
      $message = "            <p class=\"warning\">The job has been created.</p>";
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

    <div id="nav">
        <ul>
            <li><a href="select_images.php?exited=exited">exit</a></li>
            <li><a href="account.php">account</a></li>
            <li><a href="job_queue.php">queue</a></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/HuygensRemoteManagerHelpCreateJob')">help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
        <h3>Step 4 - Create Job</h3>
        
        <form method="post" action="" id="createjob">
        
            <div id="selection">
            
                <label for="OutputFileFormat">output file format:</label>
<?php

$parameter = $_SESSION['task_setting']->parameter("OutputFileFormat");
$value = $parameter->value();

?>
                <select name="OutputFileFormat" id="OutputFileFormat" size="1">
<?php

// TODO refactor

$possibleValues = $parameter->possibleValues();
if ($_SESSION['setting']->isThreeDimensional() && $_SESSION['setting']->isTimeSeries()) {
  if (!isset($_SESSION['first_visit'])) {
    $parameter->setValue("ICS (Image Cytometry Standard)");
    $_SESSION['first_visit'] = False;
  }
  $_SESSION['task_setting']->set($parameter);
  $newPossibleValues = array();
  foreach ($possibleValues as $possibleValue) {
    if (!strstr($possibleValue, 'tiff')) {
      $newPossibleValues[] = $possibleValue;
    }
  }
  $possibleValues = $newPossibleValues;
}
else {
  if (!isset($_SESSION['first_visit'])) {
    if ($_SESSION['setting']->isTwoPhoton()) {
      $parameter->setValue("IMS (Imaris Classic)");
      $_SESSION['first_visit'] = False;
    }
    // set default output file format to ICS
    else {
      $parameter->setValue("ICS (Image Cytometry Standard)");
      $_SESSION['first_visit'] = False;
    }
    $_SESSION['task_setting']->set($parameter);
  }
}

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
                <a href="javascript:openWindow('http://support.svi.nl/wiki/HuygensRemoteManagerHelpCreateJob')"><img src="images/help.png" alt="?" /></a>
                <a href="select_parameter_settings.php">parameter setting</a>: <?php print $_SESSION['setting']->name() ?>
            </legend>
            <textarea name="parameter_settings_report" cols="50" rows="10" readonly="readonly">
<?php

echo $_SESSION['setting']->display();

?>
            </textarea>
        </fieldset>
        
        <fieldset class="report">
            <legend>
                <a href="javascript:openWindow('http://support.svi.nl/wiki/HuygensRemoteManagerHelpCreateJob')"><img src="images/help.png"alt="?" /></a>
                <a href="select_task_settings.php">task setting</a>: <?php echo $_SESSION['task_setting']->name() ?>
            </legend>
            <textarea name="task_settings_report" cols="50" rows="5" readonly="readonly">
<?php

echo $_SESSION['task_setting']->displayWithoutOutputFileFormat();

?>
            </textarea>
        </fieldset>
        
        <fieldset class="report">
            <legend>
                <a href="javascript:openWindow('http://support.svi.nl/wiki/HuygensRemoteManagerHelpCreateJob')"><img src="images/help.png" alst="?" /></a>
                <a href="select_images.php">selected images</a>
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
            
    </div> <!-- content -->
    
    <div id="stuff">
    
        <div id="info">
        
            <form method="post" action="">
            
                <div id="controls">
                
<?php

if (!isset($_SESSION['jobcreated'])) {

?>
                    <input type="button" value="previous" onclick="document.location.href='select_images.php'" class="icon previous" />
                    <input type="button" value="create job" onclick="document.forms['createjob'].submit()" class="icon ok" />
<?php

}
else {

?>
                <input type="button" value="restart" onclick="document.location.href='select_parameter_settings.php'" class="icon restart" />
<?php

}

?>

                </div>
                
            </form>
            
            <p>
                Check the parameters you have chosen. Press the "Create Job" 
                button to create the job.
            </p>
            
            <p>
                Use the cancel / start again button to restart specifing a job 
                or to create another job.
            </p>
            
            <p>Use the exit link at the top of the page to quit.</p>
            
        </div>
        
        <div id="message">
<?php

echo $message;

?>
        </div>
        
    </div> <!-- stuff -->
    
<?php

include("footer.inc.php");

?>

<?php

// php page: select_images.php

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

session_start();

if (isset($_GET['exited'])) {
  if ( isset( $_SESSION['user'] ) ) $_SESSION['user']->logout();
  session_unset();
  session_destroy();
  header("Location: " . "login.php"); exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

if (isset($_SESSION['jobcreated'])) {
  unset($_SESSION['jobcreated']);
}

if (!isset($_SESSION['fileserver'])) {
  # session_register("fileserver");
  $name = $_SESSION['user']->name();
  $_SESSION['fileserver'] = new Fileserver($name);
}

$fileFormat = $_SESSION['setting']->parameter("ImageFileFormat");
$extensions = $fileFormat->fileExtensions();
$_SESSION['fileserver']->setImageExtensions($extensions);

$geometry = $_SESSION['setting']->parameter("ImageGeometry");

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

if (isset($_POST['down'])) {
  if (isset($_POST['userfiles']) && is_array($_POST['userfiles'])) {
    $_SESSION['fileserver']->addFilesToSelection($_POST['userfiles']);
  }
}
else if (isset($_POST['up'])) {		
  if (isset($_POST['selectedfiles']) && is_array($_POST['selectedfiles'])) {
    $_SESSION['fileserver']->removeFilesFromSelection($_POST['selectedfiles']);
  }  
}
else if (isset($_POST['update'])) {
  $_SESSION['fileserver']->updateAvailableFiles();
}
else if (isset($_POST['OK'])) {
  if (!$_SESSION['fileserver']->hasSelection()) {
    $message = "            <p class=\"warning\">Please add at least one image to your selection!</p>";
  }
  else {
    header("Location: " . "create_job.php"); exit();
  }
}

$script = "settings.js";

// display only relevant files
if ($fileFormat->value() == "ics") {
    $files = $_SESSION['fileserver']->files("ics");
}
else if ($fileFormat->value() == "tiff" || $fileFormat->value() == "tiff-single") {
  $files = $_SESSION['fileserver']->tiffFiles();
}
else if ($fileFormat->value() == "tiff-series") {
  $files = $_SESSION['fileserver']->tiffSeriesFiles();
}
else if ($fileFormat->value() == "tiff-leica") {
  $files = $_SESSION['fileserver']->tiffLeicaFiles();
}
else if ($fileFormat->value() == "stk") {
  //if ($geometry->value() == "XY - time" || $geometry->value() == "XYZ - time") {
    if ($_SESSION['setting']->isTimeSeries()) {
      $files = $_SESSION['fileserver']->stkSeriesFiles();
    }
    else {
      $files = $_SESSION['fileserver']->stkFiles();
    }
  //}
  //else {
  //  $files = $_SESSION['fileserver']->files("stk");
  //}
}
else {
  $files = $_SESSION['fileserver']->files();
}

if ($files != null) {

    $generatedScript = "
function imageAction (list) {
    var n = list.selectedIndex;    // Which menu item is selected
    var val = list[n].value;

    switch ( val )
    {
";

    foreach ($files as $key => $file) {
        $generatedScript .= "
        case \"$file\" :
            ". $_SESSION['fileserver']->getImageAction($file,
                $key, "src", "preview", 0, 1). "
            break;
            ";
    }


    $generatedScript .= "
    }
}
";
}



include("header.inc.php");

?>

    <div id="nav">
        <ul>
            <li><a href="select_images.php?exited=exited">exit</a></li>
<?php

if ($enableUserAdmin) {

?>
            <li><a href="account.php">account</a></li>
<?php

}

?>
            <li><a href="job_queue.php">queue</a></li>
            <li><a href="file_management.php">files</a></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpSelectImages')">help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
        <h3>Step 3 - Select Images</h3>
        <?php 
        #print "<pre>"; 
        #print_r($_SESSION['setting']->EmissionWavelength);
        #print_r($_SESSION['setting']->CCDCaptorSizeX);
        #print_r($_SESSION['setting']->ZStepSize);
        
        # print_r($_SESSION['setting']); 
        
        #print "</pre>"; 
        ?>
        
        <form method="post" action="" id="select">
        
            <fieldset>
                <legend>available images on server</legend>
                <div id="userfiles">
<?php

$flag = "";
if ($files == null) {
    $flag = " disabled=\"disabled\"";
    $message .= "<p class=\"warning\">No images of type '".
                 $fileFormat->value()."'.</p>";
}

?>
                    <select onclick="javascript:imageAction(this)" name="userfiles[]" size="10" multiple="multiple"<?php echo $flag ?>>
<?php
$keyArr = array();
if ($files != null) {
  foreach ($files as $key => $filename) {
          echo $_SESSION['fileserver']->getImageOptionLine($filename, $key, 
                                                      "src","preview", 0, 1) ;
          $keyArr[$filename] = $key;

  }
}
else echo "                        <option>&nbsp;</option>\n";

?>
                    </select>
                </div>
            </fieldset>
            
            <div id="selection">
                <input name="down" type="submit" value="" class="icon down" />
                <input name="up" type="submit" value="" class="icon up" />
            </div>
            
            <fieldset>
                <legend>selected images</legend>
                <div id="selectedfiles">
<?php

$files = $_SESSION['fileserver']->selectedFiles();
$flag = "";
if ($files == null) $flag = " disabled=\"disabled\"";

?>
                    <select onclick="javascript:imageAction(this)" name="selectedfiles[]" size="5" multiple="multiple"<?php echo $flag ?>>
<?php

if ($files != null) {
  foreach ($files as $filename) {
          $key = $keyArr[$filename];
          echo $_SESSION['fileserver']->getImageOptionLine($filename,
                                             $key, "src", "preview", 0, 1) ;
  }
}
else echo "                        <option>&nbsp;</option>\n";

?>
                    </select>
                </div>
            </fieldset>
            
            <div id="actions" class="imageselection">
                <input name="update" type="submit" value="" class="icon update" />
                <input name="OK" type="hidden" />
            </div>
            
        </form>
        
    </div> <!-- content -->
    
    
        <div id="controls">
        
            <input type="button" value="" class="icon previous" onclick="document.location.href='select_task_settings.php'" />
            <input type="submit" value="" class="icon next" onclick="process()" />
        </div>    
            
    <div id="stuff">
        <div id="info">
            <p>
                Select the image files in the upper file list. You can use SHIFT- 
		and CTRL-click to select multiple files. Use the down-arrow to
		<img src="images/add_help.png" alt="Add" width="22" height="22"/>    
		<b>add</b> files to your selection.
            </p>
            
            <p>
		Select files in the lower file list and use the up-arrow to 
                <img src="images/remove_help.png" alt="Remove" width="22" height="22"/>
		<b>remove</b> them from your selection again.
            </p>
            
	   <p>
	      	Use the 
                <img src="images/update_help.png" alt="Update" width="22" height="22"/>
                <b>refresh</b> button to reload the list of available images on the server.
	   </p>      

            <p>
                When you are done with your selection press the
                <img src="images/next_help.png" alt="Down" width="22" height="22"/>
                <b>forward</b> button to go
                to the next step.
            </p>


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

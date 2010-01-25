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

if (isset($_GET['home'])) {
  header("Location: " . "home.php"); exit();
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
if ($fileFormat->value() == "ics" || $fileFormat->value() == "ics2" ) {
    $files = $_SESSION['fileserver']->files("ics");
}
else if ($fileFormat->value() == "hdf5" ) {
  $files = $_SESSION['fileserver']->files("h5");
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
    <!--
      Tooltips
    -->
    <span id="ttSpanDown">Add files to the list of selected images.</span>
    <span id="ttSpanUp">Remove files from the list of selected images.</span>
    <span id="ttSpanRefresh">Refresh the list of available images on the server.</span>
    <span id="ttSpanBack">Go back to step 2/4 - Restoration parameters.</span>
    <span id="ttSpanForward">Continue to step 4/4 - Create job</span>
    
    <div id="nav">
        <ul>
            <li><?php echo $_SESSION['user']->name(); ?></li>
            <li><a href="<?php echo getThisPageName();?>?home=home"><img src="images/home.png" alt="home" />&nbsp;Home</a></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpSelectImages')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
        <h3>Step 3/4 - Select images</h3>
        
        <form method="post" action="" id="select">
        
            <fieldset>
                <legend>Images available on server</legend>
                <div id="userfiles">
<?php

$flag = "";
if ($files == null) {
    $flag = " disabled=\"disabled\"";
    $message .= "<p class=\"warning\">No images of type '".
                 $fileFormat->value()."'.</p>";
}

?>
                    <select onclick="javascript:imageAction(this)" onchange="javascript:imageAction(this)" name="userfiles[]" size="10" multiple="multiple"<?php echo $flag ?>>
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
                <input name="down" type="submit" value="" class="icon down"
                    onmouseover="TagToTip('ttSpanDown' )"
                    onmouseout="UnTip()" />
                <input name="up" type="submit" value="" class="icon remove"
                    onmouseover="TagToTip('ttSpanUp' )"
                    onmouseout="UnTip()" />
            </div>
            
            <fieldset>
                <legend>Selected images</legend>
                <div id="selectedfiles">
<?php

$files = $_SESSION['fileserver']->selectedFiles();
$flag = "";
if ($files == null) $flag = " disabled=\"disabled\"";

?>
                    <select onclick="javascript:imageAction(this)" onchange="javascript:imageAction(this)" name="selectedfiles[]" size="5" multiple="multiple"<?php echo $flag ?>>
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
                <input name="update" type="submit" value="" class="icon update"
                    onmouseover="TagToTip('ttSpanRefresh' )"
                    onmouseout="UnTip()" />
                <input name="OK" type="hidden" />
            </div>
            
            <div id="controls">      
              <input type="button" value="" class="icon previous"
                onclick="document.location.href='select_task_settings.php'"
                onmouseover="TagToTip('ttSpanBack' )"
                onmouseout="UnTip()" />
              <input type="submit" value="" class="icon next"
                onclick="process()"
                onmouseover="TagToTip('ttSpanForward' )"
                onmouseout="UnTip()" />
            </div>

        </form>

    </div> <!-- content -->

    <div id="rightpanel">

        <div id="info">

            <h3>Quick help</h3>

            <p>In this step, you will select the files from the list of
               available images that will be restored using the image and
               restoration parameters chosen in the previous two steps.</p>
            
            <p>You can use SHIFT- and CTRL-click to select multiple files.</p>

            <p>Click on a file name in any of the fields to get a preview.</p>
            
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

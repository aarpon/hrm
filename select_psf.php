<?php

// php page: select_psf.php

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
  $_SESSION['user']->logout();
  session_unset();
  session_destroy();
  header("Location: " . "login.php"); exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

// fileserver related code
if (!isset($_SESSION['fileserver'])) {
  # session_register('fileserver');
  $name = $_SESSION['user']->name();
  $_SESSION['fileserver'] = new Fileserver($name);
}

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

$psfParam = $_SESSION['setting']->parameter("PSF");
$psf = $psfParam->value();
for ($i = 0; $i < $_SESSION['setting']->numberOfChannels(); $i++) {
  $psfKey = "psf{$i}";
  if (isset($_POST[$psfKey])) {
    $psf[$i] = $_POST[$psfKey];
  } 
}
// get rid of extra values in case the number of channels is changed
$psf = array_slice($psf, 0, $_SESSION['setting']->numberOfChannels() );
$psfParam->setValue($psf);
$_SESSION['setting']->set($psfParam);

if (count($_POST) > 0) {
  // get rid of non relevant values
  $names = array_merge($_SESSION['setting']->microscopeParameterNames(), $_SESSION['setting']->capturingParameterNames());
  foreach ($names as $name) {
    $parameter = $_SESSION['setting']->parameter($name);
    if ($name == "ExcitationWavelength" || $name == "EmissionWavelength" || $name == "PinholeSize") {
      $parameter->setValue(array(NULL, NULL, NULL, NULL, NULL));
    }
    else {
      $parameter->setValue("");
    }
    $_SESSION['setting']->set($parameter);
  }
  $ok = $_SESSION['setting']->checkPointSpreadFunction();
  $message = "            <p class=\"warning\">".$_SESSION['setting']->message()."<br />&nbsp;</p>";
  if ($ok) {
    $saved = $_SESSION['setting']->save();			
    $message = "            <p class=\"warning\">".$_SESSION['setting']->message()."<br />&nbsp;</p>";
    if ($saved) {
      header("Location: " . "select_parameter_settings.php"); exit();
    }
  }
}

$script = "settings.js";

include("header.inc.php");

?>

    <div id="nav">
        <ul>
            <li><a href="select_images.php?exited=exited">exit</a></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpSelectPSFFiles')">help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
        <h3>Parameter Setting - Page 2</h3>
        
        <form method="post" action="select_psf.php" id="select">
        
            <div id="psfselection">
<?php

for ($i = 0; $i < $_SESSION['setting']->numberOfChannels(); $i++) {
  $parameter = $_SESSION['setting']->parameter("PSF");
  $value = $parameter->value();
  $missing = False;
  $files = $_SESSION['fileserver']->allFiles();
  if ($files != null) {
    if (!in_array($value[$i], $files)) {
      $missing = True;
    }

?>
                <p>
                    <span class="title">Ch<?php echo $i ?>:</span>
                    <input name="psf<?php echo $i ?>" type="text" value="<?php echo $value[$i] ?>" class="<?php if ($missing) {echo "psfmissing";} else {echo "psffile";} ?>" readonly="readonly" />
                    <input type="button" onclick="seek('<?php echo $i ?>')" value="browse" />
                </p>
<?php

  }
  else {
    if (!file_exists($_SESSION['fileserver']->sourceFolder())) {

?>
                <p class="info">Source image folder not found! Make sure the folder <?php echo $_SESSION['fileserver']->sourceFolder() ?> exists.</p>
<?php

    }
    else {

?>
                <p class="info">No images found on the server!</p>
<?php

    }
    break;
  }
}

?>
            </div>
            
            <div><input name="OK" type="hidden" /></div>
            
        </form>
        
    </div> <!-- content -->
    
    <div id="stuff">
    
        <div id="info">
        
            <input type="button" value="" class="icon cancel" onclick="document.location.href='image_format.php'" />
            <input type="submit" value="" class="icon apply" onclick="process()" />
            
            <p>Select a PSF file for each of the channels.</p>
            
           <p>
                When you are done with the selection, press the
                <img src="images/apply_help.png" alt="Create" width="22" height="22" /> <b>apply</b>
                button to go back to the Image Format page.
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

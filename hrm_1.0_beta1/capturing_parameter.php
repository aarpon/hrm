<?php

// php page: capturing_parameter.php

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
require_once("./inc/Parameter.inc");
require_once("./inc/Setting.inc");

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
$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

$names = $_SESSION['setting']->capturingParameterNames();
foreach ($names as $name) {
  if (isset($_POST[$name])) {
    $parameter = $_SESSION['setting']->parameter($name);
    // adaption check has to be reset if user changes pixel size values
    if ($name == "CCDCaptorSizeX" || $name == "ZStepSize") {
      if ($parameter->value() != $_POST[$name]) {
        $_SESSION['setting']->setAdaptedParameters(False);
      }
    }
    $parameter->setValue($_POST[$name]);
    $_SESSION['setting']->set($parameter);
  }
}
// manage one pinhole radius per channel
$pinholeParam = $_SESSION['setting']->parameter("PinholeSize");
$pinhole = $pinholeParam->value();
for ($i=1; $i <= $_SESSION['setting']->numberOfChannels(); $i++) {
  $pinholeKey = "PinholeSize{$i}";
  if (isset($_POST[$pinholeKey])) {
    $pinhole[$i] = $_POST[$pinholeKey];
  } 
}
// get rid of extra values in case the number of channels is changed
if (is_array($pinhole)) {
	$pinhole = array_slice($pinhole, 0, $_SESSION['setting']->numberOfChannels() + 1);
}
$pinholeParam->setValue($pinhole);
$_SESSION['setting']->set($pinholeParam);
// TODO refactor
$_SESSION['setting']->setAdaptedParameters(False);

if (isset($_POST["calculate"])) {
  	header("Location: " . "calculate_pixel_size.php");
  	exit();
} 	
if (count($_POST) > 0) {
  foreach ($names as $name) {
    // get rid of non relevant values
    if (!isset($_POST[$name]) && $name != "PinholeSize") {
      $parameter = $_SESSION['setting']->parameter($name);
      $parameter->setValue("");
      $_SESSION['setting']->set($parameter);
    }
  }
  $parameter = $_SESSION['setting']->parameter("MicroscopeType");
  if ($parameter->value() == "widefield") {
    $param = $_SESSION['setting']->parameter("PinholeSize");
    $param->setValue("");
    $_SESSION['setting']->set($param);
  }
  $ok = $_SESSION['setting']->checkCapturingParameter();
  $message = "            <p class=\"warning\">".$_SESSION['setting']->message()."<p>";
  if ($ok) {
    $saved = $_SESSION['setting']->save();			
    $message = "            <p class=\"warning\">".$_SESSION['setting']->message()."</p>";
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
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpCaptor')">help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
        <h3>Parameter Setting - Page 3</h3>
        
        <form method="post" action="capturing_parameter.php" id="select">
        
            <h4>How were these images captured?</h4>
            
            <fieldset class="setting">
            
                <legend>
                    <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=SampleSize')"><img src="images/help.png" alt="?" /></a>
                    sample size
                </legend>
<?php

$parameter = $_SESSION['setting']->parameter("CCDCaptorSizeX");
$value = $parameter->value();
// always ask for pixel size
$textForCaptorSize = "pixel size (nm)";

?>
                <ul>
                
                    <li>
                        <?php echo $textForCaptorSize ?>:
                        <input name="CCDCaptorSizeX" type="text" size="5" value="<?php echo $value ?>" />
                        <input name="calculate" type="submit" value="calculate" style="width:110px; margin: 2px;" />
<?php

// display adaption info
if ($_SESSION['setting']->hasAdaptedParameters()) {
  $adapted_value = $_SESSION['setting']->adaptedLateralSampleSize();
  if ($adapted_value != $value) {
    echo "                        <span class=\"info\">(adapted to ".floor($adapted_value).")</span>";
  }
}

?>
                    </li>
<?php

if ($_SESSION['setting']->isThreeDimensional()) {
      
?>

                    <li>
                        size of the z-step (nm):
                
<?php

  $parameter = $_SESSION['setting']->parameter("ZStepSize");

?>
                        <input name="ZStepSize" type="text" size="5" value="<?php echo $parameter->value() ?>" />
<?php
            
  // display adaption info
  // TODO refactor
  if ($_SESSION['setting']->hasAdaptedParameters()) {
    $sampleSizeZ = $parameter->value();
    $idealSampleSizeZ = $_SESSION['setting']->idealSampleSizeZ();
    if ((0.5 * $idealSampleSizeZ <= $sampleSizeZ) && ($sampleSizeZ <= 1.2 * $idealSampleSizeZ)) {
      echo "                        <span class=\"info\">(adapted to ".floor($idealSampleSizeZ).")</span>";
    }
  }

?>
                    </li>
                    
<?php

}

?>

                </ul>
                
                <a href="javascript:openWindow('http://support.svi.nl/wiki/NyquistCalculator')">
                    Nyquist rate and PSF calculator
                    <img src="images/web.png" alt="external link" />
                </a>
                
            </fieldset>
            
            <fieldset class="setting">
            
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=PixelBinning')"><img src="images/help.png" alt="?" /></a>
                binning:
                
                <select name="Binning" size="1">
<?php

$parameter = $_SESSION['setting']->parameter("Binning");
foreach ($parameter->possibleValues() as $possibleValue) {
  $flag = "";
  if ($possibleValue == $parameter->value()) {
    $flag = " selected=\"selected\"";
  }
?>
                    <option<?php echo $flag ?>><?php echo $possibleValue ?></option>
<?php

}   

?>

                </select>
                
            </fieldset>
            
<?php

if ($_SESSION['setting']->isTimeSeries()) {

?>
            <fieldset class="setting">
            
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=TimeSeries')"><img src="images/help.png" alt="?" /></a>
                time interval (s):
                
<?php

  $parameter = $_SESSION['setting']->parameter("TimeInterval");

?>
                <input name="TimeInterval" type="text" size="5" value="<?php echo $parameter->value() ?>" />
                
            </fieldset>
<?php

}

?>

<?php

if ($_SESSION['setting']->isMultiPointOrSinglePointConfocal()) {

?>
            <fieldset class="setting">
            
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=PinholeRadius')"><img src="images/help.png" alt="?" /></a>
                pinhole radius (nm):
                
<?php

  // manage one pinhole radius per channel
  for ($i = 1; $i <= $_SESSION['setting']->numberOfChannels(); $i++) {

?>
                <input name="PinholeSize<?php echo $i ?>" type="text" size="5" value="<?php if ($i < sizeof($pinhole)) echo $pinhole[$i] ?>" class="multichannelinput" />
<?php

  }

?>
                <p />
                
                <a href="javascript:openWindow('http://support.svi.nl/wiki/BackprojectedPinholeCalculator')">
                    Backprojected pinhole calculator
                    <img src="images/web.png" alt="external link" />
                </a>
                
            </fieldset>
<?php

}

?>

<?php

if ($_SESSION['setting']->isNipkowDisk()) {
      
?>
            <fieldset class="setting">
            
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=PinholeSpacing')"><img src="images/help.png" alt="?" /></a>
                pinhole spacing (micron):
                
<?php

  $parameter = $_SESSION['setting']->parameter('PinholeSpacing');

?>
                <input name="PinholeSpacing" type="text" size="5" value="<?php echo $parameter->value() ?>" />
                
            </fieldset>
<?php

}

?>

            <div><input name="OK" type="hidden" /></div>
            
        </form>
        
    </div> <!-- content -->
    
    <div id="stuff">
    
        <div id="info">
        
            <input type="button" value="" class="icon cancel" onclick="document.location.href='microscope_parameter.php'" />
            <input type="submit" value="" class="icon apply" onclick="process()" />
            
            <p>
		This is the last step. Press <br />the 
                <img src="images/apply_help.png" alt="Apply" width="22" height="22" /> <b>apply</b>
                button to save your parameter settings and to go back to the parameter settings
                page.
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

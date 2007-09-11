<?php

// php page: microscope_parameter.php

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

$names = $_SESSION['setting']->microscopeParameterNames();
foreach ($names as $name) {
  if (isset($_POST[$name])) {
    $parameter = $_SESSION['setting']->parameter($name);
    if ($name == "SampleMedium" && $_POST[$name] == "custom") {
      if (isset($_POST['SampleMediumCustomValue'])) {
        $parameter->setValue($_POST['SampleMediumCustomValue']);
      }
    }
    else $parameter->setValue($_POST[$name]);
    $_SESSION['setting']->set($parameter);
  }	 
}
$excitationParam = $_SESSION['setting']->parameter("ExcitationWavelength");
$emissionParam =  $_SESSION['setting']->parameter("EmissionWavelength");
$excitation = $excitationParam->value();
$emission = $emissionParam->value();
for ($i=1; $i <= $_SESSION['setting']->numberOfChannels(); $i++) {
  $excitationKey = "ExcitationWavelength{$i}";
  $emissionKey = "EmissionWavelength{$i}";
  if (isset($_POST[$excitationKey])) {
    $excitation[$i] = $_POST[$excitationKey];
  } 
  if (isset($_POST[$emissionKey])) {
    $emission[$i] = $_POST[$emissionKey];
  } 
}
// get rid of extra values in case the number of channels is changed
$excitation = array_slice($excitation, 0, $_SESSION['setting']->numberOfChannels() + 1);
$emission = array_slice($emission, 0, $_SESSION['setting']->numberOfChannels() + 1);
$excitationParam->setValue($excitation);
$emissionParam->setValue($emission);
$_SESSION['setting']->set($excitationParam);
$_SESSION['setting']->set($emissionParam); 

if (count($_POST) > 0) {
  $ok = $_SESSION['setting']->checkMicroscopeParameter();
  $message = "            <p class=\"warning\">".$_SESSION['setting']->message()."</p>\n";
  if ($ok) {header("Location: " . "capturing_parameter.php"); exit();}
}

$script = "settings.js";

include("header.inc.php");

?>

    <div id="nav">
        <ul>
            <li><a href="select_images.php?exited=exited">exit</a></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpOptics')">help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
        <h3>Parameter Setting - Page 2</h3>
        
        <form method="post" action="" id="select">
        
            <h4>How did you set up your microscope?</h4>
            
            <fieldset class="setting">
            
                <legend>
                    <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=MicroscopeType')"><img src="images/help.png" alt="?" /></a>
                    microscope type
                </legend>
<?php

$parameter = $_SESSION['setting']->parameter("MicroscopeType");
$possibleValues = $parameter->possibleValues();
foreach($possibleValues as $possibleValue) {
  $flag = "";
  if ($possibleValue == $parameter->value()) $flag = "checked=\"checked\" ";

?>
                <input type="radio" name="MicroscopeType" value="<?php echo $possibleValue ?>" <?php echo $flag ?>/><?php echo $possibleValue ?>
                
                <br />
<?php

}

?>
            </fieldset>
            
            <fieldset class="setting">
            
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=NumericalAperture')"><img src="images/help.png" alt="?" /></a>
                numerical aperture:
<?php

$parameter = $_SESSION['setting']->parameter("NumericalAperture");
// display adaption info
//if (($_SESSION['setting']->isWidefield() || $_SESSION['setting']->isTwoPhoton()) && $_SESSION['setting']->hasAdaptedParameters()) {
//    echo "&nbsp;<span style=\"color: red\">(adapted to ".$_SESSION['setting']->adaptedNumericalAperture().")</span>";
//}

?>
                <input name="NumericalAperture" type="text" size="5" value="<?php echo $parameter->value() ?>" />
                
            </fieldset>
            
            <fieldset class="setting">
            
                <legend>
                    <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=WaveLength')"><img src="images/help.png" alt="?" /></a>
                    light
                </legend>
                
                <ul>
                    <li>
                        excitation wavelength for channel (nm)
                        <ol>
<?php

for ($i = 1; $i <= $_SESSION['setting']->numberOfChannels(); $i++) {

?>
                            <li><input name="ExcitationWavelength<?php echo $i ?>" type="text" size="5" value="<?php if ($i < sizeof($excitation)) echo $excitation[$i] ?>" class="multichannelinput" /></li>
<?php

}

?>
                        </ol>
                    </li>

                    <li>
                        emission wavelength for channel (nm)
                        <ol>

<?php

for ($i=1; $i <= $_SESSION['setting']->numberOfChannels(); $i++) {

?>
                            <li><input name="EmissionWavelength<?php echo $i ?>" type="text" size="5" value="<?php if ($i < sizeof($emission)) echo $emission[$i] ?>" class="multichannelinput" /></li>
<?php

}

?>
                        </ol>
                    </li>
                </ul>
                
            </fieldset>
            
            <fieldset class="setting">
            
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=ObjectiveMagnification')"><img src="images/help.png" alt="?" /></a>
                objective magnification:
                
                <select name="ObjectiveMagnification" size="1">
<?php

$parameter = $_SESSION['setting']->parameter("ObjectiveMagnification");
foreach ($parameter->possibleValues() as $possibleValue) {
  $flag = "";
  if ($possibleValue == $parameter->value()) $flag = " selected=\"selected\"";

?>
                    <option<?php echo $flag ?>><?php echo $possibleValue ?></option>
<?php

}

?>
                </select>
                X
                
            </fieldset>
            
            <fieldset class="setting">
            
                <legend>
                    <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=LensImmersionMedium')"><img src="images/help.png" alt="?" /></a>
                    objective type
                </legend>
                
<?php

$parameter = $_SESSION['setting']->parameter("ObjectiveType");
$possibleValues = $parameter->possibleValues();
sort($possibleValues);
foreach ($possibleValues as $possibleValue) {
  $flag = "";
  if ($possibleValue == $parameter->value()) $flag = " checked=\"checked\"";

?>
                <input name="ObjectiveType" type="radio" value="<?php echo $possibleValue ?>" <?php echo $flag ?>/><?php echo $possibleValue ?>
                
<?php

}

?>
                
            </fieldset>
            
            <!--
            
            <fieldset class="setting">
            
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpCMount')"><img src="images/help.png" alt="?" /></a>
                cmount (0-1):
                
<?php

//$parameter = $_SESSION['setting']->parameter("CMount");

?>
                <input name="CMount" type="text" size="5" value="<?php echo $parameter->value() ?>" />
                
            </fieldset>
            
            <fieldset class="setting">
            
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpTubeFactor')"><img src="images/help.png" alt="?" /></a>
                tube factor (max. 2):
                
<?php

//$parameter = $_SESSION['setting']->parameter("TubeFactor");

?>
                <input name="TubeFactor" type="text" size="5" value="<?php echo $parameter->value() ?>" />
                
            </fieldset>
            
            -->
            
            <fieldset class="setting">
            
                <legend>
                    <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=SpecimenEmbeddingMedium')"><img src="images/help.png" alt="?" /></a>
                    sample medium
                </legend>
                
<?php

$parameter = $_SESSION['setting']->parameter("SampleMedium");
$default = False;
foreach ($parameter->possibleValues() as $possibleValue) {
  $flag = "";
  if ($possibleValue == $parameter->value()) {
    $flag = " checked=\"checked\"";
    $default = True;
  }
  $tvalue = $_SESSION['setting']->translation("SampleMedium", $possibleValue);

?>
                <input name="SampleMedium" type="radio" value="<?php echo $possibleValue ?>"<?php echo $flag ?> /><?php echo $possibleValue ?> <span class="title">[<?php echo $tvalue ?>]</span>
                
                <br />
<?php

}

$value = "";
$flag = "";
if (!$default) {
  $value = $parameter->value();
  $flag = " checked=\"checked\"";
}

?>
                <input name="SampleMedium" type="radio" value="custom"<?php echo $flag ?> /><input name="SampleMediumCustomValue" type="text" size="5" value="<?php echo $value ?>" onclick="this.form.SampleMedium[2].checked=true" />
                
            </fieldset>
            
            <div><input name="OK" type="hidden" /></div>
            
        </form>
        
    </div> <!-- content -->
    
    <div id="stuff">
    
        <div id="info">
        
            <input type="button" value="" class="icon cancel" onclick="document.location.href='image_format.php'" />
            <input type="submit" value="" class="icon apply" onclick="process()" />
            
            <p>
                Please choose or fill in the parameter values on the left 
                side.
            </p>
            
            <p>
		You will find more detailed information on the parameters
		by clicking on <br /> the 
                <img src="images/help.png" alt="Help" width="22" height="22" /> <b>help</b> 
                buttons or following the help link in the navigation bar.
            </p>
            
            <p>
                When you are ready, press the <br />
                <img src="images/apply_help.png" alt="Apply" width="22" height="22" /> <b>apply</b>
                button to go to the next <br />step
                or <img src="images/cancel_help.png" alt="Cancel" width="22" height="22" /> <b>cancel</b>
                to discard your changes.
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

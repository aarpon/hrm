<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc");
require_once("./inc/Parameter.inc");
require_once("./inc/Setting.inc");

session_start();

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
for ($i=0; $i < $_SESSION['setting']->numberOfChannels(); $i++) {
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
//$excitation = array_slice($excitation, 1,
//        $_SESSION['setting']->numberOfChannels() );
//$emission = array_slice($emission, 1, 
//        $_SESSION['setting']->numberOfChannels() );
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
    <!--
      Tooltips
    -->
    <span id="ttSpanBack">Go back to previous page.</span>  
    <span id="ttSpanCancel">Abort editing and go back to the image parameters selection page. All changes will be lost!</span>  
    <span id="ttSpanForward">Continue to next page.</span>  
    
    <div id="nav">
        <ul>
            <li><?php echo $_SESSION['user']->name(); ?></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpOptics')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
        <h3>Optical parameters / 1</h3>
        
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
              <legend> 
		<a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=NumericalAperture')"><img src="images/help.png" alt="?" /></a>
		numerical aperture
              </legend>
              <ul>
                <li>NA:
<?php

$parameter = $_SESSION['setting']->parameter("NumericalAperture");
// display adaption info
//if (($_SESSION['setting']->isWidefield() || $_SESSION['setting']->isTwoPhoton()) && $_SESSION['setting']->hasAdaptedParameters()) {
//    echo "&nbsp;<span style=\"color: red\">(adapted to ".$_SESSION['setting']->adaptedNumericalAperture().")</span>";
//}

?>
                <input name="NumericalAperture" type="text" size="5" value="<?php echo $parameter->value() ?>" />
              
                </li>
              </ul>
            </fieldset>
            
            <fieldset class="setting">
            
                <legend>
                    <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=WaveLength')"><img src="images/help.png" alt="?" /></a>
                    wavelengths
		</legend>
		<ul>
		<li>excitation (nm):

		<div class="multichannel">
<?php

for ($i = 0; $i < $_SESSION['setting']->numberOfChannels(); $i++) {

?>
	<span class="nowrap">Ch<?php echo $i ?>:<span class="multichannel"><input name="ExcitationWavelength<?php echo $i ?>" type="text" size="8" value="<?php if ($i <= sizeof($excitation)) echo $excitation[$i] ?>" class="multichannelinput" /></span>&nbsp;</span>
<?php

}

?>
</div></li>
	<li>emission (nm):
	
	<div class="multichannel">
<?php

for ($i=0; $i < $_SESSION['setting']->numberOfChannels(); $i++) {

?>
	<span class="nowrap">Ch<?php echo $i ?>:<span class="multichannel"><input name="EmissionWavelength<?php echo $i ?>" type="text" size="8" value="<?php if ($i <= sizeof($emission)) echo $emission[$i] ?>" class="multichannelinput" /></span>&nbsp;</span>
<?php

}

?>
        </div></li>
        </ul>
                
        </fieldset>

<!--          
            <fieldset class="setting">
            
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=ObjectiveMagnification')"><img src="images/help.png" alt="?" /></a>
                objective magnification:
                
                <select name="ObjectiveMagnification" size="1">
<?php

//$parameter = $_SESSION['setting']->parameter("ObjectiveMagnification");
//foreach ($parameter->possibleValues() as $possibleValue) {
//  $flag = "";
//  if ($possibleValue == $parameter->value()) $flag = " selected=\"selected\"";

?>
                  <option<?php //echo $flag ?>><?php //echo $possibleValue ?></option>
<?php

//}

?>

                </select>
                X
                
            </fieldset>
-->

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
  if ( $value != "" ) {
    $flag = " checked=\"checked\"";
  }
}

?>
                <input name="SampleMedium" type="radio" value="custom"<?php echo $flag ?> /><input name="SampleMediumCustomValue" type="text" size="5" value="<?php echo $value ?>" onclick="this.form.SampleMedium[2].checked=true" />
                
            </fieldset>
            
            <div><input name="OK" type="hidden" /></div>
            
            <div id="controls">
              <input type="button" value="" class="icon previous"
                  onmouseover="TagToTip('ttSpanBack' )"
                  onmouseout="UnTip()"
                  onclick="document.location.href='image_format.php'" />
              <input type="button" value="" class="icon up"
                  onmouseover="TagToTip('ttSpanCancel' )"
                  onmouseout="UnTip()"
                  onclick="document.location.href='select_parameter_settings.php'" />
              <input type="submit" value="" class="icon next"
                  onmouseover="TagToTip('ttSpanForward' )"
                  onmouseout="UnTip()"
                  onclick="process()" />
            </div>

        </form>
        
    </div> <!-- content -->
    
    <div id="rightpanel">
    
        <div id="info">
          
          <h3>Quick help</h3>
            
            <p>On this page you specify the parameters for the optical setup
            of your experiment.</p>
            
            <p>These parameters comprise the microscope type,
            the numerical aperture of the objective, the wavelenght of the used
            fluorophores, and the refractive indices of the sample medium and of
            the objective-embedding medium.</p>

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

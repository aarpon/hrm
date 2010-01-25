<?php

// php page: image_format.php

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

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
       header("Location: " . "login.php"); exit();
}

if (!isset($_SESSION['setting'])) {
       # session_register('setting'); 		   
       $_SESSION['setting'] = new ParameterSetting();
}	

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

// TODO refactor from here
$names = $_SESSION['setting']->imageParameterNames();
foreach ($names as $name) {
        if (isset($_POST[$name])) {
               $parameter = $_SESSION['setting']->parameter($name);
               // adaption check has to be reset if user changes pixel size values
               if ($name == "ImageFileFormat") {
                   if ($parameter->value() != $_POST[$name]) {
                          $_SESSION['setting']->setAdaptedParameters(False);
                   }
               }
               $parameter->setValue($_POST[$name]);
               $_SESSION['setting']->set($parameter);
               // set IsMultiChannel parameter value
               if ($name == "NumberOfChannels") {
                   $parameter = $_SESSION['setting']->parameter("IsMultiChannel");
                   if ($_POST[$name] > 1) {
                          $parameter->setValue("True");
                   }
                   else {
                          $parameter->setValue("False");
                   }
                   $_SESSION['setting']->set($parameter);
               }
        }
}

// set PointSpreadFunction parameter value
if (isset($_POST["PointSpreadFunction"])) {
  $parameter = $_SESSION['setting']->parameter("PointSpreadFunction");
  $parameter->setValue($_POST["PointSpreadFunction"]);
  $_SESSION['setting']->set($parameter);
  if ($_POST["PointSpreadFunction"] == "theoretical") {
     // reset PSF parameter value
     $parameter = $_SESSION['setting']->parameter("PSF");
     $parameter->setValue(array(NULL, NULL, NULL, NULL, NULL));
     $_SESSION['setting']->set($parameter);
  }
}

if (count($_POST)>0) {
  if (!isset($_POST["ImageGeometry"]) || $_POST["ImageGeometry"] == "") {
    $parameter = $_SESSION['setting']->parameter("ImageGeometry");
    $parameter->setValue("multi_XYZ");
    $_SESSION['setting']->set($parameter);
  }
  if (!isset($_POST["NumberOfChannels"]) && ($_POST["ImageFileFormat"] == "tiff-series")) {
      $parameter = $_SESSION['setting']->parameter("NumberOfChannels");
      $parameter->setValue("1");
      $_SESSION['setting']->set($parameter);
      $_POST["NumberOfChannels"] == "1";
  }
  if (!isset($_POST["ImageFileFormat"])) {
    $ok = False;
    $message = "<p class=\"warning\">Please choose a file format!</p>";
  }
  elseif (!isset($_POST["NumberOfChannels"]) && ($_POST["ImageFileFormat"] != "tiff-series")) {
    $ok = False;
    $message = "<p class=\"warning\">Please specify the number of channels!</p>";
  }
  elseif (!isset($_POST["PointSpreadFunction"])) {
    $ok = False;
    $message = "<p class=\"warning\">Please indicate whether you would like to calculate a theoretical PSF or use an existing measured one</p>";
  } else {
    $ok = $_SESSION['setting']->checkImageParameter();
    $message = $_SESSION['setting']->message();
  }

  if ($ok) {
    header("Location: " . "microscope_parameter.php"); exit();
  }
}
// TODO refactor until here

$script = "settings.js";

include("header.inc.php");

?>
    <!--
      Tooltips
    -->
    <span id="ttSpanCancel">Abort editing and go back to the image parameters selection page. All changes will be lost!</span>  
    <span id="ttSpanForward">Continue to next page.</span>  

    <div id="nav">
        <ul>
            <li><?php echo $_SESSION['user']->name(); ?></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpImageFormat')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
    </div>
    
    <div id="content">

        <h3>Image format and PSF modality</h3>
        
        <form method="post" action="" id="select">
        
            <h4>What image format will be processed with these settings?</h4>
            
            <fieldset class="setting">
            
                <legend>
                    <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=FileFormats')"><img src="images/help.png" alt="?" /></a>
                    image format
                </legend>
                
<?php

// new file formats support
$parameter = $_SESSION['setting']->parameter("ImageFileFormat");
$values = $_SESSION['setting']->values("ImageFileFormat");
$geometryFlag = "";
$channelsFlag = "";
sort($values);
foreach($values as $value) {
  if (stristr($value, "tiff")) {
    continue;
  }
  if (stristr($value, "hdf5")) {
    $version = getHucoreVersionAsInteger( $enable_code_for_huygens );
    // HDF5 is supported only from Huygens 3.5.0
    if ( $version < 3050000 ) {
      continue;
    }
  }
  $translation = $_SESSION['setting']->translation("ImageFileFormat", $value);
  $event = " onclick=\"javascript:release()\"";
  if ($value == "lsm-single" || $value == "tiff-single") {
    $event = " onclick=\"javascript:forceGeometry()\"";
  }
  else if ($value == "tiff-series") $event = " onclick=\"javascript:fixGeometryAndChannels('multi_XYZ', '1')\"";
  $flag = "";
  if ($value == $parameter->value()) {
    $flag = " checked=\"checked\"";
    if ($value == "lsm-single" || $value == "tiff-single") {
      $geometryFlag = "disabled=\"disabled\" ";
    }
    else if ($value == "tiff-series") {
      $geometryFlag = "disabled=\"disabled\" ";
      $channelsFlag = "disabled=\"disabled\" ";
    }
  }
  
?>
                <input name="ImageFileFormat" type="radio" value="<?php echo $value ?>"<?php echo $event ?><?php echo $flag ?> /><?php echo $translation ?>
                
                <br />
<?php

}

?>
                <h4>TIFF (*.tif, *.tiff)</h4>
<?php

foreach($values as $value) {
  if (!stristr($value, "tiff")) {
    continue;
  }
  $translation = $_SESSION['setting']->translation("ImageFileFormat", $value);
  $event = " onclick=\"javascript:release()\"";
  if ($value == "lsm-single" || $value == "tiff-single") {
    $event = " onclick=\"javascript:forceGeometry()\"";
  }
  else if ($value == "tiff-series") $event = " onclick=\"javascript:fixGeometryAndChannels('multi_XYZ', '1')\"";
  $flag = "";
  if ($value == $parameter->value()) {
    $flag = " checked=\"checked\"";
    if ($value == "lsm-single" || $value == "tiff-single") {
      $geometryFlag = "disabled=\"disabled\" ";
    }
    else if ($value == "tiff-series") {
      $geometryFlag = "disabled=\"disabled\" ";
      $channelsFlag = "disabled=\"disabled\" ";
    }
  }
  
?>
                <input name="ImageFileFormat" type="radio" value="<?php echo $value ?>"<?php echo $event ?><?php echo $flag ?> /><?php echo $translation ?>
                
                <br />
<?php

}

?>

            </fieldset>
            
            <fieldset id="geometry" class="setting"<?php if ($geometryFlag != "") echo " style=\"color: grey\"" ?>>
            
                <legend>
                    <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=ImageGeometry')"><img src="images/help.png" alt="?" /></a>
                    image geometry
                </legend>
                
<?php

$aParameter = $_SESSION['setting']->parameter('ImageGeometry');
$possibleValues = $aParameter->possibleValues();
foreach($possibleValues as $possibleValue) {
  $value = "multi_" . $possibleValue;
  $flag = "";
  if (!($parameter->value() == "lsm-single" || $parameter->value() == "tiff-single") && $possibleValue == $aParameter->value())
    $flag = "checked=\"checked\" ";

?>
                <input name="ImageGeometry" type="radio" value="<?php echo $value ?>" <?php echo $geometryFlag ?><?php echo $flag ?>/><?php echo $possibleValue ?>
                
<?php

}

?>

            </fieldset>
            
            <fieldset id="channels" class="setting"<?php if ($channelsFlag != "") echo " style=\"color: grey\"" ?>>
            
                <legend>
                    <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=NumberOfChannels')"><img src="images/help.png" alt="?" /></a>
                    number of channels
                </legend>
                
<?php

function check($value) {
  $parameter = $_SESSION['setting']->parameter("NumberOfChannels");
  if ($value == $parameter->value()) echo "checked=\"checked\" ";
  return "";
}

?>
                <input name="NumberOfChannels" type="radio" value="1" <?php echo $channelsFlag ?><?php check(1) ?>/>1
                <input name="NumberOfChannels" type="radio" value="2" <?php echo $channelsFlag ?><?php check(2) ?>/>2
                <input name="NumberOfChannels" type="radio" value="3" <?php echo $channelsFlag ?><?php check(3) ?>/>3
                <input name="NumberOfChannels" type="radio" value="4" <?php echo $channelsFlag ?><?php check(4) ?>/>4
                <input name="NumberOfChannels" type="radio" value="5" <?php echo $channelsFlag ?><?php check(5) ?>/>5
                
            </fieldset>
            
<?php

// manage measured PSF
$parameter = $_SESSION['setting']->parameter("PointSpreadFunction");

?>
            <?php
            $turnOnPSFAdaptationOnClick  = " onclick=\"javascript:fixCoverslip( false )\"";
            $turnOffPSFAdaptationOnClick = " onclick=\"javascript:fixCoverslip( true )\"";
            ?>
            
            <h4>Would you like to use an existing measured PSF obtained from bead images or a theoretical PSF generated from explicitly specified parameters?</h4>
            
            <fieldset class="setting">
            
                <legend>
                    <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=PointSpreadFunction')"><img src="images/help.png" alt="?" /></a>
                    PSF
                </legend>
                
                <input type="radio" name="PointSpreadFunction" value="theoretical" <?php if ($parameter->value() == "theoretical") echo "checked=\"checked\""?> <?php echo $turnOnPSFAdaptationOnClick ?>/><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=TheoreticalPsf')"><img src="images/help.png" alt="?" /></a>Theoretical
                
                <input type="radio" name="PointSpreadFunction" value="measured" <?php if ($parameter->value() == "measured") echo "checked=\"checked\"" ?> <?php echo $turnOffPSFAdaptationOnClick ?>/><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=ExperimentalPsf')"><img src="images/help.png" alt="?" /></a>Measured
                
            </fieldset>

            <div id="controls">
              <input type="button" value="" class="icon up"
                  onmouseover="TagToTip('ttSpanCancel' )"
                  onmouseout="UnTip()"
                  onclick="document.location.href='select_parameter_settings.php'" />
              <input type="submit" value="" class="icon next"
                  onmouseover="TagToTip('ttSpanForward' )"
                  onmouseout="UnTip()"
                  onclick="process()" />
            </div>
            
            <div><input name="OK" type="hidden" /></div>
            
        </form>
        
    </div> <!-- content -->
    
    <div id="rightpanel">
    
        <div id="info">
            
            <h3>Quick help</h3>
            
            <p>Here you are asked to provide information on format and
            geometry for the files you want to restore.</p>
            
            <p>Moreover, you must define whether you want to use a theoretical
            PSF, or if you instead want to use a measured PSF you distilled
            with the Huygens software.</p>

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

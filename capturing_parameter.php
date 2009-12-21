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

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}
$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

$parameter = $_SESSION['setting']->parameter("NumericalAperture");
$na = $parameter->value();
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
for ($i=0; $i < $_SESSION['setting']->numberOfChannels(); $i++) {
  $pinholeKey = "PinholeSize{$i}";
  if (isset($_POST[$pinholeKey])) {
    $pinhole[$i] = $_POST[$pinholeKey];
  } 
}
// get rid of extra values in case the number of channels is changed
if (is_array($pinhole)) {
	$pinhole = array_slice($pinhole, 0, $_SESSION['setting']->numberOfChannels() );
}
$pinholeParam->setValue($pinhole);
$_SESSION['setting']->set($pinholeParam);
// TODO refactor
$_SESSION['setting']->setAdaptedParameters(False);

// deal with the computation of theoretical pixel size from microscope parameters
//if (isset($_POST["calculate"])) {
//  	header("Location: " . "calculate_pixel_size.php");	// send a raw HTTP header
//  	exit();
//}

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

      // Depending on the selection of the PSF (theoretical) and on a possible
      // refractive index mismatch (i.e. larger than 1%) between the sample 
      // medium and the objective medium we might have to show an aberration
      // correction page. Otherwise, we make sure to turn off the correction
      // and proceed to the PSF selection page.
      
      // First check the selection of the PSF
      $PSF = $_SESSION['setting']->parameter( 'PointSpreadFunction' )->value( );
      if ($PSF == 'measured' ) {
          $pageToGo = 'select_psf.php';
          // Make sure to turn off the correction
          $_SESSION['setting']->parameter( 'AberrationCorrectionNecessary' )->setValue( '0' );
	  $_SESSION['setting']->parameter( 'PerformAberrationCorrection' )->setValue( '0' );
          $_SESSION['setting']->save();
      } else {
        // Get the refractive indices
        $sampleRI    = $_SESSION['setting']->parameter( 'SampleMedium' )->translatedValue( );
        $objectiveRI = $_SESSION['setting']->parameter( 'ObjectiveType' )->translatedValue( );              

        // Calculate the deviation
        $deviation = abs( $sampleRI - $objectiveRI ) / $objectiveRI;
              
        // Do we need to go to the aberration correction page? We 
        if ( $deviation < 0.01 ) {
          $pageToGo = 'select_parameter_settings.php';
          // Make sure to turn off the correction
          $_SESSION['setting']->parameter( 'AberrationCorrectionNecessary' )->setValue( '0' );
          $_SESSION['setting']->parameter( 'PerformAberrationCorrection' )->setValue( '0' );
          $_SESSION['setting']->save();
        } else {
          $pageToGo = 'aberration_correction.php';
          $_SESSION['setting']->parameter( 'AberrationCorrectionNecessary' )->setValue( '1' );
          $_SESSION['setting']->save();
        }
      }
      header("Location: " . $pageToGo ); exit();
    }
  }
}


$script = "settings.js";

include("header.inc.php");

$nyquist = $_SESSION['setting']->calculateNyquistRate();

?>

    <div id="nav">
        <ul>
            <li><?php echo $_SESSION['user']->name(); ?></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpCaptor')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
        <h3>Optical parameters / 2</h3>
        
        <form method="post" action="capturing_parameter.php" id="select">
        
            <h4>How were these images captured?</h4>
            
            <fieldset class="setting">
            
                <legend>
                    <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=SampleSize')"><img src="images/help.png" alt="?" /></a>
                    voxel size
                </legend>
<?php

$parameter = $_SESSION['setting']->parameter("CCDCaptorSizeX");
$value = $parameter->value();
// always ask for pixel size
$textForCaptorSize = "xy pixel size (nm)";

?>
                <ul>
                
                    <li>
                        <?php echo $textForCaptorSize ?>:
                        <input name="CCDCaptorSizeX" type="text" size="5" value="<?php echo $value ?>" /> <br/>
			<?php
                  // The calculation of pixel size from CCD chip makes sense only for widefield microscopes
                  $microscopeType  = $_SESSION['setting']->parameter('MicroscopeType' );
                  $microscopeValue = $microscopeType->value( );
                  if ( $microscopeValue == 'widefield' ) {
            ?>
            <a href="calculate_pixel_size.php">calculate</a> from CCD pixel size<br/>
                        <!-- <input name="calculate" type="submit" value="calculate" style="width:110px; margin: 2px;" /> -->
            <br/>
            <?php
                  }
            ?>
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
                    On-line Nyquist rate and PSF calculator
                    <img src="images/web.png" alt="external link" />
                </a>
                
            </fieldset>
            
<!--	    
            <fieldset class="setting">
            
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=PixelBinning')"><img src="images/help.png" alt="?" /></a>
                binning:
                
                <select name="Binning" size="1">
<?php

//$parameter = $_SESSION['setting']->parameter("Binning");
//foreach ($parameter->possibleValues() as $possibleValue) {
//  $flag = "";
//  if ($possibleValue == $parameter->value()) {
//    $flag = " selected=\"selected\"";
//  }
?>
                    <option<?php //echo $flag ?>><?php //echo $possibleValue ?></option>
<?php

//}   

?>

                </select>
                
            </fieldset>
-->
            
<?php

if ($_SESSION['setting']->isTimeSeries()) {

?>
            <fieldset class="setting">
           	<legend> 
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=TimeSeries')"><img src="images/help.png" alt="?" /></a>
                time interval
                </legend> <p />Time interval (s):
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
            
              <legend>
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=PinholeRadius')"><img src="images/help.png" alt="?" /></a>
                pinhole radius
	      </legend>
		<p />back-projected pinhole radius (nm):
            <?php
              if ( $_SESSION['setting']->numberOfChannels() > 1 ) {
              ?>  <p /> <?php
            }
            ?>
<div class="multichannel">
<?php

  // manage one pinhole radius per channel
  for ($i = 0; $i < $_SESSION['setting']->numberOfChannels(); $i++) {

?>
	<span class="nowrap">Ch<?php echo $i ?>:<span class="multichannel"><input name="PinholeSize<?php echo $i ?>" type="text" size="8" value="<?php if ($i < sizeof($pinhole)) echo $pinhole[$i] ?>" class="multichannelinput" /></span>&nbsp;</span>
<?php

  }

?></div>
                <p />
                
                <a href="calculate_bp_pinhole.php?na=<?php echo $na;?>">
                    Backprojected pinhole calculator
                </a>
                
            </fieldset>
<?php

}

?>

<?php

if ($_SESSION['setting']->isNipkowDisk()) {
      
?>
            <fieldset class="setting">
              <legend>            
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=PinholeSpacing')"><img src="images/help.png" alt="?" /></a>
                pinhole spacing
	      </legend>
 		<p />backprojected pinhole spacing (micron):
<?php

  $parameter = $_SESSION['setting']->parameter('PinholeSpacing');

?>
                <input name="PinholeSpacing" type="text" size="5" value="<?php echo $parameter->value() ?>" />
                <p />
                
                <a href="calculate_bp_pinhole.php?na=<?php echo $na;?>">
                    Backprojected pinhole calculator
                </a>
                
            </fieldset>
<?php

}

?>

            <div><input name="OK" type="hidden" /></div>

            <div id="controls">
              <input type="button" value="" class="icon previous" onclick="document.location.href='microscope_parameter.php'" />
              <input type="button" value="" class="icon up" onclick="document.location.href='select_parameter_settings.php'" />
              <input type="submit" value="" class="icon next" onclick="process()" />
            </div>
            
        </form>
        
    </div> <!-- content -->
    
    <div id="rightpanel">
    
        <div id="info">

          <h3>Quick help</h3>
          
          <p>Here you have to enter the voxel size as it was set during the
          image acquisition. Remember that the closer the acquisition sampling 
          is to the Nyquist <b>ideal sampling rate</b>, the better both the input
          and the deconvolved images will be!</p>
          <p>With current optical parameters, the ideal pixel size is
          <span style="background-color:yellow"><?php echo $nyquist[0];?>
          nm</span><?php
          if ($_SESSION['setting']->isThreeDimensional() ) {
            echo " and the ideal z-step is <span style=\"background-color:yellow\">".
            $nyquist[1]." nm</span>";
          }
          ?>.</p>
          
          <p>The Huygens Remote Manager will not try to stop you from running a
          deconvolution on undersampled data (i.e. with a sampling rate much
          larger than the ideal), but do not expect meaningful results!</p>

          <p>
            When you are ready, press the
            <img src="images/next_help.png" alt="Apply" width="22" height="22" />
            <b>next</b> button to go to the next step.</p>
            
          <p>
            You can also press the
            <img src="images/previous_help.png" alt="Apply" width="22" height="22" />
            <b>previous</b> button to go back one step,
            or <img src="images/up_help.png" alt="Cancel" width="22" height="22" /> <b>up</b>
            to discard your changes and return to the parameter selection page.
          </p>
        
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

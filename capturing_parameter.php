<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc");
require_once("./inc/Parameter.inc");
require_once("./inc/Setting.inc");
require_once("./inc/Util.inc");

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

// Here we try to figure out whether we have to continue after this page or not.
// If the user chose to use a measured PSF or if there is a refractive index
// mismatch, there will be more pages after this. Otherwise, we save the
// settings to the database and go back to select_parameter_settings.php.
// Besides the destination page, also the control buttons will need to be
// adapted.

// First check the selection of the PSF
$PSF = $_SESSION['setting']->parameter( 'PointSpreadFunction' )->value( );
if ($PSF == 'measured' ) {
  $pageToGo = 'select_psf.php';
  $controlIconToShow = "icon next";
  $controlTextToShow = "Continue to next page.";
  $saveParametersToDB = false;
  // Make sure to turn off the correction
  $_SESSION['setting']->parameter( 'AberrationCorrectionNecessary' )->setValue( '0' );
  $_SESSION['setting']->parameter( 'PerformAberrationCorrection' )->setValue( '0' );
} else {
  // Get the refractive indices
  $sampleRI    = $_SESSION['setting']->parameter( 'SampleMedium' )->translatedValue( );
  $objectiveRI = $_SESSION['setting']->parameter( 'ObjectiveType' )->translatedValue( );              

  // Calculate the deviation
  $deviation = abs( $sampleRI - $objectiveRI ) / $objectiveRI;
              
  // Do we need to go to the aberration correction page? We 
  if ( $deviation < 0.01 ) {
    $pageToGo = 'select_parameter_settings.php';
    $controlIconToShow = "icon save";
    $controlTextToShow = "Save and return to the image parameters selection page.";
    $saveParametersToDB = true;
    // Make sure to turn off the correction
    $_SESSION['setting']->parameter( 'AberrationCorrectionNecessary' )->setValue( '0' );
    $_SESSION['setting']->parameter( 'PerformAberrationCorrection' )->setValue( '0' );
  } else {
    $pageToGo = 'aberration_correction.php';
    $controlIconToShow = "icon next";
    $controlTextToShow = "Continue to next page.";
    $_SESSION['setting']->parameter( 'AberrationCorrectionNecessary' )->setValue( '1' );
    $saveParametersToDB = false;
  }
}


// Process the posted parameters
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
    if ( $saveParametersToDB == true ) {
      $saved = $_SESSION['setting']->save();			
      $message = "            <p class=\"warning\">".$_SESSION['setting']->message()."</p>";
      if ($saved) {
        header("Location: " . $pageToGo ); exit();
      }
    } else {
        header("Location: " . $pageToGo ); exit();
    }
  }
}

// Javascript includes
$script = array( "settings.js", "quickhelp/help.js",
                "quickhelp/capturingParameterHelp.js" );

include("header.inc.php");

$nyquist = $_SESSION['setting']->calculateNyquistRate();

?>
    <!--
      Tooltips
    -->
    <?php
      if ( $_SESSION['setting']->isWidefield() || $_SESSION['setting']->isMultiPointConfocal() ) {
    ?>
    <span id="ttSpanPixelSizeFromCCD">Calculate the image pixel size from the CCD pixel size.</span>
    <?php
      }
    ?>
    <?php
      if ( $_SESSION['setting']->isMultiPointOrSinglePointConfocal() ) {
    ?>
    <span id="ttSpanPinholeRadius">Calculate the back-projected pinhole radius for your microscope.</span>
    <?php
        if ($_SESSION['setting']->isNipkowDisk()) {
    ?>
    <span id="ttSpanPinholeSpacing">Calculate the back-projected pinhole spacing for your microscope.</span>
    <?php
      }
    }
    ?>
    <span id="ttSpanNyquist">Check your sampling with the online Nyquist calculator.</span>
    <span id="ttSpanBack">Go back to previous page.</span>  
    <span id="ttSpanCancel">Abort editing and go back to the image parameters selection page. All changes will be lost!</span>  
    <span id="ttSpanForward"><?php echo $controlTextToShow; ?></span>

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
            
            <fieldset class="setting"
              onmouseover="javascript:changeQuickHelp( 'voxel' );" >
            
                <legend>
                    <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=SampleSize')"><img src="images/help.png" alt="?" /></a>
                    voxel size
                </legend>

				<p class="message_small">Calculated from current optical parameters, the (Nyquist) ideal pixel size is
				  <span style="background-color:yellow"><?php echo $nyquist[0];?>nm</span>
				  <?php
					if ($_SESSION['setting']->isThreeDimensional() ) {
					  echo " and the ideal z-step is <span style=\"background-color:yellow\">".
					  $nyquist[1]." nm</span>";
					}
				  ?>.</p>
<?php

$parameter = $_SESSION['setting']->parameter("CCDCaptorSizeX");
$value = $parameter->value();
// always ask for pixel size
$textForCaptorSize = "pixel size (nm)";

?>
                <ul>
                
                    <li>
                        <?php echo $textForCaptorSize ?>:
                        <input name="CCDCaptorSizeX" type="text" size="5" value="<?php echo $value ?>" /> <br/>
			<?php
                  // The calculation of pixel size from CCD chip makes sense only for widefield microscopes
                  if ( $_SESSION['setting']->isWidefield() || $_SESSION['setting']->isMultiPointConfocal() ) {
            ?>
            
            
            
            <a href="calculate_pixel_size.php"
              onmouseover="TagToTip('ttSpanPixelSizeFromCCD' )"
              onmouseout="UnTip()" >
              <img src="images/calc_small.png" alt="" />
              Calculate from CCD pixel size
            </a>

            <?php
                  }
            ?>
                    </li>
<?php

if ($_SESSION['setting']->isThreeDimensional()) {
      
?>

                    <li>
                        z-step (nm):
                
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
                
                <a href="javascript:openWindow('http://support.svi.nl/wiki/NyquistCalculator')"
                    onmouseover="TagToTip('ttSpanNyquist' )"
                    onmouseout="UnTip()" >
                    <img src="images/calc_small.png" alt="" />
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
            <fieldset class="setting"
              onmouseover="javascript:changeQuickHelp( 'time' );" >
           	<legend> 
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=TimeSeries')"><img src="images/help.png" alt="?" /></a>
                time interval
                </legend>
            <ul>
              <li>Time interval (s):
<?php

  $parameter = $_SESSION['setting']->parameter("TimeInterval");

?>
                <input name="TimeInterval" type="text" size="5" value="<?php echo $parameter->value() ?>" />
              </li>
            </ul>
                
            </fieldset>
<?php

}

?>

<?php

if ($_SESSION['setting']->isMultiPointOrSinglePointConfocal()) {

?>
            <fieldset class="setting"
              onmouseover="javascript:changeQuickHelp( 'pinhole_radius' );" >
            
              <legend>
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=PinholeRadius')"><img src="images/help.png" alt="?" /></a>
                backprojected pinhole radius
	      </legend>
            <ul>
                <li>pinhole radius (nm):</li>
            </ul>
                
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
	<span class="nowrap">Ch<?php echo $i ?>:&nbsp;&nbsp;&nbsp;<span class="multichannel"><input name="PinholeSize<?php echo $i ?>" type="text" size="8" value="<?php if ($i < sizeof($pinhole)) echo $pinhole[$i] ?>" class="multichannelinput" /></span>&nbsp;</span>
<?php

  }

?></div>
                <p />
                
                <a href="calculate_bp_pinhole.php?na=<?php echo $na;?>"
                  target="_blank"
                  onmouseover="TagToTip('ttSpanPinholeRadius' )"
                  onmouseout="UnTip()" >
                  <img src="images/calc_small.png" alt="" />
                  Backprojected pinhole calculator
                </a>
                
            </fieldset>
<?php

}

?>

<?php

if ($_SESSION['setting']->isNipkowDisk()) {
      
?>
            <fieldset class="setting"
              onmouseover="javascript:changeQuickHelp( 'pinhole_spacing' );" >
              <legend>            
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=PinholeSpacing')"><img src="images/help.png" alt="?" /></a>
                backprojected pinhole spacing
	      </legend>
          <ul>
                <li>pinhole spacing (micron):
<?php

  $parameter = $_SESSION['setting']->parameter('PinholeSpacing');

?>

                <input name="PinholeSpacing" type="text" size="5" value="<?php echo $parameter->value() ?>" />
                </li>
          </ul>
          <p />
                
                <a href="calculate_bp_pinhole.php?na=<?php echo $na;?>"
                  onmouseover="TagToTip('ttSpanPinholeSpacing' )"
                  onmouseout="UnTip()" >
                  <img src="images/calc_small.png" alt="" />
                  Backprojected pinhole calculator
                </a>
                
            </fieldset>
<?php

}

?>

            <div><input name="OK" type="hidden" /></div>

            <div id="controls" onmouseover="javascript:changeQuickHelp( 'default' )">
              <input type="button" value="" class="icon previous"
                  onmouseover="TagToTip('ttSpanBack' )"
                  onmouseout="UnTip()"
                  onclick="document.location.href='microscope_parameter.php'" />
              <input type="button" value="" class="icon up"
                  onmouseover="TagToTip('ttSpanCancel' )"
                  onmouseout="UnTip()"
                  onclick="document.location.href='select_parameter_settings.php'" />
              <input type="submit" value="" class="<?php echo $controlIconToShow; ?>" 
                  onmouseover="TagToTip('ttSpanForward' )"
                  onmouseout="UnTip()"
                  onclick="process()" />
            </div>
            
        </form>
        
    </div> <!-- content -->
    
    <div id="rightpanel" onmouseover="javascript:changeQuickHelp( 'default' )">
    
        <div id="info">

          <h3>Quick help</h3>
          
		  <div id="contextHelp">
			<p>Here you have to enter the voxel size as it was set during the
			image acquisition. Depending on the microscope type and the dataset
			geometry, you might have to enter additional parameters, such as the
			back-projected pinhole size and spacing, and the time interval for time
			series. For microscope type that use cameras (such as widefield and
			spinning disk confocal), you have the possibility to calculate the image
			pixel size from the camera pixel size, total magnification, and binning.
			</p>

		  </div>
		  
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

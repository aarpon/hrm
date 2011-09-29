<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Parameter.inc.php");
require_once("./inc/Setting.inc.php");
require_once("./inc/Util.inc.php");
require_once("./inc/Database.inc.php");

/* *****************************************************************************
 *
 * START SESSION, CHECK LOGIN STATE, INITIALIZE WHAT NEEDED
 *
 **************************************************************************** */

session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}
$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

/* *****************************************************************************
 *
 * SET THE CONFIDENCES FOR THE RELEVANT PARAMETERS
 *
 **************************************************************************** */

$fileFormat = $_SESSION['setting']->parameter( "ImageFileFormat" );
$parameterNames = $_SESSION['setting']->capturingParameterNames();
$db = new DatabaseConnection();
foreach ( $parameterNames as $name ) {
  $parameter = $_SESSION['setting']->parameter( $name );
  $confidenceLevel = $db->getParameterConfidenceLevel( $fileFormat->value(), $name );
  $parameter->setConfidenceLevel( $confidenceLevel );
  $_SESSION['setting']->set( $parameter );
}


/* *****************************************************************************
 *
 * MANAGE THE MULTI-CHANNEL PINHOLE RADII
 *
 **************************************************************************** */

$pinholeParam = $_SESSION['setting']->parameter("PinholeSize");
$pinhole = $pinholeParam->value();
for ($i=0; $i < $_SESSION['setting']->numberOfChannels(); $i++) {
  $pinholeKey = "PinholeSize{$i}";
  if (isset($_POST[$pinholeKey])) {
    $pinhole[$i] = $_POST[$pinholeKey];
  }
}
// get rid of extra values in case the number of channels is changed
//if (is_array($pinhole)) {
//	$pinhole = array_slice($pinhole, 0, $_SESSION['setting']->numberOfChannels() );
//}
$pinholeParam->setValue($pinhole);
$pinholeParam->setNumberOfChannels( $_SESSION['setting']->numberOfChannels( ) );
$_SESSION['setting']->set($pinholeParam);

/* *****************************************************************************
 *
 * WHICH IS THE NEXT PAGE?
 *
 **************************************************************************** */

// Here we try to figure out whether we have to continue after this page or not.
// If the user chose to use a measured PSF or if there is a refractive index
// mismatch, there will be more pages after this. Otherwise, we save the
// settings to the database and go back to select_parameter_settings.php.
// Besides the destination page, also the control buttons will need to be
// adapted.

$saveToDB = false;

// First check the selection of the PSF
$PSF = $_SESSION['setting']->parameter( 'PointSpreadFunction' )->value( );
if ($PSF == 'measured' ) {
  $pageToGo = 'select_psf.php';
  // Make sure to turn off the correction
  $_SESSION['setting']->parameter( 'AberrationCorrectionNecessary' )->setValue( '0' );
  $_SESSION['setting']->parameter( 'PerformAberrationCorrection' )->setValue( '0' );
} else {
  // Get the refractive indices: if they are not set, the floatval conversion will
  // change them into 0s
  $sampleRI    = floatval( $_SESSION['setting']->parameter( 'SampleMedium' )->translatedValue( ) );
  $objectiveRI = floatval( $_SESSION['setting']->parameter( 'ObjectiveType' )->translatedValue( ) );

  // Calculate the deviation
  if ( ( $sampleRI == 0 ) ||  ( $objectiveRI == 0 ) ) {
	// If at least one of the refractive indices is not known, we cannot
	// calculate whether an aberration correction is necessary and we leave
	// the decision to the user in the aberration_correction.php page.
	$pageToGo = 'aberration_correction.php';
    $_SESSION['setting']->parameter( 'AberrationCorrectionNecessary' )->setValue( '1' );
  } else {
	// If we know both the refractive indices we can calculate the deviation
	// and skip the aberration correction page in case the deviation is smaller
	// than 1%.
	$deviation = abs( $sampleRI - $objectiveRI ) / $objectiveRI;

	// Do we need to go to the aberration correction page?
	if ( $deviation < 0.01 ) {
      // We can save the parameters
      $saveToDB = true;
      $pageToGo = 'select_parameter_settings.php';
	  // Make sure to turn off the correction
	  $_SESSION['setting']->parameter( 'AberrationCorrectionNecessary' )->setValue( '0' );
	  $_SESSION['setting']->parameter( 'PerformAberrationCorrection' )->setValue( '0' );
	} else {
	  $pageToGo = 'aberration_correction.php';
	  $_SESSION['setting']->parameter( 'AberrationCorrectionNecessary' )->setValue( '1' );
	}
  }
}

/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */

if ($_SESSION[ 'setting' ]->checkPostedCapturingParameters( $_POST ) ) {
  if ( $saveToDB ) {
    $saved = $_SESSION['setting']->save();
    $message = "            <p class=\"warning\">".$_SESSION['setting']->message()."</p>";
    if ($saved) {
      header("Location: " . $pageToGo ); exit();
    }
  }
  header("Location: " . $pageToGo ); exit();
} else {
  $message = "            <p class=\"warning\">".$_SESSION['setting']->message()."</p>";
}

/* *****************************************************************************
 *
 * CREATE THE PAGE
 *
 **************************************************************************** */

// Javascript includes
$script = array( "jquery-1.6.4.min.js", "settings.js", "quickhelp/help.js",
                "quickhelp/capturingParameterHelp.js" );

include("header.inc.php");

// If all necessaty Parameters were set, the Nyquist rate can be calculated
// displayed. If not, we inform the user
$nyquist = $_SESSION['setting']->calculateNyquistRate();
if ( $nyquist === false ) {
    $NyquistMessage = "The optimal Nyquist sampling rate could not be " .
	"calculated because not all necessary parameters were set in the " .
	"previous pages. You can use the online Nyquist calculator instead.";
} else {
  $NyquistMessage = "Calculated from current optical parameters, the " .
	"(Nyquist) ideal pixel size is <span style=\"background-color:yellow\">" .
	$nyquist[0] . " nm</span>";
  if ($_SESSION['setting']->isThreeDimensional() ) {
	$NyquistMessage .=
	  " and the ideal z-step is <span style=\"background-color:yellow\">" .
	  $nyquist[1] . " nm</span>";
  }
  $NyquistMessage .= ".";
}

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
    <?php
        if ( $saveToDB == true ) {
            $iconClass = "icon save";
    ?>
        <span id="ttSpanForward">Save and return to the image parameters selection page.</span>
    <?php
        } else {
            $iconClass = "icon next";
    ?>
        <span id="ttSpanForward">Continue to next page.</span>
    <?php
    }
    ?>

    <div id="nav">
        <ul>
            <li><img src="images/user.png" alt="user" />&nbsp;<?php echo $_SESSION['user']->name(); ?></li>
            <li><a href="javascript:openWindow('http://www.svi.nl/HuygensRemoteManagerHelpCaptor')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
    </div>

    <div id="content">

        <h3>Optical parameters / 2</h3>

        <form method="post" action="capturing_parameter.php" id="select">

            <h4>How were these images captured?</h4>

    <?php

    /***************************************************************************

      CCDCaptorSizeX

    ***************************************************************************/

      $parameterCCDCaptorSizeX = $_SESSION['setting']->parameter("CCDCaptorSizeX");

    ?>
            <fieldset class="setting <?php echo $parameterCCDCaptorSizeX->confidenceLevel(); ?>"
              onmouseover="javascript:changeQuickHelp( 'voxel' );" >

                <legend>
                    <a href="javascript:openWindow('http://www.svi.nl/SampleSize')"><img src="images/help.png" alt="?" /></a>
                    voxel size
                </legend>

				<p class="message_small"><?php echo $NyquistMessage;?></p>
<?php

$value = $parameterCCDCaptorSizeX->value();
$textForCaptorSize = "pixel size (nm)";

?>
                <ul>

                    <li>
                        <?php echo $textForCaptorSize ?>:
                        <input id="CCDCaptorSizeX" name="CCDCaptorSizeX" type="text" size="5" value="<?php echo $value ?>" /> <br/>
			<?php
                  // The calculation of pixel size from CCD chip makes sense only for widefield microscopes
                  if ( $_SESSION['setting']->isWidefield() || $_SESSION['setting']->isMultiPointConfocal() ) {
            ?>

            <a href="#"
              onmouseover="TagToTip('ttSpanPixelSizeFromCCD' )"
              onmouseout="UnTip()"
              onclick="storeValuesAndRedirect( 'calculate_pixel_size.php');" >
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

    /***************************************************************************

      ZStepSize

    ***************************************************************************/

      $parameterZStepSize = $_SESSION['setting']->parameter("ZStepSize");

    ?>
                        <input id="ZStepSize" name="ZStepSize" type="text" size="5" value="<?php echo $parameterZStepSize->value() ?>" />
                    </li>

<?php

}

?>

                </ul>

                <a href="#"
                    onmouseover="TagToTip('ttSpanNyquist' )"
                    onmouseout="UnTip()"
                    onclick="storeValuesAndRedirect( 'http://support.svi.nl/wiki/NyquistCalculator');">
                    <img src="images/calc_small.png" alt="" />
                    On-line Nyquist rate and PSF calculator
                    <img src="images/web.png" alt="external link" />
                </a>

            <p class="message_confidence_<?php echo $parameterCCDCaptorSizeX->confidenceLevel(); ?>">&nbsp;</p>
			</fieldset>

<?php

if ($_SESSION['setting']->isTimeSeries()) {

?>

    <?php

    /***************************************************************************

      TimeInterval

    ***************************************************************************/

      $parameterTimeInterval = $_SESSION['setting']->parameter("TimeInterval");

    ?>
            <fieldset class="setting <?php echo $parameterTimeInterval->confidenceLevel(); ?>"
              onmouseover="javascript:changeQuickHelp( 'time' );" >
           	<legend>
                <a href="javascript:openWindow('http://www.svi.nl/TimeSeries')"><img src="images/help.png" alt="?" /></a>
                time interval
                </legend>
            <ul>
              <li>Time interval (s):
                <input id="TimeInterval" name="TimeInterval" type="text" size="5" value="<?php echo $parameterTimeInterval->value() ?>" />
              </li>
            </ul>

            <p class="message_confidence_<?php echo $parameterTimeInterval->confidenceLevel(); ?>">&nbsp;</p>
			</fieldset>
<?php

}

?>

<?php

if ($_SESSION['setting']->isMultiPointOrSinglePointConfocal()) {

    /***************************************************************************

      PinholeSize

    ***************************************************************************/

      $parameterPinholeSize = $_SESSION['setting']->parameter("PinholeSize");

    ?>
            <fieldset class="setting <?php echo $parameterPinholeSize->confidenceLevel(); ?>"
              onmouseover="javascript:changeQuickHelp( 'pinhole_radius' );" >

              <legend>
                <a href="javascript:openWindow('http://www.svi.nl/PinholeRadius')"><img src="images/help.png" alt="?" /></a>
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
	<span class="nowrap">
        Ch<?php echo $i ?>:&nbsp;&nbsp;&nbsp;
        <span class="multichannel">
            <input id="PinholeSize<?php echo $i ?>"
                   name="PinholeSize<?php echo $i ?>"
                   type="text"
                   size="8"
                   value="<?php if ($i < sizeof($pinhole)) echo $pinhole[$i] ?>"
                   class="multichannelinput" />
        </span>&nbsp;
    </span>
<?php

  }

?></div>
                <p />

				<?php
				  $parameterNA = $_SESSION['setting']->parameter("NumericalAperture");
				  $na = $parameterNA->value();
				?>
                <a href="#"
                  onmouseover="TagToTip('ttSpanPinholeRadius' )"
                  onmouseout="UnTip()" 
                  onclick="storeValuesAndRedirect( 'calculate_bp_pinhole.php?na=<?php echo $na;?>');" >
                  <img src="images/calc_small.png" alt="" />
                  Backprojected pinhole calculator
                </a>

            <p class="message_confidence_<?php echo $parameterPinholeSize->confidenceLevel(); ?>">&nbsp;</p>
			</fieldset>
<?php

}

?>

<?php

if ($_SESSION['setting']->isNipkowDisk()) {

    /***************************************************************************

      PinholeSpacing

    ***************************************************************************/

	$parameterPinholeSpacing = $_SESSION['setting']->parameter('PinholeSpacing');

?>
            <fieldset class="setting <?php echo $parameterPinholeSpacing->confidenceLevel(); ?>"
              onmouseover="javascript:changeQuickHelp( 'pinhole_spacing' );" >
              <legend>
                <a href="javascript:openWindow('http://www.svi.nl/PinholeSpacing')"><img src="images/help.png" alt="?" /></a>
                backprojected pinhole spacing
	      </legend>
          <ul>
                <li>pinhole spacing (micron):
                <input id="PinholeSpacing" name="PinholeSpacing" type="text" size="5" value="<?php echo $parameterPinholeSpacing->value() ?>" />
                </li>
          </ul>
          <p />

                <a href="#"
                   onmouseover="TagToTip('ttSpanPinholeSpacing' )"
                   onmouseout="UnTip()"
                   onclick="storeValuesAndRedirect( 'calculate_bp_pinhole.php?na=<?php echo $na;?>');">
                    <img src="images/calc_small.png" alt="" />
                    Backprojected pinhole calculator
                </a>

            <p class="message_confidence_<?php echo $parameterPinholeSpacing->confidenceLevel(); ?>">&nbsp;</p>
			</fieldset>
<?php

}

?>

            <div><input name="OK" type="hidden" /></div>

            <div id="controls" onmouseover="javascript:changeQuickHelp( 'default' )">
              <input type="button" value="" class="icon previous"
                  onmouseover="TagToTip('ttSpanBack' )"
                  onmouseout="UnTip()"
                  onclick="javascript:deleteValuesAndRedirect( 'microscope_parameter.php' );" />
              <input type="button" value="" class="icon up"
                  onmouseover="TagToTip('ttSpanCancel' )"
                  onmouseout="UnTip()"
                  onclick="javascript:deleteValuesAndRedirect( 'select_parameter_settings.php' );" />
              <input type="submit" value="" class="<?php echo $iconClass; ?>"
                  onmouseover="TagToTip('ttSpanForward' )"
                  onmouseout="UnTip()"
                  onclick="javascript:deleteValuesAndProcess();" />
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

if ( !( strpos( $_SERVER[ 'HTTP_REFERER' ], 'calculate_pixel_size.php') === false ) ) {
?>
    <script type="text/javascript">
        $(document).ready( retrieveValues( 'CCDCaptorSizeX') );
    </script>
<?php
}

if ( !( strpos( $_SERVER[ 'HTTP_REFERER' ], 'calculate_bp_pinhole.php') === false ) ) {
?>
    <script type="text/javascript">
        $(document).ready( retrieveValues( ) );
    </script>
<?php
}

?>

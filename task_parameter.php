<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Parameter.inc.php");
require_once("./inc/Setting.inc.php");
require_once("./inc/Util.inc.php");
require_once ("./inc/System.inc.php");

/* *****************************************************************************
 *
 * START SESSION, CHECK LOGIN STATE, INITIALIZE WHAT NEEDED
 *
 **************************************************************************** */

session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

if (!isset($_SESSION['task_setting'])) {
  $_SESSION['task_setting'] = new TaskSetting();
}
if ($_SESSION['user']->isAdmin()) {
  $_SESSION['task_setting']->setNumberOfChannels(5);
}
else {
  $_SESSION['task_setting']->setNumberOfChannels(
          $_SESSION['setting']->numberOfChannels());
}

$message = "";

/* *****************************************************************************
 *
 * MAKE SURE TO HAVE THE DECONVOLUTION ALGORITHM SET TO cmle IF WE ARE COMING
 * BACK FROM THE ESTIMATOR
 *
 **************************************************************************** */

if ( ! ( strpos( $_SERVER[ 'HTTP_REFERER' ],
    'estimate_snr_from_image.php') === false ) ||
    !( strpos( $_SERVER[ 'HTTP_REFERER' ],
    'estimate_snr_from_image_beta.php') === false ) ) {
        $algorithmParameter = $_SESSION['task_setting']->parameter(
                "DeconvolutionAlgorithm");
        $algorithmParameter->setValue( 'cmle' );
        $_SESSION['task_setting']->set($algorithmParameter);
}

/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */

if ( $_SESSION[ 'task_setting' ]->checkPostedTaskParameters( $_POST ) ) {
    if ($_SESSION[ 'task_setting']->numberOfChannels() == 1) {
        $saved = $_SESSION['task_setting']->save();
        if ($saved) {
            header("Location: " . "select_task_settings.php"); exit();
        } else {
            $message = $_SESSION['task_setting']->message();
        }
    } else {
            // Continue to next page
        header("Location: " . "post_processing.php"); exit();
    }
} else {
    $message = $_SESSION['task_setting']->message();
}

/* *****************************************************************************
 *
 * CREATE THE PAGE
 *
 **************************************************************************** */

//$noRange = False;

// Javascript includes
$script = array( "jquery-1.7.1.min.js", "settings.js", "quickhelp/help.js",
                "quickhelp/taskParameterHelp.js" );

include("header.inc.php");

?>
    <!--
      Tooltips
    -->
    <span id="ttSpanCancel">
        Abort editing and go back to the Restoration parameters
        selection page. All changes will be lost!
    </span>
    
    <?php
    if ($_SESSION['task_setting']->numberOfChannels() == 1) {
    ?>
    <span id="ttSpanSave">
    Save and return to the processing parameters selection page.
    </span>
    
    <?php
    } else {
    ?>
    <span id="ttSpanForward">
        Continue to next page.
    </span>
    <?php
    }
    ?>
    
    <span id="ttEstimateSnr">
        Use a sample raw image to find a SNR estimate for each channel.
    </span>
    <span id="ttEstimateSnrBeta">
        Give the new SNR estimator (beta) a try!
    </span>
    <span id="ttEstimateSnrBetaFeedback">
        Please help us improve the new SNR estimator by providing your
        observations and remarks!
    </span>

    <div id="nav">
        <ul>
            <li>
                <img src="images/user.png" alt="user" />
                &nbsp;<?php echo $_SESSION['user']->name(); ?>
            </li>
            <li>
                <a href="javascript:openWindow(
                   'http://www.svi.nl/HuygensRemoteManagerHelpRestorationParameters')">
                    <img src="images/help.png" alt="help" />
                    &nbsp;Help</a>
            </li>
        </ul>
    </div>

    <div id="content">

        <h3>Restoration - Deconvolution</h3>

        <form method="post" action="" id="select">

           <h4>How should your images be restored?</h4>

            <!-- deconvolution algorithm -->
             <fieldset class="setting provided"
              onmouseover="javascript:changeQuickHelp( 'method' );" >

                <legend>
                    <a href="javascript:openWindow(
                       'http://support.svi.nl/wiki/RestorationMethod')">
                        <img src="images/help.png" alt="?" /></a>
                    deconvolution algorithm
                </legend>

                <select name="DeconvolutionAlgorithm"
                  onchange="javascript:switchSnrMode();" >

<?php

/*
                           DECONVOLUTION ALGORITHM
*/

$parameter = $_SESSION['task_setting']->parameter("DeconvolutionAlgorithm");
$possibleValues = $parameter->possibleValues();
$selectedMode  = $parameter->value();
foreach($possibleValues as $possibleValue) {
  $translation = $parameter->translatedValueFor( $possibleValue );
  if ( $possibleValue == $selectedMode ) {
      $option = "selected=\"selected\"";
  } else {
      $option = "";
  }
?>
                    <option <?php echo $option?>
                        value="<?php echo $possibleValue?>">
                        <?php echo $translation?>
                    </option>
<?php
}
?>
                </select>

            </fieldset>

            <!-- signal/noise ratio -->
            <fieldset class="setting provided"
              onmouseover="javascript:changeQuickHelp( 'snr' );" >

                <legend>
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/SignalToNoiseRatio')">
                        <img src="images/help.png" alt="?" /></a>
                    signal/noise ratio
                </legend>

                <div id="snr"
                     onmouseover="javascript:changeQuickHelp( 'snr' );">

<?php

$visibility = " style=\"display: none\"";
if ($selectedMode == "cmle") {
  $visibility = " style=\"display: block\"";
}

?>
                    <div id="cmle-snr"
                         class="multichannel"<?php echo $visibility?>>
                    <ul>
                      <li>SNR:
                      <div class="multichannel">
<?php

/*
                           SIGNAL-TO-NOISE RATIO
*/

  $signalNoiseRatioParam =
    $_SESSION['task_setting']->parameter("SignalNoiseRatio");
  $signalNoiseRatioValue = $signalNoiseRatioParam->value();

for ($i = 0; $i < $_SESSION['task_setting']->numberOfChannels(); $i++) {

    $value = "";
    if ($selectedMode == "cmle")
        $value = $signalNoiseRatioValue[$i];

        // Add a line break after 3 entries
        if ( $i == 3 ) {
            echo "<br />";
        }

?>
                          <span class="nowrap">Ch<?php echo $i ?>:
                              &nbsp;&nbsp;&nbsp;
                              <span class="multichannel">
                                  <input
                                    id="SignalNoiseRatioCMLE<?php echo $i ?>"
                                    name="SignalNoiseRatioCMLE<?php echo $i ?>"
                                    type="text"
                                    size="8"
                                    value="<?php echo $value ?>"
                                    class="multichannelinput" />
                              </span>&nbsp;
                          </span>
<?php

}

?>
                          </div>
                        </li>
                      </ul>

                        <p><a href="#"
                          onmouseover="TagToTip('ttEstimateSnr' )"
                          onmouseout="UnTip()"
                          onclick="storeValuesAndRedirect(
                            'estimate_snr_from_image.php');">
                          <img src="images/calc_small.png" alt="" />
                          Estimate SNR from image (classic)</a>
                        </p>

                          <div class="message_small_SNR_beta">
                          <img src="images/newSNR.png" alt="" />&nbsp;&nbsp;
                          Try the
                          <a href="#"
                            onmouseover="TagToTip('ttEstimateSnrBeta' )"
                            onmouseout="UnTip()"
                            onclick="storeValuesAndRedirect(
                            'estimate_snr_from_image_beta.php');">
                          new SNR estimator (beta)</a> and
                          <a href="javascript:openWindow(
                          'http://www.svi.nl/BetaSNRFeedback')"
                          onmouseover="TagToTip('ttEstimateSnrBetaFeedback' )"
                          onmouseout="UnTip()">
                          report your feedback!</a>&nbsp;&nbsp;
                          <img src="images/newSNR.png" alt="" /></div>
                    </div>
<?php

$visibility = " style=\"display: none\"";
if ($selectedMode == "qmle") {
  $visibility = " style=\"display: block\"";
}

?>
                    <div id="qmle-snr"
                      class="multichannel"<?php echo $visibility?>>
                      <ul>
                        <li>SNR:
                        <div class="multichannel">
<?php

for ($i = 0; $i < $_SESSION['task_setting']->numberOfChannels(); $i++) {

?>
                        <span class="nowrap">
                            Ch<?php echo $i ?>:&nbsp;&nbsp;&nbsp;
                            <select class="snrselect"
                                    name="SignalNoiseRatioQMLE<?php echo $i ?>">
<?php

  for ($j = 1; $j <= 4; $j++) {
      $option = "                                <option ";
      if (isset($signalNoiseRatioValue)) {
          if ($signalNoiseRatioValue[$i] >= 1 &&
                  $signalNoiseRatioValue[$i] <= 4) {
            if ($j == $signalNoiseRatioValue[$i])
                $option .= "selected=\"selected\" ";
          }
          else {
              if ($j == 2)
                $option .= "selected=\"selected\" ";
          }
      }
      else {
          if ($j == 2)
            $option .= "selected=\"selected\" ";
      }
      $option .= "value=\"".$j."\">";
      if ($j == 1)
        $option .= "low</option>";
      else if ($j == 2)
        $option .= "fair</option>";
      else if ($j == 3)
        $option .= "good</option>";
      else if ($j == 4)
        $option .= "inf</option>";
      echo $option;
  }

?>
                            </select>
                        </span><br />
<?php

}

?>
                          </div>
                        </li>
                      </ul>
                    </div>

                </div>


            </fieldset>

            <!-- background mode -->
            <fieldset class="setting provided"
              onmouseover="javascript:changeQuickHelp( 'background' );" >

                <legend>
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/BackgroundMode')">
                        <img src="images/help.png" alt="?" /></a>
                    background mode
                </legend>

                <div id="background">

<?php

/*
                           BACKGROUND OFFSET
*/

$backgroundOffsetPercentParam =
    $_SESSION['task_setting']->parameter("BackgroundOffsetPercent");
$backgroundOffset = $backgroundOffsetPercentParam->internalValue();

$flag = "";
if ($backgroundOffset[0] == "" || $backgroundOffset[0] == "auto") {
    $flag = " checked=\"checked\"";
}

?>

                    <p>
                        <input type="radio"
                            id="BackgroundEstimationModeAuto"
                            name="BackgroundEstimationMode"
                            value="auto"<?php echo $flag ?> />
                        automatic background estimation
                    </p>

<?php

$flag = "";
if ($backgroundOffset[0] == "object") $flag = " checked=\"checked\"";

?>

                    <p>
                        <input type="radio"
                            id="BackgroundEstimationModeObject"
                            name="BackgroundEstimationMode"
                            value="object"<?php echo $flag ?> />
                        in/near object
                    </p>

<?php

$flag = "";
if ($backgroundOffset[0] != "" && $backgroundOffset[0] != "auto" &&
    $backgroundOffset[0] != "object") {
        $flag = " checked=\"checked\"";
}

?>
                    <input type="radio"
                           id="BackgroundEstimationModeAbsValue"
                           name="BackgroundEstimationMode"
                           value="manual"<?php echo $flag ?> />
                    remove constant absolute value:

                    <div class="multichannel">
<?php

for ($i=0; $i < $_SESSION['task_setting']->numberOfChannels(); $i++) {
  $val = "";
  if ($backgroundOffset[0] != "auto" && $backgroundOffset[0] != "object") {
      $val = $backgroundOffset[$i];
  }

    // Add a line break after 3 entries
    if ( $i == 3 ) {
        echo "<br />";
    }

?>
                        <span class="nowrap">
                            Ch<?php echo $i ?>:&nbsp;&nbsp;&nbsp;
                            <span class="multichannel">
                                <input
                                   id="BackgroundOffsetPercent<?php echo $i ?>"
                                   name="BackgroundOffsetPercent<?php echo $i ?>"
                                   type="text"
                                   size="8"
                                   value="<?php echo $val ?>"
                                   class="multichannelinput"
                                   onclick="document.forms[0].BackgroundEstimationModeAbsValue.checked=true" />
                            </span>&nbsp;
                        </span>

<?php

}

/*!
	\todo	The visibility toggle should be restored but but only the
            quality change should be hidden for qmle, not the whole stopping
            criteria div!
			Also restore the changeVisibility("cmle-it") call in
            scripts/settings.js.
 */
//$visibility = " style=\"display: none\"";
//if ($selectedMode == "cmle") {
  $visibility = " style=\"display: block\"";
//}

?>
                    </div>

                </div>


            </fieldset>

            <div id="cmle-it" <?php echo $visibility ?>>

            <!-- stopping criteria -->
            <fieldset class="setting provided"
              onmouseover="javascript:changeQuickHelp( 'stopcrit' );" >

                <legend>
                    stopping criteria
                </legend>

                <div id="criteria">

                    <p><a href="javascript:openWindow(
                          'http://www.svi.nl/MaxNumOfIterations')">
                            <img src="images/help.png" alt="?" /></a>
                    number of iterations:

<?php

$parameter = $_SESSION['task_setting']->parameter("NumberOfIterations");
$value = $parameter->value();


?>
                    <input id="NumberOfIterations"
                           name="NumberOfIterations"
                           type="text"
                           size="8"
                           value="<?php echo $value ?>" />

                    </p>

                    <p><a href="javascript:openWindow(
                          'http://www.svi.nl/QualityCriterion')">
                            <img src="images/help.png" alt="?" /></a>
                    quality change:

<?php

$parameter = $_SESSION['task_setting']->parameter("QualityChangeStoppingCriterion");
$value = $parameter->value();

?>
                    <input id="QualityChangeStoppingCriterion"
                           name="QualityChangeStoppingCriterion"
                           type="text"
                           size="3"
                           value="<?php echo $value ?>" />
                    </p>

                </div>


            </fieldset>
            </div>

            <div><input name="OK" type="hidden" /></div>

            <div id="controls"
                 onmouseover="javascript:changeQuickHelp( 'default' )">
              <input type="button" value="" class="icon up"
                  onmouseover="TagToTip('ttSpanCancel' )"
                  onmouseout="UnTip()"
                  onclick="javascript:deleteValuesAndRedirect('select_task_settings.php' );"
                  />
    
    <?php
    if ($_SESSION['task_setting']->numberOfChannels() == 1) {
        $acceptButton  = "icon save";
        $acceptToolTip = "TagToTip('ttSpanSave')";
    } else {
        $acceptButton  = "icon next";
        $acceptToolTip = "TagToTip('ttSpanForward')";
    }
    ?>

<input type="submit" value=""
    class=<?php echo "\"" . $acceptButton . "\" ";?>
onmouseover=<?php echo "\"" . $acceptToolTip . "\" ";?>
onmouseout="UnTip()"
    onclick="process()" />
            </div>
        </form>
    </div> <!-- content -->

    <div id="rightpanel" onmouseover="javascript:changeQuickHelp( 'default' )">

      <div id="info">
      <h3>Quick help</h3>
        <div id="contextHelp">
          <p>On this page you specify the parameters for restoration.</p>
          <p>These parameters comprise the deconvolution algorithm, the
          signal-to-noise ratio (SNR) of the images, the mode for background
          estimation, and the stopping criteria.</p>
        </div>
     </div>

      <div id="message">
<?php

echo "<p>$message</p>";

?>
        </div>

    </div> <!-- rightpanel -->

<?php

include("footer.inc.php");

// Retrieve values from sessionStore if coming back from one of the
// SNR estimators
if ( !( strpos( $_SERVER[ 'HTTP_REFERER' ],
    'estimate_snr_from_image.php') === false ) ||
    !( strpos( $_SERVER[ 'HTTP_REFERER' ],
    'estimate_snr_from_image_beta.php') === false ) ) {

    if ( isset( $_SESSION['SNR_Calculated']) &&
            $_SESSION['SNR_Calculated'] == 'true') {
?>
        <script type="text/javascript">
            $(document).ready( retrieveValues(
            new Array( 'SignalNoiseRatioCMLE0',
            'SignalNoiseRatioCMLE1', 'SignalNoiseRatioCMLE2',
            'SignalNoiseRatioCMLE3', 'SignalNoiseRatioCMLE4' ) ) );
        </script>"

<?php
        // Now remove the SNR_Calculated flag
        unset($_SESSION['SNR_Calculated'] );

    } else {
?>
        <script type="text/javascript">
            $(document).ready( retrieveValues( ) );
        </script>"
<?php
    }
}

// Workaround for IE
if ( using_IE() && !isset( $_SERVER[ 'HTTP_REFERER' ] ) ) {
?>
        <script type="text/javascript">
            $(document).ready( retrieveValues( ) );
        </script>"
<?php
}
?>

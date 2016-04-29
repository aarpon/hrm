<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\DatabaseConnection;
use hrm\Nav;
use hrm\TaskSetting;

require_once dirname(__FILE__) . '/inc/bootstrap.inc.php';

require_once("./inc/Parameter.inc.php");
require_once("./inc/Util.inc.php");
require_once("./inc/System.inc.php");

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
    $db = new DatabaseConnection;
    $maxChanCnt = $db->getMaxChanCnt();
    $_SESSION['task_setting']->setNumberOfChannels($maxChanCnt);
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

    if ( $_SESSION[ 'task_setting' ]->numberOfChannels() > 1) {
        header("Location: " . "chromatic_aberration.php"); exit();
    } else {

        $saved = $_SESSION['task_setting']->save();
        if ($saved) {
            header("Location: " . "select_task_settings.php"); exit();
        } else {
            $message = $_SESSION['task_setting']->message();
        }
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
$script = array( "settings.js", "quickhelp/help.js",
                "quickhelp/taskParameterHelp.js" );

include("header.inc.php");

?>
    <!--
      Tooltips
    -->
    <span class="toolTip" id="ttSpanCancel">
        Abort editing and go back to the Restoration parameters
        selection page. All changes will be lost!
    </span>

    <?php
    if ($_SESSION['task_setting']->numberOfChannels() == 1) {
    ?>
    <span class="toolTip" id="ttSpanSave">
    Save and return to the processing parameters selection page.
    </span>

    <?php
    } else {

    ?>
    <span class="toolTip" id="ttSpanForward">
        Continue to next page.
    </span>
    <?php
    }
    ?>

    <span class="toolTip" id="ttEstimateSnr">
        Use a sample raw image to find a SNR estimate for each channel.
    </span>
    <span class="toolTip" id="ttEstimateSnrBeta">
        Give the new SNR estimator (beta) a try!
    </span>
    <span class="toolTip" id="ttEstimateSnrBetaFeedback">
        Please help us improve the new SNR estimator by providing your
        observations and remarks!
    </span>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
                echo(Nav::linkWikiPage('HuygensRemoteManagerHelpRestorationParameters'));
            ?>
            <li> [ <?php  echo $_SESSION['task_setting']->name(); ?> ] </li>
        </ul>
    </div>
    <div id="navright">
        <ul>
            <?php
                echo(Nav::textUser($_SESSION['user']->name()));
            ?>
        </ul>
    </div>
    <div class="clear"></div>
</div>


    <div id="content">

        <h2>Restoration - Deconvolution</h2>

        <form method="post" action="" id="select">

           <h4>How should your images be restored?</h4>

            <!-- deconvolution algorithm -->
             <fieldset class="setting provided"
              onmouseover="changeQuickHelp( 'method' );" >

                 <legend>
                     <a href="openWindow(
                       'http://support.svi.nl/wiki/RestorationMethod')">
                         <img src="images/help.png" alt="?"/></a>
                     deconvolution algorithm
                 </legend>

                 <select name="DeconvolutionAlgorithm"
                         title="Deconvolution algorithm"
                         onchange="switchSnrMode();">

<?php

/*
                           DECONVOLUTION ALGORITHM
*/

/** @var DeconvolutionAlgorithm $parameter */
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
              onmouseover="changeQuickHelp( 'snr' );" >

                <legend>
                    <a href="openWindow('http://www.svi.nl/SignalToNoiseRatio')">
                        <img src="images/help.png" alt="?" /></a>
                    signal/noise ratio
                </legend>

                <div id="snr"
                     onmouseover="changeQuickHelp( 'snr' );">

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

    /* Add a line break after a number of entries. */
    if ( $_SESSION['task_setting']->numberOfChannels() == 4 ) {
        if ($i == 2) {
            echo "<br />";
        }
    } else {
        if ($i == 3) {
            echo "<br />";
        }
    }


?>
        <span class="nowrap">Ch<?php echo $i; ?>:
        &nbsp;&nbsp;&nbsp;
                              <span class="multichannel">
                                  <input
                                    id="SignalNoiseRatioCMLE<?php echo $i; ?>"
                                    name="SignalNoiseRatioCMLE<?php echo $i; ?>"
                                    title="Signal-to-noise ratio (CMLE)"
                                    type="text"
                                    size="8"
                                    value="<?php echo $value; ?>"
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
                          Estimate SNR from image</a>
                        </p>

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
                                    title="Signal-to-noise ration (QMLE)"
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

 <div id="Autocrop">
    <fieldset class="setting provided"
    onmouseover="changeQuickHelp( 'autocrop' );" >

        <legend>
            <a href="openWindow('http://www.svi.nl/HelpCropper')">
                <img src="images/help.png" alt="?"/>
            </a>
            crop surrounding background areas?
        </legend>

        <select id="Autocrop"
                title="Autocrop"
                name="Autocrop">
<?php

/*
      AUTOCROP
*/
$parameterAutocrop =
    $_SESSION['task_setting']->parameter("Autocrop");
$possibleValues = $parameterAutocrop->possibleValues();
$selectedMode  = $parameterAutocrop->value();

        foreach($possibleValues as $possibleValue) {
            $translation =
                $parameterAutocrop->translatedValueFor( $possibleValue );
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
</div> <!-- Autocrop -->
            <!-- background mode -->
            <fieldset class="setting provided"
              onmouseover="changeQuickHelp( 'background' );" >

                <legend>
                    <a href="openWindow('http://www.svi.nl/BackgroundMode')">
                        <img src="images/help.png" alt="?"/></a>
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
                               title="Background estimation mode (auto)"
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
                               title="Background estimation mode (object)"
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
                           title="Background estimation mode (absolute value)"
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

  /* Add a line break after a number of entries. */
  if ( $_SESSION['task_setting']->numberOfChannels() == 4 ) {
      if ($i == 2) {
          echo "<br />";
      }
  } else {
      if ($i == 3) {
          echo "<br />";
      }
  }


    ?>
    <span class="nowrap">
                            Ch<?php echo $i ?>:&nbsp;&nbsp;&nbsp;
                            <span class="multichannel">
                                <input
                                    id="BackgroundOffsetPercent<?php echo $i ?>"
                                    name="BackgroundOffsetPercent<?php echo $i ?>"
                                    title="Background offset percent"
                                    type="text"
                                    size="8"
                                    value="<?php echo $val ?>"
                                    class="multichannelinput"
                                    onclick="document.forms[0].BackgroundEstimationModeAbsValue.checked=true"/>
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
              onmouseover="changeQuickHelp( 'stopcrit' );" >

                <legend>
                    stopping criteria
                </legend>

                <div id="criteria">

                    <p><a href="openWindow('http://www.svi.nl/MaxNumOfIterations')">
                            <img src="images/help.png" alt="?" /></a>
                    number of iterations:

<?php

$parameter = $_SESSION['task_setting']->parameter("NumberOfIterations");
$value = $parameter->value();


?>
                        <input id="NumberOfIterations"
                               name="NumberOfIterations"
                               title="Number of iterations"
                               type="text"
                               size="8"
                               value="<?php echo $value ?>"/>

                    </p>

                    <p><a href="javascript:openWindow(
                          'http://www.svi.nl/QualityCriterion')">
                            <img src="images/help.png" alt="?"/></a>
                        quality change:

<?php

$parameter = $_SESSION['task_setting']->parameter("QualityChangeStoppingCriterion");
$value = $parameter->value();

?>
                        <input id="QualityChangeStoppingCriterion"
                               name="QualityChangeStoppingCriterion"
                               title="Quality change stopping criterion"
                               type="text"
                               size="3"
                               value="<?php echo $value ?>"/>
                    </p>

                </div>

            </fieldset>

            </div>



    <div id="ZStabilization">
<?php
    if ($_SESSION['user']->isAdmin()
        || $_SESSION['task_setting']->isEligibleForStabilization($_SESSION['setting'])) {

    ?>

    <fieldset class="setting provided"
              onmouseover="changeQuickHelp( 'zstabilization' );" >

        <legend>
            <a href="openWindow('http://www.svi.nl/ObjectStabilizer')">
                <img src="images/help.png" alt="?"/>
            </a>
            stabilize data sets in the Z direction?
        </legend>

        <p>STED images often need to be stabilized in the Z direction before
            they
            are deconvolved. Please note that skipping this step might affect
            the
            quality of the deconvolution.</p>

        <select id="ZStabilization"
                title="Z stabilization"
                name="ZStabilization">
<?php

/*
      STABILIZATION
*/
$parameterStabilization =
    $_SESSION['task_setting']->parameter("ZStabilization");
$possibleValues = $parameterStabilization->possibleValues();
$selectedMode  = $parameterStabilization->value();

        foreach($possibleValues as $possibleValue) {
            $translation =
                $parameterStabilization->translatedValueFor( $possibleValue );
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

<?php
    } else {
        $_SESSION['task_setting']->parameter("ZStabilization")->setValue( '0' );
        ?>
        <input name="ZStabilization" type="hidden" value="0">
        <?php
    }
?>
</div> <!-- Stabilization -->


            <div><input name="OK" type="hidden" /></div>

            <div id="controls"
                 onmouseover="changeQuickHelp( 'default' )">

              <input type="button" value="" class="icon up"
                onmouseover="TagToTip('ttSpanCancel' )"
                onmouseout="UnTip()"
                onclick="deleteValuesAndRedirect('select_task_settings.php' );" />

    <?php
    /* Don't proceed to the chromatic aberration page. */
    if ($_SESSION['task_setting']->numberOfChannels() == 1) {
    ?>
              <input type="submit" value=""
                class="icon save"
                onmouseover="TagToTip('ttSpanSave')"
                onmouseout="UnTip()"
                onclick="process()" />

    <?php
    } else {
    ?>
                  <input type="submit" value="" class="icon next"
                  onmouseover="TagToTip('ttSpanForward' )"
                  onmouseout="UnTip()"
                  onclick="process()" />
    <?php
    }
    ?>



            </div>

        </form>

    </div> <!-- content -->

    <div id="rightpanel" onmouseover="changeQuickHelp( 'default' )">

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


/* Retrieve values from sessionStore if coming back from one of the SNR
   estimators. */
if ( !( strpos( $_SERVER[ 'HTTP_REFERER' ],
    'estimate_snr_from_image.php') === false ) ||
    !( strpos( $_SERVER[ 'HTTP_REFERER' ],
    'estimate_snr_from_image_beta.php') === false ) ) {

    if ( isset( $_SESSION['SNR_Calculated']) &&
            $_SESSION['SNR_Calculated'] == 'true') {
?>
        <script type="text/javascript">

        /* Consider the max chan cnt supported by Huygens. */
        snrArray = [];
        for (var i = 0; i < 32; i++) {
            snrArray.push('SignalNoiseRatioCMLE' + i);
        }
        $(document).ready( retrieveValues( snrArray ) );
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
        </script>
<?php
}
?>

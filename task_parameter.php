<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\DatabaseConnection;
use hrm\Nav;
use hrm\param\DeconvolutionAlgorithm;
use hrm\setting\TaskSetting;
use hrm\Util;

require_once dirname(__FILE__) . '/inc/bootstrap.php';


/* *****************************************************************************
 *
 * START SESSION, CHECK LOGIN STATE, INITIALIZE WHAT NEEDED
 *
 **************************************************************************** */

session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit();
}

if (!isset($_SESSION['task_setting'])) {
    $_SESSION['task_setting'] = new TaskSetting();
}
if ($_SESSION['user']->isAdmin()) {
    $db = DatabaseConnection::get();
    $maxChanCnt = $db->getMaxChanCnt();
    $_SESSION['task_setting']->setNumberOfChannels($maxChanCnt);
} else {
    $_SESSION['task_setting']->setNumberOfChannels(
        $_SESSION['setting']->numberOfChannels());
}

$message = "";


/* *****************************************************************************
 *
 * MANAGE THE DECONVOLUTION ALGORITHM
 *
 **************************************************************************** */

/** @var DeconvolutionAlgorithm $deconAlgorithmParam */
$chanCnt = $_SESSION['task_setting']->numberOfChannels();
$deconAlgorithmParam = $_SESSION['task_setting']->parameter("DeconvolutionAlgorithm");
$deconAlgorithm = $deconAlgorithmParam->value();
for ($i = 0; $i < $chanCnt; $i++) {
    $deconAlgorithmKey = "DeconvolutionAlgorithm{$i}";
    if (isset($_POST[$deconAlgorithmKey])) {
        $deconAlgorithm[$i] = $_POST[$deconAlgorithmKey];
    }
}
$deconAlgorithmParam->setValue($deconAlgorithm);
$_SESSION['task_setting']->set($deconAlgorithmParam);


/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */

if ($_SESSION['task_setting']->checkPostedTaskParameters($_POST)) {
    if ($_SESSION['user']->isAdmin()
    || $_SESSION['task_setting']->isEligibleForCAC()
    || $_SESSION['task_setting']->isEligibleForTStabilization($_SESSION['setting'])) {
        header("Location: " . "post_processing.php");
        exit();
    } else {

        $saved = $_SESSION['task_setting']->save();
        if ($saved) {
            header("Location: " . "select_task_settings.php");
            exit();
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
$script = array("settings.js", "quickhelp/help.js",
    "quickhelp/taskParameterHelp.js");

include("header.inc.php");

?>
<!--
  Tooltips
-->
<span class="toolTip" id="ttSpanCancel">
        Abort editing and go back to the Restoration parameters
        selection page. All changes will be lost!
    </span>

    <span class="toolTip" id="ttSpanForward">
        Continue to next page.
    </span>

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
            <li> [ <?php echo $_SESSION['task_setting']->name(); ?> ]</li>
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

    <h3>Restoration - Deconvolution</h3>

    <form method="post" action="" id="select">

        <h4>How should your images be restored?</h4>

        <!-- deconvolution algorithm -->
        <?php
        /***************************************************************************
         *
         * DeconvolutionAlgorithm
         ***************************************************************************/

        /** @var DeconvolutionAlgorithm $deconAlgorithmParam */
        ?>

        <fieldset class="setting provided"
                  onmouseover="changeQuickHelp( 'method' );">

            <legend>
                <a href="javascript:openWindow(
                       'https://svi.nl/RestorationMethod')">
                    <img src="images/help.png" alt="?"/></a>
                Deconvolution Algorithm
            </legend>


            <div class="DeconvolutionAlgorithmValues">
                <table class="DeconvolutionAlgorithmValues">

                    <?php
                    $possibleValues = $deconAlgorithmParam->possibleValues();

                    /* Make sure CMLE is first in the list. */
                    for ($i = 0; $i < count($possibleValues); $i++) {
                        $arrValue = $possibleValues[0];
                        if (strstr($arrValue, "gmle") || strstr($arrValue, "qmle")) {
                            array_push($possibleValues, $arrValue);
                        }
                    }

                    /* Loop on rows. */

                    for ($chan = 0; $chan < $chanCnt; $chan++) {
                        ?>
                        <tr>
                            <td>Ch<?php echo $chan; ?>:</td>

                            <td>
                                <select
                                    name="DeconvolutionAlgorithm<?php echo $chan; ?>"
                                    title="Deconvolution algorithm for channel <?php echo $chan; ?>"
                                    onchange="updateDeconEntryProperties()">

                                    <?php
                                    /* Loop for select options. */
                                    foreach ($possibleValues as $possibleValue) {
                                        $translatedValue =
                                            $deconAlgorithmParam->translatedValueFor($possibleValue);

                                        if ($possibleValue == $deconAlgorithm[$chan]) {
                                            $selected = " selected=\"selected\"";
                                        } else {
                                            $selected = "";
                                        }
                                        ?>
                                        <option
                                            value=<?php echo $possibleValue;
                                        echo $selected; ?>>
                                            <?php echo $translatedValue; ?>
                                        </option>
                                        <?php
                                    }                    /* End of loop for select options. */
                                    ?>
                                </select>
                            </td>

                        </tr>
                        <?php
                    }                        /* End of loop on rows. */
                    ?>

                </table> <!-- DeconvolutionAlgorithmValues -->
            </div> <!-- DeconvolutionAlgorithmValues -->

        </fieldset>



        <!-- signal/noise ratio -->
        <fieldset class="setting provided"
                  onmouseover="changeQuickHelp( 'snr' );">

            <legend>
                <a href="javascript:openWindow(
                    'http://www.svi.nl/SignalToNoiseRatio')">
                    <img src="images/help.png" alt="?"/></a>
                Signal/Noise Ratio
            </legend>

            <div id="snr"
                 onmouseover="changeQuickHelp( 'snr' );">

                <!-- start the SNR table-->
                <table><tr>


                <?php

                /*                SIGNAL-TO-NOISE RATIO                    */

                $signalNoiseRatioParam =
                    $_SESSION['task_setting']->parameter("SignalNoiseRatio");
                $signalNoiseRatioValue = $signalNoiseRatioParam->value();


                    /* Loop over the channels. */
                for ($ch = 0; $ch < $chanCnt; $ch++) {

                    $visibility = " style=\"display: none\"";
                    if ($deconAlgorithm[$ch] == "cmle") {
                        $visibility = " style=\"display: block\"";
                    }

                    $value = "";
                    if ($deconAlgorithm[$ch] == "cmle")
                        $value = $signalNoiseRatioValue[$ch];
                 ?>

                    <!-- A new table cell for the SNR of this channel-->
                    <td>

                    <div id="cmle-snr-<?php echo $ch;?>"
                     class="multichannel"<?php echo $visibility ?>>

                     <span class="nowrap">Ch<?php echo $ch; ?>:
                        &nbsp;&nbsp;&nbsp;
                              <span class="multichannel">
                                  <input
                                      id="SignalNoiseRatioCMLE<?php echo $ch; ?>"
                                      name="SignalNoiseRatioCMLE<?php echo $ch; ?>"
                                      title="Signal-to-noise ratio (CMLE)"
                                      type="text"
                                      size="8"
                                      value="<?php echo $value; ?>"
                                      class="multichannelinput"/>
                                        </span>&nbsp;
                                    </span>

                    </div><!-- cmle-snr-channelNumber-->


                <?php

                    $visibility = " style=\"display: none\"";
                    if ($deconAlgorithm[$ch] == "gmle") {
                        $visibility = " style=\"display: block\"";
                    }

                    $value = "";
                    if ($deconAlgorithm[$ch] == "gmle")
                        $value = $signalNoiseRatioValue[$ch];
                ?>

                    <div id="gmle-snr-<?php echo $ch;?>"
                     class="multichannel"<?php echo $visibility ?>>

                    <span class="nowrap">Ch<?php echo $ch; ?>:
                        &nbsp;&nbsp;&nbsp;
                            <span class="multichannel">
                                <input
                                    id="SignalNoiseRatioGMLE<?php echo $ch; ?>"
                                    name="SignalNoiseRatioGMLE<?php echo $ch; ?>"
                                    title="Signal-to-noise ratio (GMLE)"
                                    type="text"
                                    size="8"
                                    value="<?php echo $value; ?>"
                                    class="multichannelinput"/>
                                    </span>&nbsp;
                                </span>

                    </div><!-- gmle-snr-channelNumber-->


                <?php

                    $visibility = " style=\"display: none\"";
                    if ($deconAlgorithm[$ch] == "qmle") {
                        $visibility = " style=\"display: block\"";
                    }

                    $value = "";
                    if ($deconAlgorithm[$ch] == "qmle")
                        $value = $signalNoiseRatioValue[$ch];
                ?>

                    <div id="qmle-snr-<?php echo $ch;?>"
                     class="multichannel"<?php echo $visibility ?>>

                     <span class="nowrap">Ch<?php echo $ch; ?>:
                            <select class="snrselect"
                                    title="Signal-to-noise ratio (QMLE)"
                                    class="selection"
                                    name="SignalNoiseRatioQMLE<?php echo $ch ?>">
                        <?php

                            for ($optionIdx = 1; $optionIdx <= 4; $optionIdx++) {
                                $option = "                                <option ";
                                if (isset($signalNoiseRatioValue)) {
                                    if ($signalNoiseRatioValue[$ch] >= 1
                                       && $signalNoiseRatioValue[$ch] <= 4) {
                                        if ($optionIdx == $signalNoiseRatioValue[$ch])
                                            $option .= "selected=\"selected\" ";
                                    } else {
                                        if ($optionIdx == 2)
                                            $option .= "selected=\"selected\" ";
                                    }
                                } else {
                                    if ($optionIdx == 2)
                                        $option .= "selected=\"selected\" ";
                                }
                                $option .= "value=\"" . $optionIdx . "\">";
                                if ($optionIdx == 1)
                                    $option .= "low</option>";
                                else if ($optionIdx == 2)
                                    $option .= "fair</option>";
                                else if ($optionIdx == 3)
                                    $option .= "good</option>";
                                else if ($optionIdx == 4)
                                    $option .= "inf</option>";
                                echo $option;
                            }

                            ?>
                            </select>
                    </div><!-- qmle-snr-channelNumber-->


                <?php

                    $visibility = " style=\"display: none\"";
                    if ($deconAlgorithm[$ch] == "skip") {
                        $visibility = " style=\"display: block\"";
                    }

                    $value = "";
                    if ($deconAlgorithm[$ch] == "skip")
                        $value = $signalNoiseRatioValue[$ch];
                ?>

                    <div id="skip-snr-<?php echo $ch;?>"
                    class="multichannel"<?php echo $visibility ?>>

                    <span class="nowrap">Ch<?php echo $ch; ?>:
                        &nbsp;&nbsp;&nbsp;
                            <span class="multichannel">
                                <input
                                    id="SignalNoiseRatioSKIP<?php echo $ch; ?>"
                                    name="SignalNoiseRatioSKIP<?php echo $ch; ?>"
                                    title="Signal-to-noise ratio (SKIP)"
                                    type="text"
                                    size="8"
                                    value="<?php echo $value; ?>"
                                    class="multichannelinput"/>
                                    </span>&nbsp;
                                </span>

                    </div><!-- skip-snr-channelNumber-->

                <!-- Close the table cell for the SNR of this channel-->
                </td>

                    <?php
                    /* Start a new table row after a number of entries. */
                    if ($chanCnt == 4) {
                        if ($ch == 2) {
                            echo "</tr><tr>";
                        }
                    } else {
                        if ($ch == 2) {
                            echo "</tr><tr>";
                        }
                    }
                }
                ?>

                <!-- Close the last row and table-->
                </tr></table>


                    <p><a href="#"
                          onmouseover="TagToTip('ttEstimateSnr' )"
                          onmouseout="UnTip()"
                          onclick="storeValuesAndRedirect(
                            'estimate_snr_from_image.php');">
                            <img src="images/calc_small.png" alt=""/>
                            Estimate SNR from image</a>
                    </p>

            </div> <!-- snr div -->
        </fieldset> <!-- signal/noise ratio fieldset-->



        <div id="Autocrop">
            <fieldset class="setting provided"
                      onmouseover="changeQuickHelp('autocrop');">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/HelpCropper')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Cropping Mode
                </legend>

                <select id="Autocrop"
                        title="Autocrop"
                        name="Autocrop"
                        class="selection">
                    <?php

                    /*
                          AUTOCROP
                    */
                    $parameterAutocrop =
                        $_SESSION['task_setting']->parameter("Autocrop");
                    $possibleValues = $parameterAutocrop->possibleValues();
                    $selectedMode = $parameterAutocrop->value();

                    foreach ($possibleValues as $possibleValue) {
                        $translation =
                            $parameterAutocrop->translatedValueFor($possibleValue);
                        if ($possibleValue == $selectedMode) {
                            $option = "selected=\"selected\"";
                        } else {
                            $option = "";
                        }
                        ?>
                        <option <?php echo $option ?>
                            value="<?php echo $possibleValue ?>">
                            <?php echo $translation ?>
                        </option>
                        <?php
                    }
                    ?>

                </select>
        </div> <!-- Autocrop -->

        <div id="ArrayDetectorReductionMode">
        <?php
            if ($_SESSION['user']->isAdmin()
               || $_SESSION['task_setting']->isEligibleForArrayReduction($_SESSION['setting'])) {

            ?>
            <fieldset class="setting provided"
                      onmouseover="changeQuickHelp('arrayDetectorReductionMode');">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/ArrayDetector')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Array Detector Reduction Mode
                </legend>

                <select id="ArrayDetectorReductionMode"
                        title="ArrayDetectorReductionMode"
                        name="ArrayDetectorReductionMode"
                        class="ArrayDetectorReductionMode">
                    <?php

                    /*
                          ARRAY DETECTOR REDUCTION MODE
                    */
                    $parameterReductionMode =
                        $_SESSION['task_setting']->parameter("ArrayDetectorReductionMode");
                    $possibleValues = $parameterReductionMode->possibleValues();
                    $selectedMode = $parameterReductionMode->value();

                    foreach ($possibleValues as $possibleValue) {
                        $translation =
                            $parameterReductionMode->translatedValueFor($possibleValue);
                        if ($possibleValue == $selectedMode) {
                            $option = "selected=\"selected\"";
                        } else {
                            $option = "";
                        }
                        ?>
                        <option <?php echo $option ?>
                            value="<?php echo $possibleValue ?>">
                            <?php echo $translation ?>
                        </option>
                        <?php
                    }
                    ?>

                </select>

            <?php
            } else {
                 $_SESSION['task_setting']->parameter("ArrayDetectorReductionMode")->setValue('all');
            ?>
                 <input name="ArrayDetectorReductionMode" type="hidden" value="all">
            <?php
            }
            ?>
        </div> <!-- ArrayDetectorReductionMode -->

        <!-- background mode -->
        <fieldset class="setting provided"
                  onmouseover="changeQuickHelp('background');">

            <legend>
                <a href="javascript:openWindow(
                    'http://www.svi.nl/BackgroundMode')">
                    <img src="images/help.png" alt="?"/></a>
                Background Mode
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
                    $backgroundOffset[0] != "object"
                ) {
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

                    for ($i = 0; $i < $chanCnt; $i++) {
                        $val = "";
                        if ($backgroundOffset[0] != "auto" && $backgroundOffset[0] != "object") {
                            $val = $backgroundOffset[$i];
                        }

                        /* Add a line break after a number of entries. */
                        if ($chanCnt == 4) {
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
                                    title="Background offset"
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
                        \todo	The visibility toggle should be restored but only the
                                quality change should be hidden for qmle, not the whole stopping
                                criteria div!
                                Also restore the changeVisibility("cmle-it") call in
                                scripts/settings.js.
                     */
                    //$visibility = " style=\"display: none\"";
                    //if ($selectedMode == "cmle" || $selectedMode =="gmle") {
                    $visibility = " style=\"display: block\"";
                    //}

                    ?>
                </div>

            </div>


        </fieldset>

        <div id="cmle-it" <?php echo $visibility ?>>

            <!-- stopping criteria -->
            <fieldset class="setting provided"
                      onmouseover="changeQuickHelp('stopcrit');">

                <legend>
                    Stopping Criteria
                </legend>

                <div id="criteria">

                    <p>
                        <a href="javascript:openWindow(
                            'http://www.svi.nl/MaxNumOfIterations')">
                            <img src="images/help.png" alt="?"/></a>
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
            || $_SESSION['task_setting']->isEligibleForZStabilization($_SESSION['setting'])) {

            ?>

            <fieldset class="setting provided"
                      onmouseover="changeQuickHelp('zstabilization');">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/ObjectStabilizer')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Stabilize data sets in the Z direction?
                </legend>

                <p>STED images often need to be stabilized in the Z direction
                    before
                    they
                    are deconvolved. Please note that skipping this step might
                    affect
                    the
                    quality of the deconvolution.</p>

                <select id="ZStabilization"
                        title="Z stabilization"
                        name="ZStabilization"
                        class="selection">
                    <?php

                    /*
                          STABILIZATION
                    */
                    $parameterStabilization =
                        $_SESSION['task_setting']->parameter("ZStabilization");
                    $possibleValues = $parameterStabilization->possibleValues();
                    $selectedMode = $parameterStabilization->value();

                    foreach ($possibleValues as $possibleValue) {
                        $translation =
                            $parameterStabilization->translatedValueFor($possibleValue);
                        if ($possibleValue == $selectedMode) {
                            $option = "selected=\"selected\"";
                        } else {
                            $option = "";
                        }
                        ?>
                        <option <?php echo $option ?>
                            value="<?php echo $possibleValue ?>">
                            <?php echo $translation ?>
                        </option>
                        <?php
                    }
                    ?>

                </select>

                <?php
                } else {
                    $_SESSION['task_setting']->parameter("ZStabilization")->setValue('0');
                    ?>
                    <input name="ZStabilization" type="hidden" value="0">
                    <?php
                }
                ?>
        </div> <!-- Stabilization -->


        <div><input name="OK" type="hidden"/></div>

        <div id="controls"
             onmouseover="changeQuickHelp( 'default' )">

            <input type="button" value="" class="icon up"
                   onmouseover="TagToTip('ttSpanCancel' )"
                   onmouseout="UnTip()"
                   onclick="deleteValuesAndRedirect('select_task_settings.php' );"/>

            <?php
            /* Don't proceed to the post processing page. */
            if ($_SESSION['user']->isAdmin()
            || $_SESSION['task_setting']->isEligibleForCAC()
            || $_SESSION['task_setting']->isEligibleForTStabilization($_SESSION['setting'])) {
                ?>
                <input type="submit" value="" class="icon next"
                       onmouseover="TagToTip('ttSpanForward' )"
                       onmouseout="UnTip()"
                       onclick="process()"/>
                <?php
            } else {
                ?>
                <input type="submit" value=""
                       class="icon save"
                       onmouseover="TagToTip('ttSpanSave')"
                       onmouseout="UnTip()"
                       onclick="process()"/>

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
                signal-to-noise ratio (SNR) of the images, the mode for
                background
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
if (!(strpos($_SERVER['HTTP_REFERER'],
            'estimate_snr_from_image.php') === false) ||
    !(strpos($_SERVER['HTTP_REFERER'],
            'estimate_snr_from_image_beta.php') === false)
) {

    if (isset($_SESSION['SNR_Calculated']) &&
        $_SESSION['SNR_Calculated'] == 'true'
    ) {
        ?>
        <script type="text/javascript">

            /* Consider the max chan cnt supported by Huygens. */
            snrArray = [];
            for (var i = 0; i < 32; i++) {
                snrArray.push('SignalNoiseRatioCMLE' + i);
                snrArray.push('SignalNoiseRatioGMLE' + i);
            }
            $(document).ready(retrieveValues(snrArray));
        </script>"

        <?php
        // Now remove the SNR_Calculated flag
        unset($_SESSION['SNR_Calculated']);

    } else {
        ?>
        <script type="text/javascript">
            $(document).ready(retrieveValues());
        </script>"
        <?php
    }
}

// Workaround for IE
if (Util::using_IE() && !isset($_SERVER['HTTP_REFERER'])) {
    ?>
    <script type="text/javascript">
        $(document).ready(retrieveValues());
    </script>
    <?php
}
?>

<script type="text/javascript">
    updateDeconEntryProperties();
</script>
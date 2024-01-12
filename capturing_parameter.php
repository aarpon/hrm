<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\DatabaseConnection;
use hrm\Nav;
use hrm\param\base\Parameter;
use hrm\param\CCDCaptorSizeX;
use hrm\param\ImageFileFormat;
use hrm\param\NumericalAperture;
use hrm\param\PinholeSize;
use hrm\param\PinholeSpacing;
use hrm\param\TimeInterval;
use hrm\param\ZStepSize;
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
$message = "";

/* *****************************************************************************
 *
 * SET THE CONFIDENCES FOR THE RELEVANT PARAMETERS
 *
 **************************************************************************** */

/** @var ImageFileFormat $fileFormat */
$fileFormat = $_SESSION['setting']->parameter("ImageFileFormat");
$parameterNames = $_SESSION['setting']->capturingParameterNames();
$db = DatabaseConnection::get();
foreach ($parameterNames as $name) {
    /** @var Parameter $parameter */
    $parameter = $_SESSION['setting']->parameter($name);
    $confidenceLevel = $db->getParameterConfidenceLevel($fileFormat->value(), $name);
    $parameter->setConfidenceLevel($confidenceLevel);
    $_SESSION['setting']->set($parameter);
}


/* *****************************************************************************
 *
 * MANAGE THE MULTI-CHANNEL PINHOLE RADII
 *
 **************************************************************************** */

/** @var PinholeSize $pinholeParam */
$pinholeParam = $_SESSION['setting']->parameter("PinholeSize");
$pinhole = $pinholeParam->value();
for ($i = 0; $i < $_SESSION['setting']->numberOfChannels(); $i++) {
    $pinholeKey = "PinholeSize{$i}";
    if (isset($_POST[$pinholeKey])) {
        $pinhole[$i] = $_POST[$pinholeKey];
    }
}
$pinholeParam->setValue($pinhole);
$pinholeParam->setNumberOfChannels($_SESSION['setting']->numberOfChannels());
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

$PSF = $_SESSION['setting']->parameter('PointSpreadFunction')->value();
$MICR = $_SESSION['setting']->parameter("MicroscopeType")->value();

if ($MICR == "STED" || $MICR == 'STED 3D') {
    $pageToGo = 'sted_parameters.php';
} elseif ($MICR == "SPIM") {
    $pageToGo = 'spim_parameters.php';
} elseif ($PSF == 'measured') {
    $pageToGo = 'select_psf.php';
    // Make sure to turn off the correction
    $_SESSION['setting']->parameter(
        'AberrationCorrectionNecessary')->setValue('0');
} else {
    // Get the refractive indices: if they are not set, the floatval conversion
    // will change them into 0s
    $sampleRI = floatval($_SESSION['setting']->parameter(
        'SampleMedium')->translatedValue());
    $objectiveRI = floatval($_SESSION['setting']->parameter(
        'ObjectiveType')->translatedValue());

    // Calculate the deviation
    if (($sampleRI == 0) || ($objectiveRI == 0)) {
        // If at least one of the refractive indices is not known, we cannot
        // calculate whether an aberration correction is necessary and we leave
        // the decision to the user in the aberration_correction.php page.
        $pageToGo = 'aberration_correction.php';
        $_SESSION['setting']->parameter(
            'AberrationCorrectionNecessary')->setValue('1');
    } else {
        // If we know both the refractive indices we can calculate the deviation
        // and skip the aberration correction page in case the deviation is smaller
        // than 1%.
        $deviation = abs($sampleRI - $objectiveRI) / $objectiveRI;

        // Do we need to go to the aberration correction page?
        if ($deviation < 0.01) {
            // We can save the parameters
            $saveToDB = true;
            $pageToGo = 'select_parameter_settings.php';
            // Make sure to turn off the correction
            $_SESSION['setting']->parameter(
                'AberrationCorrectionNecessary')->setValue('0');
        } else {
            $pageToGo = 'aberration_correction.php';
            $_SESSION['setting']->parameter(
                'AberrationCorrectionNecessary')->setValue('1');
        }
    }
}

/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */
if ($_SESSION['setting']->checkPostedCapturingParameters($_POST)) {
    if ($saveToDB) {
        $saved = $_SESSION['setting']->save();
        $message = $_SESSION['setting']->message();
        if ($saved) {
            header("Location: " . $pageToGo);
            exit();
        }
    }
    header("Location: " . $pageToGo);
    exit();
} else {
    $message = $_SESSION['setting']->message();
}

/* *****************************************************************************
 *
 * CREATE THE PAGE
 *
 **************************************************************************** */

// Javascript includes
$script = array("settings.js", "quickhelp/help.js",
    "quickhelp/capturingParameterHelp.js");

include("header.inc.php");

// If all necessaty Parameters were set, the Nyquist rate can be calculated
// displayed. If not, we inform the user
if ($_SESSION['setting']->isSted() || $_SESSION['setting']->isSpim()
    || $_SESSION['setting']->isSted3D()) {
    $NyquistMessage = "To calculate the ideal sampling sizes for this " .
                      "microscope type please consult the online Nyquist " .
                      "calculator below.";
} else {
    $nyquist = $_SESSION['setting']->calculateNyquistRate();
    if ($nyquist === false) {
        $NyquistMessage = "The optimal Nyquist sampling rate could not be " .
            "calculated because not all necessary parameters were set in the " .
            "previous pages. You can use the online Nyquist calculator instead.";
    } else {
        $NyquistMessage = "Calculated from current optical parameters, the " .
            "(Nyquist) ideal pixel size is <span class=\"highlight\">" .
            $nyquist[0] . " nm</span> " .
            "and the ideal z-step is <span class=\"highlight\">" .
            $nyquist[1] . " nm</span>.";
    }   
}

?>

<!--
  Tooltips
-->
<?php
if ($_SESSION['setting']->isWidefield() ||
    $_SESSION['setting']->isMultiPointConfocal()
) {
    ?>
    <span class="toolTip" id="ttSpanPixelSizeFromCCD">
        Calculate the image pixel size from the CCD pixel size.
    </span>
    <?php
}
?>
<?php

if ($_SESSION['setting']->hasPinhole()) {
    ?>
    <span class="toolTip" id="ttSpanPinholeRadius">
        Calculate the back-projected pinhole radius for your microscope.
    </span>
    <?php
    if ($_SESSION['setting']->isNipkowDisk()) {
        ?>
        <span class="toolTip" id="ttSpanPinholeSpacing">
        Calculate the back-projected pinhole spacing for your microscope.
    </span>
        <?php
    }
}
?>
<span class="toolTip" id="ttSpanNyquist">
        Check your sampling with the online Nyquist calculator.
    </span>
<span class="toolTip" id="ttSpanBack">
        Go back to previous page.
    </span>
<span class="toolTip" id="ttSpanCancel">
        Abort editing and go back to the image parameters selection page.
        All changes will be lost!
    </span>
<?php
if ($saveToDB == true) {
    $iconClass = "icon save";
    ?>
    <span class="toolTip" id="ttSpanForward">
            Save and return to the image parameters selection page.
        </span>
    <?php
} else {
    $iconClass = "icon next";
    ?>
    <span class="toolTip" id="ttSpanForward">Continue to next page.</span>
    <?php
}
?>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
            echo(Nav::linkWikiPage('HuygensRemoteManagerHelpCaptor'));
            ?>
            <li> [ <?php echo $_SESSION['setting']->name(); ?> ]</li>
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

    <h3>Optical Parameters (2)</h3>

    <form method="post" action="capturing_parameter.php" id="select">

        <?php

        /***************************************************************************
         *
         * CCDCaptorSizeX - CCDCaptorSizeY
         ***************************************************************************/

        /** @var CCDCaptorSizeX $parameterCCDCaptorSizeX */
        $parameterCCDCaptorSizeX =
            $_SESSION['setting']->parameter("CCDCaptorSizeX");

        ?>
        <fieldset class="setting
                <?php echo $parameterCCDCaptorSizeX->confidenceLevel(); ?>"
                  onmouseover="changeQuickHelp( 'voxel' );">

            <legend>
                <a href="javascript:openWindow(
                       'http://www.svi.nl/SampleSize')">
                    <img src="images/help.png" alt="?"/>
                </a>
                Voxel Size
            </legend>
            <h4>How were these images captured?</h4>

            <p class="message_confidence_<?php echo $parameterCCDCaptorSizeX->confidenceLevel(); ?>"></p>

            <?php

            $value = $parameterCCDCaptorSizeX->value();            

            ?>
            <table id="table_nyquist">
              <tr>
                <td>
                    <?php 
                    
                    if ($_SESSION['setting']->isArrDetConf()) {
                        $textForCaptorSize = "X pixel size (nm)";
                    } else {
                        $textForCaptorSize = "XY pixel size (nm)";
                    }
                     
                    echo $textForCaptorSize; ?>
                </td>
                <td>
                    <input id="CCDCaptorSizeX"
                           title="Pixel size"
                           name="CCDCaptorSizeX"
                           type="text"
                           size="5"
                           value="<?php echo $value ?>"/>
                </td>
                <td>
                    <?php
                    // The calculation of pixel size from CCD chip makes sense
                    // only for widefield microscopes
                    if ($_SESSION['setting']->isWidefield() ||
                        $_SESSION['setting']->isMultiPointConfocal()
                    ) {
                        ?>

                        <p>
                            <a href="#"
                               onmouseover="TagToTip('ttSpanPixelSizeFromCCD' )"
                               onmouseout="UnTip()"
                               onclick="storeValuesAndRedirect( 'calculate_pixel_size.php');">
                                <img src="images/calc_small.png" alt=""/>
                                Calculate from CCD pixel size
                            </a>
                        </p>

                        <?php
                    }
                    ?>
                    </td>
                </tr>
                <tr>
                    <td>
                    <?php 
                    if ($_SESSION['setting']->isArrDetConf()) {
                        $textForCaptorSize = "Y pixel size (nm)";
                        echo $textForCaptorSize;
                    }
                    ?>
                    
                    </td>
                    <td>
                    <?php
                    if ($_SESSION['setting']->isArrDetConf()) {
                        $parameterCCDCaptorSizeY =
                            $_SESSION['setting']->parameter("CCDCaptorSizeY");
                        $value = $parameterCCDCaptorSizeY->value();       
                    ?>
                    <input id="CCDCaptorSizeY"
                           title="Pixel size"
                           name="CCDCaptorSizeY"
                           type="text"
                           size="5"
                           value="<?php echo $value ?>"/>
                    <?php                    
                    }
                    ?>    
                </td>
                <?php
                if ($_SESSION['setting']->isArrDetConf()) {
                ?>
                <td>
                    <span class="message_small">
                        Notice that <b>Y pixel size should be 4 * X pixel size</b> in Airyscan Fast Mode!
                    </span>
                </td>
                <?php
                }
                ?>
                </tr>
                <tr>
                <td>
                    Z-step (nm):
                </td>
                    <?php

                    /***************************************************************************
                     *
                     * ZStepSize
                     ***************************************************************************/

                    /** @var ZStepSize $parameterZStepSize */
                    $parameterZStepSize = $_SESSION['setting']->parameter("ZStepSize");

                    ?>
                <td>
                    <input id="ZStepSize"
                           title="Z step size"
                           name="ZStepSize"
                           type="text"
                           size="5"
                           value="<?php echo $parameterZStepSize->value() ?>"/>
                </td>
                <td>
                    <span class="message_small">
                        Set to <b>Nyquist rate in Z</b> for 2D datasets.
                    </span>
                </td>
              </tr>
            </table>
            <p class="message_small"><?php echo $NyquistMessage; ?></p>

            <p></p>
            <a href="#"
               onmouseover="TagToTip('ttSpanNyquist')"
               onmouseout="UnTip()"
               onclick="storeValuesAndRedirectExtern(
                      'https://svi.nl/NyquistCalculator');">
                <img src="images/calc_small.png" alt=""/>
                Online Nyquist rate and PSF calculator
                &nbsp;<img src="images/web.png" alt="external link"/>
            </a>

        </fieldset>

        <?php

        /***************************************************************************
         *
         * TimeInterval
         ***************************************************************************/

        /** @var TimeInterval $parameterTimeInterval */
        $parameterTimeInterval = $_SESSION['setting']->parameter("TimeInterval");

        ?>
        <fieldset class="setting
                <?php echo $parameterTimeInterval->confidenceLevel(); ?>"
                  onmouseover="changeQuickHelp( 'time' );">
            <legend>
                <a href="javascript:openWindow(
                    'http://www.svi.nl/TimeSeries')">
                    <img src="images/help.png" alt="?"/>
                </a>
                Time Interval
            </legend>
            <p class="message_confidence_<?php echo $parameterTimeInterval->confidenceLevel(); ?>"></p>
            <ul>
                <li>Time interval (s):
                    <input id="TimeInterval"
                           title="Time interval"
                           name="TimeInterval"
                           type="text"
                           size="5"
                           value="<?php echo $parameterTimeInterval->value() ?>"/>
                <span class="message_small">&nbsp;
                    Set to <b>0</b> for a single time point.
                </span>
                </li>
            </ul>

        </fieldset>

        <?php

        if ($_SESSION['setting']->hasPinhole()) {

            /***************************************************************************
             *
             * PinholeSize
             ***************************************************************************/

            /** @var PinholeSize $parameterPinholeSize */
            $parameterPinholeSize = $_SESSION['setting']->parameter("PinholeSize");

            ?>
            <fieldset class="setting
              <?php echo $parameterPinholeSize->confidenceLevel(); ?>"
                      onmouseover="changeQuickHelp( 'pinhole_radius' );">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/PinholeRadius')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Backprojected Pinhole Radius
                </legend>

                <p class="message_confidence_<?php echo $parameterPinholeSize->confidenceLevel(); ?>"></p>

                <ul>
                    <li>backprojected pinhole radius (nm):</li>
                </ul>

                <?php
                if ($_SESSION['setting']->numberOfChannels() > 1) {
                    ?>  <p></p> <?php
                }
                ?>
                <div class="multichannel">
                    <?php

                    // manage one pinhole radius per channel
                    for ($i = 0; $i < $_SESSION['setting']->numberOfChannels(); $i++) {

                        /* Add a line break after a number of entries. */
                        if ($_SESSION['setting']->numberOfChannels() == 4) {
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
            <input id="PinholeSize<?php echo $i ?>"
                   name="PinholeSize<?php echo $i ?>"
                   title="Pinhole size channel <?php echo $i ?>"
                   type="text"
                   size="8"
                   value="<?php if ($i < sizeof($pinhole)) echo $pinhole[$i] ?>"
                   class="multichannelinput"/>
        </span>&nbsp;
    </span>
                        <?php

                    }

                    ?></div>
                <p></p>

                <?php
                /** @var NumericalAperture $parameterNA */
                $parameterNA = $_SESSION['setting']->parameter("NumericalAperture");
                $na = $parameterNA->value();
                ?>
                <a href="#"
                   onmouseover="TagToTip('ttSpanPinholeRadius' )"
                   onmouseout="UnTip()"
                   onclick="storeValuesAndRedirect(
                       'calculate_bp_pinhole.php?na=<?php echo $na; ?>');">
                    <img src="images/calc_small.png" alt=""/>
                    Backprojected pinhole calculator
                </a>

            </fieldset>
            <?php

        }

        ?>

        <?php

        if ($_SESSION['setting']->isNipkowDisk()) {

            /***************************************************************************
             *
             * PinholeSpacing
             ***************************************************************************/

            /** @var PinholeSpacing $parameterPinholeSpacing */
            $parameterPinholeSpacing = $_SESSION['setting']->parameter('PinholeSpacing');

            ?>
            <fieldset class="setting
              <?php echo $parameterPinholeSpacing->confidenceLevel(); ?>"
                      onmouseover="changeQuickHelp( 'pinhole_spacing' );">
                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/PinholeSpacing')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Backprojected Pinhole Spacing
                </legend>

                <p class="message_confidence_<?php echo $parameterPinholeSpacing->confidenceLevel(); ?>"></p>

                <ul>
                    <li>backprojected pinhole spacing (micron):
                        <input id="PinholeSpacing"
                               title="Pinhole spacing"
                               name="PinholeSpacing"
                               type="text"
                               size="5"
                               value="<?php echo $parameterPinholeSpacing->value() ?>"/>
                    </li>
                </ul>
                <p></p>

                <a href="#"
                   onmouseover="TagToTip('ttSpanPinholeSpacing' )"
                   onmouseout="UnTip()"
                   onclick="storeValuesAndRedirect(
                       'calculate_bp_pinhole.php?na=<?php echo $na; ?>');">
                    <img src="images/calc_small.png" alt=""/>
                    Backprojected pinhole calculator
                </a>

            </fieldset>
            <?php

        }

        ?>

        <div><input name="OK" type="hidden"/></div>

        <div id="controls"
             onmouseover="changeQuickHelp( 'default' )">
            <input type="button" value="" class="icon previous"
                   onmouseover="TagToTip('ttSpanBack' )"
                   onmouseout="UnTip()"
                   onclick="deleteValuesAndRedirect(
                    'microscope_parameter.php' );"/>
            <input type="button" value="" class="icon up"
                   onmouseover="TagToTip('ttSpanCancel' )"
                   onmouseout="UnTip()"
                   onclick="deleteValuesAndRedirect(
                    'select_parameter_settings.php' );"/>
            <input type="submit" value="" class="<?php echo $iconClass; ?>"
                   onmouseover="TagToTip('ttSpanForward' )"
                   onmouseout="UnTip()"
                   onclick="deleteValuesAndProcess();"/>
        </div>

    </form>

</div> <!-- content -->

<div id="rightpanel"
     onmouseover="changeQuickHelp( 'default' )">

    <div id="info">

        <h3>Quick help</h3>

        <div id="contextHelp">
            <p>Here you have to enter the voxel size as it was set during
                the image acquisition. Depending on the microscope type and the
                dataset geometry, you might have to enter additional parameters,
                such as the back-projected pinhole size and spacing, and the
                time
                interval for time series. For microscope types that use cameras
                (such as widefield and spinning disk confocal), you have the
                possibility to calculate the image pixel size from the camera
                pixel size, total magnification, and binning.
            </p>
            <p>
                In <b>Airyscan Fast Mode</b> the sampling size in the Y direction is 4 
                times as large as the sampling size in the X direction. 
            </p>

        </div>

        <?php
        if (!$_SESSION["user"]->isAdmin()) {
            ?>

            <div class="requirements">
                Parameter requirements<br/>adapted for <b>
                    <?php
                    $fileFormat = $_SESSION['setting']->parameter("ImageFileFormat");
                    echo $fileFormat->value();
                    ?>
                </b> files
            </div>

            <?php
        }
        ?>

    </div>

    <div id="message">
        <?php

        echo "<p>$message</p>";

        ?>
    </div>

</div> <!-- rightpanel -->

<?php

include("footer.inc.php");

// Another horrible hack to work around an inexplicable IE8 behavior
if (Util::using_IE() && !isset($_SERVER['HTTP_REFERER'])) {
    ?>
    <script type="text/javascript">
        $(document).ready(retrieveValues());
    </script>
    <?php
}

if (!(strpos($_SERVER['HTTP_REFERER'],
        'calculate_pixel_size.php') === false)
) {

    if (isset($_SESSION['CCDCaptorSizeX_Calculated']) &&
        $_SESSION['CCDCaptorSizeX_Calculated'] == 'true'
    ) {
        ?>
        <script type="text/javascript">
            $(document).ready(retrieveValues(new Array('CCDCaptorSizeX')));
        </script>

        <?php
        // Now remove the SNR_Calculated flag
        unset($_SESSION['CCDCaptorSizeX_Calculated']);

    } else {
        ?>
        <script type="text/javascript">
            $(document).ready(retrieveValues());
        </script>
        <?php
    }
}

if (!(strpos($_SERVER['HTTP_REFERER'],
        'calculate_bp_pinhole.php') === false)
) {
    ?>
    <script type="text/javascript">
        $(document).ready(retrieveValues());
    </script>
    <?php
}
?>

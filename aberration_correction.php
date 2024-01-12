<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\DatabaseConnection;
use hrm\Nav;
use hrm\param\AberrationCorrectionMode;
use hrm\param\AdvancedCorrectionOptions;
use hrm\param\base\Parameter;
use hrm\param\CoverslipRelativePosition;
use hrm\param\ImageFileFormat;

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

/* In this page, all parameters are required! */
$parameterNames = $_SESSION['setting']->correctionParameterNames();
$db = DatabaseConnection::get();
foreach ($parameterNames as $name) {
    /** @var Parameter $parameter */
    $parameter = $_SESSION['setting']->parameter($name);
    $confidenceLevel = $db->getParameterConfidenceLevel('', $name);
    $parameter->setConfidenceLevel($confidenceLevel);
    $_SESSION['setting']->set($parameter);
}

/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */

if ($_SESSION['setting']->checkPostedAberrationCorrectionParameters($_POST)) {
    $saved = $_SESSION['setting']->save();
    $message = $_SESSION['setting']->message();
    if ($saved) {
        header("Location: select_parameter_settings.php");
        exit();
    }
} else {
    $message = $_SESSION['setting']->message();
}


/* *****************************************************************************
 *
 * PREVIOUS PAGE
 *
 **************************************************************************** */

if ($_SESSION['setting']->isSted() || $_SESSION['setting']->isSted3D()) {
    $back = "sted_parameters.php";
} else if ($_SESSION['setting']->isSpim()) {
    $back = "spim_parameters.php";
} else {
    $back = "capturing_parameter.php";
}

/* *****************************************************************************
 *
 * CREATE THE PAGE
 *
 **************************************************************************** */

// Javascript includes
$script = array("settings.js", "quickhelp/help.js",
    "quickhelp/aberrationCorrectionHelp.js");

include("header.inc.php");

?>

<!--
  Tooltips
-->
<span class="toolTip" id="ttSpanBack">
        Go back to previous page.
    </span>
<span class="toolTip" id="ttSpanCancel">
        Abort editing and go back to the image parameters selection page.
        All changes will be lost!
    </span>
<span class="toolTip" id="ttSpanSave">
        Save and return to the image parameters selection page.
    </span>
<span class="toolTip" id="ttCoverslip">
        Use a sample raw image to find the coverslip position.
    </span>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
            echo(Nav::linkWikiPage('HuygensRemoteManagerHelpEnableSACorrection'));
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

    <h3>Spherical Aberration Correction</h3>

    <form method="post" action="" id="select">
    
       <?php
        $parameterObjectiveType =
            $_SESSION['setting']->parameter("ObjectiveType")->value();
        $parameterSampleMedium =
            $_SESSION['setting']->parameter("SampleMedium")->value();
        if (!isset($parameterObjectiveType) || !isset($parameterSampleMedium)) {
           $explain = "The selected combination of objective and sample medium " .
                      "can lead to spherical aberration in the image.";
        } else if ($parameterObjectiveType != $parameterSampleMedium) {
           $explain = "The selected objective type and sample medium produce " .
                      "spherical aberration in the image (refractive index " .
                      "mismatch).";
        }
       ?>
           <h4><?php echo $explain; ?></h4>

      <?php 
        /*******************************************************************
         * PerformAberrationCorrection (deprecated parameter, always on)
         *******************************************************************/
      ?>

        <!-- (2) SPECIFY SAMPLE ORIENTATION -->


        <div id="CoverslipRelativePositionDiv">

            <?php

            /***************************************************************************
             *
             * CoverslipRelativePosition
             ***************************************************************************/

            /** @var CoverslipRelativePosition $parameterCoverslipRelativePosition */
            $parameterCoverslipRelativePosition =
                $_SESSION['setting']->parameter("CoverslipRelativePosition");

            ?>

            <fieldset class="setting <?php
            echo $parameterCoverslipRelativePosition->confidenceLevel(); ?>"
                      onmouseover="changeQuickHelp( 'orientation' );">

                <legend>
                    <a href="javascript:openWindow(
                   'http://www.svi.nl/HuygensRemoteManagerHelpSpecifySampleOrientation')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Sample Orientation
                </legend>
                <h4>To remove the aberration please specify the relative
                    position of the coverslip with respect to the first
                    acquired plane of the dataset.
                </h4>

                <p class="message_confidence_<?php echo $parameterCoverslipRelativePosition->confidenceLevel(); ?>"></p>

                <p><a href="#"
                      onmouseover="TagToTip('ttCoverslip' )"
                      onmouseout="UnTip()"
                      onclick="storeValuesAndRedirect(
                            'coverslip_viewer.php');">
                        <img src="images/preview.png" alt=""/>
                        Visualize the image</a>
                </p>

                <select name="CoverslipRelativePosition"
                        title="Specify sample orientation"
                        class="selection">

                    <?php

                    $possibleValues =
                        $parameterCoverslipRelativePosition->possibleValues();
                    $selectedValue =
                        $parameterCoverslipRelativePosition->value();

                    foreach ($possibleValues as $possibleValue) {
                        $translation = $parameterCoverslipRelativePosition->
                        translatedValueFor($possibleValue);
                        if ($possibleValue == $selectedValue) {
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

            </fieldset>

        </div> <!-- CoverslipRelativePositionDiv -->

        <!-- (3) CHOOSE ADVANCED CORRECTION MODE -->


        <div id="AberrationCorrectionModeDiv">

        <?php

            /***************************************************************************
             *
             * AberrationCorrectionMode
             ***************************************************************************/

            /** @var AberrationCorrectionMode $parameterAberrationCorrectionMode */
            $parameterAberrationCorrectionMode =
                $_SESSION['setting']->parameter("AberrationCorrectionMode");

            ?>


            <fieldset class="setting <?php
            echo $parameterAberrationCorrectionMode->confidenceLevel(); ?>"
                      onmouseover="changeQuickHelp( 'mode' );">

                <legend>
                    <a href="javascript:openWindow(
               'http://www.svi.nl/HuygensRemoteManagerHelpSaCorrectionMode')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Correction Mode
                </legend>
                <h4>Please notice that in certain circumstances, the
                    automatic correction might generate artifacts in the
                    result. If this is the case, please choose the advanced
                    correction mode.
                </h4>

                <select id="AberrationCorrectionMode"
                        title="Correction mode"
                        name="AberrationCorrectionMode"
                        class="selection"
                        onchange="switchAdvancedCorrection();">

                    <?php

                    $possibleValues =
                        $parameterAberrationCorrectionMode->possibleValues();
                    $selectedValue =
                        $parameterAberrationCorrectionMode->value();

                    foreach ($possibleValues as $possibleValue) {
                        $translation = $parameterAberrationCorrectionMode->
                        translatedValueFor($possibleValue);
                        if ($possibleValue == $selectedValue) {
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

                <p class="message_confidence_<?php
                echo $parameterAberrationCorrectionMode->confidenceLevel(); ?>">
                    &nbsp;
                </p>

            </fieldset>

        </div> <!-- AberrationCorrectionModeDiv -->

        <!-- (4) ADVANCED CORRECTION MODE -->

        <?php

        $visibility = " style=\"display: none\"";
        if ($parameterAberrationCorrectionMode->value() == "advanced") {
            $visibility = " style=\"display: block\"";
        }

        ?>

        <div id="AdvancedCorrectionOptionsDiv"<?php echo $visibility ?>>

            <?php

            /***************************************************************************
             *
             * AdvancedCorrectionOptions
             ***************************************************************************/

            /** @var AdvancedCorrectionOptions $parameterAdvancedCorrectionOptions */
            $parameterAdvancedCorrectionOptions =
                $_SESSION['setting']->parameter("AdvancedCorrectionOptions");

            ?>

            <fieldset class="setting <?php echo
            $parameterAdvancedCorrectionOptions->confidenceLevel(); ?>"
                      onmouseover="changeQuickHelp( 'advanced' );">

                <legend>
                    <a href="javascript:openWindow(
           'http://www.svi.nl/HuygensRemoteManagerHelpAdvancedSaCorrection')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Advanced Correction
                </legend>
               <h4>Most aberrations can be removed by using bricks or slice by
                   slice. Slabs work better to restore images with extreme
                   aberrations (e.g. very thick widefield data sets).
               </h4>

                <select id="AdvancedCorrectionOptions"
                        title="Advanced correction scheme"
                        name="AdvancedCorrectionOptions"
                        class="selection"
                        onchange="switchAdvancedCorrectionScheme();">

                    <?php

                    $possibleValues = $parameterAdvancedCorrectionOptions->possibleValues();
                    $selectedValue = $parameterAdvancedCorrectionOptions->value();

                    foreach ($possibleValues as $possibleValue) {
                        $translation = $parameterAdvancedCorrectionOptions->
                        translatedValueFor($possibleValue);
                        if ($possibleValue == $selectedValue) {
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

            </fieldset>

        </div> <!-- AdvancedCorrectionOptionsDiv -->

        <div><input name="OK" type="hidden"/></div>

        <div id="controls" onmouseover="changeQuickHelp( 'default' )">
            <input type="button" value="" class="icon previous"
                   onmouseover="TagToTip('ttSpanBack' )"
                   onmouseout="UnTip()"
                   onclick="document.location.href='<?php echo $back; ?>'"/>
            <input type="button" value="" class="icon up"
                   onmouseover="TagToTip('ttSpanCancel' )"
                   onmouseout="UnTip()"
                   onclick="document.location.href='select_parameter_settings.php'"/>
            <input type="submit" value="" class="icon save"
                   onmouseover="TagToTip('ttSpanSave' )"
                   onmouseout="UnTip()"
                   onclick="process()"/>
        </div>

    </form>

</div> <!-- content -->

<div id="rightpanel" onmouseover="changeQuickHelp( 'default' )">

    <div id="info">

        <h3>Quick help</h3>

        <div id="contextHelp">
            <p>The main cause of spherical aberration is a mismatch between
                the refractive index of the lens immersion medium and specimen
                embedding medium and causes the PSF to become asymmetric at
                depths of already a few &micro;m. SA is especially harmful for
                widefield microscope deconvolution. HRM can correct the image
                for SA automatically, but in case of very large refractive index
                mismatches some artifacts can be generated. Advanced parameters
                allow for fine-tuning of the correction.</p>
        </div>

        <?php
        if (!$_SESSION["user"]->isAdmin()) {
            ?>

            <div class="requirements">
                Parameter requirements<br/>adapted for <b>
                    <?php
                    /** @var ImageFileFormat $fileFormat */
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

?>

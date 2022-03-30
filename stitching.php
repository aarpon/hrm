<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\DatabaseConnection;
use hrm\Nav;
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
$message = "";



/* *****************************************************************************
 *
 * MANAGE FLATFIELD FILE NAMES
 *
 **************************************************************************** */

/** @var Flatfield $flatfieldParam */
$flatfieldParam = $_SESSION['task_setting']->parameter("StitchVignettingFlatfield");
$flatfield = $flatfieldParam->value();

if (isset($_POST["flatfield"])) {
    $flatfield = $_POST["flatfield"];
}

$flatfieldParam->setValue($flatfield);
$_SESSION['task_setting']->set($flatfieldParam);


/* *****************************************************************************
 *
 * MANAGE DARKFRAME FILE NAMES
 *
 **************************************************************************** */

/** @var Darkframe $darkframeParam */
$darkframeParam = $_SESSION['task_setting']->parameter("StitchVignettingDarkframe");
$darkframe = $darkframeParam->value();

if (isset($_POST["darkframe"])) {
    $darkframe = $_POST["darkframe"];
}

$darkframeParam->setValue($darkframe);
$_SESSION['task_setting']->set($darkframeParam);



/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */

if ($_SESSION['user']->isAdmin() || $_SESSION['task_setting']->isEligibleForStitching()) {
    if ($_SESSION['task_setting']->checkPostedStitchingParameters($_POST)) {
        if ($_SESSION['task_setting']->isEligibleForCAC()
            || $_SESSION['task_setting']->isEligibleForTStabilization($_SESSION['setting'])) {
            header("Location: " . "post_processing.php");
            exit();
        } else {
            header("Location: " . "select_hpc.php");
            exit();
        }
    } else {
        $message = $_SESSION['task_setting']->message();
    }
}


/* *****************************************************************************
 *
 * CREATE THE PAGE
 *
 **************************************************************************** */

// Javascript includes
$script = array("settings.js", "quickhelp/help.js", "common.js",
    "quickhelp/stitchingHelp.js");

include("header.inc.php");
?>


<!--
  Tooltips
-->
<span class="toolTip" id="ttSpanCancel">
        Abort editing and go back to the Restoration parameters
        selection page. All changes will be lost!
    </span>
<span class="toolTip" id="ttSpanSave">
        Save and return to the processing parameters selection page.
    </span>
<span class="toolTip" id="ttSpanBack">
        Go back to previous page.
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
    <h3>Restoration - Stitching</h3>

    <form method="post" action="stitching.php" id="stitch">

    <h4>Should the images be stitched? How?</h4>

       <?php
        /***************************************************************************
         *
         * StitchSwitch
         ***************************************************************************/

        /** @var StitchSwitch $stitchSwitch */
        ?>
        <div id="StitchSwitchDiv">
            <fieldset class="setting provided"
                      onmouseover="changeQuickHelp('stitchswitch');">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/HelpStitcher')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Enable Stitching
                </legend>

                <select id="StitchSwitch"
                        title="StitchSwitch"
                        name="StitchSwitch"
                        onchange="updateStitchingOptions()"
                        class="selection">
                    <?php

                    /*
                          STITCHSWITCH
                    */
                    $parameterSwitch =
                        $_SESSION['task_setting']->parameter("StitchSwitch");
                    $possibleValues = $parameterSwitch->possibleValues();
                    $selectedMode = $parameterSwitch->value();

                    foreach ($possibleValues as $possibleValue) {
                        $translation =
                            $parameterSwitch->translatedValueFor($possibleValue);
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
        </div> <!-- StitchSwitchDiv -->                                 


        <?php
        /***************************************************************************
         *
         * StitchOffsetsInit
         ***************************************************************************/

        /** @var StitchOffsetsInit $stitchOffsetsInit */
        ?>
        <div id="StitchOffsetsInitDiv">
            <fieldset class="setting provided"
                      onmouseover="changeQuickHelp('stitchoffsetsinit');">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/HelpStitcher')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Initial Offsets
                </legend>

                <select id="StitchOffsetsInit"
                        title="StitchOffsetsInit"
                        name="StitchOffsetsInit"
                        onchange="updateStitchingOptions()"
                        class="selection">
                    <?php

                    /*
                          STITCHOFFSETSINIT
                    */
                    $parameterOffsetsInit =
                        $_SESSION['task_setting']->parameter("StitchOffsetsInit");
                    $possibleValues = $parameterOffsetsInit->possibleValues();
                    $selectedMode = $parameterOffsetsInit->value();

                    foreach ($possibleValues as $possibleValue) {
                        $translation =
                            $parameterOffsetsInit->translatedValueFor($possibleValue);
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
        </div> <!-- StitchOffsetsInitDiv -->
       


        <?php
        /***************************************************************************
         *
         * StitchAcquisitionPattern
         ***************************************************************************/

        /** @var StitchAcquisitionPattern $stitchOAcquisitionPattern */
        ?>
        <div id="StitchAcquisitionPatternDiv">
            <fieldset class="setting provided"
                      onmouseover="changeQuickHelp('stitchacquisitionpattern');">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/HelpStitcher')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Acquisition Pattern
                </legend>

                <select id="StitchAcquisitionPattern"
                        title="StitchAcquisitionPattern"
                        name="StitchAcquisitionPattern"
                        class="selection">
                    <?php

                    /*
                          STITCHACQUISITIONPATTERN
                    */
                    $parameterAcqPattern =
                        $_SESSION['task_setting']->parameter("StitchAcquisitionPattern");
                    $possibleValues = $parameterAcqPattern->possibleValues();
                    $selectedMode = $parameterAcqPattern->value();

                    foreach ($possibleValues as $possibleValue) {
                        $translation =
                            $parameterAcqPattern->translatedValueFor($possibleValue);
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
        </div> <!-- StitchAcquisitionPatternDiv -->


        <?php
        /***************************************************************************
         *
         * StitchAcquisitionStart
         ***************************************************************************/

        /** @var StitchAcquisitionStart $stitchAcquisitionStart */
        ?>
        <div id="StitchAcquisitionStartDiv">
            <fieldset class="setting provided"
                      onmouseover="changeQuickHelp('stitchacquisitionstart');">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/HelpStitcher')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Acquisition Start
                </legend>

                <select id="StitchAcquisitionStart"
                        title="StitchAcquisitionStart"
                        name="StitchAcquisitionStart"
                        class="selection">
                    <?php

                    /*
                          STITCHACQUISITIONSTART
                    */
                    $parameterAcqStart =
                        $_SESSION['task_setting']->parameter("StitchAcquisitionStart");
                    $possibleValues = $parameterAcqStart->possibleValues();
                    $selectedMode = $parameterAcqStart->value();

                    foreach ($possibleValues as $possibleValue) {
                        $translation =
                            $parameterAcqStart->translatedValueFor($possibleValue);
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
        </div> <!-- StitchAcquisitionStartDiv -->


        <table><tr><td>    
        <?php
        /***************************************************************************
         *
         * StitchPatternWidth
         ***************************************************************************/

        /** @var StitchPatternWidth $stitchPatternWidth */
        ?>
        <div id="StitchPatternWidthDiv">
            <fieldset class="setting provided"
                      onmouseover="changeQuickHelp('stitchpatternwidth');">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/HelpStitcher')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Pattern Width
                </legend>

                <?php
                $parameterPatternWidth =
                        $_SESSION['task_setting']->parameter("StitchPatternWidth");
                $value = $parameterPatternWidth->value();
                ?>

                <input id="StitchPatternWidth"
                       name="StitchPatternWidth"
                       title="Pattern Width"
                       type="text"
                       size="6"
                       value="<?php echo $value ?>"/> 

            </fieldset>
        </div> <!-- StitchPatternWidthDiv -->

        </td><td>                   

        <?php
        /***************************************************************************
         *
         * StitchPatternHeight
         ***************************************************************************/

        /** @var StitchPatternHeight $stitchPatternHeight */
        ?>
        <div id="StitchPatternHeightDiv">
            <fieldset class="setting provided"
                      onmouseover="changeQuickHelp('stitchpatternheight');">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/HelpStitcher')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Pattern Height
                </legend>

                <?php
                $parameterPatternHeight =
                        $_SESSION['task_setting']->parameter("StitchPatternHeight");
                $value = $parameterPatternHeight->value();
                ?>

                <input id="StitchPatternHeight"
                       name="StitchPatternHeight"
                       title="Pattern Height"
                       type="text"
                       size="6"
                       value="<?php echo $value ?>"/> 

            </fieldset>
        </div> <!-- StitchPatternHeightDiv -->

        </td><td>


        <?php
        /***************************************************************************
         *
         * StitchAcquisitionOverlap
         ***************************************************************************/

        /** @var StitchAcquisitionOverlap $stitchAcquisitionOverlap */
        ?>
        <div id="StitchAcquisitionOverlapDiv">
            <fieldset class="setting provided"
                      onmouseover="changeQuickHelp('stitchacquisitionoverlap');">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/HelpStitcher')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Tile Overlap
                </legend>

                <?php
                $parameterAcqOverlap =
                        $_SESSION['task_setting']->parameter("StitchAcquisitionOverlap");
                $value = $parameterAcqOverlap->value();
                ?>

                <input id="StitchAcquisitionOverlap"
                       name="StitchAcquisitionOverlap"
                       title="Acquisition Overlap"
                       type="text"
                       size="6"
                       value="<?php echo $value ?>"/>

            </fieldset>
        </div> <!-- StitchAcquisitionOverlapDiv -->

        </td></tr></table>


        <?php
        /***************************************************************************
         *
         * StitchOptimizationChannels
         ***************************************************************************/

        /** @var StitchOptimizationChannels $stitchOptimizationChannels */
        ?>

        <div id="StitchOptimizationChannelsDiv">
            <fieldset class="setting"
                      onmouseover="changeQuickHelp( 'stitchoptimizationchannels' );">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/Stitcher')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Channels Used For Optimization Of Tile Positions
                </legend>

                <?php
                $parameterOptimizationChannels =
                    $_SESSION['task_setting']->parameter("StitchOptimizationChannels");

                $selectedValues = $parameterOptimizationChannels->value();

                for ($chan = 0;
                     $chan < $_SESSION['task_setting']->numberOfChannels();
                     $chan++) {
                    if (true == Util::isValueInArray($selectedValues, $chan)) {
                        $checked = "checked";
                    } else {
                        $checked = "";
                    }

                    ?>
                    Ch. <?php echo $chan; ?>: <input type="checkbox"
                                                     title="Optimization channel <?php echo $chan; ?>"
                                                     name="StitchOptimizationChannels[]"
                                                     value=<?php echo $chan;
                                                     if ($checked) {
                                                     ?> checked=<?php echo $checked;
                    } ?>
                    />
                <?php } ?>
            </fieldset>
        </div> <!-- StitchOptimizationChannelsDiv -->

                           

        <?php
        /***************************************************************************
         *
         * StitchAlignmentMode
         ***************************************************************************/

        /** @var StitchAlignmentMode $stitchAlignmentMode */
        ?>
        <div id="StitchAlignmentModeDiv">
            <fieldset class="setting provided"
                      onmouseover="changeQuickHelp('stitchalignmentmode');">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/HelpStitcher')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Alignment Mode
                </legend>

                <select id="StitchAlignmentMode"
                        title="StitchAlignmentMode"
                        name="StitchAlignmentMode"
                        class="selection">
                    <?php

                    /*
                          STITCHALIGNMENTMODE
                    */
                    $parameterAlignmentMode =
                        $_SESSION['task_setting']->parameter("StitchAlignmentMode");
                    $possibleValues = $parameterAlignmentMode->possibleValues();
                    $selectedMode = $parameterAlignmentMode->value();

                    foreach ($possibleValues as $possibleValue) {
                        $translation =
                            $parameterAlignmentMode->translatedValueFor($possibleValue);
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
        </div> <!-- StitchAligmentModeDiv -->



        <?php
        /***************************************************************************
         *
         * StitchPrefilterMode
         ***************************************************************************/

        /** @var StitchPrefilterMode $stitchPrefilterMode */
        ?>
        <div id="StitchPrefilterModeDiv">
            <fieldset class="setting provided"
                      onmouseover="changeQuickHelp('stitchalignmentmode');">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/HelpStitcher')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Hotpixel Prefilter Mode
                </legend>

                <select id="StitchPrefilterMode"
                        title="StitchPrefilterMode"
                        name="StitchPrefilterMode"
                        class="selection">
                    <?php

                    /*
                          STITCHPREFILTERMODE
                    */
                    $parameterPrefilterMode =
                        $_SESSION['task_setting']->parameter("StitchPrefilterMode");
                    $possibleValues = $parameterPrefilterMode->possibleValues();
                    $selectedMode = $parameterPrefilterMode->value();

                    foreach ($possibleValues as $possibleValue) {
                        $translation =
                            $parameterPrefilterMode->translatedValueFor($possibleValue);
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
        </div> <!-- StitchPrefilterModeDiv -->



        <?php
        /***************************************************************************
         *
         * StitchVignettingMode
         ***************************************************************************/

        /** @var StitchVignettingMode $stitchVignettingMode */
        ?>
        <div id="StitchVignettingModeDiv">
            <fieldset class="setting provided"
                      onmouseover="changeQuickHelp('stitchalignmentmode');">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/HelpStitcher')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Vignetting Mode
                </legend>

                <select id="StitchVignettingMode"
                        title="StitchVignettingMode"
                        name="StitchVignettingMode"
                        onchange="updateStitchingVignettingOptions()"
                        class="selection">
                    <?php

                    /*
                          STITCHVIGNETTINGMODE
                    */
                    $parameterVignettingMode =
                        $_SESSION['task_setting']->parameter("StitchVignettingMode");
                    $possibleValues = $parameterVignettingMode->possibleValues();
                    $selectedMode = $parameterVignettingMode->value();

                    foreach ($possibleValues as $possibleValue) {
                        $translation =
                            $parameterVignettingMode->translatedValueFor($possibleValue);
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
        </div> <!-- StitchVignettingModeDiv -->



        <?php
        /***************************************************************************
         *
         * StitchVignettingChannels
         ***************************************************************************/

        /** @var StitchVignettingChannels $stitchVignettingChannels */
        ?>

        <div id="StitchVignettingChannelsDiv">
            <fieldset class="setting"
                      onmouseover="changeQuickHelp( 'stitchvignettingchannels' );">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/Stitcher')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Channels For Estimation Of Vignetting
                </legend>

                <?php
                $parameterVignettingChannels =
                    $_SESSION['task_setting']->parameter("StitchVignettingChannels");

                $selectedValues = $parameterVignettingChannels->value();

                for ($chan = 0;
                     $chan < $_SESSION['task_setting']->numberOfChannels();
                     $chan++) {
                    if (true == Util::isValueInArray($selectedValues, $chan)) {
                        $checked = "checked";
                    } else {
                        $checked = "";
                    }

                    ?>
                    Ch. <?php echo $chan; ?>: <input type="checkbox"
                                                     title="Vignetting channel <?php echo $chan; ?>"
                                                     name="StitchVignettingChannels[]"
                                                     value=<?php echo $chan;
                                                     if ($checked) {
                                                     ?> checked=<?php echo $checked;
                    } ?>
                    />
                <?php } ?>
            </fieldset>
        </div> <!-- StitchVignettingChannelsDiv -->
            



        <?php
        /***************************************************************************
         *
         * StitchVignettingModel
         ***************************************************************************/

        /** @var StitchVignettingModel $stitchVignettingModel */
        ?>
        <div id="StitchVignettingModelDiv">
            <fieldset class="setting provided"
                      onmouseover="changeQuickHelp('stitchvignettingmodel');">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/HelpStitcher')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Vignetting Model
                </legend>

                <select id="StitchVignettingModel"
                        title="StitchVignettingModel"
                        name="StitchVignettingModel"
                        class="selection">
                    <?php

                    /*
                          STITCHVIGNETTINGMODEL
                    */
                    $parameterVignettingModel =
                        $_SESSION['task_setting']->parameter("StitchVignettingModel");
                    $possibleValues = $parameterVignettingModel->possibleValues();
                    $selectedMode = $parameterVignettingModel->value();

                    foreach ($possibleValues as $possibleValue) {
                        $translation =
                            $parameterVignettingModel->translatedValueFor($possibleValue);
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
        </div> <!-- StitchVignettingModelDiv -->


            
        <?php
        /***************************************************************************
         *
         * StitchVignettingAdjustment
         ***************************************************************************/

        /** @var StitchVignettingAdjustment $stitchVignettingAdjustment */
        ?>
        <div id="StitchVignettingAdjustmentDiv">
            <fieldset class="setting provided"
                      onmouseover="changeQuickHelp('stitchvignettingadjustment');">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/HelpStitcher')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Vignetting Adjustment
                </legend>

                <?php
                $parameterVignettingAdjustment =
                        $_SESSION['task_setting']->parameter("StitchVignettingAdjustment");
                $value = $parameterVignettingAdjustment->value();
                ?>

                <input id="StitchVignettingAdjustment"
                       name="StitchVignettingAdjustment"
                       title="Vignetting Adjustment"
                       type="text"
                       size="6"
                       value="<?php echo $value ?>"/>

            </fieldset>
        </div> <!-- StitchVignettingAdjustmentDiv -->



        <?php
        /***************************************************************************
         *
         * StitchVignettingFlatfield
         ***************************************************************************/

        /** @var StitchVignettingFlatfield $stitchVignettingFlatfield */
        ?>
        <div id="StitchVignettingFlatfieldDiv" class="provided">
             <fieldset class="setting provided"
                      onmouseover="changeQuickHelp('stitchvignettingflatfield');">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/HelpStitcher')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Vignetting - Flatfield Image
                </legend>

            <?php
                $parameter = $_SESSION['task_setting']->parameter("StitchVignettingFlatfield");
                $value = $parameter->value();
                $missing = False;
                $_SESSION['fileserver']->imageExtensions();
                $files = $_SESSION['fileserver']->allFiles();
                if ($files != null) {
                    if (!in_array($value[0], $files)) {
                        $missing = True;
                    }

                    ?>
                    <p>
                        <input name="StitchVignettingFlatfield"
                               title="Select a flatfield reference image"
                               type="text"
                               value="<?php echo $value[0] ?>"
                               class="
                           <?php
                               if ($missing) {
                                   echo "missing flatfield reference image";
                               } else {
                                   echo "flatfield reference image file";
                               } ?>"
                               readonly="readonly"/>
                        <input type="button"
                               onclick="seek('0', 'flatfield')"
                               value="browse"/>
                        <input type="button"
			       onclick="flatfieldReset()"
                               value="reset"/>
                    </p>
                    <?php

                } else {
                    if (!file_exists($_SESSION['fileserver']->sourceFolder())) {

                        ?>
                        <p class="info">Source image folder not found! Make sure
                            the
                            folder <?php echo $_SESSION['fileserver']->sourceFolder() ?>
                            exists.</p>
                        <?php

                    } else {

                        ?>
                        <p class="info">No images found on the server!</p>
                        <?php

                    }
                }
            ?>
     	    <p class="info">The correction is optional: leave empty for skipping.</p>
            </fieldset>
        </div> <!-- StitchVignettingFlatfieldDiv -->


        <?php
        /***************************************************************************
         *
         * StitchVignettingDarkframe
         ***************************************************************************/

        /** @var StitchVignettingDarkframe $stitchVignettingDarkframe */
        ?>
        <div id="StitchVignettingDarkframeDiv" class="provided">
             <fieldset class="setting provided"
                      onmouseover="changeQuickHelp('stitchvignettingdarkframe');">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/HelpStitcher')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Vignetting - Darkframe Image
                </legend>

            <?php
                $parameter = $_SESSION['task_setting']->parameter("StitchVignettingDarkframe");
                $value = $parameter->value();
                $missing = False;
                $_SESSION['fileserver']->imageExtensions();
                $files = $_SESSION['fileserver']->allFiles();
                if ($files != null) {
                    if (!in_array($value[0], $files)) {
                        $missing = True;
                    }

                    ?>
                    <p>
                        <input name="StitchVignettingDarkframe"
                               title="Select a darkframe reference image"
                               type="text"
                               value="<?php echo $value[0] ?>"
                               class="
                           <?php
                               if ($missing) {
                                   echo "missing darkframe reference image";
                               } else {
                                   echo "darkframe reference image file";
                               } ?>"
                               readonly="readonly"/>
                        <input type="button"
                               onclick="seek('0', 'darkframe')"
                               value="browse"/>
                        <input type="button"
			       onclick="darkframeReset()"
                               value="reset"/>
                    </p>
                    <?php

                } else {
                    if (!file_exists($_SESSION['fileserver']->sourceFolder())) {

                        ?>
                        <p class="info">Source image folder not found! Make sure
                            the
                            folder <?php echo $_SESSION['fileserver']->sourceFolder() ?>
                            exists.</p>
                        <?php

                    } else {

                        ?>
                        <p class="info">No images found on the server!</p>
                        <?php

                    }
                }
            ?>
     	    <p class="info">The correction is optional: leave empty for skipping.</p>
            </fieldset>
        </div> <!-- StitchVignettingDarkframeDiv -->
                           
                                    
                          
    <div><input name="OK" type="hidden"/></div>

    <div id="controls"
         onmouseover="changeQuickHelp( 'default' )">
        <input type="button" value="" class="icon previous"
               onmouseover="TagToTip('ttSpanBack' )"
               onmouseout="UnTip()"
               onclick="document.location.href='task_parameter.php'"/>
        <input type="button" value="" class="icon up"
               onmouseover="TagToTip('ttSpanCancel' )"
               onmouseout="UnTip()"
               onclick="deleteValuesAndRedirect(
                    'select_task_settings.php' );"/>
        <input type="submit" value="" class="icon next"
               onmouseover="TagToTip('ttSpanForward' )"
               onmouseout="UnTip()"
               onclick="process()"/>
    </div>

    </form>                          
</div> <!-- content -->
                               




<div id="rightpanel" onmouseover="changeQuickHelp( 'default' )">
    <div id="info">
        <h3>Quick help</h3>
        <div id="contextHelp">
            <p>On this page you specify the parameters of those
               restoration operations to be carried out on the deconvolved
               image.</p>
        </div>
    </div>

    <div id="message">
        <?php

        echo "<p>$message</p>";

        ?>
    </div>

</div> <!-- rightpanel -->


<script type="text/javascript">
   updateStitchingOptions();
</script>
    

<?php   
include("footer.inc.php");

// Workaround for IE
if (Util::using_IE() && !isset($_SERVER['HTTP_REFERER'])) {
    ?>
    <script type="text/javascript">
        $(document).ready(retrieveValues());
    </script>
    <?php
}
?>

<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\DatabaseConnection;
use hrm\Nav;
use hrm\param\base\Parameter;
use hrm\param\ImageFileFormat;
use hrm\param\SpimCenterOffset;
use hrm\param\SpimDir;
use hrm\param\SpimExcMode;
use hrm\param\SpimFill;
use hrm\param\SpimFocusOffset;
use hrm\param\SpimGaussWidth;
use hrm\param\SpimNA;

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

$chanCnt = $_SESSION['setting']->numberOfChannels();

/* *****************************************************************************
 *
 * SET THE CONFIDENCES FOR THE RELEVANT PARAMETERS
 *
 **************************************************************************** */

/** @var ImageFileFormat $fileFormat */
$fileFormat = $_SESSION['setting']->parameter("ImageFileFormat");
$parameterNames = $_SESSION['setting']->spimParameterNames();
$db = DatabaseConnection::get();
foreach ($parameterNames as $name) {
    /** @var Parameter $parameter */
    $parameter = $_SESSION['setting']->parameter($name);
    $confidenceLevel =
        $db->getParameterConfidenceLevel($fileFormat->value(), $name);
    $parameter->setConfidenceLevel($confidenceLevel);
    $_SESSION['setting']->set($parameter);
}


/* *****************************************************************************
 *
 * MANAGE THE SPIM EXCITATION MODE
 *
 **************************************************************************** */

/** @var SpimExcMode $spimExcModeParam */
$spimExcModeParam = $_SESSION['setting']->parameter("SpimExcMode");
$spimExcMode = $spimExcModeParam->value();
for ($i = 0; $i < $chanCnt; $i++) {
    $spimExcModeKey = "SpimExcMode{$i}";
    if (isset($_POST[$spimExcModeKey])) {
        $spimExcMode[$i] = $_POST[$spimExcModeKey];
    }
}
$spimExcModeParam->setValue($spimExcMode);
$_SESSION['setting']->set($spimExcModeParam);

/* *****************************************************************************
 *
 * MANAGE THE SPIM GAUSSIAN WIDTH
 *
 **************************************************************************** */

/** @var SpimGaussWidth $spimGaussWidthParam */
$spimGaussWidthParam = $_SESSION['setting']->parameter("SpimGaussWidth");
$spimGaussWidthParam->setNumberOfChannels($chanCnt);
$spimGaussWidth = $spimGaussWidthParam->value();

for ($i = 0; $i < $chanCnt; $i++) {
    $spimGaussWidthKey = "SpimGaussWidth{$i}";
    if (isset($_POST[$spimGaussWidthKey])) {
        $spimGaussWidth[$i] = $_POST[$spimGaussWidthKey];
    }
}

$spimGaussWidthParam->setValue($spimGaussWidth);
$spimGaussWidthParam->setNumberOfChannels($chanCnt);

$_SESSION['setting']->set($spimGaussWidthParam);

/* *****************************************************************************
 *
 * MANAGE THE SPIM SHEET FOCUS OFFSET
 *
 **************************************************************************** */

/** @var SpimFocusOffset $spimFocusOffsetParam */
$spimFocusOffsetParam = $_SESSION['setting']->parameter("SpimFocusOffset");
$spimFocusOffsetParam->setNumberOfChannels($chanCnt);
$spimFocusOffset = $spimFocusOffsetParam->value();

for ($i = 0; $i < $chanCnt; $i++) {
    $spimFocusOffsetKey = "SpimFocusOffset{$i}";
    if (isset($_POST[$spimFocusOffsetKey])) {
        $spimFocusOffset[$i] = $_POST[$spimFocusOffsetKey];
    }
}
$spimFocusOffsetParam->setValue($spimFocusOffset);
$spimFocusOffsetParam->setNumberOfChannels($chanCnt);

$_SESSION['setting']->set($spimFocusOffsetParam);

/* *****************************************************************************
 *
 * MANAGE THE SPIM SHEET CENTER OFFSET
 *
 **************************************************************************** */

/** @var SpimCenterOffset $spimCenterOffsetParam */
$spimCenterOffsetParam = $_SESSION['setting']->parameter("SpimCenterOffset");
$spimCenterOffsetParam->setNumberOfChannels($chanCnt);
$spimCenterOffset = $spimCenterOffsetParam->value();

for ($i = 0; $i < $chanCnt; $i++) {
    $spimCenterOffsetKey = "SpimCenterOffset{$i}";
    if (isset($_POST[$spimCenterOffsetKey])) {
        $spimCenterOffset[$i] = $_POST[$spimCenterOffsetKey];
    }
}
$spimCenterOffsetParam->setValue($spimCenterOffset);
$spimCenterOffsetParam->setNumberOfChannels($chanCnt);

$_SESSION['setting']->set($spimCenterOffsetParam);

/* *****************************************************************************
 *
 * MANAGE THE SPIM NA
 *
 **************************************************************************** */

/** @var SpimNA $spimNAParam */
$spimNAParam = $_SESSION['setting']->parameter("SpimNA");
$spimNAParam->setNumberOfChannels($chanCnt);
$spimNA = $spimNAParam->value();

for ($i = 0; $i < $chanCnt; $i++) {
    $spimNAKey = "SpimNA{$i}";
    if (isset($_POST[$spimNAKey])) {
        $spimNA[$i] = $_POST[$spimNAKey];
    }
}
$spimNAParam->setValue($spimNA);
$spimNAParam->setNumberOfChannels($chanCnt);

$_SESSION['setting']->set($spimNAParam);

/* *****************************************************************************
 *
 * MANAGE THE SPIM FILL FACTOR
 *
 **************************************************************************** */

/** @var SpimFill $spimFillParam */
$spimFillParam = $_SESSION['setting']->parameter("SpimFill");
$spimFillParam->setNumberOfChannels($chanCnt);
$spimFill = $spimFillParam->value();

for ($i = 0; $i < $chanCnt; $i++) {
    $spimFillKey = "SpimFill{$i}";
    if (isset($_POST[$spimFillKey])) {
        $spimFill[$i] = $_POST[$spimFillKey];
    }
}
$spimFillParam->setValue($spimFill);
$spimFillParam->setNumberOfChannels($chanCnt);

$_SESSION['setting']->set($spimFillParam);

/* *****************************************************************************
 *
 * MANAGE THE SPIM DIRECTION
 *
 **************************************************************************** */

/** @var SpimDir $spimDirParam */
$spimDirParam = $_SESSION['setting']->parameter("SpimDir");
$spimDirParam->setNumberOfChannels($chanCnt);
$spimDir = $spimDirParam->value();

for ($i = 0; $i < $chanCnt; $i++) {
    $spimDirKey = "SpimDir{$i}";
    if (isset($_POST[$spimDirKey])) {
        $spimDir[$i] = $_POST[$spimDirKey];
    }
}
$spimDirParam->setValue($spimDir);
$spimDirParam->setNumberOfChannels($chanCnt);

$_SESSION['setting']->set($spimDirParam);


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

if ($PSF == 'measured') {
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

if ($_SESSION['setting']->checkPostedSpimParameters($_POST)) {
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
    "quickhelp/spimParameters.js");
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
<span class="toolTip" id="ttSpanForward">
        Continue to next page.
    </span>
<span class="toolTip" id="ttSpanSave">
        Save and return to the image parameters selection page.
    </span>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
            echo(Nav::linkWikiPage('SPIM'));
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

    <h3>SPIM Parameters </h3>

    <form method="post" action="" id="select">

        <h4>How did you set up the SPIM system?</h4>


        <?php
        /***************************************************************************
         *
         * SpimExcMode
         ***************************************************************************/

        /** @var SpimExcMode $parameterSpimExcMode */
        $parameterSpimExcMode = $_SESSION['setting']->parameter("SpimExcMode");
        ?>

        <fieldset class="setting <?php
        echo $parameterSpimExcMode->confidenceLevel(); ?>"
                  onmouseover="changeQuickHelp( 'excMode' );">

            <legend>
                <a href="javascript:openWindow(
                       'http://www.svi.nl/HuygensRemoteManagerHelpSPIM')">
                    <img src="images/help.png" alt="?"/>
                </a>
                SPIM Excitation Mode
            </legend>

            <p class="message_confidence_<?php echo $parameterSpimExcMode->confidenceLevel(); ?>"></p>

            <div class="SpimExcModeValues">
                <table class="SpimExcModeValues">

                    <?php
                    $possibleValues = $parameterSpimExcMode->possibleValues();

                    /* Loop on rows. */
                    for ($chan = 0; $chan < $chanCnt; $chan++) {
                        ?>
                        <tr>
                            <td>Ch<?php echo $chan; ?>:</td>

                            <td>
                                <select name="SpimExcMode<?php echo $chan; ?>"
                                        title="SPIM excitation mode for channel <?php echo $chan; ?>"
                                        class="selection"
                                        onchange="changeSpimEntryProperties(this,<?php echo $chan; ?>)">

                                    <?php
                                    /* Loop for select options. */

                                    foreach ($possibleValues as $possibleValue) {
                                        $translatedValue =
                                            $parameterSpimExcMode->translatedValueFor($possibleValue);

                                        if ($translatedValue == $spimExcMode[$chan]) {
                                            $selected = " selected=\"selected\"";
                                        } else {
                                            $selected = "";
                                        }
                                        ?>
                                        <option
                                                value=<?php echo $translatedValue;
                                        echo $selected; ?>>
                                            <?php echo $possibleValue; ?>
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

                </table> <!-- SpimExcModeValues -->
            </div> <!-- SpimExcModeValues -->

        </fieldset>


        <?php
        /***************************************************************************
         *
         * SpimGaussWidth
         ***************************************************************************/

        /** @var SpimGaussWidth $parameterSpimGaussWidth */
        $parameterSpimGaussWidth =
            $_SESSION['setting']->parameter("SpimGaussWidth");
        ?>

        <fieldset class="setting <?php
        echo $parameterSpimGaussWidth->confidenceLevel(); ?>"
                  onmouseover="changeQuickHelp( 'gaussWidth' );">

            <legend>
                <a href="javascript:openWindow(
                       'http://www.svi.nl/HuygensRemoteManagerHelpSPIM')">
                    <img src="images/help.png" alt="?"/>
                </a>
                SPIM Gauss Width (&#956;m)
            </legend>

            <p class="message_confidence_<?php echo $parameterSpimGaussWidth->confidenceLevel(); ?>"></p>

            <div class="multichannel">
                <?php

                for ($i = 0; $i < $chanCnt; $i++) {

// Add a line break after 3 entries
                    if ($i == 3) {
                        echo "<br />";
                    }
                    ?>
                    <span class="nowrap">
        Ch<?php echo $i ?>:&nbsp;&nbsp;&nbsp;
        <span class="multichannel">
            <input name="SpimGaussWidth<?php echo $i ?>"
                   id="SpimGaussWidth<?php echo $i ?>"
                   title="SPIM Gauss width for channel <?php echo $chan; ?>"
                   type="text"
                   size="6"
                   value="<?php
                   if ($i <= sizeof($spimGaussWidth)) {
                       echo $spimGaussWidth[$i];
                   } ?>"
                   class="multichannelinput"/>
        </span>&nbsp;
    </span>
                    <?php
                }
                ?>

        </fieldset>

        <?php
        /***************************************************************************
         *
         * SpimFocusOffset
         ***************************************************************************/

        /** @var SpimFocusOffset $parameterSpimFocusOffset */
        $parameterSpimFocusOffset =
            $_SESSION['setting']->parameter("SpimFocusOffset");
        ?>

        <fieldset class="setting <?php
        echo $parameterSpimFocusOffset->confidenceLevel(); ?>"
                  onmouseover="changeQuickHelp( 'focusOffset' );">

            <legend>
                <a href="javascript:openWindow(
                       'http://www.svi.nl/HuygensRemoteManagerHelpSPIM')">
                    <img src="images/help.png" alt="?"/>
                </a>
                SPIM Focus Offset (&#956;m)
            </legend>

            <p class="message_confidence_<?php echo $parameterSpimFocusOffset->confidenceLevel(); ?>"></p>

            <div class="multichannel">
                <?php

                for ($i = 0; $i < $chanCnt; $i++) {

// Add a line break after 3 entries
                    if ($i == 3) {
                        echo "<br />";
                    }
                    ?>
                    <span class="nowrap">
        Ch<?php echo $i ?>:&nbsp;&nbsp;&nbsp;
        <span class="multichannel">
            <input name="SpimFocusOffset<?php echo $i ?>"
                   id="SpimFocusOffset<?php echo $i ?>"
                   title="SPIM focus offset for channel <?php echo $chan; ?>"
                   type="text"
                   size="6"
                   value="<?php
                   if ($i <= sizeof($spimFocusOffset)) {
                       echo $spimFocusOffset[$i];
                   } ?>"
                   class="multichannelinput"/>
        </span>&nbsp;
    </span>
                    <?php
                }
                ?>

        </fieldset>


        <?php
        /***************************************************************************
         *
         * SpimCenterOffset
         ***************************************************************************/

        /** @var SpimCenterOffset $parameterSpimCenterOffset */
        $parameterSpimCenterOffset =
            $_SESSION['setting']->parameter("SpimCenterOffset");
        ?>

        <fieldset class="setting <?php
        echo $parameterSpimCenterOffset->confidenceLevel(); ?>"
                  onmouseover="changeQuickHelp( 'centerOffset' );">

            <legend>
                <a href="javascript:openWindow(
                       'http://www.svi.nl/HuygensRemoteManagerHelpSPIM')">
                    <img src="images/help.png" alt="?"/>
                </a>
                SPIM Center Offset (&#956;m)
            </legend>

            <p class="message_confidence_<?php echo $parameterSpimCenterOffset->confidenceLevel(); ?>"></p>

            <div class="multichannel">
                <?php

                for ($i = 0; $i < $chanCnt; $i++) {

// Add a line break after 3 entries
                    if ($i == 3) {
                        echo "<br />";
                    }
                    ?>
                    <span class="nowrap">
        Ch<?php echo $i ?>:&nbsp;&nbsp;&nbsp;
        <span class="multichannel">
            <input name="SpimCenterOffset<?php echo $i ?>"
                   id="SpimCenterOffset<?php echo $i ?>"
                   title="SPIM center offset for channel <?php echo $chan; ?>"
                   type="text"
                   size="6"
                   value="<?php
                   if ($i <= sizeof($spimCenterOffset)) {
                       echo $spimCenterOffset[$i];
                   } ?>"
                   class="multichannelinput"/>
        </span>&nbsp;
    </span>
                    <?php
                }
                ?>

        </fieldset>


        <?php
        /***************************************************************************
         *
         * SpimNA
         ***************************************************************************/

        /** @var SpimNA $parameterSpimNA */
        $parameterSpimNA = $_SESSION['setting']->parameter("SpimNA");
        ?>

        <fieldset class="setting <?php
        echo $parameterSpimNA->confidenceLevel(); ?>"
                  onmouseover="changeQuickHelp( 'NA' );">

            <legend>
                <a href="javascript:openWindow(
                       'http://www.svi.nl/HuygensRemoteManagerHelpSPIM')">
                    <img src="images/help.png" alt="?"/>
                </a>
                SPIM NA
            </legend>

            <p class="message_confidence_<?php echo $parameterSpimNA->confidenceLevel(); ?>"></p>

            <div class="multichannel">
                <?php

                for ($i = 0; $i < $chanCnt; $i++) {

// Add a line break after 3 entries
                    if ($i == 3) {
                        echo "<br />";
                    }
                    ?>
                    <span class="nowrap">
        Ch<?php echo $i ?>:&nbsp;&nbsp;&nbsp;
        <span class="multichannel">
            <input name="SpimNA<?php echo $i ?>"
                   id="SpimNA<?php echo $i ?>"
                   title="SPIM numerical aperture for channel <?php echo $chan; ?>"
                   type="text"
                   size="6"
                   value="<?php
                   if ($i <= sizeof($spimNA)) {
                       echo $spimNA[$i];
                   } ?>"
                   class="multichannelinput"/>
        </span>&nbsp;
    </span>
                    <?php
                }
                ?>

        </fieldset>

        <?php
        /***************************************************************************
         *
         * SpimFill
         ***************************************************************************/

        /** @var SpimFill $parameterSpimFill */
        $parameterSpimFill = $_SESSION['setting']->parameter("SpimFill");
        ?>

        <fieldset class="setting <?php
        echo $parameterSpimFill->confidenceLevel(); ?>"
                  onmouseover="changeQuickHelp( 'fillFactor' );">

            <legend>
                <a href="javascript:openWindow(
                       'http://www.svi.nl/HuygensRemoteManagerHelpSPIM')">
                    <img src="images/help.png" alt="?"/>
                </a>
                SPIM Fill Factor
            </legend>

            <p class="message_confidence_<?php echo $parameterSpimFill->confidenceLevel(); ?>"></p>

            <div class="multichannel">
                <?php

                for ($i = 0; $i < $chanCnt; $i++) {

// Add a line break after 3 entries
                    if ($i == 3) {
                        echo "<br />";
                    }
                    ?>
                    <span class="nowrap">
        Ch<?php echo $i ?>:&nbsp;&nbsp;&nbsp;
        <span class="multichannel">
            <input name="SpimFill<?php echo $i ?>"
                   id="SpimFill<?php echo $i ?>"
                   title="SPIM fill factor for channel <?php echo $chan; ?>"
                   type="text"
                   size="6"
                   value="<?php
                   if ($i <= sizeof($spimFill)) {
                       echo $spimFill[$i];
                   } ?>"
                   class="multichannelinput"/>
        </span>&nbsp;
    </span>
                    <?php
                }
                ?>

        </fieldset>

        <?php
        /***************************************************************************
         *
         * SpimDir
         ***************************************************************************/

        /** @var SpimDir $parameterSpimDir */
        $parameterSpimDir = $_SESSION['setting']->parameter("SpimDir");
        ?>

        <fieldset class="setting <?php
        echo $parameterSpimDir->confidenceLevel(); ?>"
                  onmouseover="changeQuickHelp( 'direction' );">

            <legend>
                <a href="javascript:openWindow(
                       'http://www.svi.nl/HuygensRemoteManagerHelpSPIM')">
                    <img src="images/help.png" alt="?"/>
                </a>
                SPIM Illumination Direction
            </legend>

            <p class="message_confidence_<?php echo $parameterSpimDir->confidenceLevel(); ?>"></p>

            <div class="SpimDirValues">
                <table class="SpimDirValues">

                    <?php
                    $possibleValues = $parameterSpimDir->possibleValues();                

                    /* Loop on rows. */
                    for ($chan = 0; $chan < $chanCnt; $chan++) {
                        ?>
                        <tr>
                            <td>Ch<?php echo $chan; ?>:</td>

                            <td>
                                <select name="SpimDir<?php echo $chan; ?>"
                                        title="SPIM illumination direction for channel <?php echo $chan; ?>"
                                        class="selection"
                                        id="SpimDir<?php echo $chan; ?>">

                                    <?php
                                    /* Loop for select options. */
                                    foreach ($possibleValues as $possibleValue) {
                                        $translatedValue =
                                            $parameterSpimDir->translatedValueFor($possibleValue);
                                    
                                        /* Some directions have the same 'translation' for MuVi and for regular systems,
                                        but different 'value' (see DB, table 'possible_values'). Make sure that the correct
                                        'value' gets displayed for the correct systems. */
                                        if ($spimExcMode[$chan] == "gaussMuVi" && strpos($possibleValue, "+") !== FALSE) {
                                            if (!strcmp($possibleValue, $spimDir[$chan])) {
                                                $selected = " selected=\"selected\"";
                                            } else {
                                                $selected = "";
                                            }   
                                        }
                                        if ($spimExcMode[$chan] != "gaussMuVi" && strpos($possibleValue, "+") === FALSE) {
                                            if (!strcmp($possibleValue, $spimDir[$chan])) {
                                                $selected = " selected=\"selected\"";
                                            } else {
                                                $selected = "";
                                            }   
                                        }
                                        ?>
                                        <option
                                                value=<?php echo("\"$possibleValue\"");
                                        echo($selected); ?>>
                                            <?php echo $possibleValue; ?>
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

                </table> <!-- SpimDirValues -->
            </div> <!-- SpimDirValues -->

        </fieldset>


        <?php
        /****************************************************************************
         *
         * End of Parameters
         ****************************************************************************/
        ?>

        <div><input name="OK" type="hidden"/></div>

        <div id="controls"
             onmouseover="changeQuickHelp( 'default' )">
            <input type="button" value="" class="icon previous"
                   onmouseover="TagToTip('ttSpanBack' )"
                   onmouseout="UnTip()"
                   onclick="document.location.href='capturing_parameter.php'"/>
            <input type="button" value="" class="icon up"
                   onmouseover="TagToTip('ttSpanCancel' )"
                   onmouseout="UnTip()"
                   onclick="document.location.href='select_parameter_settings.php'"/>
            <?php
            if ($pageToGo != "select_parameter_settings.php") {
                ?>
                <input type="submit" value="" class="icon next"
                       onmouseover="TagToTip('ttSpanForward' )"
                       onmouseout="UnTip()"
                       onclick="process()"/>
                <?php
            } else {
                ?>
                <input type="submit" value="" class="icon save"
                       onmouseover="TagToTip('ttSpanSave' )"
                       onmouseout="UnTip()"
                       onclick="process()"/>
                <?php
            }
            ?>
        </div>
    </form>

</div> <!-- content -->


<div id="rightpanel"
     onmouseover="changeQuickHelp( 'default' );">

    <div id="info">

        <h3>Quick help</h3>

        <div id="contextHelp"
             onmouseover="changeQuickHelp( 'default' )">
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

<script type="text/javascript">
    $(document).ready(function () {
        setSpimEntryProperties();
    });
</script>

<?php
include("footer.inc.php");
?>

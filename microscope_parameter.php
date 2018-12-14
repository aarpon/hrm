<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\DatabaseConnection;
use hrm\Nav;
use hrm\param\base\Parameter;
use hrm\param\EmissionWavelength;
use hrm\param\ExcitationWavelength;
use hrm\param\ImageFileFormat;
use hrm\param\MicroscopeType;
use hrm\param\NumericalAperture;
use hrm\param\ObjectiveType;
use hrm\param\SampleMedium;
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

if (isset($_GET['home'])) {
    header("Location: " . "home.php");
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
$parameterNames = $_SESSION['setting']->microscopeParameterNames();
$db = new DatabaseConnection();
foreach ($parameterNames as $name) {
    $parameter = $_SESSION['setting']->parameter($name);
    $confidenceLevel = $db->getParameterConfidenceLevel(
        $fileFormat->value(), $name);
    /** @var Parameter $parameter */
    $parameter->setConfidenceLevel($confidenceLevel);
    $_SESSION['setting']->set($parameter);
}

/* *****************************************************************************
 *
 * MANAGE THE MULTI-CHANNEL WAVELENGTHS
 *
 **************************************************************************** */

/** @var ExcitationWavelength $excitationParam */
$excitationParam = $_SESSION['setting']->parameter("ExcitationWavelength");
$excitationParam->setNumberOfChannels($_SESSION['setting']->numberOfChannels());

/** @var EmissionWavelength $emissionParam */
$emissionParam = $_SESSION['setting']->parameter("EmissionWavelength");
$emissionParam->setNumberOfChannels($_SESSION['setting']->numberOfChannels());

$excitation = $excitationParam->value();
$emission = $emissionParam->value();
for ($i = 0; $i < $_SESSION['setting']->numberOfChannels(); $i++) {
    $excitationKey = "ExcitationWavelength{$i}";
    $emissionKey = "EmissionWavelength{$i}";
    if (isset($_POST[$excitationKey])) {
        $excitation[$i] = $_POST[$excitationKey];
    }
    if (isset($_POST[$emissionKey])) {
        $emission[$i] = $_POST[$emissionKey];
    }
}
$excitationParam->setValue($excitation);
$excitationParam->setNumberOfChannels(
    $_SESSION['setting']->numberOfChannels());
$emissionParam->setValue($emission);
$emissionParam->setNumberOfChannels(
    $_SESSION['setting']->numberOfChannels());
$_SESSION['setting']->set($excitationParam);
$_SESSION['setting']->set($emissionParam);

/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */
if ($_SESSION['setting']->checkPostedMicroscopyParameters($_POST)) {
    header("Location: " . "capturing_parameter.php");
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
    "quickhelp/microscopeParameterHelp.js");

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
<?php
// Another tooltip is used only if there are parameters that
// might require resetting (and is therefore defined later). Here
// we initialize a counter.
$nParamRequiringReset = 0;
?>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
            echo(Nav::linkWikiPage('HuygensRemoteManagerHelpOptics'));
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

    <h3>Optical Parameters</h3>

    <form method="post" action="" id="select">

        <?php

        /***************************************************************************
         *
         * MicroscopeType
         ***************************************************************************/

        /** @var MicroscopeType $parameterMicroscopeType */
        $parameterMicroscopeType = $_SESSION['setting']->parameter("MicroscopeType");

        ?>
        <fieldset class="setting <?php
        echo $parameterMicroscopeType->confidenceLevel(); ?>"
                  onmouseover="changeQuickHelp( 'type' );">

            <legend>
                <a href="javascript:openWindow(
                       'http://www.svi.nl/MicroscopeType')">
                    <img src="images/help.png" alt="?"/>
                </a>
                Microscope Type
            </legend>
            <h4>How did you set up your microscope?</h4>

            <p class="message_confidence_<?php echo $parameterMicroscopeType->confidenceLevel(); ?>"></p>

            <div class="values">
                <?php
                if (!$parameterMicroscopeType->mustProvide()) {
                    $nParamRequiringReset++;
                    ?>
                    <div class="reset"
                         onmouseover="TagToTip('ttSpanReset' )"
                         onmouseout="UnTip()"
                         onclick="document.forms[0].MicroscopeType[0].checked = true;">
                    </div>
                    <?php
                }
                ?>

                <input name="MicroscopeType"
                       title="Microscope type"
                       type="radio"
                       value=""
                       style="display:none;"/>
                <?php

                $possibleValues = $parameterMicroscopeType->possibleValues();
                foreach ($possibleValues as $possibleValue) {
                    $flag = "";
                    if ($possibleValue == $parameterMicroscopeType->value()) {
                        $flag = "checked=\"checked\" ";
                    }
                    if ($parameterMicroscopeType->hasLicense($possibleValue)) {
                        ?>
                        <input type="radio"
                               name="MicroscopeType"
                               title="<?php echo $possibleValue ?>"
                               value="<?php echo $possibleValue ?>"
                            <?php echo $flag ?>/>
                        <?php echo $possibleValue ?>

                        <br/>
                        <?php
                    }
                }

                ?>
            </div> <!-- values -->
        </fieldset>

        <?php

        /***************************************************************************
         *
         * NumericalAperture
         ***************************************************************************/

        /** @var NumericalAperture $parameterNumericalAperture */
        $parameterNumericalAperture =
            $_SESSION['setting']->parameter("NumericalAperture");

        ?>
        <fieldset class="setting <?php
        echo $parameterNumericalAperture->confidenceLevel(); ?>"
                  onmouseover="changeQuickHelp( 'NA' );">

            <legend>
                <a href="javascript:openWindow(
                    'http://www.svi.nl/NumericalAperture')">
                    <img src="images/help.png" alt="?"/>
                </a>
                Numerical Aperture
            </legend>
            <p class="message_confidence_<?php echo $parameterNumericalAperture->confidenceLevel(); ?>"></p>
            <ul>
                <li>NA:
                    <input name="NumericalAperture"
                           title="Numerical aperture"
                           type="text"
                           size="5"
                           value="<?php
                           echo $parameterNumericalAperture->value() ?>"/>

                </li>
            </ul>

        </fieldset>

        <?php

        /***************************************************************************
         *
         * (Emission|Excitation)Wavelength
         ***************************************************************************/

        /** @var EmissionWavelength $parameterEmissionWavelength */
        $parameterEmissionWavelength =
            $_SESSION['setting']->parameter("EmissionWavelength");

        ?>

        <fieldset class="setting <?php
        echo $parameterEmissionWavelength->confidenceLevel(); ?>"
                  onmouseover="changeQuickHelp( 'wavelengths' );">

            <legend>
                <a href="javascript:openWindow(
                       'http://www.svi.nl/WaveLength')">
                    <img src="images/help.png" alt="?"/>
                </a>
                Wavelengths
            </legend>

            <p class="message_confidence_<?php echo $parameterEmissionWavelength->confidenceLevel(); ?>"></p>

            <ul>
                <li>excitation (nm):

                    <div class="multichannel">
                        <?php

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
            <input name="ExcitationWavelength<?php echo $i ?>"
                   title="Excitation wavelength channel <?php echo $i ?>"
                   type="text"
                   size="8"
                   value="<?php
                   if ($i <= sizeof($excitation)) {
                       echo $excitation[$i];
                   } ?>"
                   class="multichannelinput"/>
        </span>&nbsp;
    </span>

                            <?php

                        }

                        ?>
                    </div>
                </li>
                <li>emission (nm):

                    <div class="multichannel">
                        <?php

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
            <input name="EmissionWavelength<?php echo $i ?>"
                   title="Emission wavelength channel <?php echo $i ?>"
                   type="text"
                   size="8"
                   value="<?php
                   if ($i <= sizeof($emission)) {
                       echo $emission[$i];
                   } ?>"
                   class="multichannelinput"/>
        </span>&nbsp;
    </span>

                            <?php

                        }

                        ?>
                    </div>
                </li>
            </ul>

        </fieldset>

        <?php

        /***************************************************************************
         *
         * ObjectiveType
         ***************************************************************************/

        /** @var ObjectiveType $parameterObjectiveType */
        $parameterObjectiveType = $_SESSION['setting']->parameter("ObjectiveType");

        ?>

        <fieldset class="setting <?php
        echo $parameterObjectiveType->confidenceLevel(); ?>"
                  onmouseover="changeQuickHelp( 'objective' );">

            <legend>
                <a href="javascript:openWindow(
                       'http://www.svi.nl/LensImmersionMedium')">
                    <img src="images/help.png" alt="?"/>
                </a>
                Objective Type
            </legend>

            <p class="message_confidence_<?php echo $parameterObjectiveType->confidenceLevel(); ?>"></p>

            <div class="values">
                <?php
                if (!$parameterObjectiveType->mustProvide()) {
                    $nParamRequiringReset++;
                    ?>
                    <div class="reset"
                         onmouseover="TagToTip('ttSpanReset' )"
                         onmouseout="UnTip()"
                         onclick="document.forms[0].ObjectiveType[0].checked = true;">
                    </div>
                    <?php
                }
                ?>

                <input name="ObjectiveType"
                       title="Objective type"
                       type="radio"
                       value=""
                       style="display:none;"/>

                <?php

                $default = False;
                foreach ($parameterObjectiveType->possibleValues() as $possibleValue) {
                    $flag = "";
                    if ($possibleValue == $parameterObjectiveType->value()) {
                        $flag = " checked=\"checked\"";
                        $default = True;
                    }
                    $translation = $parameterObjectiveType->translatedValueFor($possibleValue);

                    ?>
                    <input name="ObjectiveType"
                           title="<?php echo $possibleValue ?>"
                           type="radio"
                           value="<?php echo $possibleValue ?>"
                        <?php echo $flag ?> />
                    <?php echo $possibleValue ?>
                    <span class="title">[<?php echo $translation ?>]</span>

                    <br/>
                    <?php

                }

                $value = "";
                $flag = "";
                if (!$default) {
                    $value = $parameterObjectiveType->value();
                    if ($value != "") {
                        $flag = " checked=\"checked\"";
                    }
                }

                ?>
                <input name="ObjectiveType"
                       type="radio"
                       title="Custom"
                       value="custom"<?php echo $flag ?> />

                <input name="ObjectiveTypeCustomValue"
                       title="Custom"
                       type="text"
                       size="5"
                       value="<?php echo $value ?>"
                       onclick="this.form.ObjectiveType[5].checked=true"/>

            </div> <!-- values -->

        </fieldset>

        <?php

        /***************************************************************************
         *
         * SampleMedium
         ***************************************************************************/

        /** @var SampleMedium $parameterSampleMedium */
        $parameterSampleMedium = $_SESSION['setting']->parameter("SampleMedium");

        ?>

        <fieldset class="setting <?php
        echo $parameterSampleMedium->confidenceLevel(); ?>"
                  onmouseover="changeQuickHelp( 'sample' );">

            <legend>
                <a href="javascript:openWindow(
                       'http://www.svi.nl/SpecimenEmbeddingMedium')">
                    <img src="images/help.png" alt="?"/>
                </a>
                Sample Medium
            </legend>

            <p class="message_confidence_<?php echo $parameterSampleMedium->confidenceLevel(); ?>"></p>

            <div class="values">
                <?php
                if (!$parameterSampleMedium->mustProvide()) {
                    $nParamRequiringReset++;
                    ?>
                    <div class="reset"
                         onmouseover="TagToTip('ttSpanReset' )"
                         onmouseout="UnTip()"
                         onclick="document.forms[0].SampleMedium[0].checked = true;">
                    </div>
                    <?php
                }
                ?>

                <input name="SampleMedium"
                       title="Sample medium"
                       type="radio"
                       value=""
                       style="display:none;"/>

                <?php

                $default = False;
                foreach ($parameterSampleMedium->possibleValues() as $possibleValue) {
                    $flag = "";
                    if ($possibleValue == $parameterSampleMedium->value()) {
                        $flag = " checked=\"checked\"";
                        $default = True;
                    }
                    $translation = $parameterSampleMedium->translatedValueFor($possibleValue);

                    ?>
                    <input name="SampleMedium"
                           title="<?php echo $possibleValue ?>"
                           type="radio"
                           value="<?php echo $possibleValue ?>"
                        <?php echo $flag ?> />
                    <?php echo $possibleValue ?>
                    <span class="title">[<?php echo $translation ?>]</span>

                    <br/>
                    <?php

                }

                $value = "";
                $flag = "";
                if (!$default) {
                    $value = $parameterSampleMedium->value();
                    if ($value != "") {
                        $flag = " checked=\"checked\"";
                    }
                }

                ?>
                <input name="SampleMedium"
                       title="Custom"
                       type="radio"
                       value="custom"<?php echo $flag ?> />

                <input name="SampleMediumCustomValue"
                       title="Custom"
                       type="text"
                       size="5"
                       value="<?php echo $value ?>"
                       onclick="this.form.SampleMedium[3].checked=true"/>

            </div> <!-- values -->

        </fieldset>

        <div><input name="OK" type="hidden"/></div>

        <div id="controls"
             onmouseover="changeQuickHelp( 'default' )">
            <input type="button" value="" class="icon previous"
                   onmouseover="TagToTip('ttSpanBack' )"
                   onmouseout="UnTip()"
                   onclick="document.location.href='image_format.php'"/>
            <input type="button" value="" class="icon up"
                   onmouseover="TagToTip('ttSpanCancel' )"
                   onmouseout="UnTip()"
                   onclick="document.location.href='select_parameter_settings.php'"/>
            <input type="submit" value="" class="icon next"
                   onmouseover="TagToTip('ttSpanForward' )"
                   onmouseout="UnTip()"
                   onclick="process()"/>
        </div>

    </form>

    <?php
    if ($nParamRequiringReset > 0) {
        ?>
        <span class="toolTip" id="ttSpanReset">
                Click to unselect all options.
            </span>
        <?php
    }
    ?>

</div> <!-- content -->

<div id="rightpanel"
     onmouseover="changeQuickHelp( 'default' );">

    <div id="info">

        <h3>Quick help</h3>

        <div id="contextHelp">
            <p>On this page you specify the parameters for the optical setup
                of your experiment.</p>

            <p>These parameters comprise the microscope type, the numerical
                aperture of the objective, the wavelenght of the used
                fluorophores, and the refractive indices of the sample medium
                and of the objective-embedding medium.</p>
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

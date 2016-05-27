<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\DatabaseConnection;
use hrm\Nav;

require_once dirname(__FILE__) . '/inc/bootstrap.inc.php';

require_once("./inc/Util.inc.php");

/* *****************************************************************************
 *
 * START SESSION, CHECK LOGIN STATE, INITIALIZE WHAT NEEDED
 *
 **************************************************************************** */

session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}
$message = "";

/* *****************************************************************************
 *
 * SET THE CONFIDENCES FOR THE RELEVANT PARAMETERS
 *
 **************************************************************************** */

/* In this page, all parameters are required! */
$parameterNames = $_SESSION['setting']->correctionParameterNames();
$db = new DatabaseConnection();
foreach ( $parameterNames as $name ) {
    /** @var Parameter $parameter */
    $parameter = $_SESSION['setting']->parameter( $name );
  $confidenceLevel = $db->getParameterConfidenceLevel( '', $name );
  $parameter->setConfidenceLevel( $confidenceLevel );
  $_SESSION['setting']->set( $parameter );
}

/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */

if ( $_SESSION['setting']->checkPostedAberrationCorrectionParameters( $_POST ) ) {
  $saved = $_SESSION['setting']->save();
  $message = $_SESSION['setting']->message();
  if ($saved) {
    header("Location: select_parameter_settings.php" ); exit();
  }
} else {
  $message = $_SESSION['setting']->message();
}


/* *****************************************************************************
 *
 * PREVIOUS PAGE
 *
 **************************************************************************** */

if ( $_SESSION['setting']->isSted() || $_SESSION['setting']->isSted3D()) {
    $back = "sted_parameters.php";
} else if ( $_SESSION['setting']->isSpim()) {
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
$script = array( "settings.js", "quickhelp/help.js",
                "quickhelp/aberrationCorrectionHelp.js" );

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

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
                echo(Nav::linkWikiPage('HuygensRemoteManagerHelpEnableSACorrection'));
            ?>
            <li> [ <?php  echo $_SESSION['setting']->name(); ?> ] </li>
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

        <h2>Spherical aberration correction</h2>

        <form method="post" action="" id="select">

            <!-- (1) PERFORM SPHERICAL ABERRATION CORRECTION? -->

        <h4>Do you want to enable depth-specific PSF correction?
            This will try to compensate for spherical aberrations introduced
            by refractive index mismatches.
        </h4>


    <?php

    /***************************************************************************

      PerformAberrationCorrection

    ***************************************************************************/

    /** @var PerformAberrationCorrection $parameterPerformAberrationCorrection */
    $parameterPerformAberrationCorrection =
        $_SESSION['setting']->parameter("PerformAberrationCorrection");

    ?>

        <fieldset class="setting <?php
            echo $parameterPerformAberrationCorrection->confidenceLevel();
            ?>"
            onmouseover="changeQuickHelp( 'enable' );" >

            <legend>
                <a href="openWindow(
                   'http://www.svi.nl/HuygensRemoteManagerHelpDepthDependentPsf')">
                    <img src="images/help.png" alt="?" />
                </a>
                enable depth-dependent PSF correction?
            </legend>

            <select id="PerformAberrationCorrection"
                    name="PerformAberrationCorrection"
                    onchange="switchCorrection();" >

            <?php

                $possibleValues =
                    $parameterPerformAberrationCorrection->possibleValues();
                $selectedValue  =
                    $parameterPerformAberrationCorrection->value();
                // The javascript expects option values to match their indexes:
                sort($possibleValues);

                foreach($possibleValues as $possibleValue) {
                    $translation =
                        $parameterPerformAberrationCorrection->
                            translatedValueFor( $possibleValue );
                    if ($possibleValue == "0" && $selectedValue == "") {
                        $option = "selected=\"selected\"";
                    }
                    else if ( $possibleValue == $selectedValue ) {
                        $option = "selected=\"selected\"";
                    }
                    else {
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

        <p class="message_confidence_<?php
            echo $parameterPerformAberrationCorrection->confidenceLevel(); ?>">
            &nbsp;
        </p>

        </fieldset>

    <!-- (2) SPECIFY SAMPLE ORIENTATION -->

<?php

$visibility = " style=\"display: none\"";
if ($parameterPerformAberrationCorrection->value( ) == 1)
  $visibility = " style=\"display: block\"";

?>

    <div id="CoverslipRelativePositionDiv"<?php echo $visibility?>>

    <h4>For depth-dependent correction to work properly, you have to specify
        the relative position of the coverslip with respect to the first
        acquired plane of the dataset.
    </h4>

    <?php

    /***************************************************************************

      CoverslipRelativePosition

    ***************************************************************************/

    /** @var CoverslipRelativePosition $parameterCoverslipRelativePosition */
    $parameterCoverslipRelativePosition =
        $_SESSION['setting']->parameter("CoverslipRelativePosition");

    ?>

        <fieldset class="setting <?php
            echo $parameterCoverslipRelativePosition->confidenceLevel(); ?>"
            onmouseover="changeQuickHelp( 'orientation' );" >

            <legend>
                <a href="openWindow(
                   'http://www.svi.nl/HuygensRemoteManagerHelpSpecifySampleOrientation')">
                    <img src="images/help.png" alt="?" />
                </a>
                specify sample orientation
            </legend>

            <select name="CoverslipRelativePosition" >

            <?php

                $possibleValues =
                    $parameterCoverslipRelativePosition->possibleValues();
                $selectedValue  =
                    $parameterCoverslipRelativePosition->value();

                foreach($possibleValues as $possibleValue) {
                    $translation = $parameterCoverslipRelativePosition->
                        translatedValueFor( $possibleValue );
                  if ( $possibleValue == $selectedValue ) {
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

        <p class="message_confidence_<?php
            echo $parameterCoverslipRelativePosition->confidenceLevel(); ?>">
            &nbsp;
        </p>

        </fieldset>

    </div> <!-- CoverslipRelativePositionDiv -->

    <!-- (3) CHOOSE ADVANCED CORRECTION MODE -->

<?php

$visibility = " style=\"display: none\"";
if ($parameterPerformAberrationCorrection->value( ) == 1)
  $visibility = " style=\"display: block\"";

?>

    <div id="AberrationCorrectionModeDiv"<?php echo $visibility?>>

    <?php

    /***************************************************************************

      AberrationCorrectionMode

    ***************************************************************************/

    /** @var AberrationCorrectionMode $parameterAberrationCorrectionMode */
    $parameterAberrationCorrectionMode =
        $_SESSION['setting']->parameter("AberrationCorrectionMode");

    ?>

    <h4>At this point the HRM has enough information to perform depth-dependent
        aberration correction. Please notice that in certain circumstances,
        the automatic correction scheme might generate artifacts in the result.
        If this is the case, please choose the advanced correction mode.
    </h4>

    <fieldset class="setting <?php
        echo $parameterAberrationCorrectionMode->confidenceLevel(); ?>"
        onmouseover="changeQuickHelp( 'mode' );" >

        <legend>
            <a href="openWindow(
               'http://www.svi.nl/HuygensRemoteManagerHelpSaCorrectionMode')">
                <img src="images/help.png" alt="?" />
            </a>
            correction mode
        </legend>

        <select id="AberrationCorrectionMode"
            name="AberrationCorrectionMode"
            onchange="switchAdvancedCorrection();" >

            <?php

            $possibleValues =
                $parameterAberrationCorrectionMode->possibleValues();
            $selectedValue  =
                $parameterAberrationCorrectionMode->value();

            foreach($possibleValues as $possibleValue) {
                $translation = $parameterAberrationCorrectionMode->
                        translatedValueFor( $possibleValue );
                if ( $possibleValue == $selectedValue ) {
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

        <p class="message_confidence_<?php
            echo $parameterAberrationCorrectionMode->confidenceLevel(); ?>">
            &nbsp;
        </p>

        </fieldset>

    </div> <!-- AberrationCorrectionModeDiv -->

    <!-- (4) ADVANCED CORRECTION MODE -->

<?php

$visibility = " style=\"display: none\"";
if ( ($parameterPerformAberrationCorrection->value( ) == 1) &&
     ($parameterAberrationCorrectionMode->value( ) == "advanced") )
  $visibility = " style=\"display: block\"";

?>

    <div id="AdvancedCorrectionOptionsDiv"<?php echo $visibility?>>

    <?php

    /***************************************************************************

      AdvancedCorrectionOptions

    ***************************************************************************/

    /** @var AdvancedCorrectionOptions $parameterAdvancedCorrectionOptions */
    $parameterAdvancedCorrectionOptions =
        $_SESSION['setting']->parameter("AdvancedCorrectionOptions");

    ?>

    <h4>Here you can choose an advanced correction scheme.</h4>

    <fieldset class="setting <?php echo
        $parameterAdvancedCorrectionOptions->confidenceLevel(); ?>"
        onmouseover="changeQuickHelp( 'advanced' );" >

    <legend>
        <a href="openWindow(
           'http://www.svi.nl/HuygensRemoteManagerHelpAdvancedSaCorrection')">
            <img src="images/help.png" alt="?" />
        </a>
        advanced correction scheme
    </legend>

        <select id="AdvancedCorrectionOptions"
            name="AdvancedCorrectionOptions"
            onchange="switchAdvancedCorrectionScheme();" >

            <?php

            $possibleValues = $parameterAdvancedCorrectionOptions->possibleValues();
            $selectedValue  = $parameterAdvancedCorrectionOptions->value();

            foreach($possibleValues as $possibleValue) {
                $translation = $parameterAdvancedCorrectionOptions->
                    translatedValueFor( $possibleValue );
                if ( $possibleValue == $selectedValue ) {
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

$visibility = " style=\"display: none\"";
if ( ($parameterPerformAberrationCorrection->value( ) == 1) &&
     ($parameterAberrationCorrectionMode->value( ) == "advanced") &&
     ($parameterAdvancedCorrectionOptions->value( ) == "user") )
  $visibility = " style=\"display: block\"";

    /***************************************************************************

      PSFGenerationDepth

    ***************************************************************************/

    /** @var PSFGenerationDepth $parameterPSFGenerationDepth */
    $parameterPSFGenerationDepth =
        $_SESSION['setting']->parameter("PSFGenerationDepth");
    $selectedValue = $parameterPSFGenerationDepth->value();

?>

    <div id="PSFGenerationDepthDiv"<?php echo $visibility?> >
        <p>Depth for PSF generation (&micro;m):
            <input name="PSFGenerationDepth"
               type="text"
               style="width:100px;"
               value="<?php echo $selectedValue; ?>" />
        </p>
    </div>

    <p class="message_confidence_<?php
        echo $parameterAdvancedCorrectionOptions->confidenceLevel(); ?>">
        &nbsp;
    </p>

    </fieldset>

    </div> <!-- AdvancedCorrectionOptionsDiv -->

    <div><input name="OK" type="hidden" /></div>

    <div id="controls" onmouseover="changeQuickHelp( 'default' )">
        <input type="button" value="" class="icon previous"
            onmouseover="TagToTip('ttSpanBack' )"
            onmouseout="UnTip()"
            onclick="document.location.href='<?php echo $back; ?>'" />
        <input type="button" value="" class="icon up"
            onmouseover="TagToTip('ttSpanCancel' )"
            onmouseout="UnTip()"
            onclick="document.location.href='select_parameter_settings.php'" />
        <input type="submit" value="" class="icon save"
            onmouseover="TagToTip('ttSpanSave' )"
            onmouseout="UnTip()"
            onclick="process()" />
      </div>

    </form>

    </div> <!-- content -->

    <div id="rightpanel"  onmouseover="changeQuickHelp( 'default' )">

        <div id="info">

          <h3>Quick help</h3>

          <div id="contextHelp">
            <p>The main cause of spherical aberration is a mismatch between
                the refractive index of the lens immersion medium and specimen
                embedding medium and causes the PSF to become asymmetric at
                depths of already a few &micro;m. SA is especially harmful for
                widefield microscope deconvolution. The HRM can correct for SA
                automatically, but in case of very large refractive index
                mismatches some artifacts can be  generated. Advanced parameters
                allow for fine-tuning of the correction.</p>
          </div>

      <?php
              if ( !$_SESSION["user"]->isAdmin() ) {
      ?>

            <div class="requirements">
               Parameter requirements<br />adapted for <b>
               <?php
               /** @var ImageFileFormat $fileFormat */
               $fileFormat = $_SESSION['setting']->parameter( "ImageFileFormat" );
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

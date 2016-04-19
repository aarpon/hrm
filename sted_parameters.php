<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Parameter.inc.php");
require_once("./inc/Setting.inc.php");
require_once("./inc/Util.inc.php");
require_once("./inc/Database.inc.php");
require_once("./inc/Nav.inc.php");

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

$chanCnt = $_SESSION['setting']->numberOfChannels();

/* *****************************************************************************
 *
 * SET THE CONFIDENCES FOR THE RELEVANT PARAMETERS
 *
 **************************************************************************** */

$fileFormat = $_SESSION['setting']->parameter( "ImageFileFormat" );
$parameterNames = $_SESSION['setting']->stedParameterNames();
$db = new DatabaseConnection();
foreach ( $parameterNames as $name ) {
  $parameter = $_SESSION['setting']->parameter( $name );
  $confidenceLevel =
    $db->getParameterConfidenceLevel( $fileFormat->value(), $name );
  $parameter->setConfidenceLevel( $confidenceLevel );
  $_SESSION['setting']->set( $parameter );
}


/* *****************************************************************************
 *
 * MANAGE THE STED DEPLETION MODE
 *
 **************************************************************************** */

$stedDeplParam = $_SESSION['setting']->parameter("StedDepletionMode");
$stedDepl = $stedDeplParam->value();
for ($i=0; $i < $chanCnt; $i++) {
  $stedDeplKey = "StedDepl{$i}";
  if (isset($_POST[$stedDeplKey])) {
    $stedDepl[$i] = $_POST[$stedDeplKey];
  }
}
$stedDeplParam->setValue($stedDepl);
$_SESSION['setting']->set($stedDeplParam);

/* *****************************************************************************
 *
 * MANAGE THE STED SATURATION FACTOR
 *
 **************************************************************************** */

$stedSatFactParam = $_SESSION['setting']->parameter("StedSaturationFactor");
$stedSatFactParam->setNumberOfChannels($chanCnt);
$stedSatFact = $stedSatFactParam->value();

for ($i=0; $i < $chanCnt; $i++) {
  $stedSatFactKey = "StedSaturationFactor{$i}";
  if (isset($_POST[$stedSatFactKey])) {
      $stedSatFact[$i] = $_POST[$stedSatFactKey];
  }
}

$stedSatFactParam->setValue($stedSatFact);
$stedSatFactParam->setNumberOfChannels($chanCnt);

$_SESSION['setting']->set($stedSatFactParam);

/* *****************************************************************************
 *
 * MANAGE THE STED DEPLETION WAVELENGTH
 *
 **************************************************************************** */

$stedLambdaParam = $_SESSION['setting']->parameter("StedWavelength");
$stedLambdaParam->setNumberOfChannels($chanCnt);
$stedLambda = $stedLambdaParam->value();

for ($i=0; $i < $chanCnt; $i++) {
  $stedLambdaKey = "StedWavelength{$i}";
  if (isset($_POST[$stedLambdaKey])) {
      $stedLambda[$i] = $_POST[$stedLambdaKey];
  }
}
$stedLambdaParam->setValue($stedLambda);
$stedLambdaParam->setNumberOfChannels($chanCnt);

$_SESSION['setting']->set($stedLambdaParam);

/* *****************************************************************************
 *
 * MANAGE THE STED IMMUNITY FRACTION
 *
 **************************************************************************** */

$stedImmunityParam = $_SESSION['setting']->parameter("StedImmunity");
$stedImmunityParam->setNumberOfChannels($chanCnt);
$stedImmunity = $stedImmunityParam->value();

for ($i=0; $i < $chanCnt; $i++) {
  $stedImmunityKey = "StedImmunity{$i}";
  if (isset($_POST[$stedImmunityKey])) {
      $stedImmunity[$i] = $_POST[$stedImmunityKey];
  }
}
$stedImmunityParam->setValue($stedImmunity);
$stedImmunityParam->setNumberOfChannels($chanCnt);

$_SESSION['setting']->set($stedImmunityParam);

/* *****************************************************************************
 *
 * MANAGE THE STED 3D FACTOR
 *
 **************************************************************************** */

if ($_SESSION['setting']->isSted3D()) {
    $sted3DParam = $_SESSION['setting']->parameter("Sted3D");
    $sted3DParam->setNumberOfChannels($chanCnt);
    $sted3D = $sted3DParam->value();

    for ($i=0; $i < $chanCnt; $i++) {
        $sted3DKey = "Sted3D{$i}";
        if (isset($_POST[$sted3DKey])) {
            $sted3D[$i] = $_POST[$sted3DKey];
        }
    }
    $sted3DParam->setValue($sted3D);
    $sted3DParam->setNumberOfChannels($chanCnt);

    $_SESSION['setting']->set($sted3DParam);
}

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

$PSF = $_SESSION['setting']->parameter( 'PointSpreadFunction' )->value( );

if ($PSF == 'measured' ) {
  $pageToGo = 'select_psf.php';
  // Make sure to turn off the correction
  $_SESSION['setting']->parameter(
    'AberrationCorrectionNecessary' )->setValue( '0' );
  $_SESSION['setting']->parameter(
    'PerformAberrationCorrection' )->setValue( '0' );
} else {
  // Get the refractive indices: if they are not set, the floatval conversion
  // will change them into 0s
  $sampleRI    = floatval( $_SESSION['setting']->parameter(
    'SampleMedium' )->translatedValue( ) );
  $objectiveRI = floatval( $_SESSION['setting']->parameter(
    'ObjectiveType' )->translatedValue( ) );

  // Calculate the deviation
  if ( ( $sampleRI == 0 ) ||  ( $objectiveRI == 0 ) ) {
    // If at least one of the refractive indices is not known, we cannot
    // calculate whether an aberration correction is necessary and we leave
    // the decision to the user in the aberration_correction.php page.
    $pageToGo = 'aberration_correction.php';
    $_SESSION['setting']->parameter(
      'AberrationCorrectionNecessary' )->setValue( '1' );
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
      $_SESSION['setting']->parameter(
        'AberrationCorrectionNecessary' )->setValue( '0' );
      $_SESSION['setting']->parameter(
        'PerformAberrationCorrection' )->setValue( '0' );
    } else {
      $pageToGo = 'aberration_correction.php';
      $_SESSION['setting']->parameter(
        'AberrationCorrectionNecessary' )->setValue( '1' );
    }
  }
}


/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */

if ($_SESSION[ 'setting' ]->checkPostedStedParameters( $_POST ) ) {
  if ( $saveToDB ) {
    $saved = $_SESSION['setting']->save();
    $message = $_SESSION['setting']->message();
    if ($saved) {
      header("Location: " . $pageToGo ); exit();
    }
  }
  header("Location: " . $pageToGo ); exit();
} else {
  $message = $_SESSION['setting']->message();
}


/* *****************************************************************************
 *
 * CREATE THE PAGE
 *
 **************************************************************************** */

// Javascript includes
$script = array( "settings.js", "quickhelp/help.js",
                 "quickhelp/stedParameters.js");
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
                echo(Nav::linkWikiPage('STED'));
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

        <h2>STED parameters </h2>

        <form method="post" action="" id="select">

            <h4>How did you set up the STED system?</h4>


<?php
/***************************************************************************

   StedDeplMode

***************************************************************************/

$parameterStedDeplMode = $_SESSION['setting']->parameter("StedDepletionMode");
?>

            <fieldset class="setting <?php
            echo $parameterStedDeplMode->confidenceLevel(); ?>"
            onmouseover="javascript:changeQuickHelp( 'deplMode' );" >

                <legend>
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/HuygensRemoteManagerHelpSTED')">
                        <img src="images/help.png" alt="?" />
                    </a>
                    STED Depletion Mode
                </legend>

                <div class="StedDeplModeValues">
                <table class="StedDeplModeValues">

<?php
$possibleValues = $parameterStedDeplMode->possibleValues();

/* Make sure the Confocal option is the last one. */
for ($i = 0; $i < count($possibleValues); $i++) {
    $arrValue = array_shift($possibleValues);
    array_push($possibleValues,$arrValue);
    if (strstr($arrValue,"Confocal")) {
        break;
    }
}

                        /* Loop on rows. */

for ($chan = 0; $chan < $chanCnt; $chan++) {
?>
    <tr><td>Ch<?php echo $chan; ?>:</td>

    <td>
    <select name="StedDepletionMode<?php echo $chan;?>"
    onclick="javascript:changeStedEntryProperties(this,<?php echo $chan;?>)"
    onchange="javascript:changeStedEntryProperties(this,<?php echo $chan;?>)">

<?php
                        /* Loop for select options. */

    foreach($possibleValues as $possibleValue) {
        $translatedValue =
        $parameterStedDeplMode->translatedValueFor($possibleValue);

        if ($translatedValue == $stedDepl[$chan]) {
            $selected = " selected=\"selected\"";
        } else {
            $selected = "";
        }
?>
        <option value=<?php echo $translatedValue; echo $selected;?>>
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

                </table> <!-- StedDeplModeValues -->
                </div> <!-- StedDeplModeValues -->


            <div class="bottom">
                <p class="message_confidence_<?php
                echo $parameterStedDeplMode->confidenceLevel(); ?>">&nbsp;
                </p>
            </div>
            </fieldset>


<?php
/***************************************************************************

  StedSatFact

***************************************************************************/

$parameterStedSatFact = $_SESSION['setting']->parameter("StedSaturationFactor");
?>

            <fieldset class="setting <?php
            echo $parameterStedSatFact->confidenceLevel(); ?>"
            onmouseover="javascript:changeQuickHelp( 'satFact' );" >

                <legend>
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/HuygensRemoteManagerHelpSTED')">
                        <img src="images/help.png" alt="?" />
                    </a>
                    STED Saturation Factor
                </legend>


                    <div class="multichannel">
<?php

for ($i = 0; $i < $chanCnt; $i++) {

    /* Add a line break after a number of entries. */
    if ( $_SESSION['setting']->numberOfChannels() == 4 ) {
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
            <input name="StedSaturationFactor<?php echo $i ?>"
                   id="StedSaturationFactor<?php echo $i ?>"
                   type="text"
                   size="6"
                   value="<?php
                    if ($i <= sizeof($stedSatFact)) {
                        echo $stedSatFact[$i];
                    } ?>"
                   class="multichannelinput" />
        </span>&nbsp;
    </span>
<?php
}
?>

                <div class="bottom">
                <p class="message_confidence_<?php
                echo $parameterStedSatFact->confidenceLevel(); ?>">&nbsp;
                </p>
            </div>
            </fieldset>

<?php
/***************************************************************************

  StedLambda

***************************************************************************/

$parameterStedLambda = $_SESSION['setting']->parameter("StedWavelength");
?>

            <fieldset class="setting <?php
            echo $parameterStedLambda->confidenceLevel(); ?>"
            onmouseover="javascript:changeQuickHelp( 'lambda' );" >

                <legend>
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/HuygensRemoteManagerHelpSTED')">
                        <img src="images/help.png" alt="?" />
                    </a>
    STED Wavelength (nm)
                </legend>


                    <div class="multichannel">
<?php

for ($i = 0; $i < $chanCnt; $i++) {
    
    /* Add a line break after a number of entries. */
    if ( $_SESSION['setting']->numberOfChannels() == 4 ) {
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
            <input name="StedWavelength<?php echo $i ?>"
                   id="StedWavelength<?php echo $i ?>"
                   type="text"
                   size="6"
                   value="<?php
                    if ($i <= sizeof($stedLambda)) {
                        echo $stedLambda[$i];
                    } ?>"
                   class="multichannelinput" />
        </span>&nbsp;
    </span>
<?php
}
?>

                <div class="bottom">
                <p class="message_confidence_<?php
                echo $parameterStedLambda->confidenceLevel(); ?>">&nbsp;
                </p>
            </div>
            </fieldset>


<?php
    /***************************************************************************

      StedImmunity

    ***************************************************************************/

    $parameterStedImmunity = $_SESSION['setting']->parameter("StedImmunity");
?>

            <fieldset class="setting <?php
            echo $parameterStedImmunity->confidenceLevel(); ?>"
            onmouseover="javascript:changeQuickHelp( 'immunity' );" >

                <legend>
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/HuygensRemoteManagerHelpSTED')">
                        <img src="images/help.png" alt="?" />
                    </a>
    STED Immunity Fraction (%)
                </legend>


                    <div class="multichannel">
<?php

for ($i = 0; $i < $chanCnt; $i++) {
    
    /* Add a line break after a number of entries. */
    if ( $_SESSION['setting']->numberOfChannels() == 4 ) {
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
            <input name="StedImmunity<?php echo $i ?>"
                   id="StedImmunity<?php echo $i ?>"
                   type="text"
                   size="6"
                   value="<?php
                    if ($i <= sizeof($stedImmunity)) {
                        echo $stedImmunity[$i];
                    } ?>"
                   class="multichannelinput" />
        </span>&nbsp;
    </span>
<?php
}
?>

                <div class="bottom">
                <p class="message_confidence_<?php
                echo $parameterStedImmunity->confidenceLevel(); ?>">&nbsp;
                </p>
            </div>
            </fieldset>


<?php
    /***************************************************************************

      Sted3D

    ***************************************************************************/

if ($_SESSION['setting']->isSted3D()) {
    $parameterSted3D = $_SESSION['setting']->parameter("Sted3D");
?>

            <fieldset class="setting <?php
            echo $parameterSted3D->confidenceLevel(); ?>"
            onmouseover="javascript:changeQuickHelp( '3d' );" >

                <legend>
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/HuygensRemoteManagerHelpSTED')">
                        <img src="images/help.png" alt="?" />
                    </a>
    STED 3D (%)
                </legend>


                    <div class="multichannel">
<?php

    for ($i = 0; $i < $chanCnt; $i++) {

        /* Add a line break after a number of entries. */
        if ( $_SESSION['setting']->numberOfChannels() == 4 ) {
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
            <input name="Sted3D<?php echo $i ?>"
                   id="Sted3D<?php echo $i ?>"
                   type="text"
                   size="6"
                   value="<?php
                    if ($i <= sizeof($sted3D)) {
                        echo $sted3D[$i];
                    } ?>"
                   class="multichannelinput" />
        </span>&nbsp;
    </span>
<?php
    }
?>

                <div class="bottom">
                <p class="message_confidence_<?php
                echo $parameterSted3D->confidenceLevel(); ?>">&nbsp;
                </p>
            </div>
            </fieldset>
<?php
}
?>

<?php
/****************************************************************************

                       End of Parameters

****************************************************************************/
?>

           <div><input name="OK" type="hidden" /></div>

            <div id="controls"
                 onmouseover="javascript:changeQuickHelp( 'default' )">
              <input type="button" value="" class="icon previous"
                  onmouseover="TagToTip('ttSpanBack' )"
                  onmouseout="UnTip()"
                  onclick="document.location.href='capturing_parameter.php'" />
              <input type="button" value="" class="icon up"
                  onmouseover="TagToTip('ttSpanCancel' )"
                  onmouseout="UnTip()"
                  onclick="document.location.href='select_parameter_settings.php'" />
    <?php
    if ($pageToGo != "select_parameter_settings.php") {
    ?>
              <input type="submit" value="" class="icon next"
                  onmouseover="TagToTip('ttSpanForward' )"
                  onmouseout="UnTip()"
                  onclick="process()" />
    <?php
    } else {
    ?>
              <input type="submit" value="" class="icon save"
                  onmouseover="TagToTip('ttSpanSave' )"
                  onmouseout="UnTip()"
                  onclick="process()" />
    <?php
    }
    ?>
            </div>
        </form>

   </div> <!-- content -->


   <div id="rightpanel"
         onmouseover="javascript:changeQuickHelp( 'default' );" >

        <div id="info">

          <h3>Quick help</h3>

            <div id="contextHelp"
             onmouseover="javascript:changeQuickHelp( 'default' )">
            </div>

      <?php
              if ( !$_SESSION["user"]->isAdmin() ) {
      ?>

            <div class="requirements">
               Parameter requirements<br />adapted for <b>
               <?php
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

    <script type="text/javascript">
    setStedEntryProperties();
    </script>

<?php
include("footer.inc.php");
?>

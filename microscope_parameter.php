<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Parameter.inc.php");
require_once("./inc/Setting.inc.php");
require_once("./inc/Database.inc.php");

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

$fileFormat = $_SESSION['setting']->parameter( "ImageFileFormat" );
$parameterNames = $_SESSION['setting']->microscopeParameterNames();
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
 * MANAGE THE MULTI-CHANNEL WAVELENGTHS
 *
 **************************************************************************** */

$excitationParam = $_SESSION['setting']->parameter("ExcitationWavelength");
$excitationParam->setNumberOfChannels(
    $_SESSION['setting']->numberOfChannels() );
$emissionParam =  $_SESSION['setting']->parameter("EmissionWavelength");
$emissionParam->setNumberOfChannels(
    $_SESSION['setting']->numberOfChannels( ) );
$excitation = $excitationParam->value();
$emission = $emissionParam->value();
for ($i=0; $i < $_SESSION['setting']->numberOfChannels(); $i++) {
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
    $_SESSION['setting']->numberOfChannels( ) );
$emissionParam->setValue($emission);
$emissionParam->setNumberOfChannels(
    $_SESSION['setting']->numberOfChannels( ) );
$_SESSION['setting']->set($excitationParam);
$_SESSION['setting']->set($emissionParam);

/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */
if ( $_SESSION[ 'setting' ]->checkPostedMicroscopyParameters(  $_POST ) ) {
  header("Location: " . "capturing_parameter.php"); exit();
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
                "quickhelp/microscopeParameterHelp.js" );

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
        <ul>
            <li>
                <img src="images/user.png" alt="user" />
                &nbsp;<?php echo $_SESSION['user']->name(); ?>
            </li>
            <li>
                <a href="javascript:openWindow(
                   'http://www.svi.nl/HuygensRemoteManagerHelpOptics')">
                    <img src="images/help.png" alt="help" />
                    &nbsp;Help
                </a>
            </li>
        </ul>
    </div>

    <div id="content">

        <h2>Optical parameters / 1</h2>

        <form method="post" action="" id="select">

            <h4>How did you set up your microscope?</h4>

    <?php

    /***************************************************************************

      MicroscopeType

    ***************************************************************************/

    $parameterMicroscopeType =
        $_SESSION['setting']->parameter("MicroscopeType");

    ?>
            <fieldset class="setting <?php
            echo $parameterMicroscopeType->confidenceLevel(); ?>"
            onmouseover="javascript:changeQuickHelp( 'type' );" >

                <legend>
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/MicroscopeType')">
                        <img src="images/help.png" alt="?" />
                    </a>
                    microscope type
                </legend>

                <div class="values">
                    <?php
                    if ( ! $parameterMicroscopeType->mustProvide() ) {
                        $nParamRequiringReset++;
                    ?>
                    <div class="reset"
                        onmouseover="TagToTip('ttSpanReset' )"
                        onmouseout="UnTip()"
                        onclick="document.forms[0].MicroscopeType[0].checked = true;" >
                    </div>
                    <?php
                    }
                    ?>

                    <input name="MicroscopeType"
                           type="radio"
                           value=""
                           style="display:none;" />
<?php

$possibleValues = $parameterMicroscopeType->possibleValues();
foreach($possibleValues as $possibleValue) {
  $flag = "";
  if ($possibleValue == $parameterMicroscopeType->value()) {
      $flag = "checked=\"checked\" ";
  }
  if ($parameterMicroscopeType->hasLicense($possibleValue)) {
?>
                <input type="radio" 
                       name="MicroscopeType"
                       value="<?php echo $possibleValue ?>"
                       <?php echo $flag ?>/>
                       <?php echo $possibleValue ?>

                <br />
<?php
  }
}

?>
            </div> <!-- values -->
            <div class="bottom">
                <p class="message_confidence_<?php 
                echo $parameterMicroscopeType->confidenceLevel(); ?>">&nbsp;
                </p>
            </div>
            </fieldset>

    <?php

    /***************************************************************************

      NumericalAperture

    ***************************************************************************/

    $parameterNumericalAperture =
        $_SESSION['setting']->parameter("NumericalAperture");

    ?>
            <fieldset class="setting <?php
            echo $parameterNumericalAperture->confidenceLevel(); ?>"
            onmouseover="javascript:changeQuickHelp( 'NA' );" >

              <legend>
                <a href="javascript:openWindow(
                    'http://www.svi.nl/NumericalAperture')">
                    <img src="images/help.png" alt="?" />
                </a>
                numerical aperture
              </legend>
              <ul>
                <li>NA:
                <input name="NumericalAperture" 
                       type="text"
                       size="5"
                       value="<?php
                        echo $parameterNumericalAperture->value() ?>" />

                </li>
              </ul>
              <p class="message_confidence_<?php
                echo $parameterNumericalAperture->confidenceLevel(); ?>">
                  &nbsp;
              </p>
            </fieldset>

    <?php

    /***************************************************************************

      (Emission|Excitation)Wavelength

    ***************************************************************************/

    $parameterEmissionWavelength =
        $_SESSION['setting']->parameter("EmissionWavelength");

    ?>

            <fieldset class="setting <?php
            echo $parameterEmissionWavelength->confidenceLevel(); ?>"
            onmouseover="javascript:changeQuickHelp( 'wavelengths' );" >

                <legend>
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/WaveLength')">
                        <img src="images/help.png" alt="?" />
                    </a>
                    wavelengths
                </legend>
                <ul>
                <li>excitation (nm):

                <div class="multichannel">
<?php

for ($i = 0; $i < $_SESSION['setting']->numberOfChannels(); $i++) {

// Add a line break after 3 entries
if ( $i == 3 ) {
    echo "<br />";
}
?>
	<span class="nowrap">
        Ch<?php echo $i ?>:&nbsp;&nbsp;&nbsp;
        <span class="multichannel">
            <input name="ExcitationWavelength<?php echo $i ?>"
                   type="text"
                   size="8"
                   value="<?php
                    if ($i <= sizeof($excitation)) {
                        echo $excitation[$i];
                    } ?>"
                   class="multichannelinput" />
        </span>&nbsp;
    </span>

<?php

}

?>
</div></li>
	<li>emission (nm):

	<div class="multichannel">
<?php

for ($i=0; $i < $_SESSION['setting']->numberOfChannels(); $i++) {

// Add a line break after 3 entries
if ( $i == 3 ) {
    echo "<br />";
}

?>
	<span class="nowrap">
        Ch<?php echo $i ?>:&nbsp;&nbsp;&nbsp;
        <span class="multichannel">
            <input name="EmissionWavelength<?php echo $i ?>"
                   type="text"
                   size="8"
                   value="<?php
                   if ($i <= sizeof($emission)) {
                       echo $emission[$i];
                   } ?>"
                   class="multichannelinput" />
        </span>&nbsp;
    </span>

<?php

}

?>
        </div></li>
        </ul>

        <p class="message_confidence_<?php
            echo $parameterEmissionWavelength->confidenceLevel(); ?>">
            &nbsp;
        </p>
        </fieldset>

  <?php

    /***************************************************************************

      ObjectiveType

    ***************************************************************************/

    $parameterObjectiveType = $_SESSION['setting']->parameter("ObjectiveType");

  ?>

            <fieldset class="setting <?php
            echo $parameterObjectiveType->confidenceLevel(); ?>"
              onmouseover="javascript:changeQuickHelp( 'objective' );" >

                <legend>
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/LensImmersionMedium')">
                        <img src="images/help.png" alt="?" />
                    </a>
                    objective type
                </legend>

                <div class="values">
                    <?php
                    if ( ! $parameterObjectiveType->mustProvide() ) {
                        $nParamRequiringReset++;
                    ?>
                    <div class="reset"
                        onmouseover="TagToTip('ttSpanReset' )"
                        onmouseout="UnTip()"
                        onclick="document.forms[0].ObjectiveType[0].checked = true;" >
                    </div>
                    <?php
                    }
                    ?>

                    <input name="ObjectiveType"
                           type="radio"
                           value=""
                           style="display:none;" />

<?php

$possibleValues = $parameterObjectiveType->possibleValues();
sort($possibleValues);
foreach ($possibleValues as $possibleValue) {
  $flag = "";
  if ($possibleValue == $parameterObjectiveType->value()) {
      $flag = " checked=\"checked\"";
  }

?>
                    <input name="ObjectiveType" 
                           type="radio"
                           value="<?php echo $possibleValue ?>"
                           <?php echo $flag ?> />
                    <?php echo $possibleValue ?>

<?php

}

?>
                </div> <!-- values -->

                <div class="bottom">
                    <p class="message_confidence_<?php
                        echo $parameterObjectiveType->confidenceLevel(); ?>">
                        &nbsp;
                    </p>
                </div>
            </fieldset>

  <?php

    /***************************************************************************

      SampleMedium

    ***************************************************************************/

    $parameterSampleMedium = $_SESSION['setting']->parameter("SampleMedium");

  ?>

            <fieldset class="setting <?php
                echo $parameterSampleMedium->confidenceLevel(); ?>"
                onmouseover="javascript:changeQuickHelp( 'sample' );" >

                <legend>
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/SpecimenEmbeddingMedium')">
                        <img src="images/help.png" alt="?" />
                    </a>
                    sample medium
                </legend>

                <div class="values">
                    <?php
                    if ( ! $parameterSampleMedium->mustProvide() ) {
                        $nParamRequiringReset++;
                    ?>
                    <div class="reset"
                        onmouseover="TagToTip('ttSpanReset' )"
                        onmouseout="UnTip()"
                        onclick="document.forms[0].SampleMedium[0].checked = true;" >
                    </div>
                    <?php
                    }
                    ?>

                    <input name="SampleMedium"
                           type="radio"
                           value=""
                           style="display:none;" />

<?php

$default = False;
foreach ($parameterSampleMedium->possibleValues() as $possibleValue) {
  $flag = "";
  if ($possibleValue == $parameterSampleMedium->value()) {
    $flag = " checked=\"checked\"";
    $default = True;
  }
  $translation = $parameterSampleMedium->translatedValueFor( $possibleValue );

?>
                    <input name="SampleMedium"
                           type="radio"
                           value="<?php echo $possibleValue ?>"
                           <?php echo $flag ?> />
                    <?php echo $possibleValue ?>
                    <span class="title">[<?php echo $translation ?>]</span>

                    <br />
<?php

}

$value = "";
$flag = "";
if (!$default) {
  $value = $parameterSampleMedium->value();
  if ( $value != "" ) {
    $flag = " checked=\"checked\"";
  }
}

?>
                <input name="SampleMedium"
                       type="radio"
                       value="custom"<?php echo $flag ?> />

                <input name="SampleMediumCustomValue"
                       type="text"
                       size="5"
                       value="<?php echo $value ?>"
                       onclick="this.form.SampleMedium[3].checked=true" />

                </div> <!-- values -->
                <div class="bottom">
                    <p class="message_confidence_<?php
                    echo $parameterSampleMedium->confidenceLevel(); ?>">
                        &nbsp;
                    </p>
                </div>
            </fieldset>

            <div><input name="OK" type="hidden" /></div>

            <div id="controls"
                 onmouseover="javascript:changeQuickHelp( 'default' )">
              <input type="button" value="" class="icon previous"
                  onmouseover="TagToTip('ttSpanBack' )"
                  onmouseout="UnTip()"
                  onclick="document.location.href='image_format.php'" />
              <input type="button" value="" class="icon up"
                  onmouseover="TagToTip('ttSpanCancel' )"
                  onmouseout="UnTip()"
                  onclick="document.location.href='select_parameter_settings.php'" />
              <input type="submit" value="" class="icon next"
                  onmouseover="TagToTip('ttSpanForward' )"
                  onmouseout="UnTip()"
                  onclick="process()" />
            </div>

        </form>

        <?php
            if ( $nParamRequiringReset > 0 ) {
        ?>
            <span class="toolTip" id="ttSpanReset">
                Click to unselect all options.
            </span>
        <?php
        }
        ?>

    </div> <!-- content -->

    <div id="rightpanel"
         onmouseover="javascript:changeQuickHelp( 'default' );" >

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

<?php

include("footer.inc.php");

?>

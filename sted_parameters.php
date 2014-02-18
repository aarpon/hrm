<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Parameter.inc.php");
require_once("./inc/Setting.inc.php");
require_once("./inc/Util.inc.php");
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

$stedDeplParam = $_SESSION['setting']->parameter("StedDeplMode");
$stedDepl = $stedDeplParam->value();
for ($i=0; $i < $_SESSION['setting']->numberOfChannels(); $i++) {
  $stedDeplKey = "stedDepl{$i}";
  if (isset($_POST[$stedDeplKey])) {
    $stedDepl[$i] = $_POST[$stedDeplKey];
  }
}
$stedDeplParam->setValue($stedDepl);
$_SESSION['setting']->set($stedDeplParam);


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
$script = array( "settings.js", "quickhelp/help.js" );
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

        <h2>STED parameters </h2>

        <form method="post" action="" id="select">

            <h4>How did you set up the STED system?</h4>


    <?php
    /***************************************************************************

      StedDeplMode

    ***************************************************************************/

    $parameterStedDeplMode = $_SESSION['setting']->parameter("StedDeplMode");
    ?>

            <fieldset class="setting <?php
            echo $parameterStedDeplMode->confidenceLevel(); ?>"
            onmouseover="javascript:changeQuickHelp( 'type' );" >

                <legend>
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/STED')">
                        <img src="images/help.png" alt="?" />
                    </a>
                    STED Depletion Mode
                </legend>

                <div class="StedDeplModeValues">                              
                <table class="StedDeplModeValues">
                          
<?php                              
$possibleValues = $parameterStedDeplMode->possibleValues();
$chanCnt = $_SESSION['setting']->numberOfChannels();

/* Loop on rows. */
for ($chan = 0; $chan < $chanCnt; $chan++) {
?>    
    <tr><td>Ch<?php echo $chan; ?>:</td>
    
    <td><select id="StedDeplMode<?php echo $chan; ?>">
    
<?php
    /* Loop for select options. */
    foreach($possibleValues as $possibleValue) {
?>
          <option value=<?php echo "$possibleValue";?> >
            <?php echo $possibleValue; ?> 
          </option>
<?php
    } /* End of loop for select options. */
?>    
    </select></td>            

    </tr>
<?php   
} /* End of loop on rows. */
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

   </div> <!-- content -->

<?php
include("footer.inc.php");
?>
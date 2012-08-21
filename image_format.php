<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Parameter.inc.php");
require_once("./inc/Setting.inc.php");
require_once ("./inc/System.inc.php");

/* *****************************************************************************
 *
 * START SESSION, CHECK LOGIN STATE, INITIALIZE WHAT NEEDED
 *
 **************************************************************************** */

session_start();

if ( !isset( $_SESSION[ 'user' ] ) || !$_SESSION[ 'user' ]->isLoggedIn() ) {
  header("Location: " . "login.php"); exit();
}

if ( !isset( $_SESSION[ 'setting' ] ) ) {
  $_SESSION['setting'] = new ParameterSetting();
}	

$message = "";

/* *****************************************************************************
 *
 * SET THE CONFIDENCES FOR THE RELEVANT PARAMETERS
 *
 **************************************************************************** */

/*
   In this page, all parameters are required and independent of the
   file format chosen!
*/
$parameterNames = $_SESSION['setting']->imageParameterNames();
$db = new DatabaseConnection();
foreach ( $parameterNames as $name ) {
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

if ( $_SESSION[ 'setting' ]->checkPostedImageParameters( $_POST ) ) {
  
  // Now we force all variable channel parameters to have the correct number
  // of channels
  $_SESSION[ 'setting' ]->setNumberOfChannels(
    $_SESSION[ 'setting']->numberOfChannels( ) );
  
  // Continue to the next page
  header("Location: " . "microscope_parameter.php"); exit();
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
                "quickhelp/imageFormatHelp.js" );

include("header.inc.php");

?>

    <div id="nav">
        <ul>
            <li
                ><img src="images/user.png" alt="user" />
                &nbsp;<?php echo $_SESSION['user']->name(); ?>
            </li>
            <li>
                <a href="javascript:openWindow(
                   'http://www.svi.nl/HuygensRemoteManagerHelpImageFormat')">
                    <img src="images/help.png" alt="help" />
                    &nbsp;Help
                </a>
            </li>
        </ul>
    </div>
    
    <div id="content">

        <h2>Image format and PSF modality</h2>
        
        <form method="post" action="" id="select">
        
            <h4>What image format will be processed with these settings?</h4>

    <?php

    /***************************************************************************
    
      ImageFileFormat
    
    ***************************************************************************/

    $parameterImageFileFormat =
        $_SESSION['setting']->parameter("ImageFileFormat");


    /***************************************************************************
    
      ImageGeometry
    
    ***************************************************************************/

    $parameterImageGeometry =
        $_SESSION['setting']->parameter("ImageGeometry");

    ?>
    
            <fieldset id="geometry"
                      class="setting <?php
                      echo $parameterImageGeometry->confidenceLevel(); ?>"
                      <?php if ($geometryFlag != "")
              echo " style=\"color: grey\"" ?>
              onmouseover="javascript:changeQuickHelp( 'geometry' );" >
            
                <legend>
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/ImageGeometry')">
                        <img src="images/help.png" alt="?" />
                    </a>
                    image geometry
                </legend>

                <div class="values">
                    <?php
                    if ( ! $parameterImageGeometry->mustProvide() ) {
                    ?>
                    <div class="reset"
                        id="controls_reset"
                        onclick="document.forms[0].ImageGeometry[0].checked = true;" >
                    </div>
                    <?php
                    }
                    ?>

                    <input name="ImageGeometry"
                           type="radio"
                           value=""
                           style="display:none;" />
                

<?php

$possibleValues = $parameterImageGeometry->possibleValues();
foreach($possibleValues as $possibleValue) {
  $value = "multi_" . $possibleValue;
  $flag = "";
  if (!($parameterImageFileFormat->value() == "lsm-single" || 
          $parameterImageFileFormat->value() == "tiff-single") &&
          $possibleValue == $parameterImageGeometry->value())
    $flag = "checked=\"checked\" ";

?>
                <input name="ImageGeometry"
                       type="radio"
                       value="<?php echo $value ?>"
                       <?php echo $geometryFlag ?>
                       <?php echo $flag ?>/>
                       <?php echo $possibleValue ?>
                
<?php

}

?>
                    </div> <!-- values -->
                    <div class="bottom">
                        <p class="message_confidence_<?php
                        echo $parameterImageGeometry->confidenceLevel(); ?>">
                            &nbsp;
                        </p>
                    </div>
                
            </fieldset>

    <?php

    /***************************************************************************
    
      NumberOfChannels
    
    ***************************************************************************/

    $parameterNumberOfChannels =
        $_SESSION['setting']->parameter("NumberOfChannels");

    ?>
            
            <fieldset id="channels" class="setting <?php 
                echo $parameterNumberOfChannels->confidenceLevel(); ?>"
                <?php if ($channelsFlag != "")
              echo " style=\"color: grey\"" ?>
              onmouseover="javascript:changeQuickHelp( 'channels' );" >
            
                <legend>
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/NumberOfChannels')">
                        <img src="images/help.png" alt="?" />
                    </a>
                    number of channels
                </legend>
                
<?php

function check($parameter, $value) {
  if ($value == $parameter->value()) echo "checked=\"checked\" ";
  return "";
}

?>
                <div class="values">
                    <?php
                    if ( ! $parameterNumberOfChannels->mustProvide() ) {
                    ?>
                    <div class="reset"
                        id="controls_reset"
                        onclick="document.forms[0].NumberOfChannels[0].checked = true;" >
                    </div>
                    <?php
                    }
                    ?>

                    <input name="NumberOfChannels"
                           type="radio"
                           value=""
                           style="display:none;" />
                    <input name="NumberOfChannels" 
                           type="radio"
                           value="1"
                           <?php echo $channelsFlag ?>
                           <?php check($parameterNumberOfChannels, 1) ?>/>1
                    <input name="NumberOfChannels" 
                           type="radio"
                           value="2"
                           <?php echo $channelsFlag ?>
                           <?php check($parameterNumberOfChannels, 2) ?>/>2
                    <input name="NumberOfChannels" 
                           type="radio"
                           value="3"
                           <?php echo $channelsFlag ?>
                           <?php check($parameterNumberOfChannels, 3) ?>/>3
                    <input name="NumberOfChannels" 
                           type="radio"
                           value="4"
                            <?php echo $channelsFlag ?>
                            <?php check($parameterNumberOfChannels, 4) ?>/>4
                    <input name="NumberOfChannels" 
                           type="radio"
                           value="5"
                           <?php echo $channelsFlag ?>
                           <?php check($parameterNumberOfChannels, 5) ?>/>5
                </div> <!-- values -->

                <div class="bottom">
                    <p class="message_confidence_<?php
                        echo $parameterNumberOfChannels->confidenceLevel(); ?>">
                        &nbsp;
                    </p>
                </div>

            </fieldset>

    <?php

    /***************************************************************************
    
      PointSpreadFunction
    
    ***************************************************************************/

    $parameterPointSpreadFunction =
        $_SESSION['setting']->parameter("PointSpreadFunction");

    ?>
            
            <h4>Would you like to use an existing measured PSF obtained from
                bead images or a theoretical PSF generated from explicitly
                specified parameters?</h4>
            
            <fieldset class="setting <?php 
                echo $parameterPointSpreadFunction->confidenceLevel(); ?>"
                onmouseover="javascript:changeQuickHelp( 'PSF' );" >
            
                <legend>
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/PointSpreadFunction')">
                        <img src="images/help.png" alt="?" />
                    </a>
                    PSF
                </legend>

                <div class="values">
                    <?php
                    if ( ! $parameterPointSpreadFunction->mustProvide() ) {
                    ?>
                    <div class="reset"
                        id="controls_reset"
                        onclick="document.forms[0].PointSpreadFunction[0].checked = true;" >
                    </div>
                    <?php
                    }
                    ?>

                    <input name="PointSpreadFunction"
                           type="radio"
                           value=""
                           style="display:none;" />
                    <input type="radio"
                           name="PointSpreadFunction"
                           value="theoretical"
                           <?php
                           if ($parameterPointSpreadFunction->value() ==
                                "theoretical")
                                    echo "checked=\"checked\""?> />
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/TheoreticalPsf')">
                        <img src="images/help.png" alt="?" />
                    </a>
                    Theoretical
                    <input type="radio" 
                           name="PointSpreadFunction"
                           value="measured"
                           <?php
                           if ($parameterPointSpreadFunction->value() ==
                            "measured")
                                   echo "checked=\"checked\"" ?>
                           />
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/ExperimentalPsf')">
                        <img src="images/help.png" alt="?" />
                    </a>
                    Measured
                </div> <!-- values -->

                <div class="bottom">
                    <p class="message_confidence_<?php
                    echo $parameterPointSpreadFunction->confidenceLevel(); ?>">
                        &nbsp;
                    </p>
                </div>
                
            </fieldset>

            <div id="controls"
                 onmouseover="javascript:changeQuickHelp( 'default' )">
              <input type="button" value="" class="icon up"
                  id="controls_cancel"
                  onclick="document.location.href='select_parameter_settings.php'" />
              <input type="submit" value="" class="icon next"
                  id="controls_forward"
                  onclick="process()" />
            </div>
            
            <div><input name="OK" type="hidden" /></div>
            
        </form>

    </div> <!-- content -->
    
    <div id="rightpanel" onmouseover="javascript:changeQuickHelp( 'default' )">
    
        <div id="info">
            
            <h3>Quick help</h3>
            
            <div id="contextHelp">
              <p>Here you are asked to provide information on format and
              geometry for the files you want to restore.</p>
            
              <p>Moreover, you must define whether you want to use a theoretical
              PSF, or if you instead want to use a measured PSF you distilled
              with the Huygens software.</p>
            </div>

        </div>
        
        <div id="message">
<?php

echo "<p>$message</p>";

?>
        </div>
        
    </div> <!-- rightpanel -->
    
<?php

/*
 * Tooltips. 
 * 
 * Define $tooltips array with object id as key and tooltip string as value.
 */
$tooltips = array(
    "controls_cancel" => "Abort editing and go back to the image parameters selection page. All changes will be lost!",
    "controls_forward" => "Continue to next page.",
    "controls_reset" => "Click to unselect all options."
);

include("footer.inc.php");

?>

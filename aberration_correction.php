<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc");
require_once("./inc/Parameter.inc");
require_once("./inc/Setting.inc");
require_once("./inc/Util.inc");
require_once ("./inc/System.inc");

session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}
$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

if (count($_POST) > 0) {
    // Store the selected parameters
    $names = $_SESSION['setting']->correctionParameterNames();
    foreach ( $names as $name ) {
        if (isset($_POST[$name])) {
            $parameter = $_SESSION['setting']->parameter($name);
            $parameter->setValue($_POST[$name]);
            $_SESSION['setting']->set($parameter);
        }
    }
    $saved = $_SESSION['setting']->save();			
    $message = "            <p class=\"warning\">".$_SESSION['setting']->message()."</p>";
    if ($saved && isset( $_POST['OK'])) {
      header("Location: " . "select_parameter_settings.php" ); exit();
    }
}

// Javascript includes
$script = array( "settings.js", "quickhelp/help.js",
                "quickhelp/aberrationCorrectionHelp.js" );

include("header.inc.php");

?>
    <!--
      Tooltips
    -->
    <span id="ttSpanBack">Go back to previous page.</span>  
    <span id="ttSpanCancel">Abort editing and go back to the image parameters selection page. All changes will be lost!</span>  
    <span id="ttSpanForward">Save and return to the image parameters selection page.</span>

    <div id="nav">
        <ul>
            <li><?php echo $_SESSION['user']->name(); ?></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpEnableSACorrection')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
        <h3>Spherical aberration correction</h3>
          
        <form method="post" action="" id="select">
            
            <!-- (1) PERFORM SPHERICAL ABERRATION CORRECTION? -->
            
        <h4>Do you want to enable depth-specific PSF correction? This will try to compensate for spherical aberrations introduced by refractive index mismatches.</h4>
            
        <fieldset class="setting"
              onmouseover="javascript:changeQuickHelp( 'enable' );" >
            
            <legend>
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpDepthDependentPsf')"><img src="images/help.png" alt="?" /></a>
                    enable depth-dependent PSF correction?
            </legend>

<?php

$onChange = "onchange=\"javascript:switchCorrection()\"";

?>

            <select id="PerformAberrationCorrection" name="PerformAberrationCorrection"  <?php echo $onChange ?>>
                
            <?php

                $parameter = $_SESSION['setting']->parameter("PerformAberrationCorrection");
                $possibleValues = $parameter->possibleValues();
                $selectedValue  = $parameter->value();
                // The javascript expects option values to match their indexes:
                sort($possibleValues);

                foreach($possibleValues as $possibleValue) {
                    $translation = $_SESSION['setting']->translation("PerformAberrationCorrection", $possibleValue);
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

            <option <?php echo $option?> value="<?php echo $possibleValue?>"><?php echo $translation?></option>                  

            <?php
                }
            ?>

            </select>    
                
        </fieldset>

    <!-- (2) SPECIFY SAMPLE ORIENTATION -->

<?php

$visibility = " style=\"display: none\"";
if ($_SESSION['setting']->parameter("PerformAberrationCorrection")->value( ) == 1)
  $visibility = " style=\"display: block\"";

?>

    <div id="CoverslipRelativePositionDiv"<?php echo $visibility?>>
        
    <h4>For depth-dependent correction to work properly, you have to specify the relative position of the coverslip with respect to the first acquired plane of the dataset.</h4>
            
        <fieldset class="setting"
              onmouseover="javascript:changeQuickHelp( 'orientation' );" >
            
            <legend>
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpSpecifySampleOrientation')"><img src="images/help.png" alt="?" /></a>
                    specify sample orientation
            </legend>
                    
            <select name="CoverslipRelativePosition" >

            <?php

                $parameter = $_SESSION['setting']->parameter("CoverslipRelativePosition");
                $possibleValues = $parameter->possibleValues();
                $selectedValue  = $parameter->value();

                foreach($possibleValues as $possibleValue) {
                    $translation = $_SESSION['setting']->translation("CoverslipRelativePosition", $possibleValue);
                    if ( $possibleValue == $selectedValue ) {
                        $option = "selected=\"selected\"";
                    } else {
                        $option = "";
                    }

            ?>

            <option <?php echo $option?> value="<?php echo $possibleValue?>"><?php echo $translation?></option>                  

            <?php
                }
            ?>
                
            </select>
                
        </fieldset>

    </div> <!-- CoverslipRelativePositionDiv -->

    <!-- (3) CHOOSE ADVANCED CORRECTION MODE -->

<?php

$visibility = " style=\"display: none\"";
if ($_SESSION['setting']->parameter("PerformAberrationCorrection")->value( ) == 1)
  $visibility = " style=\"display: block\"";

?>

    <div id="AberrationCorrectionModeDiv"<?php echo $visibility?>>
        
    <h4>At this point the HRM has enough information to perform depth-dependent aberration correction. Please notice that in certain circumstances, the automatic correction scheme might generate artifacts in the result. If this is the case, please choose the advanced correction mode.</h4>
            
        <fieldset class="setting"
              onmouseover="javascript:changeQuickHelp( 'mode' );" >
            
            <legend>
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpSaCorrectionMode')"><img src="images/help.png" alt="?" /></a>
                    correction mode
            </legend>

<?php

$onChange = "onchange=\"javascript:switchAdvancedCorrection()\"";

?>

            <select id="AberrationCorrectionMode" name="AberrationCorrectionMode"  <?php echo $onChange ?>>

            <?php

                $parameter = $_SESSION['setting']->parameter("AberrationCorrectionMode");
                $possibleValues = $parameter->possibleValues();
                $selectedValue  = $parameter->value();

                foreach($possibleValues as $possibleValue) {
                    $translation = $_SESSION['setting']->translation("AberrationCorrectionMode", $possibleValue);
                    if ( $possibleValue == $selectedValue ) {
                        $option = "selected=\"selected\"";
                    } else {
                        $option = "";
                }

            ?>

            <option <?php echo $option?> value="<?php echo $possibleValue?>"><?php echo $translation?></option>                  

            <?php
                }
            ?>
                
            </select>
                
        </fieldset>

    </div> <!-- AberrationCorrectionModeDiv -->
    
    <!-- (4) ADVANCED CORRECTION MODE -->

<?php

$visibility = " style=\"display: none\"";
if ( ($_SESSION['setting']->parameter("PerformAberrationCorrection")->value( ) == 1) &&
     ($_SESSION['setting']->parameter("AberrationCorrectionMode")->value( ) == "advanced") )
  $visibility = " style=\"display: block\"";

?>

    <div id="AdvancedCorrectionOptionsDiv"<?php echo $visibility?>>
        
    <h4>Here you can choose an advanced correction scheme.</h4>
            
        <fieldset class="setting"
              onmouseover="javascript:changeQuickHelp( 'advanced' );" >
            
            <legend>
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpAdvancedSaCorrection')"><img src="images/help.png" alt="?" /></a>
                    advanced correction scheme
            </legend>

<?php

$onChange = "onchange=\"javascript:switchAdvancedCorrectionScheme()\"";

?>

            <select id="AdvancedCorrectionOptions" name="AdvancedCorrectionOptions"  <?php echo $onChange ?>>

            <?php

                $version = System::huCoreVersion();
                
                $parameter = $_SESSION['setting']->parameter("AdvancedCorrectionOptions");
                $possibleValues = $parameter->possibleValues();
                $selectedValue  = $parameter->value();
                if ( $version < 3030200 ) {
                    $possibleValues = array_diff($possibleValues, array( 'slice' ) );
                    $possibleValues = array_values( $possibleValues );
                    if ( $selectedValue == 'slice' ) {
                        $selectedValue == 'user';
                    }
                }

                foreach($possibleValues as $possibleValue) {
                    $translation = $_SESSION['setting']->translation("AdvancedCorrectionOptions", $possibleValue);
                    if ( $possibleValue == $selectedValue ) {
                        $option = "selected=\"selected\"";
                    } else {
                        $option = "";
                    }

            ?>

            <option <?php echo $option?> value="<?php echo $possibleValue?>"><?php echo $translation?></option>                  

            <?php
                }
            ?>
                
            </select>

<?php

$visibility = " style=\"display: none\"";
if ( ($_SESSION['setting']->parameter("PerformAberrationCorrection")->value( ) == 1) &&
     ($_SESSION['setting']->parameter("AberrationCorrectionMode")->value( ) == "advanced") &&
     ($_SESSION['setting']->parameter("AdvancedCorrectionOptions")->value( ) == "user") )
  $visibility = " style=\"display: block\"";

$parameter = $_SESSION['setting']->parameter("PSFGenerationDepth");
$selectedValue  = $parameter->value();

?>
            
            <div id="PSFGenerationDepthDiv"<?php echo $visibility?>>
                <p>Depth for PSF generation (&micro;m): <input name="PSFGenerationDepth" type="text" style="width:100px;" value="<?php echo $selectedValue; ?>" /></p>
            </div>
            
        </fieldset>

      </div> <!-- AdvancedCorrectionOptionsDiv -->
            
      <div><input name="OK" type="hidden" /></div>

      <div id="controls" onmouseover="javascript:changeQuickHelp( 'default' )">      
        <input type="button" value="" class="icon previous"
                  onmouseover="TagToTip('ttSpanBack' )"
                  onmouseout="UnTip()"
                  onclick="document.location.href='capturing_parameter.php'" />
        <input type="button" value="" class="icon up"
                  onmouseover="TagToTip('ttSpanCancel' )"
                  onmouseout="UnTip()"
                  onclick="document.location.href='select_parameter_settings.php'" />
        <input type="submit" value="" class="icon save"
                  onmouseover="TagToTip('ttSpanForward' )"
                  onmouseout="UnTip()"
                  onclick="process()" />
      </div>            
    </form>
        
    </div> <!-- content -->
    
    <div id="rightpanel"  onmouseover="javascript:changeQuickHelp( 'default' )">
    
        <div id="info">
          
          <h3>Quick help</h3>
          
          <div id="contextHelp">  
            <p>The main cause of spherical aberration is a mismatch between the
            refractive index of the lens immersion medium and specimen embedding
            medium and causes the PSF to become asymmetric at depths of already
            a few &micro;m. SA is especially harmful for widefield microscope
            deconvolution. The HRM can correct for SA automatically, but in case of
            very large refractive index mismatches some artifacts can be generated.
            Advanced parameters allow for fine-tuning of the correction.</p>
          </div>
            
        </div>
        
        <div id="message">
<?php

echo $message;

?>
        </div>
        
    </div> <!-- rightpanel -->
    
<?php

include("footer.inc.php");

?>

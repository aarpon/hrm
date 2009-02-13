<?php

// php page: capturing_parameter.php

// This file is part of huygens remote manager.

// Copyright: Montpellier RIO Imaging (CNRS)

// contributors :
// 	     Pierre Travo	(concept)
// 	     Volker Baecker	(concept, implementation)

// email:
// 	pierre.travo@crbm.cnrs.fr
// 	volker.baecker@crbm.cnrs.fr

// Web:     www.mri.cnrs.fr

// huygens remote manager is a software that has been developed at 
// Montpellier Rio Imaging (mri) in 2004 by Pierre Travo and Volker 
// Baecker. It allows running image restoration jobs that are processed 
// by 'Huygens professional' from SVI. Users can create and manage parameter 
// settings, apply them to multiple images and start image processing 
// jobs from a web interface. A queue manager component is responsible for 
// the creation and the distribution of the jobs and for informing the user 
// when jobs finished.

// This software is governed by the CeCILL license under French law and 
// abiding by the rules of distribution of free software. You can use, 
// modify and/ or redistribute the software under the terms of the CeCILL 
// license as circulated by CEA, CNRS and INRIA at the following URL 
// "http://www.cecill.info".

// As a counterpart to the access to the source code and  rights to copy, 
// modify and redistribute granted by the license, users are provided only 
// with a limited warranty and the software's author, the holder of the 
// economic rights, and the successive licensors  have only limited 
// liability.

// In this respect, the user's attention is drawn to the risks associated 
// with loading, using, modifying and/or developing or reproducing the 
// software by the user in light of its specific status of free software, 
// that may mean that it is complicated to manipulate, and that also 
// therefore means that it is reserved for developers and experienced 
// professionals having in-depth IT knowledge. Users are therefore encouraged 
// to load and test the software's suitability as regards their requirements 
// in conditions enabling the security of their systems and/or data to be 
// ensured and, more generally, to use and operate it in the same conditions 
// as regards security.

// The fact that you are presently reading this means that you have had 
// knowledge of the CeCILL license and that you accept its terms.

require_once("./inc/User.inc");
require_once("./inc/Parameter.inc");
require_once("./inc/Setting.inc");
require_once("./inc/Util.inc");

session_start();
if (isset($_GET['exited'])) {
  $_SESSION['user']->logout();
  session_unset();
  session_destroy();
  header("Location: " . "login.php"); exit();
}
if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}
$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

if (count($_POST) > 0) {
    // Store the selected parameters
    $names = array( 'PerformAberrationCorrection',
                   'CoverslipRelativePosition',
                   'AberrationCorrectionMode',
                   'AdvancedCorrectionOptions',
                   'PSFGenerationDepth' );
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

$script = "settings.js";

include("header.inc.php");

?>

   <div id="nav">
        <ul>
         <li><a href="select_images.php?exited=exited">exit</a></li>
        <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpEnableSACorrection')">help</a></li>
        </ul>
    </div> <!-- nav -->
    
    <div id="content">
    
        <h3>Parameter Setting - Page 4</h3>
          
        <form method="post" action="" id="select">
            
            <!-- (1) PERFORM SPHERICAL ABERRATION CORRECTION? -->
            
        <h4>Do you want to enable depth-specific PSF correction? This will try to compensate for spherical aberrations introduced by refractive index mismatches.</h4>
            
        <fieldset class="setting">
            
            <legend>
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&help=HuygensRemoteManagerHelpDepthDependentPsf')"><img src="images/help.png" alt="?" /></a>
                    enable depth-dependent PSF correction?
            </legend>

            <select name="PerformAberrationCorrection" style="width: 420px">
                
            <?php

                $parameter = $_SESSION['setting']->parameter("PerformAberrationCorrection");
                $possibleValues = $parameter->possibleValues();
                $selectedValue  = $parameter->value();

                foreach($possibleValues as $possibleValue) {
                    $translation = $_SESSION['setting']->translation("PerformAberrationCorrection", $possibleValue);
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

    <!-- (2) SPECIFY SAMPLE ORIENTATION -->

    <div id="CoverslipRelativePositionDiv">
        
    <h4>For depth-dependent correction to work properly, you have to specify the relative position of the coverslip with respect to the first acquired plane of the dataset.</h4>
            
        <fieldset class="setting">
            
            <legend>
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&help=HuygensRemoteManagerHelpSpecifySampleOrientation')"><img src="images/help.png" alt="?" /></a>
                    specify sample orientation
            </legend>
                
            <select name="CoverslipRelativePosition" style="width: 420px">

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

    <div id="AberrationCorrectionModeDiv">
        
    <h4>At this point the HRM hs enough information to perform depth-dependent aberration correction. Please notice that in certain circumstances, the automatic correction scheme might generate artifacts in the result. If this is the case, please choose the advanced correction mode.</h4>
            
        <fieldset class="setting">
            
            <legend>
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&help=HuygensRemoteManagerHelpSaCorrectionMode')"><img src="images/help.png" alt="?" /></a>
                    correction mode
            </legend>
                
            <select name="AberrationCorrectionMode" style="width: 420px">

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

    <div id="AdvancedCorrectionOptionsDiv">
        
    <h4>Here you can choose an advanced correction scheme.</h4>
            
        <fieldset class="setting">
            
            <legend>
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&help=HuygensRemoteManagerHelpAdvancedSaCorrection')"><img src="images/help.png" alt="?" /></a>
                    advanced correction scheme
            </legend>
                
            <select name="AdvancedCorrectionOptions" style="width: 420px">

            <?php

                $version = getHucoreVersionAsInteger( $enable_code_for_huygens );
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
                $parameter = $_SESSION['setting']->parameter("PSFGenerationDepth");
                $selectedValue  = $parameter->value();
            ?>
            
            <div id="PSFGenerationDepthDiv" >
                <p>Please enter depth for PSF generation (um): <input name="PSFGenerationDepth" type="text" value="<?php echo $selectedValue; ?>" /></p>
            </div>
            
        </fieldset>

    </div> <!-- AdvancedCorrectionOptionsDiv -->
            
    <div><input name="OK" type="hidden" /></div>
            
    </form>
        
    </div> <!-- content -->
    
    <div id="stuff">
    
        <div id="info">
        
            <input type="button" value="" class="icon cancel" onclick="document.location.href='capturing_parameter.php'" />
            <input type="submit" value="" class="icon apply" onclick="process()" />
            
            <p>The main cause of spherical aberration is a mismatch between the
            refractive index of the lens immersion medium and specimen embedding
            medium and causes the PSF to become asymmetric at depths of already
            a few um. SA is especially harmful for widefield microscope
            deconvolution.</p>
            
            <p>The HRM can correct for SA automatically, but in case of very
            large refractive index mismatches some artifacts can be generated.
            Advanced parameters allow for fine-tuning of the correction.</p>
            
        </div>
        
        <div id="message">
<?php

echo $message;

?>
        </div>
        
    </div> <!-- stuff -->
    
<?php

include("footer.inc.php");

?>

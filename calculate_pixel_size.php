<?php

// php page: calculate_pixel_size.php

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
// Montpellier Rio Imaging (mri) in 2004-2007 by Pierre Travo and Volker 
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

require_once ("./inc/User.inc");

session_start();

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

if (isset ($_GET['exited'])) {
	$_SESSION['user']->logout();
	session_unset();
	session_destroy();
	header("Location: " . "login.php");
	exit ();
}

if (!isset ($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
	header("Location: " . "login.php");
	exit ();
}

if (isset($_POST['CCDCaptorSize'])) {
	$_SESSION['CCDCaptorSize'] = $_POST['CCDCaptorSize'];
}

if (isset($_POST['CCDCaptorSize'])) {
        
	$ccd = $_SESSION['CCDCaptorSize'];      // ccd captor size (different form 'CCDCaptorSizeX'!). It's of use uf calculating CCDCaptorSizeX.

	// Get all parameters from the form
	$bin = $_POST['Binning'];
	$obm = $_POST['ObjectiveMagnification'];
	$cmf = $_POST['CMount'];
	$tf  = $_POST['TubeFactor'];
       
	$pixelSize =  (floatval($ccd) * floatval($bin)) / (floatval($obm)*floatval($cmf)*floatval($tf));        // compute the theoretical value for the pixel size
        
	$parameter = $_SESSION['setting']->parameter('CCDCaptorSizeX'); // set the value for CCDCaptorSizeX 
	$parameter->setValue($pixelSize);
	$_SESSION['setting']->set($parameter);
        
        //check if the paramaters of this page have been correctly set
        $ok = $_SESSION['setting']->checkCalculateParameter();  // $_SESSION['setting'] is an object ParameterSetting
        $message = "            <p class=\"warning\">".$_SESSION['setting']->message()."</p>\n";
        if($ok) {
                header("Location: " . "capturing_parameter.php"); 
                exit();
        }
}


$script = "settings.js";
include ("header.inc.php");
?>

<div id="nav">  
        <ul>       
            <li><a href="select_images.php?exited=exited">exit</a></li>    
            <li><a href="javascript:openWindow('')">help</a></li>
        </ul>
</div>
    
<div id="content">
    
    <h3>Parameter Setting - Calculate Pixel Size</h3>

    <h4>Please mind that these parameters are only used to calculate the pixel size are not stored!</h4> 
 
    <form method="post" action="calculate_pixel_size.php" id="select">
    
       <fieldset class="setting">

    <?php

$textForCaptorSize = "size of the ccd element (nm)";
$value = '';
if(isset($_SESSION['CCDCaptorSize']))
        $value = $_SESSION['CCDCaptorSize'];
        
?>
    <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpCCD')"><img src="images/help.png" alt="?" /></a>
    		 <?php echo $textForCaptorSize ?>:
    		 
           <input name="CCDCaptorSize" type="text" size="5" value="<?php echo $value ?>" />
            
         <br />
            
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=PixelBinning')"><img src="images/help.png" alt="?" /></a>
                binning:
                
                <select name="Binning" size="1">
<?php


$parameter = $_SESSION['setting']->parameter("Binning");
foreach ($parameter->possibleValues() as $possibleValue) {
	$flag = "";
	if ($possibleValue == $parameter->value()) {
		$flag = " selected=\"selected\"";
	}
?>
                    <option<?php echo $flag ?>><?php echo $possibleValue ?></option>
<?php


}
?>

                </select>
                <br />
 <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpCMount')"><img src="images/help.png" alt="?" /></a>
<?php


$parameter = $_SESSION['setting']->parameter("CMount");
$value = $parameter->value();
?>                
<?php echo "c-mount-factor" ?>:
                        <input name="CMount" type="text" size="5" value="<?php echo $value ?>" /> <br />
                        
 <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpTubeFactor')"><img src="images/help.png" alt="?" /></a>
<?php


$parameter = $_SESSION['setting']->parameter("TubeFactor");
$value = $parameter->value();
?>                
<?php echo "tube-factor" ?>:
                        <input name="TubeFactor" type="text" size="5" value="<?php echo $value ?>" /> <br />
                        
<a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=ObjectiveMagnification')"><img src="images/help.png" alt="?" /></a>
                objective magnification:
                
                <select name="ObjectiveMagnification" size="1">
<?php

$parameter = $_SESSION['setting']->parameter("ObjectiveMagnification");
$sortedPossibleValues = $parameter->possibleValues();
sort( $sortedPossibleValues, SORT_NUMERIC );
foreach ( $sortedPossibleValues as $possibleValue) {
  $flag = "";
  if ($possibleValue == $parameter->value()) $flag = " selected=\"selected\"";

?>
                    <option<?php echo $flag ?>><?php echo $possibleValue ?></option>
<?php

}

?>
                </select>
                X
                        
            </fieldset>
    <div><input name="OK" type="hidden" /></div>
    </form>
    
 </div> <!-- content -->
 
 <div id="stuff">
    
        <div id="info">
        
            <input type="button" value="" class="icon cancel" onclick="document.location.href='capturing_parameter.php'" />
            <input type="submit" value="" class="icon apply" onclick="process()" />
            
            <p>
               Enter the values and press the ok button to calculate the
               pixel size. Press the cancel button to go back without changing
               the pixel size.
            </p>
            
        </div>
        
        <div id="message">
                
<?php

echo $message;

?>
        </div>
        
    </div> <!-- stuff -->
    
<?php

include ("footer.inc.php");

?>

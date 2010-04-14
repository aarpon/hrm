<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

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
<!--
  Tooltips
-->
<span id="ttSpanCancel">Go back to previous page without calculating the pixel size.</span>  
<span id="ttSpanForward">Update the pixel size field on previous page with the calculated value.</span>  

<div id="nav">  
        <ul>
            <li><?php echo $_SESSION['user']->name(); ?></li>
            <li><a href="javascript:openWindow('')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
</div>
    
<div id="content">
    
    <h3>Calculate pixel size</h3>

    <h4>Please mind that these parameters are only used to calculate the pixel size and are not stored!</h4> 
 
    <form method="post" action="calculate_pixel_size.php" id="select">
    
       <fieldset class="setting">

    <?php

$textForCaptorSize = "size of the CCD element (nm)";
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
       
                <div id="controls">      
                  <input type="button" value="" class="icon up"
                    onmouseover="TagToTip('ttSpanCancel' )"
                    onmouseout="UnTip()"
                    onclick="document.location.href='capturing_parameter.php'" />
                  <input type="submit" value="" class="icon next"
                    onmouseover="TagToTip('ttSpanForward' )"
                    onmouseout="UnTip()"
                    onclick="process()" />
                </div>

    </form>
    
 </div> <!-- content -->
 
 <div id="rightpanel">
    
        <div id="info">

            <h3>Quick help</h3>           

            <p>Here you can calculate the image pixel size from the physical
            attributes of your CCD chip element and some of the relevant
            microscope parameters.</p>
            
        </div>
        
        <div id="message">
                
<?php

echo $message;

?>
        </div>
        
    </div> <!-- rightpanel -->
    
<?php

include ("footer.inc.php");

?>

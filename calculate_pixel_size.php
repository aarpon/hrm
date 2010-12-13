<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once ("./inc/User.inc");


/* *****************************************************************************
 *
 * START SESSION, CHECK LOGIN STATE, INITIALIZE WHAT NEEDED
 *
 **************************************************************************** */

session_start();

if (!isset ($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
	header("Location: " . "login.php");
	exit ();
}

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */
if ( $_SESSION[ 'setting' ]->checkPostedCalculatePixelSizeParameters( $_POST ) ) {
	
	// Calculate and set the pixel size
	$ccd = floatval( $_SESSION[ 'setting' ]->parameter( "CCDCaptorSize" )->value() );
	$bin = floatval( $_SESSION[ 'setting' ]->parameter( "Binning" )->value() );
	$obm = floatval( $_SESSION[ 'setting' ]->parameter( "ObjectiveMagnification" )->value() );
	$cmf = floatval( $_SESSION[ 'setting' ]->parameter( "CMount" )->value() );
	$tbf = floatval( $_SESSION[ 'setting' ]->parameter( "TubeFactor" )->value() );
	$pixelSize = ( $ccd * $bin ) / ( $obm * $cmf * $tbf );
	
	// Try
	$parameter = new CCDCaptorSizeX();
	$parameter->setValue( $pixelSize );
	if ( $parameter->check(  ) ) {
		$parameter = $_SESSION['setting']->parameter('CCDCaptorSizeX');
		$parameter->setValue($pixelSize);
		$_SESSION['setting']->set($parameter);
		header("Location: " . "capturing_parameter.php"); exit();
	} else {
		$message = "            <p class=\"warning\">" .
			"Something is wrong with your parameters!</p>\n";
	}
} else {
  
  $message = "            <p class=\"warning\">" .
    $_SESSION['setting']->message() . "</p>\n";
	
}

/* *****************************************************************************
 *
 * CREATE THE PAGE
 *
 **************************************************************************** */

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

    <form method="post" action="calculate_pixel_size.php" id="select">
    
       <fieldset class="setting">

    <?php

$textForCaptorSize = "size of the CCD element (nm)";
$value = '';
$parameter = $_SESSION['setting']->parameter("CCDCaptorSize");
$value = $parameter->value();
        
?>
    <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpCCD')"><img src="images/help.png" alt="?" /></a>
    		 <?php echo $textForCaptorSize ?>:
    		 
           <input name="CCDCaptorSize" type="text" size="5" value="<?php echo $value ?>" />
            
         <br />
            
                <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=PixelBinning')"><img src="images/help.png" alt="?" /></a>
                binning:
                
                <select style="width:20%;" name="Binning" size="1">
<?php


$parameter = $_SESSION['setting']->parameter("Binning");
foreach ($parameter->possibleValues() as $possibleValue) {
	$flag = "";
	if ($possibleValue == $parameter->value()) {
		$flag = " selected=\"selected\"";
	}
?>
                    <option <?php echo $flag ?>><?php echo $possibleValue ?></option>
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
<?php echo "C-mount" ?>:
                        <input name="CMount" type="text" size="5" value="<?php echo $value ?>" /> <br />
                        
 <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpTubeFactor')"><img src="images/help.png" alt="?" /></a>
<?php


$parameter = $_SESSION['setting']->parameter("TubeFactor");
$value = $parameter->value();
?>                
<?php echo "tube factor" ?>:
                        <input name="TubeFactor" type="text" size="5" value="<?php echo $value ?>" /> <br />
                        
<a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=ObjectiveMagnification')"><img src="images/help.png" alt="?" /></a>
                objective magnification:
                
                <select style="width:20%;" name="ObjectiveMagnification" size="1">
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

<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Parameter.inc.php");
require_once("./inc/Setting.inc.php");
require_once("./inc/Database.inc.php");
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

if (!isset($_SESSION['analysis_setting'])) {
  $_SESSION['analysis_setting'] = new AnalysisSetting();
}
$message = "";


/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */

if ( $_SESSION[ 'analysis_setting' ]->checkPostedAnalysisParameters( $_POST ) ) {
$saved = $_SESSION['analysis_setting']->save();
  if ($saved) {
    header("Location: " . "select_analysis_settings.php"); exit();
  } else {
    $message = $_SESSION['analysis_setting']->message();
  }
} else {
  $message = $_SESSION['analysis_setting']->message();
}


/* *****************************************************************************
 *
 * CREATE THE PAGE
 *
 **************************************************************************** */

// Javascript includes
$script = array( "settings.js", "quickhelp/help.js");

include("header.inc.php");

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

        <h2>Analysis - colocalization</h2>

        <form method="post" action="" id="select">

    <?php

    /***************************************************************************

      Colocalization

    ***************************************************************************/
    ?>
            <fieldset class="setting"
            onmouseover="javascript:changeQuickHelp( 'type' );" >

                <legend>
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/ColocalizationBasics')">
                        <img src="images/help.png" alt="?" />
                    </a>
                    Would you like to perform Colocalization Analysis?
                    </legend>
                    <select id="ColocAnalysis"
                              name="ColocAnalysis"
                              onchange="javascript:switchColocMode();">

<?php
                    
/*
      COLOCALIZATION ANALYSIS
*/
$parameterPerformColocAnalysis =
    $_SESSION['analysis_setting']->parameter("ColocAnalysis");
$possibleValues = $parameterPerformColocAnalysis->possibleValues();
$selectedMode  = $parameterPerformColocAnalysis->value();

foreach($possibleValues as $possibleValue) {
  $translation =
      $parameterPerformColocAnalysis->translatedValueFor( $possibleValue );
  if ( $possibleValue == $selectedMode ) {
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
</fieldset>



<?php

/*
      COLOCALIZATION CHANNELS
*/

$visibility = " style=\"display: none\"";
if ($parameterPerformColocAnalysis->value( ) == 1)
    $visibility = " style=\"display: block\"";

?>


<div id="ColocChannelSelectionDiv"<?php echo $visibility?>>
 <fieldset class="setting"
            onmouseover="javascript:changeQuickHelp( 'type' );" >

                <legend>
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/ColocalizationBasics')">
                        <img src="images/help.png" alt="?" />
                    </a>
Channels 
                    </legend>

    <?php
$parameterColocChannel =
    $_SESSION['analysis_setting']->parameter("ColocChannel");

$selectedValues = $parameterColocChannel->value();

    for ($chan=0;$chan< $_SESSION['setting']->numberOfChannels();$chan++) {
        if (true == isValueInArray($selectedValues, $chan)) {
            $checked = "checked";
        } else {
            $checked = "";
        }
        
        ?>
        Ch. <?php echo $chan;?>: <input type="checkbox" name="ColocChannel[]" value=<?php echo $chan;
    if ($checked) {
        ?> checked=<?php echo $checked;
    } ?>
        />
<?php } ?>
</fieldset>
</div> <!-- ColocChannelSelectionDiv -->





<?php
/*
      COLOCALIZATION COEFFICIENTS
*/
?>

<div id="ColocCoefficientSelectionDiv"<?php echo $visibility?>>
 <fieldset class="setting"
            onmouseover="javascript:changeQuickHelp( 'type' );" >

                <legend>
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/ColocalizationCoefficientsInBrief')">
                        <img src="images/help.png" alt="?" />
                    </a>
Colocalization coefficients 
                    </legend>
<table>
    <?php
$parameterColocCoefficient =
    $_SESSION['analysis_setting']->parameter("ColocCoefficient");

/* Which coloc coefficients should be displayed as choice? */
$possibleValues = $parameterColocCoefficient->possibleValues();

/* Were there coloc coefficients selected previously? */
$selectedValues  = $parameterColocCoefficient->value();

/* The coefficients will be split into two columns. */
$cellCnt = 0;

foreach ($possibleValues as $possibleValue) {

    if (in_array($possibleValue, $selectedValues)) {
        $checked = "checked";
    } else {
        $checked = "";
    }
    
    if (($cellCnt % 3) == 0) {
        echo "<tr>";
    }
    $translation =
        $parameterColocCoefficient->translatedValueFor( $possibleValue );
    
    echo "<td class=\"text\">" . $translation ?>
        </td><td class="check">
        <input type="checkbox" name="ColocCoefficient[]" value=
        <?php echo $possibleValue;
    if ($checked) {
        ?> checked=<?php echo $checked;
    }?>
        /></td>
                <?php
                if ((($cellCnt + 1) % 3) == 0) {
                    echo "</tr>";
                }
    $cellCnt++;
    
}
?>
</table>
</fieldset>
</div> <!-- ColocCoefficientSelectionDiv -->





<?php
/*
      COLOCALIZATION THRESHOLD
*/
?>
<div id="ColocThresholdSelectionDiv"<?php echo $visibility?>>
<fieldset class="setting"
    onmouseover="javascript:changeQuickHelp( 'type' );" >
    <legend>
<a href="javascript:openWindow(
                       'http://www.svi.nl/ColocalizationBasics')">
    <img src="images/help.png" alt="?" />
    </a>
Threshold 
                    </legend>

    <?php
$parameterColocThresh =
    $_SESSION['analysis_setting']->parameter("ColocThreshold");
$colocThreshold = $parameterColocThresh->internalValue();

$flag = "";
if ($colocThreshold[0] == "" || $colocThreshold[0] == "auto") {
    $flag = " checked=\"checked\"";
}
?>
        <p>
             <input type="radio"
             id="ColocThresholdAuto"
             name="ColocThresholdMode"
             value="auto"<?php echo $flag ?> />
             Automatic estimation
             </p>
<?php

$flag = "";
if ($colocThreshold[0] != "" && $colocThreshold[0] != "auto") {
    $flag = " checked=\"checked\"";
}

?>

    <input type="radio"
         id="ColocThresholdManual"
         name="ColocThresholdMode"
         value="manual"<?php echo $flag ?> />
    Percentage of the intensity range (%):
    
    <div class="multichannel">
<?php
    for ($chan=0; $chan < $_SESSION['setting']->numberOfChannels(); $chan++) {
        $threshold = "";
        if ($colocThreshold[0] != "auto") {
            $threshold = $colocThreshold[$chan];
        }
        
            /* Add a line break after 3 entries. */
        if ( $chan == 3 ) {
            echo "<br />";
        }
?>
        <span class="nowrap">
        Ch<?php echo $chan ?>:&nbsp;&nbsp;&nbsp;
        <span class="multichannel">
        
        <input id="ColocThreshold<?php echo $chan ?>"
        name="ColocThreshold<?php echo $chan ?>"
        type="text"
        size="8"
        value="<?php echo $threshold ?>"
        class="multichannelinput"
        onclick="document.forms[0].ColocThresholdManual.checked=true"/>
        
        </span>&nbsp;
        </span>

<?php
}
?>
</div><!--multichannel-->

</fieldset>
</div> <!--ColocThresholdSelectionDiv-->





<?php
/*
      COLOCALIZATION MAPS
*/
?>

<div id="ColocMapSelectionDiv"<?php echo $visibility?>>
 <fieldset class="setting"
            onmouseover="javascript:changeQuickHelp( 'type' );" >

                <legend>
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/ColocalizationMap')">
                        <img src="images/help.png" alt="?" />
                    </a>
Colocalization maps 
                    </legend>


    <?php
$parameterColocMap =
    $_SESSION['analysis_setting']->parameter("ColocMap");


$possibleValues = $parameterColocMap->possibleValues();

foreach ($possibleValues as $possibleValue) {
    $translation =
        $parameterColocMap->translatedValueFor( $possibleValue );
    
    $flag = "";
    if ($possibleValue == $parameterColocMap->value()) {
        $flag = "checked=\"checked\" ";
    }

    ?>
        <input type="radio" 
             name="ColocMap"
             value="<?php echo $possibleValue ?>"
             <?php echo $flag ?>/>
             <?php echo $translation ?>
<?php                    
}
?>

</fieldset>
</div> <!-- ColocMapSelectionDiv -->




                    
                    
            <div><input name="OK" type="hidden" /></div>

            <div id="controls"
                 onmouseover="javascript:changeQuickHelp( 'default' )">
              <input type="button" value="" class="icon up"
                  id="controls_cancel"                     
                  onclick="document.location.href='select_analysis_settings.php'" />
              <input type="submit" value="" class="icon save"
                  id="controls_save"
                  onclick="process()" />
            </div>

        </form>



    </div> <!-- content -->



    <div id="rightpanel"
         onmouseover="javascript:changeQuickHelp( 'default' );" >

        <div id="info">

          <h3>Quick help</h3>

            <div id="contextHelp">
              <p>On this page you can specify whether you would like to perform colocalization analysis on the deconvolved images.</p>
<p>You can select the channels to be taken into account for colocalization analysis, as well as the colocalization coefficients and maps.</p>
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
    "controls_cancel" => "Abort editing and go back to the processing parameters selection page. All changes will be lost!",
    "controls_save" => "Save and return to the processing parameters selection page.",
);

include("footer.inc.php");

?>

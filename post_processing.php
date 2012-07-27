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

if (!isset($_SESSION['task_setting'])) {
  $_SESSION['task_setting'] = new TaskSetting();
}
$message = "";


/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */

if ( $_SESSION[ 'task_setting' ]->checkPostedPostParameters( $_POST ) ) {
$saved = $_SESSION['task_setting']->save();
  if ($saved) {
    header("Location: " . "select_task_settings.php"); exit();
  } else {
    $message = $_SESSION['task_setting']->message();
  }
} else {
  $message = $_SESSION['task_setting']->message();
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
    <!--
      Tooltips
    -->
    <span id="ttSpanBack">
        Go back to previous page.
    </span>
    <span id="ttSpanCancel">
        Abort editing and go back to the processing parameters selection page.
        All changes will be lost!
    </span>
    <span id="ttSpanSave">
        Save and return to the processing parameters selection page.
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

        <h3>Post - deconvolution</h3>

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
                       'http://www.svi.nl/ColocalizationTheory')">
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
    $_SESSION['task_setting']->parameter("ColocAnalysis");
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
                       'http://www.svi.nl/ColocalizationTheory')">
                        <img src="images/help.png" alt="?" />
                    </a>
Channels 
                    </legend>

    <?php
$parameterColocChannel =
    $_SESSION['task_setting']->parameter("ColocChannel");
$selectedValues = $parameterColocChannel->value();

    for ($chan=0;$chan< $_SESSION['task_setting']->numberOfChannels();$chan++) {
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
                       'http://www.svi.nl/ColocalizationTheory')">
                        <img src="images/help.png" alt="?" />
                    </a>
Colocalization coefficients 
                    </legend>
<table>
    <?php
$parameterColocCoefficient =
    $_SESSION['task_setting']->parameter("ColocCoefficient");

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
    
    if (!($cellCnt % 2)) {
        echo "<tr>";
    }
    $translation =
        $parameterColocCoefficient->translatedValueFor( $possibleValue );
    
    echo "<td>" . $translation . " " . $selected?>:
        <input type="checkbox" name="ColocCoefficient[]" value=
        <?php echo $possibleValue;
    if ($checked) {
        ?> checked=<?php echo $checked;
    }?>
        /></td>
                <?php
                if ($cellCnt % 2) {
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
      COLOCALIZATION MAPS
*/
?>

<div id="ColocMapSelectionDiv"<?php echo $visibility?>>
 <fieldset class="setting"
            onmouseover="javascript:changeQuickHelp( 'type' );" >

                <legend>
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/ColocalizationTheory')">
                        <img src="images/help.png" alt="?" />
                    </a>
Colocalization maps 
                    </legend>

<input name="ColocMap"
                           type="radio"
                           value=""
                           style="display:none;" />

    <?php
$parameterColocMap =
    $_SESSION['task_setting']->parameter("ColocMap");


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
             
             <br />
<?php                    
}
?>



    
</fieldset>
</div> <!-- ColocMapSelectionDiv -->



                    
                    
            <div><input name="OK" type="hidden" /></div>

            <div id="controls"
                 onmouseover="javascript:changeQuickHelp( 'default' )">
              <input type="button" value="" class="icon previous"
                  onmouseover="TagToTip('ttSpanBack' )"
                  onmouseout="UnTip()"
                  onclick="document.location.href='task_parameter.php'" />
              <input type="button" value="" class="icon up"
                  onmouseover="TagToTip('ttSpanCancel' )"
                  onmouseout="UnTip()"
                  onclick="document.location.href='select_task_settings.php'" />
              <input type="submit" value="" class="icon save"
                  onmouseover="TagToTip('ttSpanSave' )"
                  onmouseout="UnTip()"
                  onclick="process()" />
            </div>

        </form>



    </div> <!-- content -->



    <div id="rightpanel"
         onmouseover="javascript:changeQuickHelp( 'default' );" >

        <div id="info">

          <h3>Quick help</h3>

            <div id="contextHelp">
              <p>On this page you can specify whether you would like to perform colocalization analysis on your images.</p>
<p>You can select the channels that must be taken into account for Colocalization Analysis, as well as the colocalization coefficients and maps.</p>
            </div>

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

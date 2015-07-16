<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Parameter.inc.php");
require_once("./inc/Setting.inc.php");
require_once("./inc/Database.inc.php");
require_once("./inc/wiki_help.inc.php");

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
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */
/* if ( $_SESSION[ 'task_setting' ]->checkPostedAberrationCorrectionParameters( */
/*          $_POST ) ) { */
/*   header("Location: " . "capturing_parameter.php"); exit(); */
/* } else { */
/*   $message = $_SESSION['setting']->message(); */
/* } */




/* *****************************************************************************
 *
 * CREATE THE PAGE
 *
 **************************************************************************** */

include("header.inc.php");

?>

    <div id="content">

        <h2>Restoration - Chromatic Aberration</h2>


    <div id="cac">
<?php
    if ($_SESSION['user']->isAdmin()
        || $_SESSION['task_setting']->isEligibleForCAC($_SESSION['setting'])) {
        
?>

    <fieldset class="setting provided"
    onmouseover="javascript:changeQuickHelp( 'cac' );" >
    
    <legend>
        <a href="javascript:openWindow(
                       'http://www.svi.nl/ChromaticAberrationCorrector')">
                        <img src="images/help.png" alt="?" />
        </a>
        correct images for chromatic aberration?
    </legend>

    <p>Chromatic aberrations are often present in multi-channel images.
       Correcting for this is crucial for accurate image analysis.</p> 

<table style="width:100%">
<tr>
<td class="header">Ch</td>
<td class="header">Shift x<br />(&#956m)</td>
<td class="header">Shift y<br />(&#956m)</td>
<td class="header">Shift z<br />(&#956m)</td>
<td class="header">Rotation<br />(degrees)</td>
<td class="header">Scale<br />(ratio)</td>
</tr>
<?php

for ($i = 0; $i < $_SESSION['task_setting']->numberOfChannels(); $i++) {
    $value = "";
    echo "<tr>";
?>
    <td class="header"><?php echo $i; ?></td>

<?php
    for ($j = 0; $j < $_SESSION['task_setting']->parameter("ChromaticAberration")->componentCnt(); $j++) {
?>
    
<td>
                                  <input
                                    id="ChromaticAberrationCh<?php echo $i; ?>"
                                    name="ChromaticAberrationCh<?php echo $i; ?>"
                                    type="text"
                                    size="6"
                                    value="<?php echo $value; ?>"
                                    class="multichannelinput" />
</td>
<?php
    }
?>
    <tr>
<?php
}
?>

</table>


    
<?php
    } else {

    }
?>
</div> <!-- ChromaticAberrationCorrector -->


            <div><input name="OK" type="hidden" /></div>

            <div id="controls"
                 onmouseover="javascript:changeQuickHelp( 'default' )">
              <input type="button" value="" class="icon previous"
                  onmouseover="TagToTip('ttSpanBack' )"
                  onmouseout="UnTip()"
                  onclick="javascript:deleteValuesAndRedirect(
                    'microscope_parameter.php' );" />
              <input type="button" value="" class="icon up"
                  onmouseover="TagToTip('ttSpanCancel' )"
                  onmouseout="UnTip()"
                  onclick="javascript:deleteValuesAndRedirect(
                    'select_parameter_settings.php' );" />
              <input type="submit" value="" class="icon save"
                  onmouseover="TagToTip('ttSpanSave' )"
                  onmouseout="UnTip()"
                  onclick="process()" />
            </div>

    </div> <!-- content -->

<?php

include("footer.inc.php");


// Workaround for IE
if ( using_IE() && !isset( $_SERVER[ 'HTTP_REFERER' ] ) ) {
?>
        <script type="text/javascript">
            $(document).ready( retrieveValues( ) );
        </script>
<?php
}

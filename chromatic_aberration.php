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

echo print_r($_POST, true);
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

<form method="post" action="" id="select">
<table>
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
?>
    <tr>
    <td class="header"><?php echo $i; ?></td>

<?php
    $parameter = $_SESSION['task_setting']->parameter("ChromaticAberration");
    for ($j = 0; $j < $parameter->componentCnt(); $j++) {
?>
    
<td><input
        id="ChromaticAberrationCh<?php echo $i . _ . $j;?>"
        name="ChromaticAberrationCh<?php echo $i . _ . $j;?>"
        type="text"
        size="6"
        value="<?php echo $value; ?>"
        class="multichannelinput" /></td>
<?php 
    }
?>
    <tr>
<?php
}
?>

</table>

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
                                 
       </form>
    </div> <!-- content -->

    <div id="rightpanel" onmouseover="javascript:changeQuickHelp( 'default' )">

      <div id="info">
      <h3>Quick help</h3>
        <div id="contextHelp">
          <p>On this page you specify the parameters for the chromatic
             aberration correction.</p>
          <p>These parameters comprise the shifts along x, y, z, the rotations
             and the zoom factors across channels.</p>
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

// Workaround for IE
if ( using_IE() && !isset( $_SERVER[ 'HTTP_REFERER' ] ) ) {
?>
        <script type="text/javascript">
            $(document).ready( retrieveValues( ) );
        </script>
<?php
}
?>
<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc");
require_once("./inc/Parameter.inc");
require_once("./inc/Setting.inc");
require_once("./inc/Database.inc");

/* *****************************************************************************
 *
 * START SESSION, CHECK LOGIN STATE, INITIALIZE WHAT NEEDED
 *
 **************************************************************************** */

session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

/* *****************************************************************************
 *
 * STORE THE PAGE FROM WHICH WE ARE COMING
 *
 **************************************************************************** */

if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
  $pageFrom = $_SERVER['HTTP_REFERER'];  
} else {
  $paegFrom = "login.php";
}


/* *****************************************************************************
 *
 * SET THE CONFIDENCE FOR THE ONLY PARAMETER
 *
 **************************************************************************** */

$overrideConfidence = $_SESSION['setting']->parameter( "OverrideConfidence" );
$db = new DatabaseConnection();
$confidenceLevel = $db->getParameterConfidenceLevel( null, "OverrideConfidence" );  
$overrideConfidence->setConfidenceLevel( $confidenceLevel );
$_SESSION['setting']->set( $overrideConfidence );

/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */

if ( $_SESSION[ 'setting' ]->checkPostedOverrideParameter( $_POST ) ) {
  $saved = $_SESSION['setting']->save();
  $message = "            <p class=\"warning\">".$_SESSION['setting']->message()."</p>";
  if ($saved) {
    header("Location: select_parameter_settings.php" ); exit();
  }
} else {
  $message = "            <p class=\"warning\">".$_SESSION['setting']->message()."</p>";
}

/* *****************************************************************************
 *
 * CREATE THE PAGE
 *
 **************************************************************************** */

// Javascript includes
$script = array( "settings.js" );

include("header.inc.php");

?>
    <!--
      Tooltips
    -->
    <span id="ttSpanBack">Go back to previous page.</span>  
    <span id="ttSpanCancel">Abort editing and go back to the image parameters selection page. All changes will be lost!</span>  
    <span id="ttSpanSave">Save and return to the image parameters selection page.</span>  
    
    <div id="nav">
        <ul>
            <li><img src="images/user.png" alt="user" />&nbsp;<?php echo $_SESSION['user']->name(); ?></li>
            <li><a href="javascript:openWindow('http://www.svi.nl/ConfidenceLevels')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
        <h3>Import metadata from file</h3>

        <h4>Your image files may contain valid metadata.
        Would you like to use it to override current template parameters?</h4>
        
        <form method="post" action="" id="select">

    <?php

    /***************************************************************************
    
      OverrideConfidence
    
    ***************************************************************************/

      $overrideConfidence = $_SESSION['setting']->parameter( "OverrideConfidence" );

    ?>    
            <fieldset class="setting <?php echo $overrideConfidence->confidenceLevel(); ?>">
            
                <legend>
                    <a href="javascript:openWindow('http://www.svi.nl/ConfidenceLevels')"><img src="images/help.png" alt="?" /></a>
                    Override option
                </legend>

<?php

$possibleValues = $overrideConfidence->possibleValues();
foreach($possibleValues as $possibleValue) {
  $flag = "";
  if ($possibleValue == $overrideConfidence->value()) {
    $flag = "checked=\"checked\" ";
  }
  $translatedValue = $overrideConfidence->translatedValueFor( $possibleValue );

?>
                <input type="radio" name="OverrideConfidence" value="<?php echo $possibleValue ?>" <?php echo $flag ?>/><?php echo $translatedValue ?>
                
                <br />
<?php

}

?>
            <p class="message_confidence_override">&nbsp;</p>
            </fieldset>
           
            <div><input name="OK" type="hidden" /></div>
            
            <div id="controls" onmouseover="javascript:changeQuickHelp( 'default' )">
              <input type="button" value="" class="icon previous"
                  onmouseover="TagToTip('ttSpanBack' )"
                  onmouseout="UnTip()"
                  onclick="document.location.href='<?php echo $pageFrom;?>'" />
              <input type="button" value="" class="icon up"
                  onmouseover="TagToTip('ttSpanCancel' )"
                  onmouseout="UnTip()"
                  onclick="document.location.href='select_parameter_settings.php'" />
              <input type="submit" value="" class="icon save"
                  onmouseover="TagToTip('ttSpanSave' )"
                  onmouseout="UnTip()"
                  onclick="process()" />
            </div>

        </form>
        
    </div> <!-- content -->
    
    <div id="rightpanel" onmouseover="javascript:changeQuickHelp( 'default' );" >
    
        <div id="info">
          
          <h3>Quick help</h3>
            
            <div id="contextHelp">
              <p>If you know that the files you are deconvolving contain valid
              metadata, you can decide to force Huygens to use those instead
              of the values you entered here. The selection of which parameters
              to override is dictated by their confidence level.</p>
              
              <p>This mechanism can be used to run batch jobs with files having
              differences in some of their parameters. By setting a minimum
              level of confidence, the template will adapt to all files by
              selectively replacing the parameters in the template with those
              read from file.</p>
              
              <p>Please use this option with care.</p>
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

<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Fileserver.inc.php");

/* *****************************************************************************
 *
 * START SESSION, CHECK LOGIN STATE, INITIALIZE WHAT NEEDED
 *
 **************************************************************************** */

session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

// fileserver related code
if (!isset($_SESSION['fileserver'])) {
  $name = $_SESSION['user']->name();
  $_SESSION['fileserver'] = new Fileserver($name);
}

$message = "";

/* *****************************************************************************
 *
 * MANAGE THE MULTI-CHANNEL PSF FILE NAMES
 *
 **************************************************************************** */

$psfParam = $_SESSION['setting']->parameter("PSF");
$psfParam->setNumberOfChannels( $_SESSION['setting']->numberOfChannels() );
$psf = $psfParam->value();
for ($i = 0; $i < $_SESSION['setting']->numberOfChannels(); $i++) {
  $psfKey = "psf{$i}";
  if (isset($_POST[$psfKey])) {
    $psf[$i] = $_POST[$psfKey];
  } 
}
// get rid of extra values in case the number of channels is changed
$psfParam->setValue($psf);
$_SESSION['setting']->set($psfParam);

/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 * In this case, we do not need to check the confidence level of the PSF
 * Parameter (although it is set to Provide), since there is no other
 * meaningful alternative to having to provide the file names.
 * 
 **************************************************************************** */

if (count($_POST) > 0) {
  if ($psfParam->check()) {
    // Make sure to turn off the aberration correction since we use a measured PSF
    $_SESSION['setting']->parameter(
        'AberrationCorrectionNecessary' )->setValue( '0' );
    $_SESSION['setting']->parameter(
        'PerformAberrationCorrection' )->setValue( '0' );
    $saved = $_SESSION['setting']->save();
    $message = $_SESSION['setting']->message();
    if ($saved) {
      header("Location: select_parameter_settings.php" ); exit();
    }
  } else {
    $message = $psfParam->message();
  }
}

$script = "settings.js";

include("header.inc.php");

?>
    <!--
      Tooltips
    -->
    <span class="toolTip" id="ttSpanBack">
        Go back to previous page.
    </span>
    <span class="toolTip" id="ttSpanCancel">
        Abort editing and go back to the image parameters selection page.
        All changes will be lost!
    </span>
    <span class="toolTip" id="ttSpanSave">
        Save and return to the image parameters selection page.
    </span>
    
    <div id="nav">
        <ul>
            <li>
                <img src="images/user.png" alt="user" />
                &nbsp;<?php echo $_SESSION['user']->name(); ?>
            </li>
            <li>
                <a href="javascript:openWindow(
                   'http://www.svi.nl/HuygensRemoteManagerHelpSelectPSFFiles')">
                    <img src="images/help.png" alt="help" />
                    &nbsp;Help
                </a>
            </li>
        </ul>
    </div>
    
    <div id="content">
    
        <h3>Distilled PSF file selection</h3>
        
        <form method="post" action="select_psf.php" id="select">
        
            <div id="psfselection" class="provided">
<?php

for ($i = 0; $i < $_SESSION['setting']->numberOfChannels(); $i++) {
  $parameter = $_SESSION['setting']->parameter("PSF");
  $value = $parameter->value();
  $missing = False;
  $files = $_SESSION['fileserver']->allFiles();
  if ($files != null) {
    if (!in_array($value[$i], $files)) {
      $missing = True;
    }

?>
                <p>
                    <span class="title">Ch<?php echo $i ?>:</span>
                    <input name="psf<?php echo $i ?>" 
                           type="text"
                           value="<?php echo $value[$i] ?>"
                           class="
                           <?php
                            if ($missing) {
                                echo "psfmissing";
                            } else {
                                echo "psffile";
                            } ?>"
                           readonly="readonly" />
                    <input type="button" 
                           onclick="seek('<?php echo $i ?>')"
                           value="browse" />
                </p>
<?php

  }
  else {
    if (!file_exists($_SESSION['fileserver']->sourceFolder())) {

?>
                <p class="info">Source image folder not found! Make sure the
                    folder <?php echo $_SESSION['fileserver']->sourceFolder() ?>
                    exists.</p>
<?php

    }
    else {

?>
                <p class="info">No images found on the server!</p>
<?php

    }
    break;
  }
}

?>
            </div>
            
            <div><input name="OK" type="hidden" /></div>
            <p class="message_confidence_Provide">&nbsp;</p>
            
            <div id="controls">
              <input type="button" value="" class="icon previous"
                  onmouseover="TagToTip('ttSpanBack' )"
                  onmouseout="UnTip()"
                  onclick="document.location.href='capturing_parameter.php'" />
              <input type="button" value="" class="icon up"
                  onmouseover="TagToTip('ttSpanCancel' )"
                  onmouseout="UnTip()"
                  onclick="document.location.href='select_parameter_settings.php'" />
              <input type="submit" value="" class="icon save"
                  onmouseover="TagToTip('ttSpanSave' )"
                  onmouseout="UnTip()" onclick="process()" />
            </div>
                        
        </form>
        
    </div> <!-- content -->
    
    <div id="rightpanel">
    
        <div id="info">
          
          <h3>Quick help</h3>
          
          <p>Select a PSF file for each of the channels. Only
              <strong>single-channel PSF files</strong> are supported.</p>
            
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

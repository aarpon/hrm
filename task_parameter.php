<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc");
require_once("./inc/Parameter.inc");
require_once("./inc/Setting.inc");
require_once("./inc/Util.inc");

session_start();

// Check if the SNR estimator can be turned on
$estimateSNR = false;
$db = new DatabaseConnection();
$version = $db->getHuCoreVersion();
if ( $useThumbnails && $genThumbnails && $version >= 3050100 ) {
  $estimateSNR = true;
}

function contextHelp($field, $text) {
    echo " onmouseover=\"".
        "smoothChangeDivCond('$field', 'contextHelp','".escapeJavaScript($text)."', 300)\" ";
}

$helpHeader = "
        <p>On this page you specify the parameters for restoration.</p>

        <p>These parameters comprise the deconvolution algorithm, the
        signal-to-noise ratio (SNR) of the images, the mode for background
        estimation, and the stopping criteria.</p>
        ";

$helpMethod =   "
        <p>The Huygens software has different deconvolution algorithms as
        restoration methods.  HRM offers the possibility of using the two most
        important ones:</p>
       <p>The <b>CMLE algorithm</a></b> is optimally suited for low-signal
       images.  </p>
       <p>The <b>QMLE algorithm</a></b> is faster than CMLE, but it only
       works well with noise-free images (for example, good quality widefield
       images).  </p>";

if ( $estimateSNR ) {
  $snrToolDescr = "<p>The <b>Estimate SNR from image</b> tool allows you to obtain a
       best-guess initial value for this parameter.</p>";  
} else {
  $snrToolDescr = "<p><b>Warning! If you upgrade the Huygens Core to a more 
       the recent version, HRM can offer you to estimate the SNR online from
       a selected image. Please consider upgrading!</b></p>";   
}

$helpSnrCmle = "
       <p>The <b>SNR</b> is a parameter that controls the sharpness of the
       result: only with noise-free images you can safely demand very sharp
       results (high SNR values) without amplifying noise.</p> " .
       $snrToolDescr .
       "<p><span class=\"superscript\">(*)</span>
        The field <strong>SNR</strong>
        can accept multiple values per channel.
        This is a commodity tool to optimize restoration parameters. By setting
        the SNR value to '10 20 40' in a single channel image, for instance,
        three jobs will be run: the first with SNR value 10, the second with
        SNR value 20, and the third with SNR value 40.</p>";

$helpSnrQmle = "
       <p>The <b>SNR</b> is a parameter that controls the sharpness of the result:
       only in images without much noise (high SNR) you can expect very sharp
       results without amplifying noise.</p>";
 

$helpBg = "
       <p>The <b>background</b> is any additive and approximately constant
       signal in your image that is not coming from the objects you are
       interested in, like an electronic offset in your detector, or indirect
       light. It will be removed during the restoration, to increase
       contrast.</p>";

$helpStopCrit = "
        <p>The first <b>stopping criterium</b> reached will stop the
        restoration.</p><p>The <i>quality change</i> criterium
        may apply first and stop the iterations before the <i>maximum 
        number</i> is reached: set the quality change to a low value or zero if
        you want to make sure all the set iterations are run.)</p>
        <p><span class=\"superscript\">(*)</span>
        The field <strong>number of iterations</strong> can accept multiple
        values. This is a commodity tool to optimize restoration parameters. By
        setting this field value to
        '20 50 70', for instance, three jobs will be run: the first with a 
        maximum of 20 iterations, the second with a maximum of 50, and the
        third with a maximum of 70. </p>
        <p>Notice that the <b>quality change</b> criterium
        may apply first and stop the iterations earlier in all cases.</p>";



$helpCmle = $helpHeader .
       "
       <p>The <b><a
       href=\"javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=MaximumLikelihoodEstimation')\">CMLE
       algorithm</a></b> is optimally suited for low-signal images.  </p>

       <p>The
       <b>SNR</b> is a parameter that controls the sharpness of the
       result.

       <p>The <b>background</b> is the intensity to be removed during the
       restoration, to increase contrast.

        <p>The first <b>stopping criterium</b> reached will stop the
        restoration.</p>

        <p><span class=\"superscript\">(*)</span>
        The fields <strong>SNR</strong> and <strong>number of
        iterations</strong> can accept multiple values, separated by spaces.
        </p>
        ";



$helpQmle = $helpHeader .
       "<p>The <b><a
       href=\"javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=QuickMaximumLikelihoodEstimation')\">QMLE
       algorithm</a></b> is faster than CMLE, but it only works well with
       noise-free images.  </p>

       <p>The
       <b>SNR</b> is a parameter that controls the sharpness of the
       result.

       <p>The <b>background</b> is the intensity to be removed during the
       restoration, to increase contrast.

       <p>The iterations stop automatically.</p>
        ";



if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

if (!isset($_SESSION['task_setting'])) {
  # session_register("task_setting"); 
  $_SESSION['task_setting'] = new TaskSetting();
}
if ($_SESSION['user']->name() == "admin") $_SESSION['task_setting']->setNumberOfChannels(5);
else $_SESSION['task_setting']->setNumberOfChannels($_SESSION['setting']->numberOfChannels());

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

$parameter = $_SESSION['task_setting']->parameter("FullRestoration");

// TODO refactor code to consider only full restorations
if ($parameter->value() == "False") {
  
  $parameter->setValue("True");
  $_SESSION['task_setting']->set($parameter);
  
  $parameter = $_SESSION['task_setting']->parameter("RemoveBackground");
  $parameter->setValue("False");
  $_SESSION['task_setting']->set($parameter);
  
}
else {

  // TODO refactor code to never consider remove noise
  $parameter = $_SESSION['task_setting']->parameter("RemoveNoise");
  
  if (isset($_POST['RemoveNoise'])) {
    $parameter->setValue("True");
  }
  else {
    $parameter->setValue("False");
  }
  $_SESSION['task_setting']->set($parameter);
  
  $names = $_SESSION['task_setting']->parameterNames();
  foreach ($names as $name) {
    $parameter = $_SESSION['task_setting']->parameter($name);
    if ($name != "NumberOfIterations" && isset($_POST[$name])) {
      $parameter->setValue($_POST[$name]);
      $_SESSION['task_setting']->set($parameter);
    }
    /*else {
      $value = $parameter->value();
      if ($parameter->isBoolean() && isset($_POST['OK'])) {
        $parameter->setValue("False");
        $_SESSION['task_setting']->set($parameter);
      }
    }*/
  }

  if (isset($_POST["DeconvolutionAlgorithm"]))
      $algorithm = strtoupper($_POST["DeconvolutionAlgorithm"]);
  else {
      $algorithmValue = $_SESSION['task_setting']->parameter("DeconvolutionAlgorithm")->value();
      if ($algorithmValue != null)
        $algorithm = strtoupper($algorithmValue);
      else
        $algorithm = "CMLE";
  }
  
  $backgroundOffsetPercentParam =  $_SESSION['task_setting']->parameter("BackgroundOffsetPercent");
  $backgroundOffset = $backgroundOffsetPercentParam->internalValue();
  for ($i = 0; $i < $_SESSION['task_setting']->numberOfChannels(); $i++) {
    $signalNoiseRatioKey = "SignalNoiseRatio".$algorithm.$i;
    $backgroundOffsetKey = "BackgroundOffsetPercent".$i;
    if (isset($_POST[$signalNoiseRatioKey])) {
      // enable ranges for the signal to noise ratio
      $value = $_POST[$signalNoiseRatioKey];
      $val = explode(" ", $value);
      if (count($val) > 1) {
        $values = array(NULL, NULL, NULL, NULL);
        for ($j = 0; $j < count($val); $j++) {
          $values[$j] = $val[$j];
        }
        $signalNoiseRatioRange[$i] = $values;
      }
      else {
        $signalNoiseRatio[$i] = $_POST[$signalNoiseRatioKey];
        //echo $signalNoiseRatio[$i]."<br />";
      }
    }
    if (isset($_POST[$backgroundOffsetKey])) {
      $backgroundOffset[$i] = $_POST[$backgroundOffsetKey];
    } 
  }
  $parameter = $_SESSION["task_setting"]->parameter("SignalNoiseRatioUseRange");
  
  if (isset($signalNoiseRatioRange) && count($signalNoiseRatioRange) > 0) {
    // << ECHO
    /*for ($i = 0; $i < count($signalNoiseRatioRange); $i++) {
      $range = $signalNoiseRatioRange[$i];
      for ($j = 0; $j < count($range); $j++) {
        $val = $range[$j];
        if ($val == NULL) $val = "NULL";
        echo "signalNoiseRatioRange, channel ".$i." value ".$j." = ".$val."<br>";
      }
    }*/
    $parameter->setValue("True");
    $signalNoiseRatioRangeParam = $_SESSION['task_setting']->parameter("SignalNoiseRatioRange");
    for ($i = 0; $i < $_SESSION['task_setting']->numberOfChannels(); $i++) {
      if ($signalNoiseRatioRange[$i] == NULL) {
        $signalNoiseRatioRange[$i] = array($signalNoiseRatio[$i], NULL, NULL, NULL);
        //echo "value " . $signalNoiseRatio[$i] . " added in array for channel " . $i;
      }
    }
    $signalNoiseRatioRangeParam->setValue($signalNoiseRatioRange);
    $_SESSION['task_setting']->set($signalNoiseRatioRangeParam);
  }
  else if (count($_POST) > 0) {
    $parameter->setValue("False");
    $signalNoiseRatioParam = $_SESSION['task_setting']->parameter("SignalNoiseRatio");
    $signalNoiseRatioParam->setValue($signalNoiseRatio);
    $_SESSION['task_setting']->set($signalNoiseRatioParam);
  }
  $_SESSION["task_setting"]->set($parameter);
  $backgroundOffsetPercentParam->setValue($backgroundOffset);
  $_SESSION['task_setting']->set($backgroundOffsetPercentParam);
  
  if (isset($_POST['BackgroundEstimationMode']) && $_POST['BackgroundEstimationMode'] == "auto") {
    $parameter = $_SESSION['task_setting']->parameter("BackgroundOffsetPercent");
    $parameter->setValue("auto");
    $_SESSION['task_setting']->set($parameter);
  }
  else if (isset($_POST['BackgroundEstimationMode']) && $_POST['BackgroundEstimationMode'] == "object") {
    $parameter = $_SESSION['task_setting']->parameter("BackgroundOffsetPercent");
    $parameter->setValue("object");
    $_SESSION['task_setting']->set($parameter);
  }
  
  /*
  $signalNoiseRatioRangeParam = $_SESSION['task_setting']->parameter("SignalNoiseRatioRange");
  $backgroundOffsetRangeParam = $_SESSION['task_setting']->parameter("BackgroundOffsetRange");
  $numberOfIterationsRangeParam = $_SESSION['task_setting']->parameter("NumberOfIterationsRange");
  $signalNoiseRatioRange = $signalNoiseRatioRangeParam->value();
  $backgroundOffsetRange = $backgroundOffsetRangeParam->value();
  $numberOfIterationsRange = $numberOfIterationsRangeParam->value();
  for ($i=0; $i < 4; $i++) {
    $signalNoiseRatioRangeKey = "SignalNoiseRatioRange{$i}";
    if (isset($_POST[$signalNoiseRatioRangeKey])) {
      $signalNoiseRatioRange[$i] = $_POST[$signalNoiseRatioRangeKey];
    }
    $backgroundOffsetRangeKey = "BackgroundOffsetRange{$i}";
    if (isset($_POST[$backgroundOffsetRangeKey])) {
      $backgroundOffsetRange[$i] = $_POST[$backgroundOffsetRangeKey];
    } 
    $numberOfIterationsRangeKey = "NumberOfIterationsRange{$i}";
    if (isset($_POST[$numberOfIterationsRangeKey])) {
      $numberOfIterationsRange[$i] = $_POST[$numberOfIterationsRangeKey];
    } 
  }
  $signalNoiseRatioRangeParam->setValue($signalNoiseRatioRange);
  $backgroundOffsetRangeParam->setValue($backgroundOffsetRange);
  $numberOfIterationsRangeParam->setValue($numberOfIterationsRange);
  $_SESSION['task_setting']->set($signalNoiseRatioRangeParam);
  $_SESSION['task_setting']->set($backgroundOffsetRangeParam);
  $_SESSION['task_setting']->set($numberOfIterationsRangeParam);
  */
  // number of iterations: set the use of range to false if checkbox is unchecked
  /*$parameter = $_SESSION["task_setting"]->parameter("NumberOfIterationsUseRange");
  if (isset($_POST["OK"]) && !isset($_POST["NumberOfIterationsUseRange"])) {
        $parameter = $_SESSION["task_setting"]->parameter("NumberOfIterationsUseRange");
        $parameter->setValue("False");
        $_SESSION["task_setting"]->set($parameter);
  }*/
  // enable ranges for the number of iterations
  if (isset($_POST["NumberOfIterations"])) {
    $value = $_POST["NumberOfIterations"];
    $values = explode(" ", $value);
    if (count($values) > 1) {
      $parameter = $_SESSION["task_setting"]->parameter("NumberOfIterationsUseRange");
      $parameter->setValue("True");
      $_SESSION["task_setting"]->set($parameter);
      $numberOfIterationsRangeParam = $_SESSION['task_setting']->parameter("NumberOfIterationsRange");
      $numberOfIterationsRange = $numberOfIterationsRangeParam->value();
      $numberOfIterationsRange = array(NULL, NULL, NULL, NULL);
      for ($i = 0; $i < count($values); $i++) {
        $numberOfIterationsRange[$i] = $values[$i];
      }
      $numberOfIterationsRangeParam->setValue($numberOfIterationsRange);
      $_SESSION['task_setting']->set($numberOfIterationsRangeParam);
    }
    else {
      $parameter = $_SESSION["task_setting"]->parameter("NumberOfIterationsUseRange");
      $parameter->setValue("False");
      $_SESSION["task_setting"]->set($parameter);
      $parameter = $_SESSION['task_setting']->parameter("NumberOfIterations");
      $parameter->setValue($value);
      $_SESSION['task_setting']->set($parameter);
    }
  }
  
  if (isset($_POST['QualityChangeStoppingCriterion'])) {
    $parameter = $_SESSION['task_setting']->parameter("QualityChangeStoppingCriterion");
    $parameter->setValue($_POST['QualityChangeStoppingCriterion']);
    $_SESSION['task_setting']->set($parameter);
  }
  
  if (isset($_POST['DeconvolutionAlgorithm'])) {
    $parameter = $_SESSION['task_setting']->parameter("DeconvolutionAlgorithm");
    $parameter->setValue($_POST['DeconvolutionAlgorithm']);
    $_SESSION['task_setting']->set($parameter);
  }
  
  if (count($_POST) > 0) {
    $ok = $_SESSION['task_setting']->checkParameter();
    $message = "            <p class=\"warning\">".$_SESSION['task_setting']->message()."</p>\n";
    if ($ok) {
      $saved = $_SESSION['task_setting']->save();			
      $message = "            <p class=\"warning\">".$_SESSION['task_setting']->message()."</p>\n";
      if ($saved) {
        header("Location: " . "select_task_settings.php"); exit();
      }
    }	 
  }

}

$noRange = False;

$script = "settings.js";

include("header.inc.php");

?>
    <!--
      Tooltips
    -->
    <span id="ttSpanCancel">Abort editing and go back to the Restoration parameters selection page. All changes will be lost!</span>  
    <span id="ttSpanForward">Save your settings.</span>
    <?php if ($estimateSNR) { ?>
    <span id="ttEstimateSnr">Use a sample raw image to find a SNR estimate for each channel.</span>
    <?php } ?>
    
    <div id="nav">
        <ul>
            <li><?php echo $_SESSION['user']->name(); ?></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpRestorationParameters')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
        <h3>Task Setting</h3>
        
        <form method="post" action="" id="select">
          
           <h4>How should your images be restored?</h4>
           
             <fieldset class="setting" <?php contextHelp("method", $helpMethod); ?>>  <!-- deconvolution algorithm -->
            
                <legend>
                    <a href="javascript:openWindow('http://support.svi.nl/wiki/RestorationMethod')"><img src="images/help.png" alt="?" /></a>
                    deconvolution algorithm
                </legend>

<?php

$onChange = "onChange=\"javascript:switchSnrMode()\"";

?>
                <select name="DeconvolutionAlgorithm" <?php echo $onChange ?>>
                
<?php

$parameter = $_SESSION['task_setting']->parameter("DeconvolutionAlgorithm");
$possibleValues = $parameter->possibleValues();
$selectedMode  = $parameter->value();

// This restores the default behavior in case the entry "DeconvolutionAlgorithm"
// is not in the database
if ( empty( $possibleValues ) == true )
{
  $possibleValues[0] = "cmle";
  $parameter = $_SESSION['task_setting']->parameter("DeconvolutionAlgorithm");
  $parameter->setValue( "cmle" );
  $_SESSION['task_setting']->set($parameter);
}
  
foreach($possibleValues as $possibleValue) {
  $translation = $_SESSION['task_setting']->translation("DeconvolutionAlgorithm", $possibleValue);
  // This restores the default behavior in case the entry "DeconvolutionAlgorithm"
  // is not in the database
  if ( $translation == false )
    $translation = "cmle";

  if ( $possibleValue == $selectedMode ) {
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
        
            <fieldset class="setting">  <!-- signal/noise ratio -->
            
                <legend>
                    <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=SignalToNoiseRatio')"><img src="images/help.png" alt="?" /></a>
                    signal/noise ratio
                </legend>

<?php

$parameter = $_SESSION["task_setting"]->parameter("SignalNoiseRatioUseRange");
if ($parameter->isTrue()) {
  $signalNoiseRatioRangeParam = $_SESSION['task_setting']->parameter("SignalNoiseRatioRange");
  $signalNoiseRatioRange = $signalNoiseRatioRangeParam->value();
}
else {
  $signalNoiseRatioParam = $_SESSION['task_setting']->parameter("SignalNoiseRatio");
  $signalNoiseRatioValue = $signalNoiseRatioParam->value();
}

?>
                <div id="snr">
                      
<?php





$visibility = " style=\"display: none\"";
if ($selectedMode == "cmle") {
  $visibility = " style=\"display: block\"";
  $info = $helpCmle;
}

?>
                    <div id="cmle-snr" <?php contextHelp("snrcmle", $helpSnrCmle); ?> class="multichannel"<?php echo $visibility?>>
                    <ul>
                      <li>SNR<span class="superscript">(*)</span>: 
                      <div class="multichannel">
<?php

for ($i = 0; $i < $_SESSION['task_setting']->numberOfChannels(); $i++) {
  
  if ($parameter->isTrue()) {
    $signalNoiseRatioValues = $signalNoiseRatioRange[$i];
    $value = $signalNoiseRatioValues[0];
    for ($j = 1; $j < count($signalNoiseRatioValues); $j++){
      if ($signalNoiseRatioValues[$j])
        $value .= " " . $signalNoiseRatioValues[$j];
    }
  }
  else {
    $value = "";
    if ($selectedMode == "cmle")
        $value = $signalNoiseRatioValue[$i];
  }

?>
                          <span class="nowrap">Ch<?php echo $i ?>:<span class="multichannel"><input name="SignalNoiseRatioCMLE<?php echo $i ?>" type="text" size="8" value="<?php echo $value ?>" class="multichannelinput" /></span>&nbsp;</span>
<?php

}

?>
                          </div>
                        </li>
                      </ul>

                    <?php
                    if ($estimateSNR) {
                        echo "<a href=\"estimate_snr_from_image.php\"
                          onmouseover=\"TagToTip('ttEstimateSnr' )\"
                          onmouseout=\"UnTip()\"
                        ><img src=\"images/calc_small.png\" alt=\"\" />";
                        echo " Estimate SNR from image</a>";
                    }

                    ?>
                    </div>
<?php

$visibility = " style=\"display: none\"";
if ($selectedMode == "qmle") {
  $visibility = " style=\"display: block\"";
  $info = $helpQmle;
}

?>
                    <div id="qmle-snr" <?php contextHelp("snrqmle", $helpSnrQmle); ?> class="multichannel"<?php echo $visibility?>>
                      <ul>
                        <li>SNR:
                        <div class="multichannel">
<?php

for ($i = 0; $i < $_SESSION['task_setting']->numberOfChannels(); $i++) {

?>
                        <span class="nowrap">Ch<?php echo $i ?>:
                            <select class="snrselect" name="SignalNoiseRatioQMLE<?php echo $i ?>">
<?php

  for ($j = 1; $j <= 4; $j++) {
      $option = "                                <option ";
      if (isset($signalNoiseRatioValue)) {
          if ($signalNoiseRatioValue[$i] >= 1 && $signalNoiseRatioValue[$i] <= 4) {
            if ($j == $signalNoiseRatioValue[$i])
                $option .= "selected=\"selected\" ";
          }
          else {
              if ($j == 2)
                $option .= "selected=\"selected\" ";
          }
      }
      else {
          if ($j == 2)
            $option .= "selected=\"selected\" ";
      }
      $option .= "value=\"".$j."\">";
      if ($j == 1)
        $option .= "low</option>";
      else if ($j == 2)
        $option .= "fair</option>";
      else if ($j == 3)
        $option .= "good</option>";
      else if ($j == 4)
        $option .= "inf</option>";
      echo $option;
  }

?>
                            </select>
                        </span><br />
<?php

}

?>
                          </div>
                        </li>
                      </ul>
                    </div>
                    
                </div>
                
            </fieldset>
            
            <fieldset class="setting" <?php contextHelp("bg", $helpBg); ?>>  <!-- background mode -->
            
                <legend>
                    <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=BackgroundMode')"><img src="images/help.png" alt="?" /></a>
                    background mode
                </legend>
                
                <div id="background">
                
<?php

$backgroundOffsetPercentParam =  $_SESSION['task_setting']->parameter("BackgroundOffsetPercent");
$backgroundOffset = $backgroundOffsetPercentParam->internalValue();

$flag = "";
if ($backgroundOffset[0] == "" || $backgroundOffset[0] == "auto") $flag = " checked=\"checked\"";

?>

                    <p><input type="radio" name="BackgroundEstimationMode" value="auto"<?php echo $flag ?> />automatic background estimation</p>
                    
<?php

$flag = "";
if ($backgroundOffset[0] == "object") $flag = " checked=\"checked\"";

?>

                    <p><input type="radio" name="BackgroundEstimationMode" value="object"<?php echo $flag ?> />in/near object</p>
                    
<?php

$flag = "";
if ($backgroundOffset[0] != "" && $backgroundOffset[0] != "auto" && $backgroundOffset[0] != "object") $flag = " checked=\"checked\"";

?>
                    <input type="radio" name="BackgroundEstimationMode" value="manual"<?php echo $flag ?> />
                    remove constant absolute value:
                    
                    <div class="multichannel">
<?php

for ($i=0; $i < $_SESSION['task_setting']->numberOfChannels(); $i++) {
  $val = "";
  if ($backgroundOffset[0] != "auto" && $backgroundOffset[0] != "object") $val = $backgroundOffset[$i];

?>
                        <span class="nowrap">Ch<?php echo $i ?>:<span class="multichannel"><input name="BackgroundOffsetPercent<?php echo $i ?>" type="text" size="8" value="<?php echo $val ?>" class="multichannelinput" /></span>&nbsp;</span>
                        
<?php

}

$visibility = " style=\"display: none\"";
if ($selectedMode == "cmle") {
  $visibility = " style=\"display: block\"";
  $info = $helpCmle;
}

?>
                    </div>
                    
                </div>
                
            </fieldset>

            <div id="cmle-it" <?php echo $visibility ?>>

            <fieldset class="setting" <?php contextHelp("stop", $helpStopCrit);?>>  <!-- stopping criteria -->
            
                <legend>
                    stopping criteria
                </legend>
                
                <div id="criteria">
                <p>
                
                    <p><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=MaxNumOfIterations')"><img src="images/help.png" alt="?" /></a>
                    number of iterations<span class="superscript">(*)</span>:
                    
<?php

$parameter = $_SESSION['task_setting']->parameter("NumberOfIterations");
$value = 40;
if ($parameter->value() != NULL) {
  $value = $parameter->value();
}

$parameter = $_SESSION["task_setting"]->parameter("NumberOfIterationsUseRange");
if ($parameter->isTrue()) {
  $numberOfIterationsRangeParam = $_SESSION['task_setting']->parameter("NumberOfIterationsRange");
  $numberOfIterationsRange = $numberOfIterationsRangeParam->value();
  $value = $numberOfIterationsRange[0];
  for ($i = 1; $i < 4; $i++){
    if ($numberOfIterationsRange[$i] != NULL)
      $value .= " " . $numberOfIterationsRange[$i];
  }
}


?>
                    <input name="NumberOfIterations" type="text" size="8" value="<?php echo $value ?>" />
                    
                    </p><p>
                    
                    <p><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=QualityCriterion')"><img src="images/help.png" alt="?" /></a>
                    quality change:
                    
<?php

$parameter = $_SESSION['task_setting']->parameter("QualityChangeStoppingCriterion");
$value = 0.1;
if ($parameter->value() != null) {
  $value = $parameter->value();
}

?>
                    <input name="QualityChangeStoppingCriterion" type="text" size="3" value="<?php echo $value ?>" />
                    </p>
                    
                    </p>
                    
                </div>
                
            </fieldset>
            </div>
            
            <div><input name="OK" type="hidden" /></div>
            
            <div id="controls"  onmouseover="showRestorationHelp()">
              <input type="button" value="" class="icon up"
                  onmouseover="TagToTip('ttSpanCancel' )"
                  onmouseout="UnTip()"
                  onclick="document.location.href='select_task_settings.php'" />
              <input type="submit" value="" class="icon save"
                  onmouseover="TagToTip('ttSpanForward' )"
                  onmouseout="UnTip()"
                  onclick="process()" />
            </div>

        </form>
        
    </div> <!-- content -->

    <script type="text/javascript">
        <!--
            window.helpCmle='<?php echo escapeJavaScript($helpCmle); ?>';
            window.helpQmle='<?php echo escapeJavaScript($helpQmle); ?>';
            window.restorationMode='<?php echo escapeJavaScript($selectedMode); ?>';
            window.divCondition = 'general';
        -->
    </script>
 
    
    <div id="rightpanel" onmouseover="showRestorationHelp()">
    
      <div id="info">
      <h3>Quick help</h3>
      <div id="contextHelp">
        <?php echo $info; ?>
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

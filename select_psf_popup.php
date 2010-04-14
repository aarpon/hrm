<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc");
require_once("./inc/Fileserver.inc");

session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

// fileserver related code
if (!isset($_SESSION['fileserver'])) {
  # session_register("fileserver");
  $name = $_SESSION['user']->name();
  $_SESSION['fileserver'] = new Fileserver($name);
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
$mTypeSetting = $_SESSION['setting']->parameter("MicroscopeType")->translatedValue();
$twoPhoton = $_SESSION['setting']->isTwoPhoton();
if ( $twoPhoton ) {
    $mTypeSetting = "multiphoton";
}
$NAsetting = $_SESSION['setting']->parameter("NumericalAperture")->value();
$emSettingArr = $_SESSION['setting']->parameter("EmissionWavelength")->value();
$chan = $_GET["channel"];
$emSetting = $emSettingArr[$chan];

?>

<!DOCTYPE html 
    PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" 
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">

<head>
    <title>Huygens Remote Manager</title>
    <script type="text/javascript">
    <!--
      function lock(l) {
            var psf = l.options[l.options.selectedIndex].value;
            window.opener.document.forms["select"].elements["<?php echo "psf".$_GET["channel"] ?>"].value = psf;
            window.opener.document.forms["select"].elements["<?php echo "psf".$_GET["channel"] ?>"].style.color = "black";
      }
    //-->
    </script>
    <style type="text/css">
        @import "stylesheets/default.css";
    </style>
</head>

<body>

<div>

  <form method="get" action="">

    <div id="box">
    
      <fieldset>
      
        <legend>available PSF files</legend>
<?php        
$icsFiles = $_SESSION['fileserver']->files("ics");
$icsData = $_SESSION['fileserver']->getMetaData("ics");
$hdfFiles = $_SESSION['fileserver']->files("h5");
$hdfData = $_SESSION['fileserver']->getMetaData("h5");

$files = array_merge( $hdfFiles, $icsFiles);
$data = array_merge( $hdfData, $icsData);
sort($files);

?>
        <div id="userfiles">
          <select name="userfiles[]" size="10" onchange="lock(this)">
<?php

$showWarning = false;

foreach ($files as $file) {
  $mType =  $data[$file]['mType'][0];
  $nChan = $data[$file]['dimensions'][4];
  if ( $nChan == 0 ) {
      $nChan = 1;
  }
  $NA = $data[$file]['NA'][0];
  $pCnt = $data[$file]['photonCnt'][0];
  if ($pCnt > 1) { 
      $mType = "multiphoton";
  }
  $ex = $data[$file]['lambdaEx'][0];
  $em = $data[$file]['lambdaEm'][0];

  $style = "";
  $mismatch = false;
  if ($mType != $mTypeSetting ) {
      $mismatch = true;
  }
  if (abs($NA - $NAsetting) / $NA > .02 ) {
      $mismatch = true;
  }
  if (abs($em - $emSetting) / $emSetting > .05 ) {
      $mismatch = true;
  }
  if ($mismatch ) {
      $showWarning = true;
      $style = "class=\"info\" ";
  }

  print "            <option value=\"$file\" $style>$file ".
        "($mType, NA = $NA, em = $em nm, $nChan chan) </option>\n";
}

?>
          </select>
        </div>
        
      </fieldset>
        
      <div>
        <input name="channel" type="hidden" value="<?php echo $_GET["channel"] ?>" />
        <input name="update" type="submit" value="" class="icon update" />
      </div>
      
      <div>
        <input type="button" value="close" onclick="window.close()" />
      </div>
      
    </div> <!-- box -->
    
    <div id="message">
<?php

  # print $message;

  if ( $showWarning ) {
        print "<p class=\"message\">&nbsp;<br />".
        "Files with parameters very different than those ".
        "in the current setting ".
        "($mTypeSetting, NA=$NAsetting, emission = $emSetting nm) ".
        "are <i class=\"info\">highligthed</i> and could produce ".
        "unexpected results.</p>";
  }


?>
    </div>
    
  </form>
    
</div>
  
</body>

</html>

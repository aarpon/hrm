<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Fileserver.inc.php");

session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

// fileserver related code
if (!isset($_SESSION['fileserver'])) {
  $name = $_SESSION['user']->name();
  $_SESSION['fileserver'] = new Fileserver($name);
}


$mTypeSetting = $_SESSION['setting']->parameter(
    "MicroscopeType")->translatedValue();
$twoPhoton = $_SESSION['setting']->isTwoPhoton();
if ( $twoPhoton ) {
    $mTypeSetting = "multiphoton";
}
$NAsetting = $_SESSION['setting']->parameter(
    "NumericalAperture")->value();
$emSettingArr = $_SESSION['setting']->parameter(
    "EmissionWavelength")->value();
$chan = $_GET["channel"];
$emSetting = $emSettingArr[$chan];

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
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
        @import "css/default.css";
    </style>
</head>

<body>

<div>

  <form method="get" action="">

    <div id="box">

      <fieldset>

        <legend>available PSF files</legend>
<?php
$files = $_SESSION['fileserver']->getPSFiles();
$data = $_SESSION['fileserver']->getMetaDataFromFiles($files);

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

  if (!isset($NA) || $NA == '') {
      $mismatch = true;
  } elseif (abs($NA - $NAsetting) / $NA > .02 ) {
      $mismatch = true;
  }

  if (!isset($emSetting) || $emSetting == '') {
      $mismatch = true;
  } elseif (abs($em - $emSetting) / $emSetting > .05 ) {
      $mismatch = true;
  }

  if ($mismatch) {
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
        <input name="channel"
               type="hidden"
               value="<?php echo $_GET["channel"] ?>" />
        <input name="update"
               type="submit"
               value=""
               class="icon update" />
      </div>

      <div>
        <input type="button" value="close" onclick="window.close()" />
      </div>

    </div> <!-- box -->

    <div id="message">
<?php

  # echo "<p>$message</p>";

  if ( $showWarning ) {
        print "<p>&nbsp;<br />".
        "Files with parameters very different than those ".
        "in the current setting ".
        "($mTypeSetting, NA=$NAsetting, emission = $emSetting nm) ".
        "are <i class=\"info\">highligthed</i> and could produce ".
        "unexpected results.</p>";
  }

// The PSF popup sets the fileserver to HDF5 and ICS in order to be able to read
// metadata. Reset it to ALL files to avoid problems with the image selector.
$_SESSION['fileserver']->resetFiles();
$files = $_SESSION['fileserver']->files();

?>
    </div>

  </form>

</div>

</body>

</html>

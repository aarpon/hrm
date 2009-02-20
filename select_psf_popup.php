<?php

// php page: select_psf_popup.php

// This file is part of huygens remote manager.

// Copyright: Montpellier RIO Imaging (CNRS)

// contributors :
// 	     Pierre Travo	(concept)
// 	     Volker Baecker	(concept, implementation)

// email:
// 	pierre.travo@crbm.cnrs.fr
// 	volker.baecker@crbm.cnrs.fr

// Web:     www.mri.cnrs.fr

// huygens remote manager is a software that has been developed at 
// Montpellier Rio Imaging (mri) in 2004 by Pierre Travo and Volker 
// Baecker. It allows running image restoration jobs that are processed 
// by 'Huygens professional' from SVI. Users can create and manage parameter 
// settings, apply them to multiple images and start image processing 
// jobs from a web interface. A queue manager component is responsible for 
// the creation and the distribution of the jobs and for informing the user 
// when jobs finished.

// This software is governed by the CeCILL license under French law and 
// abiding by the rules of distribution of free software. You can use, 
// modify and/ or redistribute the software under the terms of the CeCILL 
// license as circulated by CEA, CNRS and INRIA at the following URL 
// "http://www.cecill.info".

// As a counterpart to the access to the source code and  rights to copy, 
// modify and redistribute granted by the license, users are provided only 
// with a limited warranty and the software's author, the holder of the 
// economic rights, and the successive licensors  have only limited 
// liability.

// In this respect, the user's attention is drawn to the risks associated 
// with loading, using, modifying and/or developing or reproducing the 
// software by the user in light of its specific status of free software, 
// that may mean that it is complicated to manipulate, and that also 
// therefore means that it is reserved for developers and experienced 
// professionals having in-depth IT knowledge. Users are therefore encouraged 
// to load and test the software's suitability as regards their requirements 
// in conditions enabling the security of their systems and/or data to be 
// ensured and, more generally, to use and operate it in the same conditions 
// as regards security.

// The fact that you are presently reading this means that you have had 
// knowledge of the CeCILL license and that you accept its terms.

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
$files = $_SESSION['fileserver']->files("ics");
$data = $_SESSION['fileserver']->getMetaData("ics");
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

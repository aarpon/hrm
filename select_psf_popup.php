<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt


use hrm\Fileserver;

require_once dirname(__FILE__) . '/inc/bootstrap.php';

session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit();
}

// fileserver related code
if (!isset($_SESSION['fileserver'])) {
    $name = $_SESSION['user']->name();
    $_SESSION['fileserver'] = new Fileserver($name);
}


$mTypeSetting = $_SESSION['setting']->parameter(
    "MicroscopeType")->translatedValue();
$twoPhoton = $_SESSION['setting']->isTwoPhoton();
if ($twoPhoton) {
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
            window.opener.document.forms["select"].elements["<?php echo "psf" . $_GET["channel"] ?>"].value = psf;
            window.opener.document.forms["select"].elements["<?php echo "psf" . $_GET["channel"] ?>"].style.color = "black";
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

            <fieldset>

                <legend>Available PSF files</legend>
                <?php
                $files = $_SESSION['fileserver']->getPSFiles();
                $data = $_SESSION['fileserver']->getMetaDataFromFiles($files);

                ?>

                <div id="userfiles">
                    <select name="userfiles[]"
                            title="Available PSF files"
                            class="selection"
                            size="10"
                            onchange="lock(this)">
                        <?php

                        $showWarning = false;

                        foreach ($files as $file) {
                            $mType = $data[$file]['mType'][0];
                            $nChan = $data[$file]['dimensions'][4];
                            if ($nChan == 0) {
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
                            if ($mType != $mTypeSetting) {
                                $mismatch = true;
                            }

                            if (!isset($NA) || $NA == '') {
                                $NA = "unknown";
                                $mismatch = true;
                            } elseif (abs($NA - $NAsetting) / $NA > .02) {
                                $mismatch = true;
                            }

                            if (!isset($emSetting) || $emSetting == '') {
                                $mismatch = true;
                            } elseif (abs($em - $emSetting) / $emSetting > .05) {
                                $mismatch = true;
                            }

                            if ($mismatch) {
                                $showWarning = true;
                                $style = "class=\"highlightedPSF\" ";
                            }

                            print "            <option value=\"$file\" $style>$file " .
                                "($mType, NA = $NA, em = $em nm, $nChan chan) </option>\n";
                        }

                        ?>
                    </select>
                </div>

            </fieldset>

            <div>
                <input name="channel"
                       type="hidden"
                       value="<?php echo $_GET["channel"] ?>"/>
                <input name="update"
                       type="submit"
                       value=""
                       class="icon update"/>
            </div>

            <div>
                <input type="button" value="close" onclick="window.close()"/>
            </div>

        <div>
            <?php

            if ($showWarning) {

                if (!isset($mTypeSetting) || $mTypeSetting == '') {
                    $mTypeDisplay = "type: unspecified";
                } else {
                    $mTypeDisplay = "type: $mTypeSetting";
                }

                if (!isset($NAsetting) || $NAsetting == '') {
                    $NADisplay = "NA = unspecified";
                } else {
                    $NADisplay = "NA = $NAsetting";
                }
                if (!isset($emSetting) || $emSetting == '') {
                    $emSettingDisplay = "Emission wavelength: unspecified";
                } else {
                    $emSettingDisplay = "Emission wavelength: $emSetting nm";
                }

                echo("<p class=\"message_small\">&nbsp;<br />" .
                    "Files with parameters very different than current ones (<b>" .
                    "$mTypeDisplay, $NADisplay, $emSettingDisplay" .
                    "</b>) are <i class=\"highlightedPSF\">highligthed</i>
                     since they could produce wrong or unexpected results.</p>");
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

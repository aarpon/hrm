<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Nav;
use hrm\param\Binning;
use hrm\param\CCDCaptorSize;
use hrm\param\CCDCaptorSizeX;

require_once dirname(__FILE__) . '/inc/bootstrap.php';

/* *****************************************************************************
 *
 * START SESSION, CHECK LOGIN STATE, INITIALIZE WHAT NEEDED
 *
 **************************************************************************** */

session_start();

if (!isset ($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit ();
}

$message = "";

// If the $_SESSION['CCDCaptorSizeX_Calculated'] flag is set we unset it, to
// make sure that it is there only when 'storing' and going back to
// 'parameter_'capturing_parameter.php'
if (isset($_SESSION['CCDCaptorSizeX_Calculated'])) {
    unset($_SESSION['CCDCaptorSizeX_Calculated']);
}

/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */
if ($_SESSION['setting']->checkPostedCalculatePixelSizeParameters($_POST)) {

    // Calculate and set the pixel size
    $ccd = floatval($_SESSION['setting']->parameter(
        "CCDCaptorSize")->value());
    $bin = floatval($_SESSION['setting']->parameter(
        "Binning")->value());
    $obm = floatval($_SESSION['setting']->parameter(
        "ObjectiveMagnification")->value());
    $cmf = floatval($_SESSION['setting']->parameter(
        "CMount")->value());
    $tbf = floatval($_SESSION['setting']->parameter(
        "TubeFactor")->value());
    $pixelSize = ($ccd * $bin) / ($obm * $cmf * $tbf);

    // Try
    $parameter = new CCDCaptorSizeX();
    $parameter->setValue($pixelSize);
    if ($parameter->check()) {
        /** @var CCDCaptorSizeX $parameter */
        $parameter = $_SESSION['setting']->parameter('CCDCaptorSizeX');
        $parameter->setValue($pixelSize);
        $_SESSION['setting']->set($parameter);

        // Inform capturing_parameter.php that we do not want the values to be
        // recovered from SessionStorage
        $_SESSION['CCDCaptorSizeX_Calculated'] = 'true';

        header("Location: " . "capturing_parameter.php");
        exit();
    } else {
        $message = "Please check your parameters!";
    }
} else {

    $message = $_SESSION['setting']->message();

}

/* *****************************************************************************
 *
 * CREATE THE PAGE
 *
 **************************************************************************** */

$script = "settings.js";
include("header.inc.php");
?>

<!--
  Tooltips
-->
<span class="toolTip" id="ttSpanCancel">
    Go back to previous page without calculating the pixel size.
</span>
<span class="toolTip" id="ttSpanForward">
    Update the pixel size field on previous page with the calculated value.
</span>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
            echo(Nav::linkWikiPage('HRMHelp#Calculate_pixel_size'));
            ?>
        </ul>
    </div>
    <div id="navright">
        <ul>
            <?php
            echo(Nav::textUser($_SESSION['user']->name()));
            ?>
        </ul>
    </div>
    <div class="clear"></div>
</div>

<div id="content">

    <h3>Calculate pixel size</h3>

    <form method="post" action="calculate_pixel_size.php" id="select">

        <fieldset class="setting">

            <?php

            $textForCaptorSize = "physical pixel size on CCD chip (nm)";
            $value = '';
            /** @var CCDCaptorSize $parameter */
            $parameter = $_SESSION['setting']->parameter("CCDCaptorSize");
            $value = $parameter->value();

            ?>
            <a href="javascript:openWindow(
       'http://www.svi.nl/HuygensRemoteManagerHelpCCD')">
                <img src="images/help.png" alt="?"/>
            </a>

            <?php echo $textForCaptorSize ?>:

            <input name="CCDCaptorSize"
                   title="<?php echo $textForCaptorSize; ?>"
                   type="text"
                   size="5"
                   value="<?php echo $value ?>"/>

            <br/>

            <a href="javascript:openWindow(
                'http://www.svi.nl/PixelBinning')">
                <img src="images/help.png" alt="?"/>
            </a>
            binning:

            <select class="selection" title="Binning" name="Binning" size="1">
                <?php


                /** @var Binning $parameter */
                $parameter = $_SESSION['setting']->parameter("Binning");
                foreach ($parameter->possibleValues() as $possibleValue) {
                    $flag = "";
                    if ($possibleValue == $parameter->value()) {
                        $flag = " selected=\"selected\"";
                    }
                    ?>
                    <option <?php echo $flag ?>><?php echo $possibleValue ?></option>
                    <?php


                }
                ?>

            </select>
            <br/>
            <a href="javascript:openWindow(
                'http://www.svi.nl/HuygensRemoteManagerHelpCMount')">
                <img src="images/help.png" alt="?"/>
            </a>
            <?php


            $parameter = $_SESSION['setting']->parameter("CMount");
            $value = $parameter->value();
            ?>
            <?php echo "C-mount" ?>:
            <input name="CMount"
                   title="C-mount"
                   type="text"
                   size="5"
                   value="<?php echo $value ?>"/>
            <br/>

            <a href="javascript:openWindow(
       'http://www.svi.nl/HuygensRemoteManagerHelpTubeFactor')">
                <img src="images/help.png" alt="?"/>
            </a>
            <?php

            $parameter = $_SESSION['setting']->parameter("TubeFactor");
            $value = $parameter->value();
            ?>
            <?php echo "tube factor" ?>:
            <input name="TubeFactor"
                   title="Tube factor"
                   type="text"
                   size="5"
                   value="<?php echo $value ?>"/>
            <br/>

            <a href="javascript:openWindow(
       'http://www.svi.nl/ObjectiveMagnification')">
                <img src="images/help.png" alt="?"/>
            </a>
            objective magnification:

            <select class="selection" title="Objective magnification"
                    name="ObjectiveMagnification" size="1">
                <?php

                $parameter = $_SESSION['setting']->parameter("ObjectiveMagnification");
                $sortedPossibleValues = $parameter->possibleValues();
                sort($sortedPossibleValues, SORT_NUMERIC);
                foreach ($sortedPossibleValues as $possibleValue) {
                    $flag = "";
                    if ($possibleValue == $parameter->value()) $flag = " selected=\"selected\"";

                    ?>
                    <option<?php echo $flag ?>><?php echo $possibleValue ?></option>
                    <?php

                }

                ?>
            </select>
            X

        </fieldset>

        <div id="controls">
            <input type="button" value="" class="icon up"
                   onmouseover="TagToTip('ttSpanCancel' )"
                   onmouseout="UnTip()"
                   onclick="document.location.href='capturing_parameter.php'"/>
            <input type="submit" value="" class="icon next"
                   onmouseover="TagToTip('ttSpanForward' )"
                   onmouseout="UnTip()"
                   onclick="process()"/>
        </div>

    </form>

</div> <!-- content -->

<div id="rightpanel">

    <div id="info">

        <h3>Quick help</h3>

        <p>Here you can calculate the image pixel size from the physical
            attributes of your CCD chip element and some of the relevant
            microscope parameters.</p>
        <p>Notice that the size of the CCD element must be in
            <strong>nm</strong> (e.g. 6450).</p>

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

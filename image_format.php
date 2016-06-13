<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\DatabaseConnection;
use hrm\Nav;
use hrm\param\base\Parameter;
use hrm\setting\ParameterSetting;
use hrm\Util;

require_once dirname(__FILE__) . '/inc/bootstrap.php';


/* *****************************************************************************
 *
 * START SESSION, CHECK LOGIN STATE, INITIALIZE WHAT NEEDED
 *
 **************************************************************************** */

session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit();
}

if (isset($_GET['home'])) {
    header("Location: " . "home.php");
    exit();
}

if (!isset($_SESSION['setting'])) {
    $_SESSION['setting'] = new ParameterSetting();
}

$message = "";

/* *****************************************************************************
 *
 * SET THE CONFIDENCES FOR THE RELEVANT PARAMETERS
 *
 **************************************************************************** */

/*
   In this page, all parameters are required and independent of the
   file format chosen!
*/
$parameterNames = $_SESSION['setting']->imageParameterNames();
$db = new DatabaseConnection();
foreach ($parameterNames as $name) {
    $parameter = $_SESSION['setting']->parameter($name);
    $confidenceLevel = $db->getParameterConfidenceLevel('', $name);
    $parameter->setConfidenceLevel($confidenceLevel);
    $_SESSION['setting']->set($parameter);
}

/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */

if ($_SESSION['setting']->checkPostedImageParameters($_POST)) {

    // Now we force all variable channel parameters to have the correct number
    // of channels
    $_SESSION['setting']->setNumberOfChannels(
        $_SESSION['setting']->numberOfChannels());

    // Continue to the next page
    header("Location: " . "microscope_parameter.php");
    exit();
} else {
    $message = $_SESSION['setting']->message();
}

/* *****************************************************************************
 *
 * CREATE THE PAGE
 *
 **************************************************************************** */

// Javascript includes
$script = array("settings.js", "quickhelp/help.js", "quickhelp/imageFormat.js");

include("header.inc.php");

?>

<!--
  Tooltips
-->
<span class="toolTip" id="ttSpanCancel">
        Abort editing and go back to the image parameters selection page.
        All changes will be lost!
    </span>
<span class="toolTip" id="ttSpanForward">
        Continue to next page.
    </span>
<?php
// Another tooltip is used only if there are parameters that
// might require resetting (and is therefore defined later). Here
// we initialize a counter.
$nParamRequiringReset = 0;
?>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
            echo(Nav::linkWikiPage('HuygensRemoteManagerHelpImageFormat'));
            ?>
            <li> [ <?php echo $_SESSION['setting']->name(); ?> ]</li>
        </ul>
    </div>
    <div id="navright">
        <ul>
            <?php
            echo(Nav::textUser($_SESSION['user']->name()));
            echo(Nav::linkHome(Util::getThisPageName()));
            ?>
        </ul>
    </div>
    <div class="clear"></div>
</div>

<div id="content">

    <h2>Number of channels and PSF modality</h2>

    <form method="post" action="" id="select">

        <h4>How many channels (wavelengths) in your datasets?</h4>

        <?php

        /***************************************************************************
         *
         * NumberOfChannels
         ***************************************************************************/

        $parameterNumberOfChannels =
            $_SESSION['setting']->parameter("NumberOfChannels");

        ?>

        <fieldset id="channels" class="setting <?php
        echo $parameterNumberOfChannels->confidenceLevel(); ?>"
                  onmouseover="changeQuickHelp( 'channels' );">

            <legend>
                <a href="openWindow(
                       'http://www.svi.nl/NumberOfChannels')">
                    <img src="images/help.png" alt="?"/>
                </a>
                number of channels
            </legend>

            <?php

            function check($parameter, $value)
            {
                /** @var Parameter $parameter */
                if ($value == $parameter->value()) echo "checked=\"checked\" ";
                return "";
            }

            ?>
            <div class="values">
                <?php
                if (!$parameterNumberOfChannels->mustProvide()) {
                    $nParamRequiringReset++;
                    ?>
                    <div class="reset"
                         onmouseover="TagToTip('ttSpanReset' )"
                         onmouseout="UnTip()"
                         onclick="document.forms[0].NumberOfChannels[0].checked = true;">
                    </div>
                    <?php
                }

                for ($i = 0; $i <= $db->getMaxChanCnt(); $i++) {
                    if ($i == 0) {
                        ?>
                        <input name="NumberOfChannels"
                               title="Number of channels"
                               type="radio"
                               value=""
                               style="display:none;"/>
                        <?php
                    } else {
                        ?>
                        <input name="NumberOfChannels"
                               title="Number of channels"
                               type="radio"
                               value="<?php echo $i; ?>"
                            <?php check($parameterNumberOfChannels, $i) ?> /> <?php echo $i; ?>
                        <?php
                    }
                }
                ?>


            </div> <!-- values -->


            <div class="bottom">
                <p class="message_confidence_<?php
                echo $parameterNumberOfChannels->confidenceLevel(); ?>">
                    &nbsp;
                </p>
            </div>

        </fieldset>

        <?php

        /***************************************************************************
         *
         * PointSpreadFunction
         ***************************************************************************/

        $parameterPointSpreadFunction =
            $_SESSION['setting']->parameter("PointSpreadFunction");

        ?>

        <h4>Would you like to use an existing measured PSF obtained from
            bead images or a theoretical PSF generated from explicitly
            specified parameters?</h4>

        <fieldset class="setting <?php
        echo $parameterPointSpreadFunction->confidenceLevel(); ?>"
                  onmouseover="changeQuickHelp( 'PSF' );">

            <legend>
                <a href="openWindow(
                       'http://www.svi.nl/PointSpreadFunction')">
                    <img src="images/help.png" alt="?"/>
                </a>
                PSF
            </legend>

            <div class="values">
                <?php
                if (!$parameterPointSpreadFunction->mustProvide()) {
                    $nParamRequiringReset++;
                    ?>
                    <div class="reset"
                         onmouseover="TagToTip('ttSpanReset' )"
                         onmouseout="UnTip()"
                         onclick="document.forms[0].PointSpreadFunction[0].checked = true;">
                    </div>
                    <?php
                }
                ?>

                <input name="PointSpreadFunction"
                       title="Point spread function"
                       type="radio"
                       value=""
                       style="display:none;"/>
                <input type="radio"
                       title="Theoretical point spread function"
                       name="PointSpreadFunction"
                       value="theoretical"
                    <?php
                    if ($parameterPointSpreadFunction->value() ==
                        "theoretical"
                    )
                        echo "checked=\"checked\"" ?> />
                <a href="openWindow(
                       'http://www.svi.nl/TheoreticalPsf')">
                    <img src="images/help.png" alt="?"/>
                </a>
                Theoretical
                <input type="radio"
                       name="PointSpreadFunction"
                       title="Measured point spread function"
                       value="measured"
                    <?php
                    if ($parameterPointSpreadFunction->value() ==
                        "measured"
                    )
                        echo "checked=\"checked\"" ?>
                />
                <a href="javascript:openWindow(
                       'http://www.svi.nl/ExperimentalPsf')">
                    <img src="images/help.png" alt="?"/>
                </a>
                Measured
            </div> <!-- values -->

            <div class="bottom">
                <p class="message_confidence_<?php
                echo $parameterPointSpreadFunction->confidenceLevel(); ?>">
                    &nbsp;
                </p>

            </div>
        </fieldset>

        <div id="controls"
             onmouseover="changeQuickHelp( 'default' )">

            <input type="button" value="" class="icon up"
                   onmouseover="TagToTip('ttSpanCancel' )"
                   onmouseout="UnTip()"
                   onclick="document.location.href='select_parameter_settings.php'"/>
            <input type="submit" value="" class="icon next"
                   onmouseover="TagToTip('ttSpanForward' )"
                   onmouseout="UnTip()"
                   onclick="process()"/>
        </div>

        <div><input name="OK" type="hidden"/></div>

    </form>

    <?php
    if ($nParamRequiringReset > 0) {
        ?>
        <span class="toolTip" id="ttSpanReset">
                Click to unselect all options.
            </span>
        <?php
    }
    ?>

</div> <!-- content -->

<div id="rightpanel" onmouseover="changeQuickHelp( 'default' )">

    <div id="info">
        <h3>Quick help</h3>

        <div id="contextHelp">
            <p>Here you are asked to define the number of channels in your
                data and whether you want to use a theoretical PSF or a
                measured PSF you distilled with the Huygens Software.</p>
        </div>


        <?php
        if (!$_SESSION["user"]->isAdmin()) {
            ?>

            <div class="requirements">
                Parameter requirements<br/>adapted for <b>
                    <?php
                    $fileFormat = $_SESSION['setting']->parameter("ImageFileFormat");
                    echo $fileFormat->value();
                    ?>
                </b> files
            </div>

            <?php
        }
        ?>


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

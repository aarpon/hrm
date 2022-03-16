<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\DatabaseConnection;
use hrm\Nav;
use hrm\setting\TaskSetting;
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
$message = "";















/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */

if ($_SESSION['task_setting']->checkPostedTaskParameters($_POST)) {
    if ($_SESSION['user']->isAdmin()
    || $_SESSION['task_setting']->isEligibleForCAC()
    || $_SESSION['task_setting']->isEligibleForTStabilization($_SESSION['setting'])) {
        header("Location: " . "post_processing.php");
        exit();
    } else {
        header("Location: " . "select_hpc.php");
        exit();
    }
} else {
    $message = $_SESSION['task_setting']->message();
}


/* *****************************************************************************
 *
 * CREATE THE PAGE
 *
 **************************************************************************** */

// Javascript includes
$script = array("settings.js", "quickhelp/help.js",
    "quickhelp/postProcessingHelp.js");

include("header.inc.php");
?>


<!--
  Tooltips
-->
<span class="toolTip" id="ttSpanCancel">
        Abort editing and go back to the Restoration parameters
        selection page. All changes will be lost!
    </span>
<span class="toolTip" id="ttSpanSave">
        Save and return to the processing parameters selection page.
    </span>
<span class="toolTip" id="ttSpanBack">
        Go back to previous page.
    </span>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
            echo(Nav::linkWikiPage('HuygensRemoteManagerHelpRestorationParameters'));
            ?>
            <li> [ <?php echo $_SESSION['task_setting']->name(); ?> ]</li>
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
    <h3>Restoration - Stitching</h3>

    <form method="post" action="" id="stitch">

    <h4>How should your images be stitched?</h4>


        <?php
        /***************************************************************************
         *
         * StitchOffsetsInit
         ***************************************************************************/

        /** @var StitchOffsetsInit $stitchOffsetsInit */
        ?>
        <div id="StitchOffsetsInit">
            <fieldset class="setting provided"
                      onmouseover="changeQuickHelp('stitchoffsetsinit');">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/HelpStitcher')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Initial Offsets
                </legend>

                <select id="StitchOffsetsInit"
                        title="StitchOffsetsInit"
                        name="StitchOffsetsInit"
                        class="selection">
                    <?php

                    /*
                          STITCHOFFSETSINIT
                    */
                    $parameterOffsetsInit =
                        $_SESSION['task_setting']->parameter("StitchOffsetsInit");
                    $possibleValues = $parameterOffsetsInit->possibleValues();
                    $selectedMode = $parameterOffsetsInit->value();

                    foreach ($possibleValues as $possibleValue) {
                        $translation =
                            $parameterOffsetsInit->translatedValueFor($possibleValue);
                        if ($possibleValue == $selectedMode) {
                            $option = "selected=\"selected\"";
                        } else {
                            $option = "";
                        }
                        ?>
                        <option <?php echo $option ?>
                            value="<?php echo $possibleValue ?>">
                            <?php echo $translation ?>
                        </option>
                        <?php
                    }
                    ?>

                </select>
        </div> <!-- StitchOffsetsInit -->
       


        <?php
        /***************************************************************************
         *
         * StitchAcquisitionPattern
         ***************************************************************************/

        /** @var StitchAcquisitionPattern $stitchOAcquisitionPattern */
        ?>
        <div id="StitchAcquisitionPattern">
            <fieldset class="setting provided"
                      onmouseover="changeQuickHelp('stitchacquisitionpattern');">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/HelpStitcher')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Acquisition Pattern
                </legend>

                <select id="StitchAcquisitionPattern"
                        title="StitchAcquisitionPattern"
                        name="StitchAcquisitionPattern"
                        class="selection">
                    <?php

                    /*
                          STITCHACQUISITIONPATTERN
                    */
                    $parameterAcqPattern =
                        $_SESSION['task_setting']->parameter("StitchAcquisitionPattern");
                    $possibleValues = $parameterAcqPattern->possibleValues();
                    $selectedMode = $parameterAcqPattern->value();

                    foreach ($possibleValues as $possibleValue) {
                        $translation =
                            $parameterAcqPattern->translatedValueFor($possibleValue);
                        if ($possibleValue == $selectedMode) {
                            $option = "selected=\"selected\"";
                        } else {
                            $option = "";
                        }
                        ?>
                        <option <?php echo $option ?>
                            value="<?php echo $possibleValue ?>">
                            <?php echo $translation ?>
                        </option>
                        <?php
                    }
                    ?>

                </select>
        </div> <!-- StitchAcquisitionPattern -->


        <?php
        /***************************************************************************
         *
         * StitchAcquisitionStart
         ***************************************************************************/

        /** @var StitchAcquisitionStart $stitchAcquisitionStart */
        ?>
        <div id="StitchAcquisitionStart">
            <fieldset class="setting provided"
                      onmouseover="changeQuickHelp('stitchacquisitionstart');">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/HelpStitcher')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Acquisition Start
                </legend>

                <select id="StitchAcquisitionStart"
                        title="StitchAcquisitionStart"
                        name="StitchAcquisitionStart"
                        class="selection">
                    <?php

                    /*
                          STITCHACQUISITIONSTART
                    */
                    $parameterAcqStart =
                        $_SESSION['task_setting']->parameter("StitchAcquisitionStart");
                    $possibleValues = $parameterAcqStart->possibleValues();
                    $selectedMode = $parameterAcqStart->value();

                    foreach ($possibleValues as $possibleValue) {
                        $translation =
                            $parameterAcqStart->translatedValueFor($possibleValue);
                        if ($possibleValue == $selectedMode) {
                            $option = "selected=\"selected\"";
                        } else {
                            $option = "";
                        }
                        ?>
                        <option <?php echo $option ?>
                            value="<?php echo $possibleValue ?>">
                            <?php echo $translation ?>
                        </option>
                        <?php
                    }
                    ?>

                </select>
        </div> <!-- StitchAcquisitionStart -->

            
        <?php
        /***************************************************************************
         *
         * StitchPatternWidth
         ***************************************************************************/

        /** @var StitchPatternWidth $stitchPatternWidth */
        ?>
        <div id="StitchPatternWidth">
            <fieldset class="setting provided"
                      onmouseover="changeQuickHelp('stitchpatternwidth');">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/HelpStitcher')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Pattern Width
                </legend>

                <?php
                $parameterPatternWidth =
                        $_SESSION['task_setting']->parameter("StitchPatternWidth");
                $value = $parameterPatternWidth->value();
                ?>

                <input id="PatternWidth"
                       name="PatternWidth"
                       title="Pattern Width"
                       type="text"
                       size="8"
                       value="<?php echo $value ?>"/>

            </fieldset>
        </div> <!-- StitchPatternWidth -->

                           

        <?php
        /***************************************************************************
         *
         * StitchPatternHeight
         ***************************************************************************/

        /** @var StitchPatternHeight $stitchPatternHeight */
        ?>
        <div id="StitchPatternHeight">
            <fieldset class="setting provided"
                      onmouseover="changeQuickHelp('stitchpatternheight');">

                <legend>
                    <a href="javascript:openWindow(
                        'http://www.svi.nl/HelpStitcher')">
                        <img src="images/help.png" alt="?"/>
                    </a>
                    Pattern Height
                </legend>

                <?php
                $parameterPatternHeight =
                        $_SESSION['task_setting']->parameter("StitchPatternHeight");
                $value = $parameterPatternHeight->value();
                ?>

                <input id="PatternHeight"
                       name="PatternHeight"
                       title="Pattern Height"
                       type="text"
                       size="8"
                       value="<?php echo $value ?>"/>

            </fieldset>
        </div> <!-- StitchPatternHeight -->















            








                                    
                                    
                          
    <div><input name="OK" type="hidden"/></div>

    <div id="controls"
         onmouseover="changeQuickHelp( 'default' )">
        <input type="button" value="" class="icon previous"
               onmouseover="TagToTip('ttSpanBack' )"
               onmouseout="UnTip()"
               onclick="document.location.href='task_parameter.php'"/>
        <input type="button" value="" class="icon up"
               onmouseover="TagToTip('ttSpanCancel' )"
               onmouseout="UnTip()"
               onclick="deleteValuesAndRedirect(
                    'select_task_settings.php' );"/>
        <input type="submit" value="" class="icon next"
               onmouseover="TagToTip('ttSpanForward' )"
               onmouseout="UnTip()"
               onclick="process()"/>
    </div>

    </form>                          
</div> <!-- content -->
                               




<div id="rightpanel" onmouseover="changeQuickHelp( 'default' )">
    <div id="info">
        <h3>Quick help</h3>
        <div id="contextHelp">
            <p>On this page you specify the parameters of those
               restoration operations to be carried out on the deconvolved
               image.</p>
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
if (Util::using_IE() && !isset($_SERVER['HTTP_REFERER'])) {
    ?>
    <script type="text/javascript">
        $(document).ready(retrieveValues());
    </script>
    <?php
}
?>

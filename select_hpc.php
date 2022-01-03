<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Fileserver;
use hrm\Nav;
use hrm\param\HotPixelCorrection;

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

// fileserver related code
if (!isset($_SESSION['fileserver'])) {
    $name = $_SESSION['user']->name();
    $_SESSION['fileserver'] = new Fileserver($name);
}

$message = "";

/* *****************************************************************************
 *
 * MANAGE HPC FILE NAMES
 *
 **************************************************************************** */

/** @var HPC $hpcParam */
$hpcParam = $_SESSION['task_setting']->parameter("HotPixelCorrection");
$hpc = $hpcParam->value();

if (isset($_POST["hpc"])) {
    $hpc = $_POST["hpc"];
}

$hpcParam->setValue($hpc);
$_SESSION['task_setting']->set($hpcParam);

/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 * *
 **************************************************************************** */

if (count($_POST) > 0) {
    if ($hpcParam->check()) {        
        $saved = $_SESSION['task_setting']->save();
        $message = $_SESSION['task_setting']->message();
        if ($saved) {
            header("Location: select_task_settings.php");
            exit();
        }
    } else {
        $message = $hpcParam->message();
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
    </span>
<span class="toolTip" id="ttSpanSave">
        Save and return to the image parameters selection page.
    </span>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
            echo(Nav::linkWikiPage('Hot-Cold-PixelRemover'));
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

    <h3><img alt="SelectHPC" src="./images/hpc.png"
             width="40"/>&nbsp;Hot Pixel Correction - mask file selection</h3>

    <form method="post" action="select_hpc.php" id="select">

        <div id="HotPixelCorrection" class="provided">
            <?php

                /** @var HPC $parameter */
                $parameter = $_SESSION['task_setting']->parameter("HotPixelCorrection");
                $value = $parameter->value();
                $missing = False;
                $_SESSION['fileserver']->imageExtensions();
                $files = $_SESSION['fileserver']->allFiles();
                if ($files != null) {
                    if (!in_array($value[0], $files)) {
                        $missing = True;
                    }

                    ?>
                    <p>
                        <input name="hpc"
                               title="Select a Hot Pixel Correction mask"
                               type="text"
                               value="<?php echo $value[0] ?>"
                               class="
                           <?php
                               if ($missing) {
                                   echo "hpcmissing";
                               } else {
                                   echo "hpcfile";
                               } ?>"
                               readonly="readonly"/>
                        <input type="button"
                               onclick="seek('0', 'hpc')"
                               value="browse"/>
                        <input type="button"
			       onclick="hpcReset()"
                               value="reset"/>
                    </p>
                    <?php

                } else {
                    if (!file_exists($_SESSION['fileserver']->sourceFolder())) {

                        ?>
                        <p class="info">Source image folder not found! Make sure
                            the
                            folder <?php echo $_SESSION['fileserver']->sourceFolder() ?>
                            exists.</p>
                        <?php

                    } else {

                        ?>
                        <p class="info">No images found on the server!</p>
                        <?php

                    }
                }
            ?>
     	    <p class="info">The correction is optional: leave empty for skipping.</p>
        </div>

        <div>
	   <input name="OK" type="hidden"/>
	</div>

        <div id="controls">
            <input type="button" value="" class="icon previous"
                   onmouseover="TagToTip('ttSpanBack' )"
                   onmouseout="UnTip()"
                   onclick="document.location.href='post_processing.php'"/>
            <input type="button" value="" class="icon up"
                   onmouseover="TagToTip('ttSpanCancel' )"
                   onmouseout="UnTip()"
                   onclick="document.location.href='select_task_settings.php'"/>
            <input type="submit" value="" class="icon save"
                   onmouseover="TagToTip('ttSpanSave' )"
                   onmouseout="UnTip()" onclick="process()"/>
        </div>

    </form>

</div> <!-- content -->

<div id="rightpanel">

    <div id="info">

        <h3>Quick help</h3>
        <p>Select a Hot Pixel Correction mask. This correction will be applied to the target images before any other image processing is executed. For multiple channels, please select a single file with different masks for the channels. </p>
        <p>Since the images of a batch may or may not have all the same dimensions please notice that the correction will be skipped on runtime for images whose dimensions don't match the selected mask. </p>
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

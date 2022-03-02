<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Fileserver;
use hrm\Nav;

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

if ($_SESSION['user']->isAdmin()
   || $_SESSION['task_setting']->isEligibleForCAC()
   || $_SESSION['task_setting']->isEligibleForTStabilization($_SESSION['setting'])) {
    $back = "post_processing.php";
} else {
    $back = "task_parameter.php";
}

// fileserver related code
if (!isset($_SESSION['fileserver'])) {
    $name = $_SESSION['user']->name();
    $_SESSION['fileserver'] = new Fileserver($name);
}

$message = "";

/* *****************************************************************************
 *
 * MANAGE THE STITCHER
 *
 **************************************************************************** */


/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 * *
 **************************************************************************** */


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
            echo(Nav::linkWikiPage('Stitcher'));
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

    <h3><img alt="Stitching" src="./images/stitching.png"
             width="40"/>&nbsp;Stitching</h3>


        <div id="controls">
            <input type="button" value="" class="icon previous"
                   onmouseover="TagToTip('ttSpanBack' )"
                   onmouseout="UnTip()"
                   onclick="document.location.href='<?php echo $back;?>'"/>
            <input type="button" value="" class="icon up"
                   onmouseover="TagToTip('ttSpanCancel' )"
                   onmouseout="UnTip()"
                   onclick="document.location.href='select_task_settings.php'"/>
            <input type="submit" value="" class="icon save"
                   onmouseover="TagToTip('ttSpanSave' )"
                   onmouseout="UnTip()" onclick="process()"/>
        </div>

</div> <!-- content -->

<div id="rightpanel">

    <div id="info">

        <h3>Quick help</h3>
        <p> </p>
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

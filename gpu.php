<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Nav;

require_once dirname(__FILE__) . '/inc/bootstrap.inc.php';

require_once("./inc/User.inc.php");
require_once("./inc/Util.inc.php");
require_once("./inc/System.inc.php");

session_start();

if (isset($_GET['home'])) {
  header("Location: " . "home.php"); exit();
}

// The admin must be logged on
if ( ( !isset( $_SESSION[ 'user' ] ) ) ||
    ( !$_SESSION[ 'user' ]->isAdmin() ) ) {
        header("Location: " . "login.php"); exit();
}

$message = "";

if (isset($_GET["turnon"])) {
    $db = new DatabaseConnection();
    $message = $db->SwitchGPUState( "On" );
}
if (isset($_GET["turnoff"])) {
    $db = new DatabaseConnection();
    $message = $db->SwitchGPUState( "Off" );
}


include("header.inc.php");

?>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
                echo(Nav::linkWikiPage('HuygensGPU'));
            ?>
        </ul>
    </div>
    <div id="navright">
        <ul>
            <?php
                echo(Nav::textUser($_SESSION['user']->name()));
                echo(Nav::linkHome(getThisPageName()));
            ?>
        </ul>
    </div>
    <div class="clear"></div>
</div>

    <div id="content">

        <h3><img alt="SwitchGPU" src="./images/gpu.png"
                 width="40"/>&nbsp;&nbsp;Switch GPU</h3>

        <fieldset>
            <legend>log</legend>
            <textarea rows="15" readonly="readonly">
<?php

echo $message;

?>
            </textarea>
        </fieldset>

    </div> <!-- content -->

    <div id="rightpanel">

        <div id="info">

            <h3>Quick help</h3>

            <p>
                This page allows you to toggle the GPU option in Huygens.
                Please visit <a href="https://svi.nl/HuygensGPU">Huygens GPU</a>
                for detailed instructions on how to install CUDA.
            </p>

            <p>
                Each deconvolution job has a log that can be reached via the
                user account: <b>'Results' -> 'Select an image' ->
                'Detailed view' -> 'log'.</b>
                The log shows whether the image has been processed on the CPU
                or on the GPU.
            </p>

            <p>
                GPU deconvolution is available in Huygens from version
                 <b>15.10</b> onwards.
            </p>

            <br>

            <form method="GET" action="" id="GPU">
              <input type="submit" name="turnon" value="ON"
                   onclick="document.forms['GPU'].submit()" />

              <input type="submit" name="turnoff" value="OFF"
                   onclick="document.forms['GPU'].submit()" />
            </form>


        </div>

        <div id="message">
      </div>

    </div> <!-- rightpanel -->

<?php

include("footer.inc.php");

?>

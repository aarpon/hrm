<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Util.inc.php");
require_once("./inc/System.inc.php");
require_once("./inc/wiki_help.inc.php");

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

if (isset($_GET["enable"])) {
    $message = askHuCore( "toggleGPU", "-newState \"enable\"");
    $message = $message['GPUSTATE'];
}
if (isset($_GET["disable"])) {
    $message = askHuCore( "toggleGPU", "-newState \"disable\"");
    $message = $message['GPUSTATE'];
}


include("header.inc.php");

?>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
                wiki_link('HuygensGPU');
            ?>
        </ul>
    </div>
    <div id="navright">
        <ul>
            <?php
                include("./inc/nav/user.inc.php");
                include("./inc/nav/home.inc.php");
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

            <br>

            <form method="GET" action="" id="GPU">
              <input type="submit" name="enable" value="enable"
                   onclick="document.forms['GPU'].submit()" />

              <input type="submit" name="disable" value="disable"
                   onclick="document.forms['GPU'].submit()" />
            </form>


        </div>

        <div id="message">
      </div>

    </div> <!-- rightpanel -->

<?php

include("footer.inc.php");

?>

<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Nav;

require_once dirname(__FILE__) . '/inc/bootstrap.php';

require_once("./inc/User.inc.php");
require_once("./inc/Util.inc.php");
require_once("./inc/System.php");

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

if (isset($_GET["action"])) {
    if ($_GET["action"] == "dbupdate") {
        $interface = "hrm";
        include("setup/dbupdate.php");
    }

}

include("header.inc.php");

?>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
                echo(Nav::linkWikiPage('HuygensRemoteManagerHelpUpdate'));
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

        <h3><img alt="UpdateDatabase" src="./images/updatedb.png"
                 width="40"/>&nbsp;&nbsp;Update database</h3>

    <?php
    if ( System::isDBUpToDate( ) == true ) {
    	echo "<h4>The database is up-to-date.</h4>";
    } else {
        echo "<h4>The database must be updated.</h4>";
    }
    ?>

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
                This page allows you to verify and patch the database after an
                update to a new release. The interface might not function
                properly until you do so.
            </p>

            <p>
                New HRM releases are available from the project
                <a href="javascript:openWindow(
                   'http://sourceforge.net/projects/hrm')">website</a>.
            </p>

            <br>

            <form method="GET" action="" id="dbupdate">
                <input type="hidden" name="action" value="dbupdate">
            </form>

            <input type="button" name="" value="update"
                   onclick="document.forms['dbupdate'].submit()" />

        </div>

        <div id="message">
<?php

//echo $message;

?>
        </div>

    </div> <!-- rightpanel -->

<?php

include("footer.inc.php");

?>

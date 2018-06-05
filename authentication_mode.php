<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Nav;
use hrm\user\proxy\ProxyFactory;
use hrm\Util;
use hrm\user\UserManager;

require_once dirname(__FILE__) . '/inc/bootstrap.php';


global $email_sender;

/* *****************************************************************************
 *
 * START SESSION, CHECK LOGIN STATE, INITIALIZE WHAT NEEDED
 *
 **************************************************************************** */

session_start();

if (isset($_GET['home'])) {
    header("Location: " . "home.php");
    exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit();
}

$username = "";
if (isset($_GET['name'])) {
    $username = $_GET['name'];
}

$clean = array();
if (isset($_POST["authMode"])) {
    $allAuthMap = ProxyFactory::getAllConfiguredAuthenticationModes();
    if (array_key_exists($_POST["authMode"], $allAuthMap)) {
        $clean["authMode"] = $_POST["authMode"];
    }
}

// The posted username overrides passed via GET
if (isset($_POST["username"]) && $_POST["username"] != "") {
    $username = $_POST["username"];
}

// Retrieve the user
if ($username == "") {
    throw new Exception("Expected user name!");
}
$edit_user = UserManager::findUserByName($username);

$message = "";

/* *****************************************************************************
 *
 * UPDATE USER AUTHENTICATION MODE
 *
 **************************************************************************** */

if (isset($_POST['modify'])) {

    // Set the authentication mode
    if (UserManager::setAuthenticationMode($edit_user->name(), $clean["authMode"])) {
        // Now remove the modified User from the session and return to the user management page.
        unset($_SESSION['account_user']);
        $_SESSION['account_update_message'] = "Authentication mode successfully modified " .
         "for user '" . $edit_user->name() . "'.";
        header("Location: " . "user_management.php");
        exit();
    } else {
        $message = "Sorry, could not update the authentication mode for the user!";
    }
}

/* *****************************************************************************
 *
 * DISPLAY PAGE
 *
 **************************************************************************** */

include("header.inc.php");
?>

<!--
  Tooltips
-->
<span class="toolTip" id="ttSpanCancel">Discard changes and go back.</span>
<span class="toolTip" id="ttSpanSave">Save the changes.</span>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
            echo(Nav::linkWikiPage('HuygensRemoteManagerHelpAccount'));
            ?>
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

    <h3><img alt="AuthenticationMode" src="./images/account_title.png"
             width="40"/>&nbsp;Authentication mode</h3>

    <form method="post" action="" id="auth_mode">

        <div id="modify_user">
            <?php


            // Retrieve all configured authentication modes
            $allAuthMap = ProxyFactory::getAllConfiguredAuthenticationModes();

            ?>

            <p>Please select the authentication mode for user '<?php echo($edit_user->name()); ?>':</p>

            <select title="Authentication mode"
                    name="authMode"
                    id="authMode"
                    class="selection">

                <?php

                // Get current authentication mode
                $currentAuthMode = $edit_user->authenticationMode();
                $auth_keys = array_keys($allAuthMap);
                for ($i = 0; $i < count($allAuthMap); $i++) {
                    $value = $auth_keys[$i];
                    $text = $allAuthMap[$value];
                    if ($currentAuthMode == $value) {
                        $selected = "selected";
                    } else {
                        $selected = "";
                    }
                    echo("<option value='$value' $selected>$text</option>");
                }

                ?>
            </select>

            <p/>

        </div>

        <div id="controls">
            <input type="hidden" name="username" value="<?php echo($username); ?>" />
            <input type="hidden" name="modify" value="1" />
            <input type="button" name="cancel" value=""
                   class="icon cancel"
                   onmouseover="TagToTip('ttSpanCancel' )"
                   onmouseout="UnTip()"
                   onclick="document.location.href='user_management.php'"/>
            <input type="button" name="save" value=""
                   class="icon save"
                   onmouseover="TagToTip('ttSpanSave' )"
                   onmouseout="UnTip()"
                   onclick="document.forms['auth_mode'].submit()"/>
        </div>

    </form>

</div> <!-- content -->

<div id="rightpanel">

    <div id="info">

        <h3>Quick help</h3>

        <p>Please set the authentication mode.</p>

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

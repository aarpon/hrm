<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Fileserver;
use hrm\Log;
use hrm\Nav;
use hrm\user\mngm\UserManagerFactory;
use hrm\Validator;
use hrm\DatabaseConnection;
use hrm\user\User;
use hrm\System;

require_once dirname(__FILE__) . '/inc/bootstrap.php';

global $email_admin;
global $authenticateAgainst;

/*
 *
 * SANITIZE INPUT
 *   We check the relevant contents of $_POST for validity and store them in
 *   a new array $clean that we will use in the rest of the code.
 *
 *   After this step, only the $clean array and no longer the $_POST array
 *   should be used!
 *
 */

// Here we store the cleaned variables
$clean = array(
    "username" => "",
    "password" => "");

// Username
if (isset($_POST["username"])) {
    if (Validator::isUserNameValid($_POST["username"])) {
        $clean["username"] = $_POST["username"];
    }
}

// Password
if (isset($_POST["password"])) {
    if (Validator::isPasswordValid($_POST["password"])) {
        $clean["password"] = $_POST["password"];
    }
}

// TODO Clean $_POST['request']

/*
 *
 * END OF SANITIZE INPUT
 *
 */

$message = "";

session_start();
if (isset($_SESSION['request'])) {
    $req = $_SESSION['request'];
} else {
    $req = "";
}
if (isset($_POST['request'])) {
    $req = $_POST['request'];
}

/* Reset all! */
session_unset();
session_destroy();

session_start();

if (isset($_POST['password']) && isset($_POST['username'])) {
    if ($clean['password'] != "" && $clean['username'] != "") {

        // Get the UserManager
        $userManager = UserManagerFactory::getUserManager(false);

        // Create a user
        $tentativeUser = new User();
        $tentativeUser->setName($clean['username']);
        $tentativeUser->logOut(); // TODO

        if ($tentativeUser->logIn($clean['username'], $clean['password'])) {

            if ($tentativeUser->isLoggedIn()) {

                // Register the user in the session
                $_SESSION['user'] = $tentativeUser;

                // Make sure that the user source and destination folders exist
                $fileServer = new Fileserver($tentativeUser->name());
                if (!$fileServer->isReachable()) {
                    $userManager->createUserFolders($tentativeUser->name());
                }

                // Update the user data and the access date in the database
                $userManager->storeUser($_SESSION['user']);

                // Log successful logon
                Log::info("User " . $_SESSION['user']->name() . " (" .
                    $_SESSION['user']->emailAddress() . ") logged on.");

                // If the database is not up-to-date go straight to the
                // database update page
                if (!System::isDBUpToDate()) {
                    if ($_SESSION['user']->isAdmin()) {
                        header("Location: update.php");
                        exit();
                    } else {
                        $message = "Only the administrator is allowed to login " .
                            "to perform maintenance";
                    }
                } else {
                    // Is there a requested redirection?
                    if ($req != "") {
                        header("Location: " . $req);
                        exit();
                    } else {
                        // Proceed to home
                        header("Location: " . "home.php");
                        exit();
                    }
                }
            }
        } else if ($userManager->isLoginRestrictedToAdmin()) {
            if ($tentativeUser->isAdmin()) {
                $message = "Wrong password";
            } else {
                $message = "Only the administrator is allowed to login " .
                    "to perform maintenance";
            }
        } else {
            if ($userManager->isSuspended($tentativeUser)) {
                $message = "Your account has been suspended, please " .
                    "contact the administrator";
            } else {
                $message = "Sorry, wrong user name or password, or not authorized.";
            }
        }
    } else {
        $message = "Sorry, invalid user name or password";
    }
}

include("header.inc.php");

?>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
            echo(Nav::linkWikiPage('HuygensRemoteManagerHelpLogin'));
            echo(Nav::linkMailingList());
            ?>
        </ul>
    </div>
    <div id="navright">
        <ul>
            <?php
            echo(Nav::linkWhatsNew());
            echo(Nav::linkProjectWebsite());
            echo(Nav::linkSVIWiki());
            ?>
        </ul>
    </div>
    <div class="clear"></div>
</div>

<div id="welcome"><?php
    // Check that the database is reachable
    $db = new DatabaseConnection();
    if (!$db->isReachable()) {
        echo "<div class=\"dbOutDated\">Warning: the database is not reachable!\n";
        echo "<p>Please contact your administrator.</p>" .
            "<p>You will not be allowed to login " .
            "until this issue has been fixed.</p></div>";
        echo "</div>\n";
        include("footer.inc.php");
        return;
    }
    // Check that the hucore version is known
    if (System::getHuCoreVersionAsInteger() == 0) {
        echo "<div class=\"dbOutDated\">Warning: unknown HuCore version!\n";
        echo "<p>Please ask the administrator to start the queue manager.</p>" .
            "<p>You are now allowed to login until this issue has been " .
            "fixed.</p></div>";
        echo "</div>\n";
        include("footer.inc.php");
        return;
    }
    // Check that hucore is recent enough to run this version of the HRM
    if (System::isMinHuCoreVersion() == false) {
        echo "<div class=\"dbOutDated\">Warning: your HuCore version is " .
            System::getHucoreVersionAsString() . ", you need at least HuCore " .
            "version " . System::getMinHuCoreVersionAsString() . " for HRM " .
            System::getHRMVersionAsString() . "!\n";
        echo "<p>Please contact the administrator.</p></div>";
        echo "</div>\n";
        include("footer.inc.php");
        return;
    }
    // Check that the database is up-to-date
    if (System::isDBUpToDate() == false) {
        echo "<div class=\"dbOutDated\">Warning: the database is not up-to-date!\n";
        echo "<p>This happens if HRM was recently updated but the " .
            "database was not. You are not allowed to login " .
            "until this issue has been fixed.</p>";
        echo "<p>Only the administrator can login.</p></div>";
    }

    // Check that HuCore has a valid license, unless this is a development setup
    if (file_exists('.hrm_devel_version') == false) {
        if (System::hucoreHasValidLicense() == false) {
            echo "<div class=\"dbOutDated\">Warning: no valid HuCore license found!\n";
            echo "<p>Please contact the administrator.</p></div>";
            echo "</div>\n";
            include("footer.inc.php");
            return;
        }
    }

    ?>
    <h2>Welcome</h2>

    <p class="intro">The <a
            href="javascript:openWindow('http://hrm.sourceforge.net')">Huygens
            Remote Manager</a> is an easy to use interface to the Huygens
        Software
        by <a href="javascript:openWindow('http://www.svi.nl')">Scientific
            Volume Imaging B.V.</a> that allows for multi-user, large-scale
        deconvolution and analysis.</p>

    <?php
    /*
     * Include user/login_user.inc if it exists. This allows installations
     * to be customized without having to change anything in the HRM code.
     */
    if (file_exists("user/login_user.inc") == true) {
        echo "<div id=\"login_user\">\n";
        include "user/login_user.inc";
        echo "</div>";
    }
    ?>

    <h2>Collaborators</h2>

    <div id="logos">

        <!-- First row -->
        <table class="firstRow">

            <!-- Logos -->
            <tr>
                <td class="epfl"
                    onclick="openWindow('http://biop.epfl.ch')">
                </td>
                <td class="fmi"
                    onclick="openWindow('http://www.fmi.ch')">
                </td>
                <td class="mri"
                    onclick="openWindow('http://www.mri.cnrs.fr')">
                </td>
                <td class="bsse"
                    onclick="openWindow('http://www.bsse.ethz.ch')">
                </td>
            </tr>

            <!-- Captions -->
            <tr class="caption">
                <td>
                    EPF Lausanne<br/>
                    <a href="http://biop.epfl.ch"
                       onclick="this.target='_blank'; return true;">
                        BioImaging and Optics platform
                    </a>
                </td>
                <td>
                    Friedrich Miescher Institute<br/>
                    <a href="http://www.fmi.ch/faim"
                       onclick="this.target='_blank'; return true;">
                        Facility for Advanced<br/>
                        Imaging and Microscopy
                    </a>
                </td>
                <td>
                    <a href="http://www.mri.cnrs.fr"
                       onclick="this.target='_blank'; return true;">
                        Montpellier RIO Imaging
                    </a>
                </td>
                <td>
                    <a href="http://www.bsse.ethz.ch"
                       onclick="this.target='_blank'; return true;">
                        ETH Zurich<br/>
                        Single-Cell Unit
                    </a>
                </td>
            </tr>

        </table>

        <!-- Second row -->
        <table class="secondRow">

            <!-- Logos -->
            <tr>
                <td class="svi"
                    onclick="openWindow('http://www.svi.nl')">
                </td>
                <td class="lin"
                    onclick="openWindow('http://www.lin-magdeburg.de')">
                </td>
                <td class="bio-basel"
                    onclick="openWindow('http://www.biozentrum.unibas.ch')">
                </td>
                <td class="cni"
                    onclick="openWindow('http://cni.ifn-magdeburg.de')">
                </td>
            </tr>

            <!-- Captions -->
            <tr class="caption">
                <td>
                    <a href="http://svi.nl"
                       onclick="this.target='_blank'; return true;">
                        Scientific Volume Imaging
                    </a>
                </td>
                <td>
                    <a href="http://www.lin-magdeburg.de"
                       onclick="this.target='_blank'; return true;">
                        Leibniz Institute for Neurobiology<br/>
                        Magdeburg
                    </a>
                </td>
                <td>
                    <a href="http://www.biozentrum.unibas.ch"
                       onclick="this.target='_blank'; return true;">
                        Biozentrum Basel<br/>
                        University of Basel<br/>
                        The Center for<br/>
                        Molecular Life Sciences
                    </a>
                </td>
                <td>
                    <a href="http://cni.ifn-magdeburg.de"
                       onclick="this.target='_blank'; return true;">
                        Combinatorial Neuroimaging<br/>
                        Magdeburg
                    </a>
                </td>
            </tr>

        </table>

    </div>
</div>
<!-- welcome -->

<div id="rightpanel">
    <p>&nbsp;</p>
    <div id="login">
        <form method="post" action="">
            <fieldset>
                <legend>
                    <a href="openWindow(
               'http://www.svi.nl/HuygensRemoteManagerHelpLogin')">
                        <img src="images/help.png" alt="?"/></a> Login
                </legend>

                <p class="expl">Please enter your credentials.</p>

                <label for="username">Username</label><br/>
                <input id="username" name="username" type="text"
                       class="textfield"
                       tabindex="1"/> <br/>
                <label for="password">Password</label><br/>
                <input id="password" name="password" type="password"
                       class="textfield"
                       tabindex="2"/> <br/>
                <input type="hidden" name="request" value="<?php echo $req ?>"/>
                <input type="submit" class="button" value="login"/>
            </fieldset>

            <?php
            if ($authenticateAgainst == "MYSQL") {
                ?>
                <fieldset>
                    <legend>
                        <a href="openWindow(
               'http://www.svi.nl/HuygensRemoteManagerHelpRegistration')">
                            <img src="images/help.png" alt="?"/></a>
                        Registration
                    </legend>

                    <div id="login_registration">
                        <table>
                            <tr>
                                <td class="icon"
                                    onclick="document.location.href='registration.php'">
                                </td>
                                <td class="text">
                                    <b>No HRM account yet?</b><br/>
                                    Please register <a href="registration.php">here</a>.
                                </td>
                            </tr>
                        </table>
                    </div>

                </fieldset>
                <?php
            }
            ?>

        </form>
    </div>

    <div id="message"><?php

        echo "<p>$message</p>";

        ?></div>

</div>
<!-- rightpanel -->

<?php

include("footer.inc.php");

?>

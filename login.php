<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Fileserver;
use hrm\Log;
use hrm\Nav;
use hrm\System;
use hrm\user\proxy\ProxyFactory;
use hrm\user\UserManager;
use hrm\user\UserV2;
use hrm\Validator;
use hrm\DatabaseConnection;

require_once dirname(__FILE__) . '/inc/bootstrap.php';

global $email_admin;

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

        // Create a user
        $tentativeUser = new UserV2($clean['username']);

        if ($tentativeUser->logIn($clean['password'])) {

            // If the user does not exist yet in the system, we add it
            if (!UserManager::existsUser($tentativeUser)) {
                UserManager::addUser($tentativeUser, $clean['password']);
            }

            // Register the user in the session
            $_SESSION['user'] = $tentativeUser;

            // Make sure that the user source and destination folders exist
            $fileServer = new Fileserver($tentativeUser->name());
            if (!$fileServer->isReachable()) {
                UserManager::createUserFolders($tentativeUser->name());
            }

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

        } else if (UserManager::isLoginRestrictedToAdmin()) {
            if ($tentativeUser->isAdmin()) {
                $message = "Wrong password";
            } else {
                $message = "Only the administrator is allowed to login " .
                    "to perform maintenance";
            }
        } else {
            if ($tentativeUser->isDisabled()) {
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
            echo(Nav::externalSupportLinks());
            ?>
        </ul>
    </div>
    <div id="navright">
        <ul>
            <?php
            echo(Nav::linkLogIn(true,
                ProxyFactory::getDefaultAuthenticationMode() == "integrated",
                $req));
            ?>
        </ul>
    </div>
    <div class="clear"></div>
</div>

<!-- Error messages -->
<div id="message">
    <?php echo "<p>$message</p>"; ?>
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
    // Check that hucore is recent enough to run this version of HRM
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
    <div id="welcome_intro">
        The <a href="javascript:openWindow('http://hrm.sourceforge.net')">
            Huygens Remote Manager</a> is an easy to use interface to the<br/>
        Huygens Software by <a href="javascript:openWindow('http://www.svi.nl')">
            Scientific Volume Imaging B.V.</a> that allows for</br>
        multi-user, large-scale deconvolution and analysis.</div>

    <?php
    /*
     * Include user/login_user.inc if it exists. This allows installations
     * to be customized without having to change anything in the HRM code.
     */
    if (file_exists("user/login_user.inc") == true) {
        echo "<div id=\"login_user\">\n";
        /** @noinspection PhpIncludeInspection */
        include "user/login_user.inc";
        echo "</div>";
    }
    ?>

    <div id="logos">
        A <a href="credits.php">collaboration</a> of:
        <!-- First row -->
        <table class="firstRow">
            <!-- Logos -->
            <tr>
                <td class="mri"
                    onclick="javascript:openWindow('https://www.mri.cnrs.fr')">
                </td>
                <td class="fmi"
                    onclick="javascript:openWindow('https://www.fmi.ch')">
                </td>
                <td class="epfl"
                    onclick="javascript:openWindow('https://biop.epfl.ch')">
                </td>
                <td class="svi"
                    onclick="javascript:openWindow('https://www.svi.nl')">
                </td>
            </tr>

            <!-- Captions -->
            <tr class="caption">
                <td>
                    <a href="https://www.mri.cnrs.fr"
                       onclick="this.target='_blank'; return true;">
                        Montpellier RIO Imaging
                    </a><br/>
                    National Center for Scientific<br />Research Montpellier

                </td>
                <td>
                    <a href="https://www.fmi.ch/faim"
                       onclick="this.target='_blank'; return true;">
                        Facility for Advanced Imaging<br/>
                        and Microscopy
                    </a><br/>
                    Friedrich Miescher Institute
                </td>
                <td>
                    <a href="https://biop.epfl.ch"
                       onclick="this.target='_blank'; return true;">
                        BioImaging and Optics platform
                    </a><br/>
                    EPF Lausanne
                </td>
                <td>
                    <a href="https://svi.nl"
                       onclick="this.target='_blank'; return true;">
                        Scientific Volume Imaging
                    </a><br/>
                    Hilversum
                </td>
            </tr>

        </table>

        <!-- Second row -->
        <table class="secondRow">

            <!-- Logos -->
            <tr>
                <td class="bsse"
                    onclick="javascript:openWindow('https://www.bsse.ethz.ch')">
                </td>
                <td class="bio-basel"
                    onclick="javascript:openWindow('https://www.biozentrum.unibas.ch')">
                </td>
                <td class="lin"
                    onclick="javascript:openWindow('http://www.lin-magdeburg.de')">
                </td>
                <td class="cni"
                    onclick="javascript:openWindow('http://cni.ifn-magdeburg.de')">
                </td>
            </tr>

            <!-- Captions -->
            <tr class="caption">
                <td>
                    <a href="https://www.bsse.ethz.ch/scf"
                       onclick="this.target='_blank'; return true;">
                        Single-Cell Facility
                    </a><br/>
                    ETH Zurich
                </td>
                <td>
                    <a href="https://www.biozentrum.unibas.ch/research/groups-platforms/overview/unit/imcf/"
                       onclick="this.target='_blank'; return true;">
                        Imaging Core Facility
                    </a><br/>
                    Biozentrum University of Basel
                </td>
                <td>
                    <a href="http://www.lin-magdeburg.de"
                       onclick="this.target='_blank'; return true;">
                        Leibniz Institute for Neurobiology
                    </a><br/>
                    Magdeburg
                </td>
                <td>
                    <a href="http://cni.ifn-magdeburg.de"
                       onclick="this.target='_blank'; return true;">
                        Combinatorial Neuroimaging
                    </a><br/>
                    Magdeburg
                </td>
            </tr>

            <!-- Third row -->
            <table class="thirdRow">

                <!-- Logos -->
                <tr>
                    <td class="unf"
                        onclick="javascript:openWindow('https://www3.unifr.ch/')">
                    </td>
                    <td class="miap"
                        onclick="javascript:openWindow('https://miap.uni-freiburg.de')">
                    </td>
                    <td class="unimanchester"
                        onclick="javascript:openWindow('https://www.manchester.ac.uk/')">
                    </td>                    
                    <td class="blank">
                        &nbsp;
                    </td>
                </tr>

                <!-- Captions -->
                <tr class="caption">
                    <td>
                        <a href="https://www3.unifr.ch/bioimage"
                           onclick="this.target='_blank'; return true;">
                            Bioimage | Light Microscopy Facility
                        </a><br/>
                        University of Fribourg
                    </td>
                    <td>
                        <a href="https://miap.uni-freiburg.de"
                           onclick="this.target='_blank'; return true;">
                            Microscopy and Image Analysis Platform
                        </a><br/>
                        University of Freiburg
                    </td>
                    <td>
                        <a href="https://www.bmh.manchester.ac.uk/research/facilities/#bioimaging"
                           onclick="this.target='_blank'; return true;">
                        BioImaging Facility
                        </a><br/>
                        University of Manchester
                    </td>
                    <td>
                        &nbsp;
                    </td>
                </tr>

            </table>

    </div>
</div>
<!-- welcome -->

<?php

include("footer.inc.php");

?>


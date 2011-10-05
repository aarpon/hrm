<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Database.inc.php");
require_once("./inc/hrm_config.inc.php");
require_once("./inc/Fileserver.inc.php");
require_once("./inc/System.inc.php");
require_once("./inc/Validator.inc.php");

global $email_admin;
global $enableUserAdmin;
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
    "password" => "" );

// Username
if ( isset( $_POST["username"] ) ) {
	if ( Validator::isUsernameValid( $_POST["username"] ) ) {
		$clean["username"] = $_POST["username"];
	}
}

// Password
if ( isset( $_POST["password"] ) ) {
	if ( Validator::isPasswordValid( $_POST["password"] ) ) {
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

if ( isset( $_POST['password'] ) && isset( $_POST['username'] ) ) {
	if ( $clean['password'] != "" && $clean['username'] != "" ) {

		// Create a user
		$tentativeUser = new User();
		$tentativeUser ->setName($clean['username']);
		$tentativeUser ->logOut(); // TODO

		if ($tentativeUser->logIn($clean['username'], $clean['password'],
                $_SERVER['REMOTE_ADDR'])) {
			if ($tentativeUser ->isLoggedIn()) {
				// Make sure that the user source and destination folders exist
				{
					$fileServer = new FileServer( $clean['username'] );
					if ( $fileServer->isReachable() == false ) {
						shell_exec("bin/hrm create " . $clean['username'] );
					}
				}

				// TODO unregister also "setting" and "task_setting"
				unset($_SESSION['editor']);

				// Register the user in the session
				$_SESSION['user'] = $tentativeUser;

				if ( $authenticateAgainst == "MYSQL" ) {
					if ( $req != "" ) {
						header("Location: " . $req);
						exit();
					} else {
						// Proceed to home
						header("Location: " . "home.php");
						exit();
					}
				} else {
					// Alternative authentication: proceed to home
					header("Location: " . "home.php");
					exit();
				}
			}
		} else if ( $tentativeUser->isLoginRestrictedToAdmin() ) {
			if ( $tentativeUser->isAdmin() && $tentativeUser->exists() ) {
				$message = "Wrong password";
			} else {
				$message = "Only the administrator is allowed to login " .
                    "in order to perform maintenance";
			}
		} else {
			if ( $tentativeUser->isSuspended()) {
				$message = "Your account has been suspended, please " .
                "contact the administrator";
			} else {
				$message = "Sorry, wrong user name or password";
			}
		}
	} else {
		$message = "Sorry, invalid user name or password";
	}
}

include("header.inc.php");

?>

<div id="nav">
<ul>
	<li>
        <a href="javascript:openWindow(
           'http://huygens-rm.org/home/?q=node/27')">
            <img src="images/whatsnew.png" alt="website" />
            &nbsp;What's new?
        </a>
    </li>
	<li><a href="javascript:openWindow('http://www.huygens-rm.org')">
            <img src="images/logo_small.png" alt="website" />
            &nbsp;Website
        </a>
    </li>
	<li>
        <a href="javascript:openWindow('http://www.svi.nl/FrontPage')">
            <img Src="images/wiki.png" alt="website" />
            &nbsp;SVI wiki
        </a>
    </li>
	<li><a href="javascript:openWindow(
           'http://www.svi.nl/HuygensRemoteManagerHelpLogin')">
            <img src="images/help.png" alt="help" />
            &nbsp;Help
        </a>
    </li>
</ul>
</div>

<div id="content"><?php
// Check that the database is reachable
$db   = new DatabaseConnection( );
if ( !$db->isReachable( ) ) {
	echo "<div class=\"dbOutDated\">Warning: the database is not reachable!\n";
	echo "<p>Please contact your administrator.</p>".
  				  "<p>You will not be allowed to login " .
  				  "until this issue has been fixed.</p></div>";
	echo "</div>\n";
	include("footer.inc.php");
	return;
}
// Check that the hucore version is known
if ( System::huCoreVersion( ) == 0 ) {
	echo "<div class=\"dbOutDated\">Warning: unknown HuCore version!\n";
	echo "<p>Please ask the administrator to start the queue manager.</p>" .
         "<p>You are now allowed to login until this issue has been " .
         "fixed.</p></div>";
	echo "</div>\n";
	include("footer.inc.php");
	return;
}
// Check that hucore is recent enough to run this version of the HRM
if ( System::isMinHuCoreVersion( ) == false ) {
	echo "<div class=\"dbOutDated\">Warning: you need at least HuCore " .
	"version " . System::minHuCoreVersion() . " for HRM " .
    System::getHRMVersion() . "!\n";
	echo "<p>Please contact the administrator.</p></div>";
	echo "</div>\n";
	include("footer.inc.php");
	return;
}
// Check that the database is up-to-date
if ( System::isDBUpToDate( ) == false ) {
	echo "<div class=\"dbOutDated\">Warning: the database is not up-to-date!\n";
	echo "<p>This happens if HRM was recently updated but the " .
  				  "database was not. You are not allowed to login " .
  				  "until this issue has been fixed.</p>";
	echo "<p>Only the administrator can login.</p></div>";
}
?>
<h2>Welcome</h2>

<p class="intro">The <a
	href="javascript:openWindow('http://hrm.sourceforge.net')">Huygens
Remote Manager</a> is an easy to use interface to the Huygens Software
by <a href="javascript:openWindow('http://www.svi.nl')">Scientific
Volume Imaging B.V.</a> that allows for multi-user, large-scale
deconvolution.</p>

<?php
    /*
     * Include user/login_user.inc if it exists. This allows installations
     * to be customized without having to change anything in the HRM code.
     */
    if ( file_exists( "user/login_user.inc" ) == true ) {
        echo "<div id=\"login_user\">\n";
        include "user/login_user.inc";
        echo "</div>";
    }
?>

<div id="logos">
<div class="logo-fmi"><a
	href="javascript:openWindow('http://www.fmi.ch')"><img
	src="images/logo_fmi.png" alt="FMI" /></a>
<p>Friedrich Miescher Institute</p>
<p><a href="javascript:openWindow('http://www.fmi.ch/faim')">Facility
for Advanced Imaging and Microscopy</a></p>
</div>
<div class="logo-mri"><a
	href="javascript:openWindow('http://www.mri.cnrs.fr')"><img
	src="images/logo_mri.png" alt="MRI" /></a>
<p>Montpellier RIO Imaging</p>
</div>
<div class="logo-epfl"><a
	href="javascript:openWindow('http://www.epfl.ch')"><img
	src="images/logo_epfl.png" alt="EPFL" /></a>
<p>Federal Institute of Technology - Lausanne</p>
<p><a href="javascript:openWindow('http://biop.epfl.ch')">BioImaging and
Optics platform</a></p>
</div>
</div>

</div>
<!-- content -->

<div id="rightpanel">
<p />
<div id="login">
<form method="post" action="" autocomplete="off">
    <fieldset>
        <legend>
            <a href="javascript:openWindow(
               'http://www.svi.nl/HuygensRemoteManagerHelpLogin')">
                <img src="images/help.png" alt="?" /></a> Login
        </legend>
    <?php
        if ($authenticateAgainst == "MYSQL") {
            $login_message = "<p class=\"expl\">" .
                    "If you do not have an account, please register " .
                    "<a href=\"registration.php\">here</a>.</p>";
        } else {
            $login_message = "<p class=\"expl\">Please enter your ".
                    "credentials.</p>";
        }
        echo $login_message;
    ?>
        <label for="username">Username:</label>
        <input id="username" name="username" type="text" class="textfield"
               tabindex="1" /> <br />
        <label for="password">Password:</label>
        <input id="password" name="password" type="password" class="textfield"
               tabindex="2" /> <br />
        <input type="hidden" name="request" value="<?php echo $req ?>" />
        <input type="submit" class="button" value="login" />
    </fieldset>
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
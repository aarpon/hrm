<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc");
require_once("./inc/Database.inc"); // for account management (email & last_access fields)
require_once("./inc/CreditOwner.inc");
require_once("./inc/hrm_config.inc");
require_once("./inc/Fileserver.inc");
require_once("./inc/versions.inc");

global $email_admin;
global $enableUserAdmin;
global $use_accounting_system;
global $authenticateAgainst;

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

session_start();
if (isset($_SESSION['request'])) {
    $req = $_SESSION['request'];
} else {
    $req = false;
}
if (isset($_POST['request'])) {
    $req = $_POST['request'];
}

/* Reset all! */
session_unset();
session_destroy();

session_start();

$_SESSION['user'] = new User();


if (isset($_POST['password'])) {
  $_SESSION['user']->setName(strtolower($_POST['username']));
  $_SESSION['user']->logOut(); // TODO
  
  if ($_SESSION['user']->logIn(strtolower($_POST['username']), $_POST['password'], $_SERVER['REMOTE_ADDR'])) {
  	if ($use_accounting_system) {
		$creditOwner = new CreditOwner($_SESSION['user']->name());
		$positiveCredits = $creditOwner->positiveCredits();
		if (count($positiveCredits) == 0) {
			$_SESSION['user']->logOut();
			$message = "You don't have any hours left.<br>Please contact the microscopy team!";		
		}
	}
  	if ($_SESSION['user']->isLoggedIn()) {
            $_SESSION['user']->setName(strtolower($_POST['username']));

            // Make sure that the user source and destination folders exist
            {
              $fileServer = new FileServer( strtolower($_POST['username']) );
              if ( $fileServer->isReachable() == false ) {
                shell_exec("bin/hrm create " . $_POST['username']);
              }
            }
            
            if ( $authenticateAgainst == "MYSQL" ) {
              // account management
              // get email address and group
              $_SESSION['user']->load();
              // update last access date
              $_SESSION['user']->updateLastAccessDate();
              #$_SESSION['registered_user'] = $_SESSION['user'];
              // TODO unregister also "setting" and "task_setting"
              unset($_SESSION['editor']);
              if ($use_accounting_system) {
                  if (count($positiveCredits)>1) {
                          header("Location: " . "select_credit.php"); exit();				
                  }
                  $firstCredit = $positiveCredits[0];
                  $groups = $creditOwner->myGroupsForCredit($firstCredit);
                  if (count($positiveCredits)==1 && count($groups)>1) {
                          $_SESSION['credit'] = $firstCredit->id();
                          header("Location: " . "select_group.php"); exit();
                  }
                  if (count($positiveCredits)==1 && count($groups)==1) {
                          $_SESSION['credit'] = $firstCredit->id();
                          $_SESSION['group'] = $groups[0]->id();
                  }
              }
              if ( $req != false ) {
                  header("Location: " . $req); 
                  exit();
                }
              else {
                  // Proceed to home
                  header("Location: " . "home.php");
                  exit();
              }
            } else {
                // Proceed to home
                header("Location: " . "home.php");
                exit();
            }
  	}
  } else if ($_SESSION['user']->isLoginRestrictedToAdmin()) {
    if ( ( strcmp($_SESSION['user']->name( ), 'admin' ) == 0 ) && ($_SESSION['user']->exists()) ) {
      $message = "            <p class=\"warning\">Wrong password.</p>\n";
    } else {
      $message = "            <p class=\"warning\">Only the administrator is allowed to login in order to perform maintenance.</p>\n";
    }
  } else {
    if ($_SESSION['user']->isSuspended()) {
      $message = "            <p class=\"warning\">Your account has been suspended, please contact the administrator.</p>\n";
    }
    else {
      $message = "            <p class=\"warning\">Sorry, wrong user name or password.</p>\n";
    }
  }
}

include("header.inc.php");

?>

    <div id="nav">
        <ul>
            <li><a href="javascript:openWindow('http://huygens-rm.org/wiki/index.php?title=Changelog')"><img src="images/whatsnew.png" alt="website" />&nbsp;What's new?</a></li>
            <li><a href="javascript:openWindow('http://www.huygens-rm.org')"><img src="images/logo_small.png" alt="website" />&nbsp;Website</a></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki')"><img src="images/wiki.png" alt="website" />&nbsp;SVI wiki</a></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpLogin')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li> 
        </ul>
    </div>
    
    <div id="content">
    
        <?php
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
          // Check that the database is up-to-date
          if ( Versions::isDBUpToDate( ) == false ) {
            echo "<div class=\"dbOutDated\">Warning: the database is not up-to-date!\n";
            echo "<p>This happens if HRM was recently updated but the " . 
                  "database was not. You are now allowed to login " .
                  "until this issue has been fixed.</p>";
            echo "<p>Only the administrator can login.</p></div>";
          }  
        ?>
        <h2>Welcome</h2>
        
        <p class="intro">
	    The <a href="javascript:openWindow('http://hrm.sourceforge.net')">Huygens
	    Remote Manager</a> is an easy to use interface to the
            Huygens Software by 
            <a href="javascript:openWindow('http://www.svi.nl')">Scientific 
            Volume Imaging B.V.</a> that allows for multi-user, large-scale deconvolution.
        </p>
        
        <div id="logos">
            <div class="logo-fmi">
                <a href="javascript:openWindow('http://www.fmi.ch')"><img src="images/logo_fmi.png" alt="FMI" /></a>
                <p>Friedrich Miescher Institute</p>
                <p><a href="javascript:openWindow('http://www.fmi.ch/faim')">Facility for Advanced Imaging and Microscopy</a></p>
            </div>
            <div class="logo-mri">
                <a href="javascript:openWindow('http://www.mri.cnrs.fr')"><img src="images/logo_mri.png" alt="MRI" /></a>
                <p>Montpellier RIO Imaging</p>
            </div>
            <div class="logo-epfl">
                <a href="javascript:openWindow('http://www.epfl.ch')"><img src="images/logo_epfl.png" alt="EPFL" /></a>
                <p>Federal Institute of Technology - Lausanne</p>
                <p><a href="javascript:openWindow('http://biop.epfl.ch')">BioImaging and Optics platform</a></p>
            </div>
        </div>

    </div> <!-- content -->
    
    <div id="rightpanel">
	<p />    
	<div id="login">
            <form method="post" action="">
                <fieldset>
                    <legend>
                        <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpLogin')"><img src="images/help.png" alt="?"/></a>
                        Login
                    </legend>
                    <?php
                    if ( $authenticateAgainst == "MYSQL" ) {
                      $login_message = "<p class=\"expl\">If you do not have an account, please register <a href=\"registration.php\">here</a>.</p>";
                    } else {
                      $login_message = "<p class=\"expl\">Please enter your credentials.</p>";
                    }
                    echo $login_message;
                    ?>
                    <label for="username">Username:</label>
                    <input id="username" name="username" type="text" class="textfield" tabindex="1" />
                    <br />
                    <label for="password">Password:</label>
                    <input id="password" name="password" type="password" class="textfield" tabindex="2" />
                    <br />
                    <input type="hidden" name="request" value="<?php echo $req?>" />
                    <input type="submit" class="button" value="login" />
                </fieldset>
            </form>
        </div>
        
        <div id="message">
<?php

echo $message;

?>
        </div>
     
    </div> <!-- rightpanel -->

<?php

include("footer.inc.php");

?>

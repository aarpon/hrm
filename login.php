<?php

// php page: login.php

// This file is part of huygens remote manager.

// Copyright: Montpellier RIO Imaging (CNRS)

// contributors :
// 	     Pierre Travo	(concept)
// 	     Volker Baecker	(concept, implementation)

// email:
// 	pierre.travo@crbm.cnrs.fr
// 	volker.baecker@crbm.cnrs.fr

// Web:     www.mri.cnrs.fr

// huygens remote manager is a software that has been developed at 
// Montpellier Rio Imaging (mri) in 2004 by Pierre Travo and Volker 
// Baecker. It allows running image restoration jobs that are processed 
// by 'Huygens professional' from SVI. Users can create and manage parameter 
// settings, apply them to multiple images and start image processing 
// jobs from a web interface. A queue manager component is responsible for 
// the creation and the distribution of the jobs and for informing the user 
// when jobs finished.

// This software is governed by the CeCILL license under French law and 
// abiding by the rules of distribution of free software. You can use, 
// modify and/ or redistribute the software under the terms of the CeCILL 
// license as circulated by CEA, CNRS and INRIA at the following URL 
// "http://www.cecill.info".

// As a counterpart to the access to the source code and  rights to copy, 
// modify and redistribute granted by the license, users are provided only 
// with a limited warranty and the software's author, the holder of the 
// economic rights, and the successive licensors  have only limited 
// liability.

// In this respect, the user's attention is drawn to the risks associated 
// with loading, using, modifying and/or developing or reproducing the 
// software by the user in light of its specific status of free software, 
// that may mean that it is complicated to manipulate, and that also 
// therefore means that it is reserved for developers and experienced 
// professionals having in-depth IT knowledge. Users are therefore encouraged 
// to load and test the software's suitability as regards their requirements 
// in conditions enabling the security of their systems and/or data to be 
// ensured and, more generally, to use and operate it in the same conditions 
// as regards security.

// The fact that you are presently reading this means that you have had 
// knowledge of the CeCILL license and that you accept its terms.

require_once("./inc/User.inc");
require_once("./inc/Database.inc"); // for account management (email & last_access fields)
require_once("./inc/CreditOwner.inc");
require_once("./inc/hrm_config.inc");

global $email_admin;
global $enableUserAdmin;
global $use_accounting_system;

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

/* Reset all! */
session_start();
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
            if ($_SESSION['user']->isAdmin()) {
                if ($enableUserAdmin) {
                    header("Location: " . "user_management.php"); exit();
                }
                else {
                    header("Location: " . "select_parameter_settings.php"); exit();
                }
            } else {
                header("Location: " . "select_parameter_settings.php"); exit();
            }
  	}
  } else {
    if ($_SESSION['user']->isSuspended()) {
      $message = "            <p class=\"warning\">Your account has been suspended, please contact the administrator.</p>\n";
    }
    else if ($_SESSION['user']->exists()) {
      $message = "            <p class=\"warning\">This username/password combination does not match, please try again.</p>\n";
    }
    else {
      $message = "            <p class=\"warning\">This account does not exist, please use the link above to register.</p>\n";
    }
  }
}

include("header.inc.php");

?>

    <div id="nav">
        <ul>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpLogin')">help</a></li> 
            <li><a href="about.php">about</a></li>
            <li><a href="last_changes.php">changes</a></li>
        </ul>
    </div>
    
    <div id="content">
    
        <h2>Welcome</h2>
        
        <p>
	    <a href="javascript:openWindow('http://hrm.sourceforge.net')">Huygens
	    Remote Manager</a> is an easy to use interface to the
            Huygens Software by 
            <a href="javascript:openWindow('http://www.svi.nl')">Scientific 
            Volume Imaging B.V.</a> that allows for multi-user, large-scale deconvolution.
        </p>

	<div id="logos">
            <div class="logo-fmi">
                <a href="javascript:openWindow('http://www.fmi.ch')"><img src="images/logo_fmi.png" alt="FMI" /></a>
                <p>Friedrich Miescher Institute</p>
                <p><a href="javascript:openWindow('http://www.fmi.ch/html/technical_resources/microscopy/database/home.html')">Facility for Advanced Imaging and Microscopy</a></p>
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
    
    <div id="stuff">
    
        <div id="login">
            <form method="post" action="">
                <fieldset>
                    <legend>
                        <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpLogin')"><img src="images/help.png" alt="?"/></a>
                        Login
                    </legend>
                    <?php
                    if ( $enableUserAdmin == True ) {
                      $login_message = "<p>If you do not have an account, please register <a href=\"registration.php\">here</a>.</p>";
                    } else {
                      $login_message = "<p>If you do not have an account, please contact your administrator.</p>";
                    }
                    echo $login_message;
                    ?>
                    <label for="username">Name:</label>
                    <input id="username" name="username" type="text" class="textfield" tabindex="1" />
                    <br />
                    <label for="password">Password:</label>
                    <input id="password" name="password" type="password" class="textfield" tabindex="2" />
                    <br />
                    <input type="submit" class="button" value="login" />
                </fieldset>
            </form>
        </div>
        
        <div id="message">
<?php

echo $message;

?>
        </div>
        
        <div id="linklist">
        
<?php

if (isset($internal_link)) {

?>
            <h3>Internal Links</h3>
            <ul>
<?php

  foreach ($internal_link as $url => $link) {

?>
                <li><a href="javascript:openWindow('<?php echo $url ?>')"><?php echo substr($link, strpos($link, '<') + 1, strpos($link, '>') - strpos($link, '<') - 1) ?></a><?php echo substr($link, strpos($link, '>') + 1) ?></li>
<?php

  }

?>
            </ul>
            
<?php

}

if (isset($external_link)) {

?>
            <h3>External Links</h3>
            <ul>
<?php

  foreach ($external_link as $url => $link) {

?>
                <li><a href="javascript:openWindow('<?php echo $url ?>')"><?php echo substr($link, strpos($link, '<') + 1, strpos($link, '>') - strpos($link, '<') - 1) ?></a><?php echo substr($link, strpos($link, '>') + 1) ?></li>
<?php

  }

?>
            </ul>
            
<?php

  }

?>

        </div>
        
    </div> <!-- stuff -->

<?php

include("footer.inc.php");

?>

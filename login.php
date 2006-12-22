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

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

$user = new User();

if (isset($_POST['password'])) {
  $user->setName(strtolower($_POST['username']));
  $user->logOut(); // TODO
  if ($user->logIn(strtolower($_POST['username']), $_POST['password'], $_SERVER['REMOTE_ADDR'])) {
    session_start();
    session_register("user");
    $user->setName(strtolower($_POST['username']));
    // account management
    $db = new DatabaseConnection();
    // get email address
    if ($user->name() == "admin") {
      $user->setEmail($email_admin);
      $db->execute("UPDATE user SET email = '".$email_admin."' WHERE name = 'admin'");
    }
    else {
      $user->setEmail($user->emailAddress());
    }
    // get user group
    $user->setGroup($db->queryLastValue("SELECT research_group FROM user WHERE name= '".$user->name()."'"));
    $_SESSION['user'] = $user;
    // update last access date
    $db->execute("UPDATE user SET last_access_date = CURRENT_TIMESTAMP WHERE name = '".$user->name()."'");
    // TODO unregister also "setting" and "task_setting"
    unset($_SESSION['editor']);
    if ($user->name() == "admin") {
      header("Location: " . "user_management.php"); exit();
    }
    else {
      header("Location: " . "select_parameter_settings.php"); exit();
    }
  } else {
    $message = "            <p class=\"warning\">This account does not exist, please try again!</p>\n";
  }
}

include("header.inc.php");

?>

    <div id="nav">
        <ul>
            <li><a href="javascript:openWindow('help/helpLoginPage.html')">help</a></li> 
            <li><a href="about.php">about</a></li>
            <li><a href="last_changes.php">changes</a></li>
        </ul>
    </div>
    
    <div id="content">
    
        <h2>HRM - Huygens Remote Manager</h2>
        
        <h3>Welcome</h3>
        <p>
            Welcome to the remote image restoration interface. HRM lets you 
            process large-scale deconvolution of multiple images using 
            Huygens Software by 
            <a href="javascript:openWindow('http://www.svi.nl')">Scientific 
            Volume Imaging B.V.</a>
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
                        <a href="javascript:openWindow('help/helpLoginPage.html')"><img src="images/help.png" alt="?"/></a>
                        Login
                    </legend>
                    <p>If you do not have an account, please register <a href="registration.php">here</a>.</p>
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
        
            <h3>Internal Links</h3>
            <ul>
                <li><a href="javascript:openWindow('http://svintranet.epfl.ch')">Faculté SV - Intranet</a></li>
            </ul>
            
            <h3>External Links</h3>
            <ul>
                <li><a href="javascript:openWindow('http://www.svi.nl')">Scientific Volume Imaging B.V.</a></li>
                <li><a href="javascript:openWindow('http://support.svi.nl/wiki')">SVI-wiki</a> on 3D microscopy, deconvolution, visualization and analysis</li>
            </ul>
            
        </div>
        
    </div> <!-- stuff -->

<?php

include("footer.inc.php");

?>
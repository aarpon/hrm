<?php

// php page: update.php

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
require_once("./inc/Database.inc");

session_start();

$db = new DatabaseConnection();

if (isset($_GET['home'])) {
  header("Location: " . "home.php"); exit();
}

if (isset($_GET['seed'])) {
  $query = "SELECT status FROM username WHERE status = '".$_GET['seed']."'";
  if ($db->queryLastValue($query) != $_GET['seed']) {
    header("Location: " . "login.php"); exit();
  }
  else {
    $admin = new User();
    $admin->isLoggedIn = True;
    $admin->lastActivity = time();
    $admin->name = "admin";
    if (isset($_SERVER['REMOTE_ADDR'])) $admin->ip = $_SERVER["REMOTE_ADDR"];
    else $admin->ip = $HTTP_SERVER_VARS["REMOTE_ADDR"];
    # session_register("user");
    $_SESSION['user'] = $admin;
  }
}

else if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn() || $_SESSION['user']->name() != "admin") {
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
        <ul>
            <li><?php echo $_SESSION['user']->name(); ?></li>
            <li><a href="<?php echo getThisPageName();?>?home=home" onclick="clean()"><img src="images/restart_help.png" alt="home" />&nbsp;Home</a></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpUpdate')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
        <h3>Update</h3>
        
        <fieldset>
            <legend>log</legend>
            <textarea rows="15" readonly="readonly">
<?php

//include("changelog");
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
                New HRM releases are available from the project <a href="javascript:openWindow('http://sourceforge.net/projects/hrm')">website</a>.
            </p>
            
            <br>
            
            <form method="GET" action="" id="dbupdate">
                <input type="hidden" name="action" value="dbupdate">
            </form>
            
            <input type="button" name="" value="database update" onclick="document.forms['dbupdate'].submit()" />
            
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

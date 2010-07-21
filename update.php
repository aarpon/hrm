<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc");
require_once("./inc/Database.inc");
require_once("./inc/Util.inc");

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
            <li><a href="<?php echo getThisPageName();?>?home=home" onclick="clean()"><img src="images/home.png" alt="home" />&nbsp;Home</a></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpUpdate')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
        <h3>Update</h3>
        
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

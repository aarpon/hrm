<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc");
require_once("./inc/Util.inc");

session_start();

if (isset($_GET['home'])) {
  header("Location: " . "home.php"); exit();
}

// The admin must be logged on
if ( ( !isset( $_SESSION[ 'user' ] ) ) || ( !$_SESSION[ 'user' ]->isAdmin() ) ) {
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
            <li><img src="images/user.png" alt="user" />&nbsp;<?php echo $_SESSION['user']->name(); ?></li>
            <li><a href="<?php echo getThisPageName();?>?home=home" onclick="clean()"><img src="images/home.png" alt="home" />&nbsp;Home</a></li>
            <li><a href="javascript:openWindow('http://www.svi.nl/HuygensRemoteManagerHelpUpdate')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
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

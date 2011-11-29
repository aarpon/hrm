<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/hrm_config.inc.php");
require_once("./inc/Fileserver.inc.php");
require_once("./inc/System.inc.php");

global $email_admin;
global $enableUserAdmin;
global $authenticateAgainst;

session_start();

if (isset($_GET['home'])) {
  header("Location: " . "home.php"); exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

// The admin user should be redirected to the source folder
if ( $_SESSION['user']->isAdmin() ) {
    header("Location: " . "file_management.php?folder=src"); exit();
}

// Keep track of who the referer is: the file_manager will allow returning
// to some selected pages
if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
    if ( strpos( $_SERVER['HTTP_REFERER'], 'home.php' ) ||
         strpos( $_SERVER['HTTP_REFERER'], 'select_parameter_settings.php' ) ||
         strpos( $_SERVER['HTTP_REFERER'], 'select_task_settings.php' ) ||
         strpos( $_SERVER['HTTP_REFERER'], 'select_images.php' ) ||
         strpos( $_SERVER['HTTP_REFERER'], 'create_job.php' ) ) {
        $_SESSION['filemanager_referer'] = $_SERVER['HTTP_REFERER'];
    }
}

$message = "";

// Javascript includes
$script = array( "settings.js", "quickhelp/help.js",
                "quickhelp/fileManagerHelp.js" );

include("header.inc.php");
?>

    <div id="nav">
        <ul>
			<li>
                <img src="images/user.png" alt="user" />
                &nbsp;<?php echo $_SESSION['user']->name(); ?>
            </li>
            <?php
            if ( isset( $_SESSION['filemanager_referer'] ) ) {
                $referer = $_SESSION['filemanager_referer'];
                if ( strpos( $referer, 'home.php' ) === False ) {
            ?>
            <li>
                <a href="<?php echo $referer;?>">
                    <img src="images/back_small.png" alt="back" />&nbsp;Back</a>
            </li>
            <?php
                }
            }
            ?>
            <li>
                <a href="<?php echo getThisPageName();?>?home=home">
                    <img src="images/home.png" alt="home" />
                    &nbsp;Home
                </a>
            </li>
            <li>
                <a href="javascript:openWindow('#')">
                    <img src="images/help.png" alt="help" />
                    &nbsp;Help
                </a>
            </li>
        </ul>
    </div>

    <div id="filemanager">
        <h3><img src="images/filemanager_small.png" alt="File manager" />
            &nbsp;File manager</h3>
        <div id="left"><a href="./file_management.php?folder=src">
            <img src="images/raw.png" alt="raw"
                onmouseover="javascript:changeQuickHelp( 'raw' );"
                onmouseout="javascript:changeQuickHelp( 'default' );" /></a>
        </div>
        <div id="right"><a href="./file_management.php?folder=dest">
            <img src="images/deconvolved.png" alt="deconvolved"
                onmouseover="javascript:changeQuickHelp( 'deconvolved' );"
                onmouseout="javascript:changeQuickHelp( 'default' );" /></a>
        </div>
        <div id="bottom">
            <div id="contextHelp">
                <p>Transfer, view and manage your images.</p>
            </div>
        </div>
    </div>

<?php

include("footer.inc.php");

?>

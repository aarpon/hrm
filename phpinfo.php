<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");

session_start();

if (isset($_GET['home'])) {
  header("Location: " . "home.php"); exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn() || !$_SESSION['user']->isAdmin()) {
  header("Location: " . "login.php"); exit();
}

if (isset($_SERVER['HTTP_REFERER'])) {
  $_SESSION['referer'] = $_SERVER['HTTP_REFERER'];
}

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

include("header.inc.php");

// Get and parse the phpinfo() output
ob_start();
phpinfo();
$phpinfo = ob_get_contents();
ob_end_clean();
$matches = array();
// Get the body content
preg_match ('%<body>(.*?)</body>%s', $phpinfo, $matches);
$info = $matches[ 1 ];

// "Resize" tables
$info = str_replace( 'width="600"', 'width="100%"', $info );

// Correct the HTML
$info = preg_replace('%<font(.*?)>%s', "", $info );
$info = preg_replace('%</font>%s', "", $info );
$info = preg_replace('%<img border="0" src=%s', "<img src=", $info );

?>
    <div id="nav">
        <ul>
            <li><img src="images/user.png" alt="user" />&nbsp;<?php echo $_SESSION['user']->name(); ?></li>
            <li><a href="<?php echo getThisPageName();?>?home=home"><img src="images/home.png" alt="home" />&nbsp;Home</a></li>
            <li><a href="javascript:openWindow('http://www.svi.nl/HuygensRemoteManagerHelpSystemSummary')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
    </div>

    <div id="content">

      <h3>Extended system summary</h3>

      <div id="phpinfo">
        <?php echo $info; ?>
      </div> <!-- system -->

    </div> <!-- content -->

    <div id="rightpanel">

        <div id="info">

          <h3>Quick help</h3>

          <p>This page displays extended information about your PHP
              installation.</p>

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

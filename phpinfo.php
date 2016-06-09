<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Nav;

require_once dirname(__FILE__) . '/inc/bootstrap.php';

session_start();

if (isset($_GET['home'])) {
  header("Location: " . "home.php"); exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn() ||
        !$_SESSION['user']->isAdmin()) {
  header("Location: " . "login.php"); exit();
}

if (isset($_SERVER['HTTP_REFERER'])) {
  $_SESSION['referer'] = $_SERVER['HTTP_REFERER'];
}

$message = "";

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
    <div id="navleft">
        <ul>
            <?php
                echo(Nav::linkWikiPage('HuygensRemoteManagerHelpSystemSummary'));
            ?>
        </ul>
    </div>
    <div id="navright">
        <ul>
            <?php
                echo(Nav::textUser($_SESSION['user']->name()));
                echo(Nav::linkBack($_SESSION['referer']));
                echo(Nav::linkHome(getThisPageName()));
            ?>
        </ul>
    </div>
    <div class="clear"></div>
</div>

    <div id="content">

      <h3><img alt="Summary" src="./images/system_title.png" width="40"/>
          &nbsp;Extended system summary</h3>

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

echo "<p>$message</p>";

?>
        </div>

    </div> <!-- rightpanel -->

<?php

include("footer.inc.php");

?>

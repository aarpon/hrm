<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc");
require_once("./inc/Util.inc");
require_once("./inc/Database.inc");
require_once("./inc/hrm_config.inc");

session_start();

$db = new DatabaseConnection();

if (isset($_GET['home'])) {
  header("Location: " . "home.php"); exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn() || $_SESSION['user']->name() != "admin") {
  header("Location: " . "login.php"); exit();
}

if (isset($_SERVER['HTTP_REFERER']) && !strstr($_SERVER['HTTP_REFERER'], 'account')) {
  $_SESSION['referer'] = $_SERVER['HTTP_REFERER'];
}

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

include("header.inc.php");

?>
    <div id="nav">
        <ul>
            <li><?php echo $_SESSION['user']->name(); ?></li>
            <li><a href="<?php echo getThisPageName();?>?home=home"><img src="images/home.png" alt="home" />&nbsp;Home</a></li>
            <li><a href="javascript:openWindow('#')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
    </div>
    
    <div id="content">

        <!-- // TODO: use css for styling tables below -->
        
        <h3>System summary</h3>

        <h4>Huygens Core</h4>
        <table style="font-size:75%">
          <tbody>
            <tr>
              <td style="width:60%;text-align:left;">
                HuCore version
              </td>
              <td style="width:40%;text-align:left;">
                <?php echo getHuCoreVersionAsString( $db->getHuCoreVersion() ); ?>
              </td>
            </tr>
          </tbody>
        </table>
        
        <h4>Database</h4>
        <table style="font-size:75%">
          <tbody>
            <tr>
              <td style="width:60%;text-align:left;">
                Database type
              </td>
              <td style="width:40%;text-align:left;">
                <?php echo $db->type(); ?>
              </td>
            </tr>
            <tr>
              <td style="width:60%;text-align:left;">
                Database version
              </td>
              <td style="width:40%;text-align:left;">
                <?php echo $db->version(); ?>
              </td>
            </tr>
          </tbody>
        </table>

        <h4>PHP</h4>
        <table style="font-size:75%">
          <tbody>
            <tr>
              <td style="width:60%;text-align:left;">
                PHP version
              </td>
              <td style="width:40%;text-align:left;">
                <?php echo phpversion( ); ?>
              </td>
            </tr>
          </tbody>
        </table>

        <h4>Memory and file sizes</h4>
        <table style="font-size:75%">
          <tbody>
            <tr>
              <td style="width:60%;text-align:left;">
                Memory limit (php.ini)
              </td>
              <td style="width:40%;text-align:left;">
                <?php echo ini_get( 'memory_limit' ); ?>
              </td>
            </tr>
            <tr>
              <td style="width:60%;text-align:left;">
                Maximum post size (php.ini)
              </td>
              <td style="width:40%;text-align:left;">
                <?php echo ini_get( 'post_max_size' ); ?>
              </td>
            </tr>
            <tr>
              <td style="width:60%;text-align:left;">
                Maximum post size (configuration)
              </td>
              <td style="width:40%;text-align:left;">
                <?php
                if ( isset( $max_post_limit ) ) {
                  if ( $max_post_limit == 0 ) {
                    echo "Limited by php.ini.";
                  } else {
                    echo $max_post_limit . "M";
                  }
                } else {
                  echo "Not defined!";
                }
                ?>
              </td>
            </tr>
            <tr>
              <td style="width:60%;text-align:left;">
                Maximum upload file size (php.ini)
              </td>
              <td style="width:40%;text-align:left;">
                <?php echo ini_get( 'upload_max_filesize' ); ?>
              </td>
            </tr>
            <tr>
              <td style="width:60%;text-align:left;">
                Maximum post size (configuration)
              </td>
              <td style="width:40%;text-align:left;">
                <?php
                if ( isset( $max_upload_limit ) ) {
                  if ( $max_upload_limit == 0 ) {
                    echo "Limited by php.ini.";
                  } else {
                    echo $max_upload_limit . "M";
                  }
                } else {
                  echo "Not defined!";
                }
                ?>
              </td>
            </tr>
          </tbody>
        </table>

    </div> <!-- content -->
    
    <div id="rightpanel">
    
        <div id="info">

          <h3>Quick help</h3>
          
          <p>This page (in construction) displays information about your installation.</p>
          
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

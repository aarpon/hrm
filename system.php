<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc");
require_once("./inc/System.inc");

session_start();

if (isset($_GET['home'])) {
  header("Location: " . "home.php"); exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn() || $_SESSION['user']->name() != "admin") {
  header("Location: " . "login.php"); exit();
}

if (isset($_SERVER['HTTP_REFERER'])) {
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

      <h3>System summary</h3>

      <div id="system">
        <table>
          <tr>
            <td class="section">System</td>
            <td class="value">&nbsp;</td>
          </tr>
          <tr>
            <td class="key">Operating system</td>
            <td class="value"><?php echo System::operatingSystem(); ?></td>
          </tr>
          <tr>
            <td class="key">Kernel release</td>
            <td class="value"><?php echo System::kernelRelease(); ?></td>
          </tr>
          <tr>
            <td class="section">Huygens</td>
            <td class="value">&nbsp;</td>
          </tr>
          <tr>
            <td class="key">HuCore version</td>
            <td class="value"><?php echo System::huCoreVersion(); ?></td>
          </tr>
          <tr>
            <td class="section">LAMP versions</td>
            <td class="value">&nbsp;</td>
          </tr>
          <tr>
            <td class="key">Apache version</td>
            <td class="value"><?php echo System::apacheVersion(); ?></td>
          </tr>
          <tr>
            <td class="key">Database type and version</td>
            <td class="value"><?php echo System::databaseType() . ' ' .
              System::databaseVersion(); ?></td>
          </tr>
          <tr>
            <td class="key">PHP (Apache mod) version</td>
            <td class="value"><?php echo System::phpVersion(); ?></td>
          </tr>
          <tr>
            <td class="section">Configuration</td>
            <td class="value">&nbsp;</td>
          </tr>
          <tr>
            <td class="key">Memory limit</td>
            <td class="value"><?php echo System::memoryLimit(); ?></td>
          </tr>
          <tr>
            <td class="key">Maximum post size (php.ini)</td>
            <td class="value"><?php echo System::postMaxSizeFromIni(); ?></td>
          </tr>
          <tr>
            <td class="key">Maximum post size (HRM configuration)</td>
            <td class="value"><?php echo System::postMaxSizeFromConfig(); ?></td>
          </tr>
          <tr>
            <td class="key">Maximum post size (in use)</td>
            <td class="value"><?php echo System::postMaxSize(); ?></td>
          </tr>
          <tr>
            <td class="key">File uploads (HRM configuration)</td>
            <td class="value"><?php echo System::uploadEnabledFromConfig(); ?></td>
          </tr>
          <tr>
            <td class="key">Maximum upload file size (php.ini)</td>
            <td class="value"><?php echo System::uploadMaxFileSizeFromIni(); ?></td>
          </tr>
          <tr>
            <td class="key">Maximum upload file size (HRM configuration)</td>
            <td class="value"><?php echo System::uploadMaxFileSizeFromConfig(); ?></td>
          </tr>
          <tr>
            <td class="key">Maximum upload file size (in use)</td>
            <td class="value"><?php echo System::uploadMaxFileSize(); ?></td>
          </tr>
        </table>
      </div> <!-- system -->
      
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

<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/System.inc.php");

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

$message = "";

include("header.inc.php");

?>
    <div id="nav">
        <ul>
            <li><img src="images/user.png" alt="user" />&nbsp;<?php echo $_SESSION['user']->name(); ?></li>
            <li><a href="<?php echo getThisPageName();?>?home=home"><img src="images/home.png" alt="home" />&nbsp;Home</a></li>
            <li><a href="javascript:openWindow('http://www.svi.nl/HuygensRemoteManagerHelpSystemSummary')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
    </div>

    <div id="content">

      <h3>System summary</h3>

      <div id="system">
        <table>
          <tr>
            <td class="section">Huygens Remote Manager</td>
            <td class="value">&nbsp;</td>
          </tr>
          <tr>
            <td class="key">HRM version</td>
            <td class="value"><?php echo System::getHRMVersion(); ?></td>
          </tr>
          <tr>
            <td class="key">HRM required database version</td>
            <td class="value"><?php echo System::getDBLastRevision(); ?></td>
          </tr>
          <tr>
            <td class="key">HRM current database version</td>
            <td class="value"><?php echo System::getDBCurrentRevision(); ?></td>
          </tr>
          <tr>
            <td class="key">HuCore version</td>
            <td class="value"><?php echo System::huCoreVersionAsString(); ?></td>
          </tr>
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
            <td class="key">File downloads</td>
            <td class="value">&nbsp;</td>
          </tr>
          <tr>
            <td class="subkey">HRM configuration</td>
            <td class="value"><?php echo System::downloadEnabledFromConfig(); ?></td>
          </tr>
          <tr>
            <td class="key">Maximum post size</td>
            <td class="value">&nbsp;</td>
          </tr>
          <tr>
            <td class="subkey">php.ini</td>
            <td class="value"><?php echo System::postMaxSizeFromIni(); ?></td>
          </tr>
          <tr>
            <td class="subkey">HRM configuration</td>
            <td class="value"><?php echo System::postMaxSizeFromConfig(); ?></td>
          </tr>
          <tr>
            <td class="subkey">in use</td>
            <td class="value"><?php echo System::postMaxSize(); ?></td>
          </tr>
          <tr>
            <td class="key">File uploads</td>
            <td class="value">&nbsp;</td>
          </tr>
          <tr>
            <td class="subkey">HRM configuration</td>
            <td class="value"><?php echo System::uploadEnabledFromConfig(); ?></td>
          </tr>
          <tr>
            <td class="key">Maximum upload file size</td>
            <td class="value">&nbsp;</td>
          </tr>
          <tr>
            <td class="subkey">php.ini</td>
            <td class="value"><?php echo System::uploadMaxFileSizeFromIni(); ?></td>
          </tr>
          <tr>
            <td class="subkey">HRM configuration</td>
            <td class="value"><?php echo System::uploadMaxFileSizeFromConfig(); ?></td>
          </tr>
          <tr>
            <td class="subkey">in use</td>
            <td class="value"><?php echo System::uploadMaxFileSize(); ?></td>
          </tr>
          <tr>
            <td class="section">Extended PHP configuration</td>
            <td class="value">&nbsp;</td>
          </tr>
          <tr>
            <td class="subkey"><a href="./phpinfo.php">Display php info</a></td>
            <td class="value">&nbsp;</td>
          </tr>
        </table>

      </div> <!-- system -->

    </div> <!-- content -->

    <div id="rightpanel">

        <div id="info">

          <h3>Quick help</h3>

          <p>This page displays information about your installation and server.</p>

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

<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Nav;
use hrm\System;
use hrm\Util;

require_once dirname(__FILE__) . '/inc/bootstrap.php';


session_start();

if (isset($_GET['home'])) {
    header("Location: " . "home.php");
    exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn() || !$_SESSION['user']->isAdmin()) {
    header("Location: " . "login.php");
    exit();
}

if (isset($_SERVER['HTTP_REFERER'])) {
    $_SESSION['referer'] = $_SERVER['HTTP_REFERER'];
}

$message = "";

// Javascript includes
$script = array("ajax_utils.js", "json-rpc-client.js");

include("header.inc.php");

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
            echo(Nav::linkHome(Util::getThisPageName()));
            ?>
        </ul>
    </div>
    <div class="clear"></div>
</div>


<div id="content">

    <h3><img alt="Summary" src="./images/system_title.png" width="40"/>&nbsp;System
        summary</h3>

    <div id="system">

        <?php
        // Check if we have the latest HRM version
        $latestVersion = System::getLatestHRMVersionFromRemoteAsInteger();
        if ($latestVersion != -1 &&
            System::getHRMVersionAsInteger() < $latestVersion
        ) {
            ?>
            <p class="updateNotification">
                <a href="javascript:openWindow(
                'http://huygens-rm.org/home/?q=node/4')">
                    <img src="images/check_for_update.png" alt="Version check"/>
                    &nbsp;&nbsp
                    A newer version of HRM
                    <?php
                    echo "(" . System::getHRMVersionAsString($latestVersion) . ")";
                    ?>
                    is available!</a></p>
            <?php
        } else {
            ?>
            <p class="noUpdateNotification">
                <img src="images/check_for_update.png" alt="Version check"/>&nbsp;&nbsp
                Congratulations, you are running the latest version of HRM!</p>
            <?php
        }
        ?>

        <table>
            <tr>
                <td class="section">
                    Huygens Remote Manager
                </td>
                <td class="value">
                    &nbsp;
                </td>
            </tr>
            <tr>
                <td class="key">
                    HRM version
                </td>
                <td class="value">
                    <?php echo System::getHRMVersionAsString(); ?>
                </td>
            </tr>
            <tr>
                <td class="key">
                    HRM required database version
                </td>
                <td class="value">
                    <?php echo System::getDBLastRevision(); ?>
                </td>
            </tr>
            <tr>
                <td class="key">
                    HRM current database version
                </td>
                <td class="value">
                    <?php echo System::getDBCurrentRevision(); ?>
                </td>
            </tr>
            <tr>
                <td class="key">
                    HuCore minimum required version
                </td>
                <td class="value">
                    <?php echo System::getMinHuCoreVersionAsString(); ?>
                </td>
            </tr>
            <tr>
                <td class="key">
                    HuCore current version
                </td>
                <td class="value">
                    <?php echo System::getHucoreVersionAsString(); ?>
                </td>
            </tr>
            <tr>
                <td class="section">
                    Licenses in use
                </td>
                <td class="value">
                    &nbsp;
                </td>
            </tr>
            <tr>
                <td class="key">
                    HuCore license file
                </td>
                <td class="value">
                    <?php
                    if (System::hucoreHasValidLicense()) {
                        echo "valid";
                    } else {
                        echo "missing or invalid";
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <td class="key">
                    Server type
                </td>
                <td class="value">
                    <?php echo System::getHucoreServerType(); ?>
                </td>
            </tr>
            <tr>
                <td class="key">
                    Microscope types
                </td>
                <td class="value">
                    <?php
                    $micro = array();
                    if (System::hasLicense("widefield")) {
                        $micro[] = "widefield";
                    }
                    if (System::hasLicense("confocal")) {
                        $micro[] = "single-point confocal";
                    }
                    if (System::hasLicense("nipkow-disk")) {
                        $micro[] = "multi-point confocal";
                    }
                    if (System::hasLicense("multi-photon")) {
                        $micro[] = "two photon";
                    }
                    if (count($micro) == 0) {
                        $microStrg = "no microscope licenses.";
                    } else {
                        $microStrg = implode("<br />", $micro);
                    }
                    echo $microStrg;
                    ?>
                </td>
            </tr>
            <tr>
                <td class="key">
                    Analysis modules
                </td>
                <td class="value">
                    <?php
                    $analysis = array();
                    if (System::hasLicense("coloc")) {
                        $analysis[] = "colocalization";
                    }
                    if (count($analysis) == 0) {
                        $analysisStrg = "no analysis licenses.";
                    } else {
                        $analysisStrg = implode("<br />", $analysis);
                    }
                    echo $analysisStrg;
                    ?>
                </td>
            </tr>
            <tr>
                <td class="section">
                    System
                </td>
                <td class="value">
                    &nbsp;
                </td>
            <tr>
                <td class="key">
                    Operating system
                </td>
                <td class="value">
                    <?php echo System::getOperatingSystem(); ?>
                </td>
            </tr>
            <tr>
                <td class="key">
                    Kernel release
                </td>
                <td class="value">
                    <?php echo System::getKernelRelease(); ?>
                </td>
            </tr>
            <tr>
                <td class="section">
                    LAMP versions
                </td>
                <td class="value">
                    &nbsp;
                </td>
            </tr>
            <tr>
                <td class="key">
                    Apache version
                </td>
                <td class="value">
                    <?php echo System::getApacheVersion(); ?>
                </td>
            </tr>
            <tr>
                <td class="key">
                    Database type and version
                </td>
                <td class="value">
                    <?php echo System::getDatabaseType() . ' ' .
                        System::getDatabaseVersion(); ?>
                </td>
            </tr>
            <tr>
                <td class="key">
                    PHP (Apache mod) version
                </td>
                <td class="value">
                    <?php echo System::getPHPVersion(); ?>
                </td>
            </tr>
            <tr>
                <td class="section">
                    Configuration
                </td>
                <td class="value">
                    &nbsp;
                </td>
            </tr>
            <tr>
                <td class="key">
                    <a href="#" id="sendMail">
                        <img src="images/note.png" alt="Send"/>
                        Send test e-mail to HRM admin
                    </a>
                </td>
                <td class="value" id="sendMailStatus">
                    &nbsp;
                </td>
            </tr>

            <tr>
                <td class="key"
                >Memory limit
                </td>
                <td class="value">
                    <?php echo System::getMemoryLimit(); ?>
                </td>
            </tr>
            <tr>
                <td class="key">
                    Max execution time
                </td>
                <td class="value">
                    <?php echo System::getMaxExecutionTimeFromIni(); ?>
                </td>
            </tr>
            <tr>
                <td class="key">
                    File downloads
                </td>
                <td class="value">
                    &nbsp;
                </td>
            </tr>
            <tr>
                <td class="subkey">
                    HRM configuration
                </td>
                <td class="value">
                    <?php echo System::isDownloadEnabledFromConfig(); ?>
                </td>
            </tr>
            <tr>
                <td class="key">
                    Maximum post size
                </td>
                <td class="value">
                    &nbsp;
                </td>
            </tr>
            <tr>
                <td class="subkey">
                    php.ini
                </td>
                <td class="value">
                    <?php echo System::getPostMaxSizeFromIni(); ?>
                </td>
            </tr>
            <tr>
                <td class="subkey">
                    HRM configuration
                </td>
                <td class="value">
                    <?php echo System::getPostMaxSizeFromConfig(); ?>
                </td>
            </tr>
            <tr>
                <td class="subkey">
                    in use
                </td>
                <td class="value">
                    <?php echo System::getPostMaxSize(); ?>
                </td>
            </tr>
            <tr>
                <td class="key"
                >File uploads
                </td>
                <td class="value">
                    &nbsp;
                </td>
            </tr>
            <tr>
                <td class="subkey">
                    php.ini
                </td>
                <td class="value">
                    <?php echo System::isUploadEnabledFromIni(); ?>
                </td>
            </tr>
            <tr>
                <td class="subkey">
                    HRM configuration
                </td>
                <td class="value">
                    <?php echo System::isUploadEnabledFromConfig(); ?>
                </td>
            </tr>
            <tr>
                <td class="subkey">in use
                </td>
                <td class="value">
                    <?php echo System::isUploadEnabled(); ?>
                </td>
            </tr>
            <tr>
                <td class="key">
                    Maximum upload file size
                </td>
                <td class="value">
                    &nbsp;
                </td>
            </tr>
            <tr>
                <td class="subkey">
                    php.ini
                </td>
                <td class="value">
                    <?php echo System::isUploadMaxFileSizeFromIni(); ?>
                </td>
            </tr>
            <tr>
                <td class="subkey">
                    HRM configuration
                </td>
                <td class="value">
                    <?php echo System::isUploadMaxFileSizeFromConfig(); ?>
                </td>
            </tr>
            <tr>
                <td class="subkey">in use
                </td>
                <td class="value">
                    <?php echo System::getUploadMaxFileSize(); ?>
                </td>
            </tr>
            <tr>
                <td class="section">
                    Extended PHP configuration
                </td>
                <td class="value">
                    &nbsp;
                </td>
            </tr>
            <tr>
                <td class="subkey">
                    <a href="./phpinfo.php">
                        <img src="images/note.png" alt="PHP info"/>
                        Display PHP info
                    </a>
                </td>
                <td class="value">
                    &nbsp;
                </td>
            </tr>
        </table>

    </div> <!-- system -->

</div> <!-- content -->

<div id="rightpanel">

    <div id="info">

        <h3>Quick help</h3>

        <p>This page displays information about your installation
            and server.</p>

        <p>Click on <b>Display PHP info</b> at the bottom of the
            table to get more extended information on your
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
<!-- Activate Ajax functions to send a test email to the HRM administrator -->
<script type="text/javascript">
    $(document).ready($('#sendMail').click(function () {
        JSONRPCRequest({
            method: 'jsonSendTestEmail',
            params: []
        }, function (response) {
            if ("message" in response) {
                $('#sendMailStatus').html("<b>" + response['message'] + "</b>");
            } else {
                // In case of a timeout
                $('#sendMailStatus').html("<b>Interrupted!</b>");
            }
        });
    }));
</script>

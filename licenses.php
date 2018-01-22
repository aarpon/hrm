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

<?php
// Retrieve an array with all license names (i.e., hucore names) and their
// human-friendly names.
$allLicenses = System::getAllLicenses();

// Get active licenses on this system
$activeLicenses = System::getActiveLicenses();
?>

<div id="content">

    <h3><img alt="License summary" src="./images/licenses_title.png"
             width="40"/>&nbsp;License summary</h3>

    <div id="licenses">

        <?php

            // Get the categories
            $categories = array_keys($allLicenses);

            foreach ($categories as $category) {

                $categoryName = ucwords(str_replace('_', ' ', $category));

                ?>

                <p class="category"><?php echo($categoryName); ?></p>

                <?php

                foreach ($allLicenses[$category] as $key => $value) {

                    // Is the license active?
                    if (array_search($key, $activeLicenses) !== false) {
                        $nameClass = "present";
                        $iconClass = "checked";
                    } else {
                        $nameClass = "absent";
                        $iconClass = "unchecked";
                    }
                    ?>
                    <div class="row">
                        <div class="<?php echo($iconClass); ?>">&nbsp;</div>
                        <div class="<?php echo($nameClass); ?>"><?php echo($value); ?></div>
                    </div>
                    <?php
                }
            }
            ?>

    </div> <!-- licenses -->

</div> <!-- content -->

<div id="rightpanel">

    <div id="info">

        <h3>Quick help</h3>

        <p>This page displays information about your currently licensed modules.</p>

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

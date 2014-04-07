<?php
if ($_SESSION['user']->isAdmin()) {
?>
    <li>
        <a href="#" onclick="checkForUpdates();">
            <img src="images/check_for_update.png" alt="Check for updates" />
            &nbsp;Check for updates
        </a>
    </li>
<?php
}
?>

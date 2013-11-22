<?php
if ( !$_SESSION['user']->isAdmin()) {
?>
<li>
    <a href="file_management.php?folder=src">
        <img src="images/rawdata_small.png" alt="raw images" />
        &nbsp;Raw images
    </a>
</li>
<?php
}
?>

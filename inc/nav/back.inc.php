<?php
// $referer can be pre-set, which is used e.g. in the FileBrowser to allow for
// jumping to the "Raw images" from the "Select images" step (when creating a
// new job) and still be able to return to the initial referring page with the
// "Back" button, no matter to which part of the FileBrowser the user browsed
// inbetween.
if (! isset($referer)) {
    $referer = $_SESSION['referer'];
}
?>
<li>
    <a href="<?php echo $referer;?>">
        <img src="images/back_small.png" alt="back" />&nbsp;Back</a>
</li>

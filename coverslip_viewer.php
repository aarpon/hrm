<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\HuygensTools;
use hrm\Nav;
use hrm\Util;

require_once dirname(__FILE__) . '/inc/bootstrap.php';

// Two private functions, for the two tasks of this script:

// This configures and shows the file browser module inc/FileBrowser.php.
// The Signal-to-noise estimator works only on raw images, so the listed
// directory is the source ('src') one.
function showFileBrowser()
{

    //$browse_folder can be 'src' or 'dest'.
    $browse_folder = "src";
    $page_title = "Coverslip Position";
    $explanation_text = "Please choose an image and click on 'generate " .
        "preview' to visualize the image.";
    $form_title = "Available images";
    $top_nav_left = Nav::linkWikiPage('HuygensRemoteManagerHelpSaCorrectionMode');
    $top_nav_right = "";
    $multiple_files = false;
    // Number of displayed files.
    $size = 15;
    $type = "";
    $useTemplateData = 0;
    if ($_SESSION['user']->isAdmin()) {
        // The administrator can edit templates without adding a task, so no
        // image type is predefined in this case...
        $restrictFileType = false;
        $expandSubImages = true;
    } else {
        // Show files of the same type as in the current task:
        $restrictFileType = true;
        $fileFormat = $_SESSION['setting']->parameter("ImageFileFormat");
        $type = $fileFormat->value();
        // To (re)generate the thumbnails, use data from the current template
        // for colors (wavelengths).
        $useTemplateData = 1;
    }
    $file_buttons = array();
    $file_buttons[] = "update";


    $control_buttons = "
        <input type=\"button\" value=\"\" class=\"icon previous\"
        onmouseover=\"Tip('Go back without visualizing the image.')\"
        onmouseout=\"UnTip()\"
        onclick=\"document.location.href='aberration_correction.php'\" />";

    $info = '
            <h3>Quick help</h3>

            <p>Select a raw image for visualization.</p>';

    if ($type != "") {
        $info .= "<p>Only images of type <b>$type</b>, as set in the image
        parameters, are shown.</p>";
    }

    $info .= "<p>Click on 'generate preview' to visualize the image. " .
             "The preview will feature XY and <b>XZ</b> Maximum Intensity " .
             "Projections.</p><p>With the visual aid of the <b>XZ</b> view one " .
             "can state if image shows the coverslip near the 0 plane (at the " .
             "bottom) or far from the 0 plane (at the top). </p>";

    include("./inc/FileBrowser.php");
}


// This is the starting point of this script.


session_start();

if (isset($_GET['home'])) {
    header("Location: " . "home.php");
    exit();
}

// Ask the user to login if necessary.
if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit();
}


// Just show (or refresh) the file browser with the file list
showFileBrowser();


include("footer.inc.php");

?>

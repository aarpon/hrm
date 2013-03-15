<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Fileserver.inc.php");
require_once("./inc/System.inc.php");

session_start();

if (isset($_GET['home'])) {
  header("Location: " . "home.php"); exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  $req = $_SERVER['REQUEST_URI'];
  $_SESSION['request'] = $req;
  header("Location: " . "login.php"); exit();
}

// Keep track of who the referer is: the filemanager (src) will allow returning
// to some selected pages
if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
    if ( strpos( $_SERVER['HTTP_REFERER'], 'home.php' ) ||
         strpos( $_SERVER['HTTP_REFERER'], 'select_parameter_settings.php' ) ||
         strpos( $_SERVER['HTTP_REFERER'], 'select_task_settings.php' ) ||
         strpos( $_SERVER['HTTP_REFERER'], 'select_analysis_settings.php' ) ||
         strpos( $_SERVER['HTTP_REFERER'], 'select_images.php' ) ||
         strpos( $_SERVER['HTTP_REFERER'], 'create_job.php' ) ) {
        $_SESSION['filemanager_referer'] = $_SERVER['HTTP_REFERER'];
    }
}

if (isset($_SERVER['HTTP_REFERER']) &&
        !strstr($_SERVER['HTTP_REFERER'], 'job_queue')) {
  $_SESSION['referer'] = $_SERVER['HTTP_REFERER'];
}

if (isset($_GET['ref'])) {
    $_SESSION['referer'] = $_GET['ref'];
} else if (isset($_POST['ref'])) {
    $_SESSION['referer'] = $_POST['ref'];
} else {
    if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
        $_SESSION['referer'] = $_SERVER['HTTP_REFERER'];
    }
}

if (!isset($_SESSION['fileserver'])) {
    # session_register("fileserver");
    $name = $_SESSION['user']->name();
    $_SESSION['fileserver'] = new Fileserver($name);
}

if (isset($_GET['getThumbnail'])) {
    if (isset($_GET['dir'])) {
        $dir = $_GET['dir'];
    } else {
        $dir = "dest";
    }
    $_SESSION['fileserver']->getThumbnail( rawurldecode($_GET['getThumbnail']),
         $dir );
    exit;
}

if (isset($_GET['getMovie'])) {
    if (isset($_GET['dir'])) {
        $dir = $_GET['dir'];
    } else {
        $dir = "dest";
    }
    $_SESSION['fileserver']->getMovie( rawurldecode($_GET['getMovie']),
         $dir );
    exit;
}

if (isset($_GET['viewStrip'])) {

    if (isset($_GET['dir'])) {
        $src = $_GET['dir'];
    } else {
        $src = "dest";
    }
    if (isset($_GET['type'])) {
        $type = $_GET['type'];
    } else {
        $type = "stack.comparison";
    }
    if (isset($_GET['embed'])) {
        $embed = true;
    } else {
        $embed = false;
    }

    $_SESSION['fileserver']->viewStrip( $_GET['viewStrip'],
        $type, $src, $embed );
    exit;

}

if (isset($_GET['genPreview'])) {
    if (isset($_GET['size'])) {
        $size = $_GET['size'];
    } else {
        $size = "preview";
    }
    if (isset($_GET['src'])) {
        $src = $_GET['src'];
    } else {
        $src = "dest";
    }
    if (isset($_GET['dest'])) {
        $dest = $_GET['dest'];
    } else {
        $dest = $src;
    }
    if (isset($_GET['data'])) {
        $data = $_GET['data'];
    } else {
        $data = 0;
    }
    if (isset($_GET['index'])) {
        $index = $_GET['index'];
    } else {
        $index = 0;
    }


    $_SESSION['fileserver']->genPreview( $_GET['genPreview'],
        $src, $dest, $index, $size, $data );
    exit;
}

if (isset($_GET['compareResult'])) {
    if (isset($_GET['size'])) {
        $size = $_GET['size'];
    } else {
        $size = "400";
    }
    if (isset($_GET['op'])) {
        $op = $_GET['op'];
    } else {
        $op = "home";
    }
    if (isset($_GET['mode'])) {
        $mode = $_GET['mode'];
    } else {
        $mode = "MIP";
    }


    $_SESSION['fileserver']->previewPage(
        rawurldecode($_GET['compareResult']), $op, $mode, $size);
    exit;

}


# $browse_folder can be 'src' or 'dest'.
$browse_folder = "dest";

if (isset($_GET['folder']) ) {
    $browse_folder = $_GET['folder'];
}


if ($allowHttpTransfer) {
    $message = "";
    if (isset($_POST['download'])) {
        if (isset($_POST['userfiles']) && is_array($_POST['userfiles'])) {
           $message =
            $_SESSION['fileserver']->downloadResults($_POST['userfiles']);
           exit;
        }
    } else if (isset($_GET['download']) ) {
        $downloadArr = array ( $_GET['download'] );
           $message = $_SESSION['fileserver']->downloadResults($downloadArr);
           exit;
    }
}

$operationResult = "";

if ( System::isUploadEnabled() == "enabled" ) {

    if (isset($_POST['uploadForm']) && isset($_FILES) ) {
        $operationResult =
            $_SESSION['fileserver']->uploadFiles($_FILES['upfile'],
                $browse_folder);

    } else if (isset($_GET['upload'])) {
        $max = getMaxPostSize() / 1024 / 1024;
        $maxPost = "$max MB";
        $operationResult = "<b>Nothing uploaded!</b> Probably total post ".
            "exceeds maximum allowed size of $maxPost.<br>\n";
    }
}


if (isset($_POST['delete'])) {
    if (isset($_POST['userfiles']) && is_array($_POST['userfiles'])) {
        if ( $browse_folder == "dest" ) {
            $message =
                $_SESSION['fileserver']->deleteFiles($_POST['userfiles'],
                        "dest");
        } else {
            $message =
                $_SESSION['fileserver']->deleteFiles($_POST['userfiles'],
                        "src");
        }
    }
} else if (isset($_GET['delete']) ) {
    # This method is only for result files.
    $deleteArr = array ( $_GET['delete'] );
    $message = $_SESSION['fileserver']->deleteFiles($deleteArr);
    exit;
} else if (isset($_POST['update'])) {
        if ( $browse_folder == "dest" ) {
            $_SESSION['fileserver']->resetDestFiles();
        } else {
            $_SESSION['fileserver']->resetFiles();
        }
} else if (!isset($_POST['update']) && isset($_GET) && isset($_GET['folder'])){
        // Force an update in the file browser
        $_POST['update'] = "1";
}


// To (re)generate the thumbnails, don't use template data, as it is not present
// here.

$useTemplateData = 0;
$file_buttons = array();

if ( $browse_folder == "dest" ) {
    // Number of displayed files.
    $size = 15;
    $multiple_files = true;
    $page_title = "Results";
    $explanation_text = "These are the <b>processed image files</b> " .
    "currently in your file area.";
    $form_title = "Your result files";

    $info = "<h3>Quick help</h3>
            <p>Click on a file name to see a preview.</p>
            <p><strong>Click on <img src = \"images/eye.png\" /> Detailed View
            over the file preview to access previews, reports and 
            analysis results.</strong></p>";
    
    if ($allowHttpTransfer) {
        $info .= "<p>Select the files you want to download (you can <b>SHIFT-</b> 
            and <b>CTRL-click</b> for multiple selection) and press the
            <b>download</b> icon to compress the files into an archive and 
            start the download process. (Please mind that large files may take
            a <b>long time </b>to be packaged before downloading.)</p>
            <p> Use the <b>delete</b> icon to delete the selected files
            instead.</p>";
        $file_buttons[] = "download";
    }
    $info .= "<p><strong>Move your mouse pointer over the action buttons at " .
      "the bottom to redisplay this help.</strong></p>";
} else {
    $browse_folder = "src";
    $size = 15;
    $multiple_files = true;
    $page_title = "Raw images";
    $explanation_text = "These are the <b>original image files</b> currently " .
      "in your file area.";
    $form_title = "Your raw files";

    $info = "<h3>Quick help</h3>
            <p>Click on a file name to see (or create) a preview.</p>
            <p>Select the files you want to delete (you can <b>SHIFT-</b> and
            <b>CTRL-click</b> for multiple selection) and press the
            <b>delete</b> icon to delete them.
            </p>";

    
    if (System::isUploadEnabled() == "enabled") {

        $validExtensions = str_replace( " ", ", ",
          $_SESSION['fileserver']->getValidArchiveTypesAsString() );
        $info .= "<p>You can also upload files to deconvolve. To upload
        multiple files, it may be convenient to pack them first in a single
        <b>archive ($validExtensions)</b>, that will be unpacked
        automatically after upload.";
        $file_buttons[] = "upload";
    }
    $info .= "<p><strong>Move your mouse pointer over the action buttons at " .
      "the bottom to redisplay this help.</strong></p>";
}

$top_navigation = '
            <ul>
            <li>
                <img src="images/user.png" alt="user" />
                &nbsp;'.$_SESSION['user']->name().'
            </li>';
        
if ( isset( $_SESSION['filemanager_referer'] ) ) {
    $referer = $_SESSION['filemanager_referer'];
        if ( strpos( $referer, 'home.php' ) === False ) {
$top_navigation .= '
            <li>
                <a href="' . $referer . '">
                    <img src="images/back_small.png" alt="back" />&nbsp;Back</a>
            </li>';
       }
    }

    if ( $browse_folder == "dest" ) {
        $top_navigation .= '
            <li>
                <a href="file_management.php?folder=src">
                    <img src="images/rawdata_small.png" alt="raw images" />&nbsp;&nbsp;Raw images</a>
            </li>';
    } else {
        $top_navigation .= '
            <li>
                <a href="file_management.php?folder=dest">
                    <img src="images/results_small.png" alt="results" />&nbsp;&nbsp;Results</a>
            </li>';
    }
    
$top_navigation .= '<li>
                <a href="'.getThisPageName().'?home=home">
                    <img src="images/home.png" alt="home" />
                    &nbsp;Home
                </a>
            </li>
            <li>
                <a href="javascript:openWindow(
                \'http://www.svi.nl/HuygensRemoteManagerHelpFileManagement\')">
                <img src="images/help.png" alt="help" />
                &nbsp;Help
                </a>
                </li>
        </ul>
 ';

$file_buttons[] = "delete";
$file_buttons[] = "update";

$control_buttons = '
<input name="ref" type="hidden" value="'.$_SESSION['referer'].'" />
<input name="OK" type="hidden" />
';

include ("omero_actions.php");

include("./inc/FileBrowser.inc.php");

include("footer.inc.php");

?>

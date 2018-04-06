<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Nav;
use hrm\System;
use hrm\Util;
use hrm\UtilV2;
use hrm\Fileserver;

require_once dirname(__FILE__) . '/inc/bootstrap.php';


session_start();

if (isset($_GET['home'])) {
    header("Location: " . "home.php");
    exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit();
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
    $_SESSION['fileserver']->getThumbnail(rawurldecode($_GET['getThumbnail']),
        $dir);
    exit;
}

// Message
$message = "";

if (System::isUploadEnabled() == "enabled") {

    if (isset($_POST['uploadForm']) && isset($_FILES)) {
        $operationResult =
            $_SESSION['fileserver']->uploadFiles($_FILES['upfile'],
                $browse_folder);

    } else if (isset($_GET['upload'])) {
        $max = UtilV2::getMaxPostSize() / 1024 / 1024;
        $maxPost = "$max MB";
        $operationResult = "<b>Nothing uploaded!</b> Probably total post " .
            "exceeds maximum allowed size of $maxPost.<br>\n";
    }
}

if (isset($_POST['delete'])) {
    if (isset($_POST['userfiles']) && is_array($_POST['userfiles'])) {
            $message =
                $_SESSION['fileserver']->deleteFiles($_POST['userfiles'],
                    "src");
    }
} else if (isset($_GET['delete'])) {
    # This method is only for result files.
    $deleteArr = array($_GET['delete']);
    $message = $_SESSION['fileserver']->deleteFiles($deleteArr);
    exit;
} else if (isset($_POST['update'])) {
    $_SESSION['fileserver']->resetFiles();
} else if (!isset($_POST['update']) && isset($_GET) && isset($_GET['folder'])) {
    // Force an update in the file browser
    $_POST['update'] = "1";
}

// Refresh the directory listing:
if (isset($_POST['update'])) {
    $_SESSION['fileserver']->resetFiles();
}

/*************** Code for the interaction with OMERO. ***************/
global $omero_transfers;
if ($omero_transfers) {
    if (isset($_SESSION['omeroConnection'])) {
        $omeroConnection = $_SESSION['omeroConnection'];
    }
}


if (isset($_POST['refreshOmero'])) {
    if (isset($omeroConnection)) {
        if ($omeroConnection->loggedIn) {
            $omeroConnection->resetNodes();
        }
    }
}

/************ End of code for the interaction with OMERO. **********/

// Files can be restricted to a certain image type only, or to all.

if (!isset($restrictFileType) || $restrictFileType === false) {
    // Show all image files.
    if (isset($expandSubImages) && $expandSubImages) {
        // In certain conditions (e.g. the SNR estimator) we want to list
        // all subimages that every file may contain.
        $files = $_SESSION['fileserver']->listFiles(true);
    } else {
        // In the file manager we want to treat files as such, not listing
        // subimages.
        $files = $_SESSION['fileserver']->listFiles(false);
    }

} else {

    // Show files of one image type only.
    $copyFileserver = clone($_SESSION['fileserver']);
    $fileFormatParam = $_SESSION['setting']->parameter("ImageFileFormat");
    $fileFormat = $fileFormatParam->value();
    $extensions = $fileFormatParam->fileExtensions();
    $_SESSION['fileserver']->setImageExtensions($extensions);
    $isTimeSeries = false;
    if (isset($_SESSION['autoseries']) && $_SESSION['autoseries'] == "TRUE") {
        $isTimeSeries = true;
    }
    $files = $_SESSION['fileserver']->filesOfType($fileFormat, $isTimeSeries);
    $_SESSION['fileserver'] = $copyFileserver;
}

// JavaScript
$script = array("settings.js",
    "jquery-1.8.3.min.js",
    "jqTree/tree.jquery.js",
    "jquery-ui/jquery-ui-1.9.1.custom.js",
    "jquery-ui/jquery.bgiframe-2.1.2.js",
    "omero.js");

// Include Fine Uploader JavaScript
if (System::isUploadEnabled() == "enabled") {
    array_push($script, "fineuploader/jquery.fine-uploader.js");
}

// Include header with elements for file uploader.
if (System::isUploadEnabled() == "enabled") {
    include("header_fb.inc.php");
} else {
    include("header.inc.php");
}

?>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
            echo("&nbsp;");
            ?>
        </ul>
    </div>
    <div id="navright">
        <ul>
            <?php
            echo(Nav::linkResults());
            echo(Nav::textUser($_SESSION['user']->name()));
            echo(Nav::linkHome(Util::getThisPageName()));
            ?>
        </ul>
    </div>
    <div class="clear"></div>
</div>

<div id="content">

    <h3><img alt="Raw images" src=./images/rawdata_title.png width="40"/>
        &nbsp;Raw images</h3>

    <form method="post" action="?folder=src"
          id="file_browser" onsubmit="return confirmSubmit()">

        <fieldset>

            <legend>Your raw files</legend>

            <p class="message_small">These are the <b>original image files</b> currently in your file area.</p>

            <div id="userfiles" onmouseover="showPreview()">
                <select name="userfiles[]" id="fileSelection"
                        class="selection"
                        onchange="imageAction(this)"
                        onkeyup="this.blur();this.focus();"
                        size="15" multiple="multiple">
                    <?php

                    // Populate the select field with the list of available images:
                    if ($files != null) {
                        foreach ($files as $key => $file) {

                            $path = explode("/", $file);
                            if (count($path) > 2)
                                $filename = $path[0] . "/.../" . $path[count($path) - 1];
                            else
                                $filename = $file;

                            // Consecutive spaces are collapsed into one space in HTML.
                            // Hence '&nbsp;' to correct this when the file has more spaces.
                            $filename = str_replace(' ', '&nbsp;', $filename);
                     ?>
                            <option value="<?php echo($file);?>">
                                <?php echo($filename);?>
                            </option>
                    <?php
                        }
                    }
                    else echo "<option>&nbsp;</option>\n";
                    ?>
                </select>
            </div>

        </fieldset>

        <div id="selection" onmouseover="showInstructions()">

            <?php
            if (System::isUploadEnabled() == "enabled") {
                ?>

                <input class="icon upload" type="button"
                       onclick="UnTip(); uploadImagesAlt()"
                       onmouseover="Tip('Upload one or more files (or compressed archives of files) to the server')"
                       onmouseout="UnTip()"/>
                <?php
            }
            ?>
            <input class="icon delete" type="button"
                   onclick="UnTip(); deleteImages()"
                   onmouseover="Tip('Delete selected images')" onmouseout="UnTip()"/>

            <input name="update" type="submit"
                   value="update" class="icon update"
                   onclick="UnTip(); setActionToUpdate();"
                   onmouseover="Tip('Refresh image list')" onmouseout="UnTip()"/>

        </div>
        <div id="actions" onmouseover="showInstructions()">
            <!-- do not remove !-->
        </div>
        <div id="controls" class="imageselection"
             onmouseover="showInstructions()">

            <input name="ref" type="hidden" value="http://localhost/hrm/home.php"/>
            <input name="OK" type="hidden"/>

            <?php
            include("omero_actions.php");
            ?>

        </div>
    </form>

    <div id="upMsg">
        <!-- Do not remove -->
    </div>
    <div id="upMsgError">
        <!-- Do not remove -->
    </div>

    <div id="up_form" onmouseover="showInstructions()">
        <div id="fine-uploader-manual-trigger"></div>
    </div>

</div> <!-- content -->

<script type="text/javascript">

    window.pageInstructions = '' +
        '<h3>Quick help</h3>' +
        '' +
        '<p>Click on a file name to see (or create) a preview.</p>' +
        '<p>Select the files you want to delete (you can <b>SHIFT-</b> and ' +
        '<b>CTRL-click</b> for multiple selection) and press the ' +
        '<b>delete</b> icon to delete them.</p>' +
        '' +
        '<p>You can also upload files to deconvolve. To upload ' +
        'multiple files, it may be convenient to pack them first in a ' +
        'single <b>archive <?php echo(
        str_replace(" ", ", ",
            $_SESSION['fileserver']->getValidArchiveTypesAsString()));
            ?></b>, that will be unpacked automatically after upload.</p>' +
        '' +
        '<p><strong>Move your mouse pointer over the action buttons at the ' +
        'bottom to redisplay this help.</strong></p>';
</script>

<div id="rightpanel">

    <div id="info">
        <!-- Instructions shown on load. -->
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

<script type="text/javascript">

<?php

# If requested, add the Fine Uploader widget
if (System::isUploadEnabled() == "enabled") {

?>
<!-- Fine Uploader -->
$(document).ready(function () {

    var totalSizeOfSelectedFiles = 0;
    var totalAllowedSizeOfSelectedFiles = <?php echo(UtilV2::getMaxPostSize()); ?>;
    var totalAllowedSizeOfSingleFile = <?php echo(UtilV2::getMaxUploadFileSize()); ?>;

    $("#upMsgError").empty();
    $("#upMsg").empty();

    $('#fine-uploader-manual-trigger').fineUploader({
        template: 'qq-template-manual-trigger',
        cors: {
            expected: false,
            sendCredentials: false
        },
        maxConnections: <?php echo(UtilV2::getNumberConcurrentUploads()); ?>,
        folders: false,
        request: {
            endpoint: "<?php echo(UtilV2::getRelativePathToFileUploader());?>",
            forceMultipart: true,
            customHeaders: {
                "DestinationFolder": "<?php echo($_SESSION['fileserver']->sourceFolder()); ?>",
                "ImageExtensions": ['dv', 'ims', 'lif', 'lof', 'lsm', 'oif', 'pic', 'r3d', 'stk',
                    'zvi', 'czi', 'nd2', 'nd', 'tf2', 'tf8', 'btf', 'h5', 'tif', 'tiff', 'ome.tif',
                    'ome.tiff', 'ome', 'ics', 'ids']
            }
        },
        chunking: {
            enabled: true,
            concurrent: {
                enabled: true
            },
            mandatory: true,
            partSize: <?php echo(UtilV2::getMaxConcurrentUploadSize(
                UtilV2::getNumberConcurrentUploads())); ?>,
            success: {
                endpoint: "<?php echo(UtilV2::getRelativePathToFileUploader() . "?done"); ?>"
            }
        },
        validation: {
            stopOnFirstInvalidFile: false,
            sizeLimit: totalAllowedSizeOfSingleFile,
            acceptFiles: ".dv,.ims,.lif,.lof,.lsm,.oif,.pic,.3rd,.stk,.zvi,.czi,.nd2,.nd,.tf2,.tf8,.btf,.h5," +
            ".tif,.tiff,.ome.tif,.ome.tiff,.ics,.ids,.zip,.tgz,.tar,.tar.gz",
            allowedExtensions: ['dv', 'ims', 'lif', 'lof', 'lsm', 'oif', 'pic', 'r3d', 'stk',
                'zvi', 'czi', 'nd2', 'nd', 'tf2', 'tf8', 'btf', 'h5', 'tif', 'tiff', 'ome.tif',
                'ome.tiff', 'ome', 'ics', 'ids', 'zip', 'tgz', 'tar', 'tar.gz'],
        },
        resume: {
            enabled: true
        },
        retry: {
            enableAuto: true,
            showButton: true
        },
        autoUpload: false,
        display: {
            fileSizeOnSubmit: true
        },
        callbacks: {
            onAllComplete: function (succeeded, failed) {
                // Rescan the source folder only if everything was uploaded successfully.
                // If not the user can still interact with the files.
                if (failed.length == 0) {
                    $("#upMsgError").empty();
                    $("#upMsg").empty();
                    setActionToUpdate();
                    $("form#file_browser").submit();
                }
            },
            onStatusChange: function (id, oldStatus, newStatus) {
                // Here, we only care to subtract the size of removed files
                if (newStatus == "canceled") {
                    totalSizeOfSelectedFiles -= this.getSize(id);
                }
                $("#upMsg").empty().text("Selected " + totalSizeOfSelectedFiles +
                    " of " + totalAllowedSizeOfSelectedFiles + " bytes.");
            },
            onValidate: function (data, button) {

                // Check the file of the individual file
                if (data.size > totalAllowedSizeOfSingleFile) {
                    $("#upMsgError").append("<br />File " + data.name +
                        " was rejected: too large.");
                    return false;
                }

                // Check the total size of added files
                if (totalSizeOfSelectedFiles + data.size > totalAllowedSizeOfSelectedFiles) {
                    $("#upMsgError").append("<br />File " + data.name +
                        " was rejected: total allowed size exceeded.");
                    return false;
                } else {
                    totalSizeOfSelectedFiles = totalSizeOfSelectedFiles + data.size;
                    $("#upMsg").empty().text("Selected " + totalSizeOfSelectedFiles +
                        " of " + totalAllowedSizeOfSelectedFiles + " bytes.");
                    return true;
                }
            }
        }
    });

    $('#trigger-upload').click(function () {
        $('#fine-uploader-manual-trigger').fineUploader('uploadStoredFiles');
    });

    // Hide the uploader until requested by the user
    $("#up_form").hide();

});
<!-- End of Fine Uploader -->


<?php
}
?>

    <!-- Thumbnails -->
    function imageAction(list) {

        var action = '';
        $("#upMsg").html("");
        $("#actions").html("");

        var n = list.selectedIndex;     // Which item is the first selected one

        if (undefined === window.lastSelectedImgs) {
            window.lastSelectedImgs = [];
            window.lastSelectedImgsKey = [];
            window.lastShownIndex = -1;
        }

        var selectedNew = 0;

        var count = 0;

        // Compare last selection with the current one, to find which file has been
        // selected or deselected.

        for (var i = 0; i < list.options.length; i++) {
            if (list.options[i].selected) {
                if (undefined === window.lastSelectedImgsKey[i]) {
                    // New selected item
                    selectedNew = 1;
                    n = i;
                }
                count++;
            }
        }

        if (selectedNew === 0) {
            // If nothing was selected, it means that the click deselected an image
            for (i = 0; i < window.lastSelectedImgs.length; i++) {
                var key = window.lastSelectedImgs[i];
                if (!list.options[key].selected) {
                    selectedNew = -1;
                    n = key;
                }
            }
        }

        // Remember the current selection for the next user interaction.

        window.lastSelectedImgs = [];
        window.lastSelectedImgsKey = [];
        count = 0;
        for (i = 0; i < list.options.length; i++) {
            if (list.options[i].selected) {
                window.lastSelectedImgs[count] = i;
                window.lastSelectedImgsKey[i] = true;
                count++;
            }
        }

        if (count === 0) {
            window.previewSelected = -1;
        }

        // Show image preview of the last clicked element in the list:

        var val = list[n].value;

        if (n === window.lastShownIndex) {
            return
        }
        window.lastShownIndex = n;

        switch (val) {
        <?php
            foreach ($files as $key => $file) {
            ?>

            case "<?php echo($file);?>":

                <?php echo($_SESSION['fileserver']->getImageAction($file, $key,
                    "src", "preview", 1, 0));?>;
                break;

        <?php
            }
            ?>
        }

    }
    <!-- End of Thumbnails -->

    <!-- Show default instructions -->
    $(document).ready(function () {
        window.infoShown = false;
        showInstructions();
        window.previewSelected = -1;
    });
    <!-- End of Show default instructions -->
</script>

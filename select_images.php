<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Fileserver;
use hrm\Nav;
use hrm\setting\ParameterSetting;
use hrm\System;
use hrm\Util;


require_once dirname(__FILE__) . '/inc/bootstrap.php';

session_start();

if (isset($_GET['home'])) {
    header("Location: " . "home.php");
    $_SESSION['fileserver'] = null;
    exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit();
}

if (isset($_SESSION['jobcreated'])) {
    unset($_SESSION['jobcreated']);
}

if (System::hasLicense("coloc")) {
    $numberSteps = 5;
} else {
    $numberSteps = 4;
}

$currentStep = 1;
$nextStep = $currentStep + 1;
$goNextMessage = "Continue to step $nextStep/$numberSteps - ";
$goNextMessage .= "Select image template.";
$name = $_SESSION['user']->name();


if (!isset($_SESSION['fileserver']) || $_SESSION['fileserver'] == null) {
    // If there is no other action on this path, we assume it's entry on the page and initialize the Fileserver object.
    $_SESSION['fileserver'] = new Fileserver($name);
}
if (!isset($_SESSION['parametersetting'])) {
    $_SESSION['parametersetting'] = new ParameterSetting();
}
$fileFormat = $_SESSION['parametersetting']->parameter("ImageFileFormat");

$message = "";

if (isset($_POST['OK'])) {
    if (!$_SESSION['fileserver']->hasSelection()) {
        $message = "Please add at least one image to your selection";
    } else {
        header("Location: " . "select_parameter_settings.php");
        exit();
    }
}

// Add needed JS scripts
$script = array("settings.js", "ajax_utils.js", "json-rpc-client.js");

include("header.inc.php");

$info = "<h3>Quick help</h3>" .
    "<p>Here you can select the files to be restored from the list " .
    "of available images. The file names are filtered by the selected " .
    "file format. Use SHIFT- and CTRL-click to select multiple files.</p>" .
    "<p>Where applicable, the files belonging to a series can be condensed " .
    "into one file name by checking the 'autoseries' option. These files " .
    "will be loaded and deconvolved as one large dataset. Unchecking " .
    "'autoseries' causes each file to be deconvolved independently.</p>" .
    "<p>Click on a file name in any of the fields to get (or to create) " .
    "a preview.</p>";

?>

<!--
  Tooltips
-->
<span class="toolTip" id="ttSpanDown">
        Add files to the list of selected images.
    </span>
<span class="toolTip" id="ttSpanUp">
        Remove files from the list of selected images.
    </span>
<span class="toolTip" id="ttSpanRefresh">
        Refresh the list of available images on the server.
    </span>
<span class="toolTip" id="ttSpanForward">
    <?php echo $goNextMessage; ?>
    </span>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
            echo(Nav::linkWikiPage('HuygensRemoteManagerHelpSelectImages'));
            ?>
        </ul>
    </div>
    <div id="navright">
        <ul>
            <?php
            echo(Nav::textUser($_SESSION['user']->name()));
            if (!$_SESSION['user']->isAdmin()) {
                echo(Nav::linkRawImages());
            }
            echo(Nav::linkHome(Util::getThisPageName()));
            ?>
        </ul>
    </div>
    <div class="clear"></div>
</div>

<div id="content">
    <h3><img alt="SelectImages" src="./images/select_images.png" width="40"/>
        &nbsp;Step
        <?php echo $currentStep . "/" . $numberSteps; ?>
        - Select images
    </h3>

    <form method="post" action="" id="select">
        <fieldset class="setting">
            <legend>
                <a href="javascript:openWindow('http://www.svi.nl/FileFormats')">
                    <img src="images/help.png" alt="?"/>
                </a>
                Image file format
            </legend>

            <select name="ImageFileFormat"
                    class="selection"
                    id="ImageFileFormat"
                    title="Supported image file formats"
                    size="1"                    
                    onchange='setFileFormat($("#ImageFileFormat").val())'
                    onkeyup="this.blur();this.focus();">
            </select>
        </fieldset>

        <fieldset>
            <legend id="images_legend">Images available on server</legend>
            <div id="userfiles" onmouseover="showPreview()">
                <select id="filesPerFormat"
                        name="userfiles[]"
                        class="selection"
                        title="List of available images"
                        size="10"
                        multiple="multiple">
                </select>
            </div>

            <label id="autoseries_label">
                <input type="checkbox"
                       name="autoseries"
                       class="autoseries"
                       id="autoseries"
                       value="TRUE"
                       onclick='setAutoSeriesFlag($("#autoseries").is(":checked"));'
                       onchange='setAutoSeriesFlag($("#autoseries").is(":checked"));'
                />
                When applicable, load file series automatically
            </label>
        </fieldset>

        <div id="selection">
            <input name="down"
                   id="down"
                   type="button"
                   value=""
                   class="icon down"
                   onmouseover="TagToTip('ttSpanDown')"
                   onmouseout="UnTip()"/>

            <input name="up"
                   id="up"
                   type="button"
                   value=""
                   class="icon remove"
                   onmouseover="TagToTip('ttSpanUp')"
                   onmouseout="UnTip()"/>
        </div>

        <fieldset>
            <legend id="selection_legend">Selected images</legend>
            <div id="selectedfiles" onmouseover="showPreview()">
                <select id="selectedimages"
                        name="selectedfiles[]"
                        class="selection"                        
                        title="List of selected images"
                        size="5"
                        multiple="multiple">
                </select>
            </div>
        </fieldset>

        <div id="actions" class="imageselection" onmouseover="showInstructions()">
            <input name="update"
                   id = "update"
                   value=""
                   class="icon update"
                   onmouseover="TagToTip('ttSpanRefresh')"
                   onmouseout="UnTip()"
            />
        </div>

        <div id="controls"
             onmouseover="showInstructions()">
            <input type="submit"
                   name="OK"
                   value=""
                   class="icon next"
                   onmouseover="TagToTip('ttSpanForward')"
                   onmouseout="UnTip()"/>
        </div>

    </form>

</div> <!-- content -->

<script type="text/javascript">
    window.pageInstructions = '<?php echo Util::escapeJavaScript($info); ?>';
    window.infoShown = true;
    window.previewSelected = -1;
</script>


<div id="rightpanel">

    <div id="info">
        <?php echo $info; ?>
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


<!-- Ajax functions -->
<script type="text/javascript">

    // Assign an action to each image
    function imageAction(item) {
        if (undefined === item) {
            return;
        }
        var filename = item.text();
        var index = parseInt(item.val());
        ajaxGetImgPreview(filename, index, 'src');
        window.previewSelected = 1;
    }

    /**
     * Update the file format and retrieve a new file list if needed.
     * $param format: new file format.
     */
    function setFileFormat(format) {
        // Only retrieve a new file list (and update the autoseries flag)
        // if the value really changed
        if (format !== window.selectImagesPageVariables.format) {
            window.selectImagesPageVariables.format = format;
            retrieveFileList(window.selectImagesPageVariables.format,
                window.selectImagesPageVariables.autoseries)

            // Hide any thumbnail
            window.previewSelected = -1;
            showInstructions();
        }
    }

    /**
     * Update the autoseries flag and retrieve a new file list if needed.
     * $param autoseries: whether to condense series or not.
     */
    function setAutoSeriesFlag(autoseries) {
        // Only retrieve a new file list (and update the autoseries flag)
        // if the value really changed
        if (autoseries !== window.selectImagesPageVariables.autoseries) {
            window.selectImagesPageVariables.autoseries = autoseries;
            retrieveFileList(window.selectImagesPageVariables.format,
                window.selectImagesPageVariables.autoseries)

            // Hide any thumbnail
            window.previewSelected = -1;
            showInstructions();
        }
    }

    /**
     * Reset the source file list on the server.
     */
    function reset(format) {
        // Hide any thumbnail
        window.previewSelected = -1;
        showInstructions();

        // Call jsonGetFileFormats on the server
        JSONRPCRequest({
            method: 'jsonResetSourceFiles',
            params: [format]
        }, function (response) {
            if (response["success"] === "false") {
                // Display error message
                $("#message p").text(response["message"]);

            } else {
                // Rescan
                retrieveFileList(window.selectImagesPageVariables.format,
                window.selectImagesPageVariables.autoseries);
            }
        });
    }

    /**
     * Remove current selection from selected files
     */
    function removeFromSelection(format) {

        // Inform
        $("#selection_legend").text("Selected images: updating...");

        // Get selected files from filesPerFormat
        var listOfSelectedFiles = [];
        $("#selectedimages option:selected").each(function() {
            listOfSelectedFiles.push($(this).text());
        });

        if (listOfSelectedFiles.length == 0) {
            // Restore legend text
            $("#selection_legend").text("Selected images");

            return;
        }

        // Since the array can be large, we stringify it to prevent
        // that the ajax post truncates it.
        var listOfSelectedFilesString = JSON.stringify(listOfSelectedFiles);

        // Call jsonGetFileFormats on the server
        JSONRPCRequest({
            method: 'jsonRemoveFilesFromSelection',
            params: [listOfSelectedFilesString, format]

        }, function (response) {
            // Update relevant fields
            updateFilesAndSelectedFiles(response);

            // Restore legend text
            $("#selection_legend").text("Selected images");
        });
    }

    /**
     * Remove everything from selected files
     */
    function removeAllFromSelection(format) {

        // Inform
        $("#selection_legend").text("Selected images: updating...");

        // Get selected files from filesPerFormat
        var listOfSelectedFiles = [];
        $("#selectedimages option").each(function() {
            listOfSelectedFiles.push($(this).text());
        });

        if (listOfSelectedFiles.length === 0) {
            // Restore legend text
            $("#selection_legend").text("Selected images");

            return;
        }

        // Since the array can be large, we stringify it to prevent
        // that the ajax post truncates it.
        var listOfSelectedFilesString = JSON.stringify(listOfSelectedFiles);

        // Call jsonGetFileFormats on the server
        JSONRPCRequest({
            method: 'jsonRemoveFilesFromSelection',
            params: [listOfSelectedFilesString, format]

        }, function (response) {
            // Update relevant fields
            updateFilesAndSelectedFiles(response);

            // Restore legend text
            $("#selection_legend").text("Selected images");
        });
    }

    // Update bot files and selected files elements
    function updateFilesAndSelectedFiles(response) {
        if (response["success"] === "false") {

            // Display error message
            $("#message p").text(response["message"]);

        } else {

            // Get the 'selectedimages' select element
            var selectedImages = $("#selectedimages");

            // Remove all options
            selectedImages.empty();

            // Add the new files
            $.each(response["selected_files"], function (value, filename) {
                var opt = $('<option>');
                opt.val(filename);
                opt.text(filename);
                opt.click(function() { imageAction(opt); } );
                selectedImages.append(opt);
            });

            // Enable/disable select element
            selectedImages.prop('disabled', response["selected_files"].length === 0);

            // Get the 'filesPerFormat' select element
            var filesPerFormat = $("#filesPerFormat");

            // Remove all options
            filesPerFormat.empty();

            // Add the new files
            $.each(response["files"], function (value, filename) {
                var opt = $('<option>');
                opt.val(filename);
                opt.text(filename);
                opt.click(function() { imageAction(opt); } );
                filesPerFormat.append(opt);
            });

            // Enable/disable select element
            filesPerFormat.prop('disabled', response["files"].length === 0);
        }
    }

    /**
     * Add files to selection
     */
    function addToSelection(format) {

        // Inform
        $("#selection_legend").text("Selected images: updating...");

        // Get selected files from filesPerFormat
        var listOfSelectedFiles = [];
        $("#filesPerFormat option:selected").each(function() {
            listOfSelectedFiles.push($(this).text());
        });

        if (listOfSelectedFiles.length === 0) {
            // Restore legend text
            $("#selection_legend").text("Selected images");

            return;
        }

        // Since the array can be large, we stringify it to prevent
        // that the ajax post truncates it.
        var listOfSelectedFilesString = JSON.stringify(listOfSelectedFiles);

        // Call jsonGetFileFormats on the server
        JSONRPCRequest({
            method: 'jsonAddFilesToSelection',
            params: [listOfSelectedFilesString, format]
        }, function (response) {
            if (response["success"] === "false") {
                // Display error message
                $("#message p").text(response["message"]);

            } else {
                // Update relevant fields
                updateFilesAndSelectedFiles(response);

                // Restore legend text
                $("#selection_legend").text("Selected images");
            }
        });
    }

    /**
     * Retrieve the list of file formats from the server
     * @param format: File format
     */
    function retrieveFileFormatsList(format) {
        // Call jsonGetFileFormats on the server
        JSONRPCRequest({
            method: 'jsonGetFileFormats',
            params: []
        }, function (response) {
            if (response["success"] === "false") {
                // Display error message
                $("#message p").text(response["message"]);

            } else {
                // Get the 'ImageFileFormat' select element
                var imageFileFormat = $("#ImageFileFormat");

                // Remove all options
                imageFileFormat.empty();

                // Add the new files
                $.each(response["formats"], function (value, translation) {
                    imageFileFormat.append($('<option>', {
                        name: value,
                        value: value,
                        text : translation
                    }));
                });

                // Select current format
                $("#ImageFileFormat").val(window.selectImagesPageVariables.format);
            }
        });
    }

    /**
     * Retrieve the file list from the server
     * @param format: File format
     * @param autoseries: whether to condense series or not.
     */
    function retrieveFileList(format, autoseries) {
        // Inform
        $("#images_legend").text("Images available on server: loading...");

        // Call jsonScanSourceFiles on the server
        JSONRPCRequest({
            method: 'jsonScanSourceFiles',
            params: [format, autoseries]
        }, function (response) {
            if (response["success"] === "false") {
                // Display error message
                $("#message p").text(response["message"]);

            } else {
                // Update relevant fields
                updateFilesAndSelectedFiles(response);

                // Reset text
                $("#images_legend").text("Images available on server");
            }
        });
    }

    // Set everything up and retrieve the file list from the server
    $(document).ready(function () {
        // Reset texts
        $("#images_legend").text("Images available on server");
        $("#selection_legend").text("Selected images");

        <?php
        if (isset($_SESSION['autoseries'])) {
            $autoseries = json_encode($_SESSION['autoseries']);
        } else {
            $autoseries = json_encode(false);
        }
        ?>

        // Initialize some variables. They will we used to
        // keep track of user selections in the page.
        window.selectImagesPageVariables = {
          "autoseries": <?php echo $autoseries; ?>,
          "format": "<?php echo $fileFormat->value(); ?>"
        };

        // Add callbacks
        $("#update").click(function() {
            reset(window.selectImagesPageVariables.format);
        });

        $("#down").click(function() {
            addToSelection(window.selectImagesPageVariables.format);
        });

        $("#up").click(function() {
            window.previewSelected = -1;
            showInstructions();
            removeFromSelection(window.selectImagesPageVariables.format);
        });

        // Set the autoseries flag
        $("#autoseries").prop('checked', selectImagesPageVariables.autoseries);

        // Retrieve the list of formats
        retrieveFileFormatsList(window.selectImagesPageVariables.format);

        // Retrieve the file list from the server
        retrieveFileList(window.selectImagesPageVariables.format,
            window.selectImagesPageVariables.autoseries);
    })

</script>

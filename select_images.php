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
    "file format." .
    "<p>Use SHIFT- and CTRL-click to select multiple files from any of the boxes.</p>" .
    "<p>From the <b><i>Images available on server</i></b> box, select the images you " .
    "want to process and use the <img src=\"images/add.png\" alt=\"Help\" width=\"22\" height=\"22\"/> " .
    "button to add them to the <b><i>Selected Images</i></b> box.</p>".
    "<p>To remove images, select them in the <b><i>Selected Images</i></b> box " .
    "and hit the <img src=\"images/remove.png\" alt=\"Help\" width=\"22\" height=\"22\"/> button.</p>" .
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
        var filename = item.val();
        var index = -1; // This is not used to create src 2D/3D thumbnails
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
    function removeFromSelection() {

        // Get the filesPerFormat select
        var filesPerFormat = $("#filesPerFormat");

        // Get the selectedimages select
        var selectedImages = $("#selectedimages");

        // Get selected files from selectedimages
        var listOfSelectedFiles = [];
        $("#selectedimages option:selected").each(function() {
            listOfSelectedFiles.push($(this).text());

            // Add the file to #filesPerFormat
            var opt = $('<option>');
            opt.val($(this).val());
            opt.text($(this).text());
            opt.click(function() { imageAction(opt); } );
            filesPerFormat.append(opt);

            // Remove the file from #filesPerFormat
            $(this).remove();

        });

        if (listOfSelectedFiles.length == 0) {
            return;
        }

        // Since the array can be large, we stringify it to prevent
        // that the ajax post truncates it.
        var listOfSelectedFilesString = JSON.stringify(listOfSelectedFiles);

        // Call jsonGetFileFormats on the server
        JSONRPCRequest({
            method: 'jsonRemoveFilesFromSelection',
            params: [listOfSelectedFilesString]

        }, function (response) {
            if (response["success"] === "false") {
                // Display error message
                $("#message p").text(response["message"]);

                // Restore legend text
                revertLegend($("#selection_legend"), "Selected images");

            } else {
                // Enable/disable select elements
                filesPerFormat.prop('disabled', $("#filesPerFormat option").length === 0);
                selectedImages.prop('disabled', $("#selectedimages option").length === 0);
            }
        });

        // Sort filesPerFormat's options
        sortOptions("filesPerFormat");
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
    function addToSelection() {

        // Get the selectedimages select
        var selectedImages = $("#selectedimages");

        // Get the filesPerFormat select
        var filesPerFormat = $("#filesPerFormat");

        // Get selected files from filesPerFormat
        var listOfSelectedFiles = [];
        $("#filesPerFormat option:selected").each(function() {

            // Add the file name to the list of files to send to the server
            listOfSelectedFiles.push($(this).text());

            // Add the file to #selectedImages
            var opt = $('<option>');
            opt.val($(this).val());
            opt.text($(this).text());
            opt.click(function() { imageAction(opt); } );
            selectedImages.append(opt);

            // Remove the file from #filesPerFormat
            $(this).remove();
        });

        if (listOfSelectedFiles.length === 0) {
            return;
        }

        // Since the array can be large, we stringify it to prevent
        // that the ajax post truncates it.
        var listOfSelectedFilesString = JSON.stringify(listOfSelectedFiles);

        // Call jsonGetFileFormats on the server
        JSONRPCRequest({
            method: 'jsonAddFilesToSelection',
            params: [listOfSelectedFilesString]
        }, function (response) {
            if (response["success"] === "false") {
                // Display error message
                $("#message p").text(response["message"]);

                // Restore legend text
                revertLegend($("#selection_legend"), "Selected images");

            } else {
                // Enable/disable select elements
                filesPerFormat.prop('disabled', $("#filesPerFormat option").length === 0);
                selectedImages.prop('disabled', $("#selectedimages option").length === 0);
            }
        });

        // Sort the options
        sortOptions("selectedimages");
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
        addToLegend($("#images_legend"), "Images available on server", "Loading...", "highlighted");

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
                revertLegend($("#images_legend"), "Images available on server");
            }
        });
    }

    // Sort the options in the passed select object
    function sortOptions(selectObjId) {
        var options = $("#" + selectObjId + " option");
        var arr = options.map(function(_, o) {
            return {
                t: $(o).text(),
                v: o.value
            };
        }).get();
        arr.sort(function(o1, o2) {
            return o1.t > o2.t ? 1 : o1.t < o2.t ? -1 : 0;
        });
        options.each(function(i, o) {
            o.value = arr[i].v;
            $(o).text(arr[i].t);
        });
    }

    // Add an highlighted message to a given legend for long-running processes
    function addToLegend(divObj, baseText, extendedText, className) {
        divObj.html(baseText + ": " + "<span class='" + className + "'>" + extendedText + "</span>");
    }

    // Revert a given legend text
    function revertLegend(divObj, baseText) {
        divObj.text(baseText);
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
            // Inform
            addToLegend($("#selection_legend"), "Selected images", "Updating...", "highlighted");

            setTimeout(function() {
                // Add files to selection
                addToSelection();

                // Inform
                revertLegend($("#selection_legend"), "Selected images");
            }, 50);
        });

        $("#up").click(function() {
            window.previewSelected = -1;
            showInstructions();

            // Inform
            addToLegend($("#selection_legend"), "Selected images", "Updating...", "highlighted");

            setTimeout(function() {
                // Remove files from selection
                removeFromSelection();

                // Inform
                revertLegend($("#selection_legend"), "Selected images");
            }, 50);
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

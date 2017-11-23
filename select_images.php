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

if (!isset($_SESSION['parametersetting'])) {
    $_SESSION['parametersetting'] = new ParameterSetting();
}
$fileFormat = $_SESSION['parametersetting']->parameter("ImageFileFormat");

if (isset($_POST['autoseries'])) {
    $_SESSION['autoseries'] = $_POST['autoseries'];
} else {
    $_SESSION['autoseries'] = "";
}

$message = "";
if (isset($_POST['down'])) {
    if (isset($_POST['userfiles']) && is_array($_POST['userfiles'])) {
        $indices = array_values($_POST['userfiles']);
        $files = $_SESSION['fileserver']->justGimmeTheFilesAndDoNothingElse();
        $selection = array();
        foreach ($indices as $i) {
            $selection[] = $files[$i];
        }
        $_SESSION['fileserver']->addFilesToSelection($selection);
    }
} else if (isset($_POST['up'])) {
    if (isset($_POST['selectedfiles']) && is_array($_POST['selectedfiles'])) {
        $selectedFiles = array_values($_POST['selectedfiles']);
        $_SESSION['fileserver']->removeFilesFromSelection($selectedFiles);
    }
} else if (isset($_POST['update'])) {
    $_SESSION['fileserver']->resetFiles();
} else if (isset($_POST['OK'])) {
    print_r("ok<br/>");
    if (!$_SESSION['fileserver']->hasSelection()) {
        $message = "Please add at least one image to your selection";
    } else {
        header("Location: " . "select_parameter_settings.php");
        exit();
    }
} else if ($_SESSION['fileserver'] == null) {
    print_r("init<br/>");
    // If there is no other action on this path, we assume it's entry on the page and initialize the Fileserver object.
    $_SESSION['fileserver'] = new Fileserver($name);
    $_SESSION['autoseries'] = "TRUE";
}

$script = array("settings.js", "ajax_utils.js");

// All the user's files in the server.
if ($_SESSION['fileserver']->hasFiles()) {
    $allFiles = $_SESSION['fileserver']->justGimmeTheFilesAndDoNothingElse();
} else {
    $allFiles = $_SESSION['fileserver']->scanAndStoreFiles(TRUE);
}

$generatedScript = "
function storeFileFormatSelection(sel,series) {
   // Get current selection
   var format = $('#' + sel.id + ' :selected').attr(\"name\");

   // Store it
   ajaxSetFileFormat(format);
};
";

// display only relevant files.
if ($allFiles != null) {

    $generatedScript .= "
function imageAction (list) {

    var n = list.selectedIndex;     // Which item is the first selected one

    if( undefined === window.lastSelectedImgs ){
        window.lastSelectedImgs = [];
        window.lastSelectedImgsKey = [];
        window.lastShownIndex = -1;
    }

    var snew = 0;

    var count = 0;
    for (var i=0; i<list.options.length; i++) {
        if (list.options[i].selected) {
            if( undefined === window.lastSelectedImgsKey[i] ){
                // New selected item
                snew = 1;
                n = i;
            }
            count++;
        }
    }

    if (snew == 0) {
        // deselected image
        for (var i=0; i<window.lastSelectedImgs.length; i++) {
            key = window.lastSelectedImgs[i];
            if ( !list.options[key].selected ) {
                snew = -1
                    n = key;
            }
        }
    }

    window.lastSelectedImgs = [];
    window.lastSelectedImgsKey = [];
    count = 0;
    for (var i=0; i<list.options.length; i++) {
        if (list.options[i].selected) {
            window.lastSelectedImgs[count] = i;
            window.lastSelectedImgsKey[i] = true;
            count++;
        }
    }

    if (count == 0 ) {
        window.previewSelected = -1;
    }

    if ( n == window.lastShownIndex ) {
        return
    }
    window.lastShownIndex = n;

    index = parseInt(list[n].value)
    filename = list[n].text
    imgPrev(filename, 0, 1, 0, index, 'src', '', 1);
};
";
}

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
    <h3><img alt="SelectImages" src="./images/select_images.png"
             width="40"/>
        &nbsp;Step
        <?php echo $currentStep . "/" . $numberSteps; ?>
        - Select images
    </h3>

    <form method="post" action="" id="select">
        <fieldset class="setting">

            <legend>
                <a href="javascript:openWindow(
                       'http://www.svi.nl/FileFormats')">
                    <img src="images/help.png" alt="?"/>
                </a>
                Image file format
            </legend>

            <select name="ImageFileFormat" id="ImageFileFormat"
                    title="Supported image file formats"
                    size="1"
                    onclick="storeFileFormatSelection(this,autoseries)"
                    onchange="storeFileFormatSelection(this,autoseries);this.form.submit();"
                    onkeyup="this.blur();this.focus();">


                <option name='' value='' format=''>
                    Please choose a file format...
                </option>

                <?php

                // File formats support
                $formats = $fileFormat->possibleValues();
                sort($formats);


                foreach ($formats as $key => $format) {
                    $translation = $fileFormat->translatedValueFor($format);

                    if ($format == $fileFormat->value()) {
                        $selected = " selected=\"selected\"";
                    } else {
                        $selected = "";
                    }
                    ?>
                    <option <?php echo "name = \"" . $format . "\"  value = \"" .
                        $format . "\"" . $selected ?>><?php echo $translation ?>
                    </option>
                    <?php

                }

                ?>

            </select>
        </fieldset>

        <fieldset>
            <legend>Images available on server</legend>
            <div id="userfiles" onmouseover="showPreview()">
                <?php

                $flag = "";
                if ($allFiles == null) {
                    $flag = " disabled=\"disabled\"";
                    $message .= "";
                }

                ?>

                <select onchange="imageAction(this)"
                        title="List of available images"
                        id="filesPerFormat"
                        name="userfiles[]"
                        size="10"
                        multiple="multiple"<?php echo $flag ?>>
                    <?php
                    $keyArr = array();
                    if ($allFiles == null) {
                        echo "                        <option>&nbsp;</option>\n";
                    } else {
                        if ($fileFormat->value() != "") {
                            $format = $fileFormat->value();

                            if (isset($_SESSION['autoseries']) && $_SESSION['autoseries'] == "TRUE") {
                                $files = $_SESSION['fileserver']->condenseSeries();
                            } else {
                                $files = $allFiles;
                            }
                            $selectedFiles = $_SESSION['fileserver']->selectedFiles();

                            foreach ($files as $key => $file) {
                                if ($_SESSION['fileserver']->checkAgainstFormat($file, $format)) {
                                    // Consecutive spaces are collapsed into one space in HTML.
                                    // Hence '&#32;' to correct this when the file has more spaces.
                                    $filteredFile = str_replace(' ', '&#32;', $file);
                                    $exists = false;
                                    foreach ($selectedFiles as $skey => $sfile) {
                                        if (strcmp($sfile, $file) == 0) {
                                            $exists = true;
                                        }
                                    }
                                    if (!$exists) {
                                        echo "<option value=\"" . $key . "\">" . $filteredFile . "</option>\n";
                                        $keyArr[$file] = $key;
                                    }
                                    $keyArr[$file] = $key;
                                }
                            }
                        }
                    }

                    ?>
                </select>
            </div>

            <label id="autoseries_label">

                <input type="checkbox"
                       name="autoseries"
                       class="autoseries"
                       id="autoseries"
                       value="TRUE"
                    <?php
                    if (isset($_SESSION['autoseries']) &&
                        $_SESSION['autoseries'] == "TRUE"
                    ) {
                        echo " checked=\"checked\" ";
                    }
                    ?>
                       onclick="storeFileFormatSelection(ImageFileFormat,this)"
                       onchange="storeFileFormatSelection(ImageFileFormat,this);this.form.submit();"
                />

                Automatically load file series if supported

            </label>

        </fieldset>

        <div id="selection">

            <input name="down"
                   type="submit"
                   value=""
                   class="icon down"
                   onmouseover="TagToTip('ttSpanDown')"
                   onmouseout="UnTip()"/>

            <input name="up"
                   type="submit"
                   value=""
                   class="icon remove"
                   onmouseover="TagToTip('ttSpanUp')"
                   onmouseout="UnTip()"/>

        </div>

        <fieldset>
            <legend>Selected images</legend>
            <div id="selectedfiles" onmouseover="showPreview()">
                <?php

                $selectedFiles = $_SESSION['fileserver']->selectedFiles();

                $flag = "";
                if ($selectedFiles == null) {
                    $flag = " disabled=\"disabled\"";
                }

                ?>
                <select onclick="imageAction(this)"
                        onchange="imageAction(this)"
                        title="List of selected images"
                        id="selectedimages"
                        name="selectedfiles[]"
                        size="5"
                        multiple="multiple"<?php echo $flag ?>>
                    <?php
                    if ($selectedFiles != null) {
                        foreach ($selectedFiles as $filename) {
                            $key = $keyArr[$filename];
                            echo $_SESSION['fileserver']->getImageOptionLine($filename,
                                $key, "src", "preview", 0, 1);
                        }
                    } else echo "                        <option>&nbsp;</option>\n";

                    ?>
                </select>
            </div>
        </fieldset>

        <div id="actions" class="imageselection"
             onmouseover="showInstructions()">
            <input name="update"
                   type="submit"
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

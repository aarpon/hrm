<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Fileserver.inc.php");
require_once("./inc/System.inc.php");
require_once("./inc/wiki_help.inc.php");

session_start();

if (isset($_GET['home'])) {
  header("Location: " . "home.php"); exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
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
$nextStep    = $currentStep + 1;
$goNextMessage  = "Continue to step $nextStep/$numberSteps - ";
$goNextMessage .= "Image parameters";

if (!isset($_SESSION['fileserver'])) {
  # session_register("fileserver");
  $name = $_SESSION['user']->name();
  $_SESSION['fileserver'] = new Fileserver($name);
}

if (!isset($_SESSION[ 'parametersetting' ])) {
    $_SESSION[ 'parametersetting' ] = new ParameterSetting();
}
$fileFormat = $_SESSION[ 'parametersetting' ]->parameter("ImageFileFormat");

$message = "";
if (isset($_POST['down'])) {
    if (isset($_POST['autoseries'])) {
        $_SESSION['autoseries'] = $_POST['autoseries'];
    } else {
        $_SESSION['autoseries'] = "";
    }

    if (isset($_POST['userfiles']) && is_array($_POST['userfiles'])) {

        // Remove spaces added by the HRM file selector. See '&nbsp;' below.
        $fileNames = array();
        foreach ($_POST['userfiles'] as $file) {
            $name = htmlentities($file, null, 'utf-8');
            $name = str_replace("&nbsp;", " ", $name);
            $name = html_entity_decode($name);
            $fileNames[] = $name;
        }
        $_SESSION['fileserver']->addFilesToSelection($fileNames);
    }
}
else if (isset($_POST['up'])) {
    if (isset($_POST['autoseries'])) {
        $_SESSION['autoseries'] = $_POST['autoseries'];
    } else {
        $_SESSION['autoseries'] = "";
    }
    if (isset($_POST['selectedfiles']) && is_array($_POST['selectedfiles'])) {

        // Remove spaces added by the HRM file selector. See '&nbsp;' below.
        $fileNames = array();
        foreach ($_POST['selectedfiles'] as $file) {
            $name = htmlentities($file, null, 'utf-8');
            $name = str_replace("&nbsp;", " ", $name);
            $name = html_entity_decode($name);
            $fileNames[] = $name;
        }
        $_SESSION['fileserver']->removeFilesFromSelection($fileNames);
    }
}
else if (isset($_POST['update'])) {
    if (isset($_POST['autoseries'])) {
        $_SESSION['autoseries'] = $_POST['autoseries'];
    } else {
        $_SESSION['autoseries'] = "";
    }
    $_SESSION['fileserver']->resetFiles();
}
else if (isset($_POST['OK'])) {

    if (!$_SESSION['fileserver']->hasSelection()) {
        $message = "Please add at least one image to your selection";
    } else {
        header("Location: " . "select_parameter_settings.php"); exit();
    }
}

$script = array( "settings.js","ajax_utils.js" );

$_SESSION['fileserver']->resetFiles();

/* Set the default extensions. */
$_SESSION['fileserver']->imageExtensions();

// All the user's files in the server.
$allFiles = $_SESSION['fileserver']->listFiles(TRUE);

// All the user's series in the server.
$condensedSeries = $_SESSION['fileserver']->condenseSeries();


// display only relevant files.
if ($allFiles != null) {

    $generatedScript = "
function storeFileFormatSelection(sel,series) {

   // Get current selection
   var format = $('#' + sel.id + ' :selected').attr(\"name\");

   // Store it
   ajaxSetFileFormat(format);

   // Now filter by type
   filterImages(sel,series);
};
";

    $generatedScript .= "
function filterImages (format,series) {

    var selectObject = document.getElementById(\"selectedimages\");
    if (selectObject.length >= 0) {
        for (var i = selectObject.length - 1; i>=0; i--) {
            selectObject.remove(selectObject.length - 1);
        }
    }

    var selectObject = document.getElementById(\"filesPerFormat\");
    if (selectObject.length > 0) {
        for (var i = selectObject.length - 1; i>=0; i--) {
            selectObject.remove(selectObject.length - 1);
        }
    }

    var selectedFormat = format.options[format.selectedIndex].value;

    var autoseries = document.getElementById(\"autoseries\");
";

    /* For each file, create javascript code for when the file
    belongs to a series and for when it doesn't. */
    foreach ($allFiles as $key => $file) {

        if ($_SESSION['fileserver']->isPartOfFileSeries($file)) {

            $generatedScript .= "

              // Automatically load file series.
              if(autoseries.checked) {
              ";

            if (in_array($file,$condensedSeries)) {
                $generatedScript .= "
                  if(checkAgainstFormat('$file', selectedFormat)) {
                    var f = \"$file\";
                    f = f.replace(/ /g, '&nbsp;');
                    var selectItem = document.createElement('option');
                    $(selectItem).html(f);
                    $(selectItem).attr('title', '$file');
                    selectObject.add(selectItem,null);
                  }
                  ";
            }
            $generatedScript .= "

              } else {

                  // Do not load file series automatically.
                  if(checkAgainstFormat('$file', selectedFormat)) {
                    var f = \"$file\";
                    f = f.replace(/ /g, '&nbsp;');
                    var selectItem = document.createElement('option');
                    $(selectItem).html(f);
                    $(selectItem).attr('title', '$file');
                    selectObject.add(selectItem,null);
                  }
              }
              ";

        } else {
            $generatedScript .= "
            if(checkAgainstFormat('$file', selectedFormat)) {
                    var f = \"$file\";
                    f = f.replace(/ /g, '&nbsp;');
                    var selectItem = document.createElement('option');
                    $(selectItem).html(f);
                    $(selectItem).attr('title', '$file');
                    selectObject.add(selectItem,null);
                  }
            ";
        }

    }

    $generatedScript .= "

               // Since we have changed the file format, we reset
               // the last selected image index. Otherwise, if the
               // user chose the first file of format A and then switches
               // to the first file of format B, the thumbnail won't
               // be refreshed.
               window.lastSelectedImgs = [];
               window.lastSelectedImgsKey = [];
               window.lastShownIndex = -1;
               ";

    $generatedScript .= "

}

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

    var val = list[n].value;

    if ( n == window.lastShownIndex ) {
        return
    }
    window.lastShownIndex = n;

    switch ( val )
    {
";

    foreach ($allFiles as $key => $file) {
        $generatedScript .= "
        case \"$file\" :
            ". $_SESSION['fileserver']->getImageAction($file,
                $key, "src", "preview", 0, 1). "
            break;
            ";
    }


    $generatedScript .= "
    }
}
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
    <span class="toolTip"  id="ttSpanUp">
        Remove files from the list of selected images.
    </span>
    <span class="toolTip"  id="ttSpanRefresh">
        Refresh the list of available images on the server.
    </span>
    <span class="toolTip"  id="ttSpanForward">
    <?php echo $goNextMessage;?>
    </span>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
                wiki_link('HuygensRemoteManagerHelpSelectImages');
            ?>
        </ul>
    </div>
    <div id="navright">
        <ul>
            <?php
                include("./inc/nav/user.inc.php");
                include("./inc/nav/raw_images.inc.php");
                include("./inc/nav/home.inc.php");
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

                    <form method="post" action="" id="fileformat">
                    <fieldset class="setting" >

                <legend>
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/FileFormats')">
                        <img src="images/help.png" alt="?" />
                    </a>
                    Image file format
                </legend>

                    <select name="ImageFileFormat" id="ImageFileFormat"
                     size="1"
                     onclick="javascript:storeFileFormatSelection(this,autoseries)"
                     onchange="javascript:storeFileFormatSelection(this,autoseries)"
                     onkeyup="this.blur();this.focus();" >


<option name = '' value = '' format = ''>
Please choose a file format...
</option>

<?php

// File formats support
$formats = $fileFormat->possibleValues();
sort($formats);



foreach($formats as $key => $format) {
  $translation = $fileFormat->translatedValueFor($format);

    if ($format == $fileFormat->value()) {
        $selected = " selected=\"selected\"";
    } else {
        $selected = "";
    }
?>
      <option <?php echo "name = \"" . $format . "\"  value = \"" .
           $format  . "\"" . $selected ?>><?php echo $translation ?>
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

                    <select onchange="javascript:imageAction(this)"
                            id = "filesPerFormat"
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

        if (isset($_SESSION['autoseries']) &&
            $_SESSION['autoseries'] == "TRUE") {
            $files = $condensedSeries;
        } else {
            $files = $allFiles;

        }

        foreach ($files as $key => $file) {
            if ($_SESSION['fileserver']->checkAgainstFormat($file, $format)) {
                // Consecutive spaces are collapsed into one space in HTML.
                // Hence '&nbsp;' to correct this when the file has more spaces.
                echo "<option>" .
                    str_replace(' ','&nbsp;',$file) .
                    "</option>\n";
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
                                   $_SESSION['autoseries'] == "TRUE") {
                               echo " checked=\"checked\" ";
                           }
                           ?>
                           onclick="javascript:storeFileFormatSelection(ImageFileFormat,this)"
                           onchange="javascript:storeFileFormatSelection(ImageFileFormat,this)"
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
                onmouseout="UnTip()" />

              <input name="up"
                type="submit"
                value=""
                class="icon remove"
                onmouseover="TagToTip('ttSpanUp')"
                onmouseout="UnTip()" />

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
                    <select onclick="javascript:imageAction(this)"
                            onchange="javascript:imageAction(this)"
                            id = "selectedimages"
                            name="selectedfiles[]"
                            size="5"
                            multiple="multiple"<?php echo $flag ?>>
<?php
if ($selectedFiles != null) {
  foreach ($selectedFiles as $filename) {
          $key = $keyArr[$filename];
          echo $_SESSION['fileserver']->getImageOptionLine($filename,
              $key, "src", "preview", 0, 1) ;
  }
}
else echo "                        <option>&nbsp;</option>\n";

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
                <input name="OK" type="hidden" />
            </div>

            <div id="controls"
                 onmouseover="showInstructions()">
              <input type="submit"
                     value=""
                     class="icon next"
                     onclick="process()"
                     onmouseover="TagToTip('ttSpanForward')"
                     onmouseout="UnTip()" />
            </div>

        </form>

    </div> <!-- content -->

    <script type="text/javascript">
        <!--
            window.pageInstructions='<?php echo escapeJavaScript($info); ?>';
            window.infoShown = true;
            window.previewSelected = -1;
        //-->
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

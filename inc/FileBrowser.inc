<?php

// Module FileBrowser.inc

// This file is part of huygens remote manager.

// Copyright: Montpellier RIO Imaging (CNRS)

// contributors :
// >         Jose Viña          (file browser tools)

// email:
// 	jose@svi.nl

// Web:     www.mri.cnrs.fr

// huygens remote manager is a software that has been developed at 
// Montpellier Rio Imaging (mri) in 2004 by Pierre Travo and Volker 
// Baecker. It allows running image restoration jobs that are processed 
// by 'Huygens professional' from SVI. Users can create and manage parameter 
// settings, apply them to multiple images and start image processing 
// jobs from a web interface. A queue manager component is responsible for 
// the creation and the distribution of the jobs and for informing the user 
// when jobs finished.

// This software is governed by the CeCILL license under French law and 
// abiding by the rules of distribution of free software. You can use, 
// modify and/ or redistribute the software under the terms of the CeCILL 
// license as circulated by CEA, CNRS and INRIA at the following URL 
// "http://www.cecill.info".

// As a counterpart to the access to the source code and  rights to copy, 
// modify and redistribute granted by the license, users are provided only 
// with a limited warranty and the software's author, the holder of the 
// economic rights, and the successive licensors  have only limited 
// liability.

// In this respect, the user's attention is drawn to the risks associated 
// with loading, using, modifying and/or developing or reproducing the 
// software by the user in light of its specific status of free software, 
// that may mean that it is complicated to manipulate, and that also 
// therefore means that it is reserved for developers and experienced 
// professionals having in-depth IT knowledge. Users are therefore encouraged 
// to load and test the software's suitability as regards their requirements 
// in conditions enabling the security of their systems and/or data to be 
// ensured and, more generally, to use and operate it in the same conditions 
// as regards security.

// The fact that you are presently reading this means that you have had 
// knowledge of the CeCILL license and that you accept its terms.





// This file is used to generate an image file browser in certain HRM tools,
// like in estimate_snr_from_image.php or file_management.php. When a file
// browser must be combined in a more complex page, like in select_image.php,
// this can not be so easily used. This is an interface to Fileserver.inc.


//!---------------------------------------------------------
// @function fileButton
// @desc     Generates some basic buttons to be used with the file browser,
//           dependending on the tool needs.
//!---------------------------------------------------------

function fileButton ($type) {
    global $decompressBin;

    $error = false;

    # Some buttons post the form, but other use JavaScript to show some
    # confirmation before actually posting.
    $mode = "ajax";

    switch ($type) {
        case "download":
            $img = "images/download.png";
            $onClick = "downloadImages()";
            $alt = "Download";
            $tip = 'Pack selected images and related files, and download';
            break;

        case "upload":
            $max = getMaxFileSize() / 1024 / 1024;
            $maxFile = "$max MB";
            $max = getMaxPostSize() / 1024 / 1024;
            $maxPost = "$max MB";
            $img = "images/upload.png";
            $validExtensions = 
                $_SESSION['fileserver']->getValidArchiveTypesAsString();
            $onClick = "uploadImages('$maxFile', '$maxPost', ".
                "'$validExtensions')";
            $alt = "Upload";
            $tip = 'Upload a file (or a compressed archive of files) to the '.
                'server';
            break;



        case "delete":
            $img = "images/delete.png";
            $onClick = "deleteImages()";
            $alt = "Delete";
            $tip = 'Delete selected images and related files';
            break;

        case "update":
            # This button posts the form.
            # $img = "images/update.png";
            # $onClick = "updateListing()";
            # $alt = "Refresh";
            $mode = "post";
            $value = "";
            $name = "update";
            $class = "icon update";
            $tip = "Refresh image list";
            break;

        default:
            $error = "No button of type $type";

    }

    if ($error) { return $error; }

    if ($mode == "post" ) {
        $ret = "\n\n<input name=\"$name\" type=\"submit\" 
                 value=\"$value\" class=\"$class\" 
                 onmouseover=\"Tip('$tip')\" onmouseout=\"UnTip()\" />";
    } else {
        $ret = "\n\n<img src=\"$img\" alt=\"$alt\"
            onclick=\"UnTip(); $onClick\"
            onmouseover=\"Tip('$tip')\" onmouseout=\"UnTip()\" />";
    }

    return $ret;

}



// This tool requires setting some configuration parameters, that are defined
// in the code that includes this script.

// FileServer related code:
if (!isset($_SESSION['fileserver'])) {
    $name = $_SESSION['user']->name();
    $_SESSION['fileserver'] = new Fileserver($name);
}

// Refresh the directory listing:
if (isset($_POST['update'])) {
    if ($browse_folder == "src") {
        $_SESSION['fileserver']->updateAvailableFiles();
    } else {
        $_SESSION['fileserver']->updateAvailableDestFiles();
    }
}


// JavaScript
$script = "settings.js";

if (!isset($operationResult)) {
    $operationResult = "";
}

// There are two possible main folders for the user to inspect: one for the
// source (src) images, another for the result images (dest).

// $browse_folder can be 'src' or 'dest'.
if ($browse_folder == "src") {

    // Files can be restricted to a certain image type only, or to all.

    if (!isset($restrictFileType) || $restrictFileType === false ) {
        // Show all image files.

        $_SESSION['fileserver']->setDefaultImageExtensions(array());
        if (isset($expandSubImages) && $expandSubImages ) {
            // In certain conditions (e.g. the SNR estimator) we want to list
            // all subimages that every file may contain.
            $_SESSION['fileserver']->expandSubImages(true);
        } else {
            // In the file manager we want to treat files as such, not listing
            // subimages.
            $_SESSION['fileserver']->expandSubImages(false);
        }
        $files = $_SESSION['fileserver']->files();

    } else {

        // Show files of one image type only.

        $fileFormatParam = $_SESSION['setting']->parameter("ImageFileFormat");
        $fileFormat = $fileFormatParam->value();
        $extensions = $fileFormatParam->fileExtensions();
        $_SESSION['fileserver']->setImageExtensions($extensions);
        $files = $_SESSION['fileserver']->filesOfType($fileFormat);

    }
} else {
    // When listing results images, all types are shown.
    $files = $_SESSION['fileserver']->destFiles();
}

if ($multiple_files) {
    // Allow multiple selection.
    $multiple = "multiple=\"multiple\"";
} else {
    $multiple = "";
}

if ($files != null) {

    // JavaScript code to show image thumbnails when the user clicks some file
    // in the list.

    $generatedScript = "
function imageAction (list) {
    action = '';
    changeDiv('upMsg', '');
    changeDiv('actions', '');


    var n = list.selectedIndex;     // Which item is the first selected one

    if( undefined === window.lastSelectedImgs ){
        window.lastSelectedImgs = [];
        window.lastSelectedImgsKey = [];
        window.lastShownIndex = -1;
    }

    var selectedNew = 0;

    count = 0;

    // Compare last selection with the current one, to find which file has been
    // selected or deselected.

    for (i=0; i<list.options.length; i++) {
        if (list.options[i].selected) {
            if( undefined === window.lastSelectedImgsKey[i] ){
                // New selected item
                selectedNew = 1;
                n = i;
            }
            count++;
        }
    }

    if (selectedNew == 0) {
        // If nothing was selected, it means that the click deselected an image
        for (i=0; i<window.lastSelectedImgs.length; i++) {
            key = window.lastSelectedImgs[i];
            if ( !list.options[key].selected ) {
                selectedNew = -1;
                n = key;
            }
        }
    }

    // Remember the current selection for the next user interaction.

    window.lastSelectedImgs = [];
    window.lastSelectedImgsKey = [];
    count = 0;
    for (i=0; i<list.options.length; i++) {
        if (list.options[i].selected) {
            window.lastSelectedImgs[count] = i;
            window.lastSelectedImgsKey[i] = true;
            count++;
        }
    }

    if (count == 0 ) {
        window.previewSelected = -1;
    }

    // Show image preview of the last clicked element in the list:

    var val = list[n].value;

    if ( n == window.lastShownIndex ) {
        return
    }
    window.lastShownIndex = n;

    switch ( val )
    {
";

    # Generate at case for each of the available files, so that the
    # correspondent thumbnail and information is shown when the user clicks on
    # an image.

    foreach ($files as $key => $file) {
        $generatedScript .= "
        case \"$file\" :
            ". $_SESSION['fileserver']->getImageAction($file,
                $key, $browse_folder, "preview", 1, $useTemplateData). "
            break;
            ";
    }


    $generatedScript .= "
    }
}
";
}

// The form is enabled only if files are available, otherwise there's nothing
// to operate with.
$flag = "";
if ($files == null) $flag = " disabled=\"disabled\"";

include("header.inc.php");



// HTML CODE:
?>

    <div id="nav">
        <ul><?php echo $top_navigation; ?></ul>
    </div>


    <div id="content" >
        <h3><?php echo $page_title; ?></h3>
  <form method="post" action="?folder=<?php echo $browse_folder;?>" id="file_browser" onSubmit="return confirmSubmit()" >

    
      <fieldset >
      
        <legend><?php echo $form_title; ?></legend>
<?php        
?>




        <div id="userfiles" onmouseover="showPreview()">
          <select onchange="javascript:imageAction(this)" onkeyup="this.blur();this.focus();" name="userfiles[]" size="<?php echo $size;?>" <?php echo $multiple.$flag ?>>
          <?php
          // Populate the select field with the list of available images:

          if ($files != null) {
              foreach ($files as $key => $file) {
                  echo $_SESSION['fileserver']->getImageOptionLine($file,
                          $key, "dest", "preview", 1, 0);
              }
          }
          else echo "                        <option>&nbsp;</option>\n";


          ?>
          </select>
        </div>
        
      </fieldset>

      <div id="selection" onmouseover="showInstructions()">
        <?php foreach ($file_buttons as $b) { echo fileButton($b); }; ?>
      </div>
      <div id="actions" onmouseover="showInstructions()"><!-- do not remove !--></div>
      <div id="controls" class="imageselection" onmouseover="showInstructions()">
        <?php echo $control_buttons; ?>
      </div>
  </form>
  <div id="upMsg"><!-- do not remove !--></div>
  <div id="up_form" onmouseover="showInstructions()"><!-- do not remove !--></div>
      
    </div> <!-- content -->


    <script type="text/javascript">
        <!--
            window.pageInstructions='<?php echo escapeJavaScript($info); ?>';
            window.infoShown = true;
            window.previewSelected = -1;
        -->
    </script>
    <div id="rightpanel">
        <div id="info">
        <?php if ($operationResult != "") echo $operationResult; else echo $info; ?>
        </div>


    <div id="message">
<?php
     // Display any message coming from lower instances.

     echo $message;

?>
    </div> <!-- message -->

    </div> <!-- rightpanel -->



<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

/*!
  \brief Image file browser

  This file is used to generate an image file browser in certain HRM tools,
  like in estimate_snr_from_image.php or file_management.php. When a file
  browser must be combined in a more complex page, like in select_image.php,
  this can not be so easily used. This is an interface to the Fileserver.
*/

require_once( "inc/Util.inc.php" );
require_once( "inc/OmeroConnection.inc.php");
require_once( "inc/wiki_help.inc.php" );
require_once( "inc/Util.inc.php" );
require_once( "inc/UtilV2.inc.php" );

/*!
  \brief  Generates basic buttons for the image file browser

  This function generates basic buttons depending on what the file browser
  needs. This is specified with the input parameter $type.

  \param  $type   One of 'download', 'upload', 'delete', or 'update'.
 */
function fileButton($type) {

  global $decompressBin;

  $error = false;

  # Some buttons post the form, but other use JavaScript to show some
  # confirmation before actually posting.
  $mode = "ajax";

  switch ($type) {
    case "download":
      $onClick = "downloadImages()";
      $name = "download";
      $tip = 'Compress and download';
      break;

    case "upload":
      $max = getMaxFileSize() / 1024 / 1024;
      $maxFile = "$max MB";
      $max = getMaxPostSize() / 1024 / 1024;
      $maxPost = "$max MB";
      $validExtensions =
              $_SESSION['fileserver']->getValidArchiveTypesAsString();
      $onClick = "uploadImagesAlt()";
      $tip = 'Upload one or more files (or compressed archives of files) to the ' .
              'server';
      $name = "upload";
      break;

    case "delete":
      $onClick = "deleteImages()";
      $name = "delete";
      $tip = 'Delete selected images';
      break;

    case "update":
      # This button posts the form.
      # $img = "images/update.png";
      $onClick = "setActionToUpdate();";
      # $alt = "Refresh";
      $mode = "post";
      $value = "update";
      $name = "update";
      $class = "icon update";
      $tip = "Refresh image list";
      break;

    case "omeroImport":
        $name    = "getOmeroData";
        $value   = "OMERO Data";
        $mode    = "post";
        $onClick = "setActionToUpdate();";
        $class = "icon omero";
        $tip = 'Select and import data for deconvolution '.
               'from your OMERO account';
        break;

    case "omeroExport":
        $name    = "getOmeroData";
        $value   = "OMERO Data";
        $mode    = "post";
        $onClick = "setActionToUpdate();";
        $class = "icon omero";
        $tip = 'Select and export deconvolved data to your OMERO account';
        break;

    default:
      $error = "No button of type $type";
  }

  if ($error) {
    return $error;
  }

  if ($mode == "post") {
      if ( !isset( $onClick ) ) {
          $onClick = '';
      }
    $ret = "\n\n<input name=\"$name\" type=\"submit\"
                 value=\"$value\" class=\"$class\"
                 onclick=\"UnTip(); $onClick\"
                 onmouseover=\"Tip('$tip')\" onmouseout=\"UnTip()\" />";
  } else {
    $ret = "\n\n<input class=\"icon $name\" type=\"button\"
            onclick=\"UnTip(); $onClick\"
            onmouseover=\"Tip('$tip')\" onmouseout=\"UnTip()\" />";
  }

  return $ret;
}

// Doxygen gets very confused by this page. We force it to skip the whole code
/*!
  \cond
*/


// This tool requires setting some configuration parameters, that are defined
// in the code that includes this script.

// Doxygen makes a mess parsing this code block
/*!
  \cond
*/

// FileServer related code:
if (!isset($_SESSION['fileserver'])) {
  $name = $_SESSION['user']->name();
  $_SESSION['fileserver'] = new Fileserver($name);
}

// Refresh the directory listing:
if (isset($_POST['update'])) {
  if ($browse_folder == "src") {
    $_SESSION['fileserver']->resetFiles();
  } else {
    $_SESSION['fileserver']->resetDestFiles();
  }
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



// JavaScript
$script = array("settings.js",
                "jquery-1.8.3.min.js",
                "jqTree/tree.jquery.js",
                "jquery-ui/jquery-ui-1.9.1.custom.js",
                "jquery-ui/jquery.bgiframe-2.1.2.js",
                "fineuploader/jquery.fine-uploader.js",
                "omero.js");

if (!isset($operationResult)) {
  $operationResult = "";
}

/*!
  \endcond
*/

// There are two possible main folders for the user to inspect: one for the
// source (src) images, another for the result images (dest).

// $browse_folder can be 'src' or 'dest'.
if ($browse_folder == "src") {

  // Files can be restricted to a certain image type only, or to all.

  if (!isset($restrictFileType) || $restrictFileType === false) {
    // Show all image files.
    if (isset($expandSubImages) && $expandSubImages) {
      // In certain conditions (e.g. the SNR estimator) we want to list
      // all subimages that every file may contain.
      $files = $_SESSION['fileserver']->listFiles( true );
    } else {
      // In the file manager we want to treat files as such, not listing
      // subimages.
      $files = $_SESSION['fileserver']->listFiles( false );
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

  # FIXME: this is broken with file names containing double quotes!

  # Generate at case for each of the available files, so that the
  # correspondent thumbnail and information is shown when the user clicks on
  # an image.

  if ($browse_folder == "src") {
    $pdir = $_SESSION['fileserver']->sourceFolder();
  } else {
    $pdir = $_SESSION['fileserver']->destinationFolder();
  }

  // Sometimes the file variable stores part of the path as well.
  $pattern = "/(.*)\/(.*)_(.*)_(.*)\.(.*)$/";

  foreach ($files as $key => $file) {

    // The source folder contains no hrm job ids.
    if ($browse_folder == "src") {
      $fileForAction = $file;
    } else {

      // The destination folder needs parsing to locate the previews.
      $pathAndFile = $pdir . "/" . $file;
      preg_match($pattern, $pathAndFile, $matches);
      $filePreview = $matches[1] . "/hrm_previews/";
      $filePreview .= basename($file) . ".preview_xy.jpg";

      // Build a file name compliant with the new naming convention.
      if (!file_exists($filePreview)) {
        $subdir = str_replace($pdir . "/", "", $matches[1], $count);
        if ($subdir && $count) {
          $fileForAction = $subdir . "/";
        } else {
          $fileForAction = "";
        }
        $fileForAction .= $matches[3] . "_" . $matches[4];
        $fileForAction .= "." . $matches[5];
      } else {
        // Build a file name compliant with the old naming convention.
        $fileForAction = $file;
      }
    }

    $generatedScript .= "
        case \"$file\" :
            " . $_SESSION['fileserver']->getImageAction($fileForAction, $key,
              $browse_folder, "preview", 1, $useTemplateData) . "
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
if ($files == null) {
  $flag = " disabled=\"disabled\"";
}

include("header_fb.inc.php");



// HTML CODE:
?>

    <div id="nav">
        <div id="navleft">
            <ul>
            <?php
                if (isset($top_nav_left)) {
                    echo $top_nav_left;
                } else {
                    wiki_link('HuygensRemoteManagerHelpFileManagement');
                }
            ?>
            </ul>
        </div>
        <div id="navright">
            <ul>
            <?php
            if ( isset( $_SESSION['filemanager_referer'] ) ) {
                $referer = $_SESSION['filemanager_referer'];
                // the "Back" button is only displayed if the referring page
                // was not the dashboard (home.php) but e.g. the "Select
                // images" when creating a new job.
                if ( strpos( $referer, 'home.php' ) === False ) {
                    include("./inc/nav/back.inc.php");
                }
            }
            if ( $browse_folder == "dest" ) {
                include("./inc/nav/files_raw.inc.html");
            } else {
                include("./inc/nav/files_results.inc.html");
            }
            include("./inc/nav/user.inc.php");
            include("./inc/nav/home.inc.php");
            ?>
            </ul>
        </div>
        <div class="clear"></div>
    </div>


    <div id="content" >

    <?php if ($page_title == "Raw images") {
    $icon = "./images/rawdata_title.png";
} else {
    $icon = "./images/results_title.png";
} ?>

<h3><img alt=<?php echo $page_title;?> src=<?php echo $icon;?> width="40"/>
    &nbsp;&nbsp;<?php echo $page_title; ?></h3>

    <form method="post" action="?folder=<?php echo $browse_folder;?>"
        id="file_browser" onsubmit="return confirmSubmit()" >

        <?php
            // Add additional input elements if defined
            if (isset($additionalHTMLElements)) {
                echo $additionalHTMLElements;
            }
        ?>

        <p class="message_small"><?php echo $explanation_text; ?></p>

      <fieldset >

        <legend><?php echo $form_title; ?></legend>

        <div id="userfiles" onmouseover="showPreview()">
          <select name="userfiles[]" id="fileSelection"
                  onchange="javascript:imageAction(this)"
                  onkeyup="this.blur();this.focus();"
                  size="<?php echo $size;?>" <?php echo $multiple.$flag ?>>
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
      <div id="actions" onmouseover="showInstructions()">
          <!-- do not remove !-->
      </div>
      <div id="controls" class="imageselection"
           onmouseover="showInstructions()">
        <?php echo $control_buttons; ?>
      </div>
      </form>


      <?php
      include("./omero_UI.php");
      ?>

        <div id="upMsg">
            <!-- Do not remove -->
        </div>

      <div id="up_form" onmouseover="showInstructions()">
        <div id="fine-uploader-manual-trigger"></div>
	  </div>

        <script type="text/javascript">
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
                        "DestinationFolder" : "<?php echo($_SESSION['fileserver']->sourceFolder()); ?>",
                        "ImageExtensions" : ['dv', 'ims', 'lif', 'lsm', 'oif', 'pic', 'r3d', 'stk',
                            'zvi', 'czi', 'nd2', 'tf2', 'tf8', 'btf', 'h5', 'tif', 'tiff', 'ome.tif',
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
                    acceptFiles: ".dv,.ims,.lif,.lsm,.oif,.pic,.3rd,.stk,.zvi,.czi,.nd2,.tf2,.tf8,.btf,.h5," +
                        ".tif,.tiff,.ome.tif,.ome.tiff,.ics,.ids,.zip,.tgz,.tar,.tar.gz",
                    allowedExtensions: ['dv', 'ims', 'lif', 'lsm', 'oif', 'pic', 'r3d', 'stk',
                        'zvi', 'czi', 'nd2', 'tf2', 'tf8', 'btf', 'h5', 'tif', 'tiff', 'ome.tif',
                        'ome.tiff', 'ome', 'ics', 'ids', 'zip', 'tgz', 'tar', 'tar.gz']
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
                    onAllComplete: function(succeeded, failed) {
                        // Rescan the source folder only if everything was uploaded successfully.
                        // If not the user can still interact with the files.
                        if (failed.length == 0) {
                            setActionToUpdate();
                            $("form#file_browser").submit();
                        }
                    }
                }
            });

            $('#trigger-upload').click(function() {
                $('#fine-uploader-manual-trigger').fineUploader('uploadStoredFiles');
            });

            // Hide the uploader until requested by the user
            $("#up_form").hide();
        </script>

    </div> <!-- content -->


    <script type="text/javascript">
            window.pageInstructions='<?php echo escapeJavaScript($info); ?>';
            window.infoShown = true;
            window.previewSelected = -1;
    </script>
    <div id="rightpanel">
        <div id="info">
        <?php
        if ($operationResult != "") {
            echo $operationResult;
        } else {
            echo $info;
        }
        ?>
        </div>


    <div id="message">
<?php
     // Display any message coming from lower instances.
     if (!isset($message)) {
         $message = "";
     }
     echo "<p>$message</p>";

?>
    </div> <!-- message -->

    </div> <!-- rightpanel -->

<?php
// Doxygen gets very confused by this page. We force it to skip the whole code
/*!
 \endcond
*/
?>

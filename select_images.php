<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Fileserver.inc.php");

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

if (!isset($_SESSION['fileserver'])) {
  # session_register("fileserver");
  $name = $_SESSION['user']->name();
  $_SESSION['fileserver'] = new Fileserver($name);
}

$fileFormat = $_SESSION['setting']->parameter("ImageFileFormat");
$extensions = $fileFormat->fileExtensions();
$_SESSION['fileserver']->setImageExtensions($extensions);

$geometry = $_SESSION['setting']->parameter("ImageGeometry");

$message = "";

if (isset($_POST['down'])) {
  if (isset($_POST['userfiles']) && is_array($_POST['userfiles'])) {
    $_SESSION['fileserver']->addFilesToSelection($_POST['userfiles']);
  }
}
else if (isset($_POST['up'])) {		
  if (isset($_POST['selectedfiles']) && is_array($_POST['selectedfiles'])) {
    $_SESSION['fileserver']->removeFilesFromSelection($_POST['selectedfiles']);
  }  
}
else if (isset($_POST['update'])) {
  $_SESSION['fileserver']->updateAvailableFiles();
}
else if (isset($_POST['OK'])) {
  if (!$_SESSION['fileserver']->hasSelection()) {
    $message = "Please add at least one image to your selection";
  }
  else {
    header("Location: " . "create_job.php"); exit();
  }
}

$script = "settings.js";

// display only relevant files
if ($fileFormat->value() == "ics" || $fileFormat->value() == "ics2" ) {
    $files = $_SESSION['fileserver']->files("ics");
}
else if ($fileFormat->value() == "hdf5" ) {
  $files = $_SESSION['fileserver']->files("h5");
}
else if ($fileFormat->value() == "tiff" || $fileFormat->value() == "tiff-single") {
  $files = $_SESSION['fileserver']->tiffFiles();
}
else if ($fileFormat->value() == "tiff-series") {
  $files = $_SESSION['fileserver']->tiffSeriesFiles();
}
else if ($fileFormat->value() == "tiff-leica") {
  $files = $_SESSION['fileserver']->tiffLeicaFiles();
}
else if ($fileFormat->value() == "stk") {
    if ($_SESSION['setting']->isTimeSeries()) {
      $files = $_SESSION['fileserver']->stkSeriesFiles();
    }
    else {
      $files = $_SESSION['fileserver']->stkFiles();
    }
}
else {
  $files = $_SESSION['fileserver']->files();
}

if ($files != null) {

    $generatedScript = "
function imageAction (list) {

    var n = list.selectedIndex;     // Which item is the first selected one

    if( undefined === window.lastSelectedImgs ){
        window.lastSelectedImgs = [];
        window.lastSelectedImgsKey = [];
        window.lastShownIndex = -1;
    }

    var snew = 0;

    count = 0;
    for (i=0; i<list.options.length; i++) {
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
        for (i=0; i<window.lastSelectedImgs.length; i++) {
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

    var val = list[n].value;

    if ( n == window.lastShownIndex ) {
        return
    }
    window.lastShownIndex = n;

    switch ( val )
    {
";

    foreach ($files as $key => $file) {
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

$info = " <h3>Quick help</h3> <p>In this step, you will select the files from the list of available images that will be restored using the image and restoration parameters chosen in the previous two steps.</p> <p>Only files of type <b>". $fileFormat->value()."</b>, as selected in the image paremeters, are shown.</p><p>You can use SHIFT- and CTRL-click to select multiple files.</p> <p>Click on a file name in any of the fields to get a preview.</p>";
 

?>
    <!--
      Tooltips
    -->
    <span id="ttSpanDown">Add files to the list of selected images.</span>
    <span id="ttSpanUp">Remove files from the list of selected images.</span>
    <span id="ttSpanRefresh">Refresh the list of available images on the server.</span>
    <span id="ttSpanBack">Go back to step 2/4 - Restoration parameters.</span>
    <span id="ttSpanForward">Continue to step 4/4 - Create job</span>
    
    <div id="nav">
        <ul>
            <li><img src="images/user.png" alt="user" />&nbsp;<?php echo $_SESSION['user']->name(); ?></li>
            <li><a href="<?php echo getThisPageName();?>?home=home"><img src="images/home.png" alt="home" />&nbsp;Home</a></li>
            <li><a href="javascript:openWindow('http://www.svi.nl/HuygensRemoteManagerHelpSelectImages')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
        <h3>Step 3/4 - Select images</h3>
        
        <form method="post" action="" id="select">
        
            <fieldset>
                <legend>Images available on server</legend>
                <div id="userfiles"  onmouseover="showPreview()">
<?php

$flag = "";
if ($files == null) {
    $flag = " disabled=\"disabled\"";
    $message .= "No images of type '" .$fileFormat->value();
}

?>
                    <select onchange="javascript:imageAction(this)" name="userfiles[]" size="10" multiple="multiple"<?php echo $flag ?>>
<?php
$keyArr = array();
if ($files != null) {
  foreach ($files as $key => $filename) {
          echo $_SESSION['fileserver']->getImageOptionLine($filename, $key, 
                                                      "src","preview", 0, 1) ;
          $keyArr[$filename] = $key;

  }
}
else echo "                        <option>&nbsp;</option>\n";

?>
                    </select>
                </div>
            </fieldset>
            
            <div id="selection">
                <input name="down" type="submit" value="" class="icon down"
                    onmouseover="TagToTip('ttSpanDown' )"
                    onmouseout="UnTip()" />
                <input name="up" type="submit" value="" class="icon remove"
                    onmouseover="TagToTip('ttSpanUp' )"
                    onmouseout="UnTip()" />
            </div>
            
            <fieldset>
                <legend>Selected images</legend>
                <div id="selectedfiles" onmouseover="showPreview()">
<?php

$files = $_SESSION['fileserver']->selectedFiles();
$flag = "";
if ($files == null) $flag = " disabled=\"disabled\"";

?>
                    <select onclick="javascript:imageAction(this)" onchange="javascript:imageAction(this)" name="selectedfiles[]" size="5" multiple="multiple"<?php echo $flag ?>>
<?php

if ($files != null) {
  foreach ($files as $filename) {
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
            
            <div id="actions" class="imageselection"  onmouseover="showInstructions()">
                <input name="update" type="submit" value="" class="icon update"
                    onmouseover="TagToTip('ttSpanRefresh' )"
                    onmouseout="UnTip()" />
                <input name="OK" type="hidden" />
            </div>
            
            <div id="controls"  onmouseover="showInstructions()">      
              <input type="button" value="" class="icon previous"
                onclick="document.location.href='select_task_settings.php'"
                onmouseover="TagToTip('ttSpanBack' )"
                onmouseout="UnTip()" />
              <input type="submit" value="" class="icon next"
                onclick="process()"
                onmouseover="TagToTip('ttSpanForward' )"
                onmouseout="UnTip()" />
            </div>

        </form>

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

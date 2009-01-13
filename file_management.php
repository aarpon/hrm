<?php

// php page: file_management.php

// This file is part of huygens remote manager.

// Copyright: Montpellier RIO Imaging (CNRS)

// contributors :
// 	     Pierre Travo	(concept)
// 	     Volker Baecker	(concept, implementation)

// email:
// 	pierre.travo@crbm.cnrs.fr
// 	volker.baecker@crbm.cnrs.fr

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

require_once("./inc/User.inc");
require_once("./inc/Fileserver.inc");

session_start();

if (isset($_GET['exited'])) {
  $_SESSION['user']->logout();
  session_unset();
  session_destroy();
  header("Location: " . "login.php"); exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  $req = $_SERVER['REQUEST_URI'];
  $_SESSION['request'] = $req;
  header("Location: " . "login.php"); exit();
}

if (isset($_SERVER['HTTP_REFERER']) && !strstr($_SERVER['HTTP_REFERER'], 'job_queue')) {
  $_SESSION['referer'] = $_SERVER['HTTP_REFERER'];
}

if (isset($_GET['ref'])) {
    $_SESSION['referer'] = $_GET['ref'];
} else if (isset($_POST['ref'])) {
    $_SESSION['referer'] = $_POST['ref']; 
} else {
    $_SESSION['referer'] = $_SERVER['HTTP_REFERER'];
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



    $_SESSION['fileserver']->compareResult(rawurldecode($_GET['compareResult']),
                             $size, $op, $mode);
    exit;
}


if (isset($_POST['update'])) {
    $_SESSION['fileserver']->updateAvailableDestFiles();
}

if ($allowHttpTransfer) {
    $message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";
    if (isset($_POST['download'])) {
        if (isset($_POST['userfiles']) && is_array($_POST['userfiles'])) {
           $message = $_SESSION['fileserver']->downloadResults($_POST['userfiles']);
        }
    } else if (isset($_GET['download']) ) {
        $downloadArr = array ( $_GET['download'] );
           $message = $_SESSION['fileserver']->downloadResults($downloadArr);
           exit;
    }
}

if (isset($_POST['delete'])) {
    if (isset($_POST['userfiles']) && is_array($_POST['userfiles'])) {
        $message = $_SESSION['fileserver']->deleteFiles($_POST['userfiles']);
        $_SESSION['fileserver']->updateAvailableDestFiles();
    }
} else if (isset($_GET['delete']) ) {
    $deleteArr = array ( $_GET['delete'] );
    $message = $_SESSION['fileserver']->deleteFiles($deleteArr);
    exit;
}

$files = $_SESSION['fileserver']->destFiles();

if ($files != null) {

    $generatedScript = "
function imageAction (list) {
    var n = list.selectedIndex;     // Which menu item is selected
    var val = list[n].value;

    switch ( val )
    {
";

    foreach ($files as $key => $file) {
        $generatedScript .= "
        case \"$file\" :
            ". $_SESSION['fileserver']->getImageAction($file,
                $key, "dest", "preview", 1, 0). "
            break;
            ";
    }


    $generatedScript .= "
    }
}
";
}

include("header.inc.php");

?>

    <div id="nav">
        <ul>
            <li><a href="file_management.php?exited=exited">exit</a></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpFileManagement')">help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
        <h3>File Management</h3>
        
        <form method="post" action="" id="select">
        
            <fieldset>
                <legend>available restored images on server</legend>
                <div id="userfiles">
<?php

$instructions =
            "<p>
            This is a list of the images in your destination directory.
            You can use SHIFT- and CTRL-click to select multiple files. 
            </p><p>
            Use the disk icon to start the downloading.
            That will
            create first a compressed archive including the selected files.
            </p><p>
            Use the trash icon to delete the selected files.
            </p><p>
            Please mind that large files may take a <b>long
            time </b>to be packaged before downloading.
            </p>";


// display only relevant files
$flag = "";
if ($files == null) $flag = " disabled=\"disabled\"";


?>
                    <select onclick="javascript:imageAction(this)" name="userfiles[]" size="10" multiple="multiple"<?php echo $flag ?>>
<?php

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
            
    <div id="selection">
    <?php if ($allowHttpTransfer) { ?>
                <img src="images/download.png" onClick="downloadImages()" />
                <?php } ?>
            <img src="images/delete.png" onClick="deleteImages()" />
            </div>
           
            <div id="actions" class="imageselection">
                <input name="update" type="submit" value="" class="icon update" />
                <input name="ref" type="hidden" value="<?php echo $_SESSION['referer']; ?>" />
                <input name="OK" type="hidden" />
            </div>
            
        </form>
        
    </div> <!-- content -->
    
    <div id="controls">
      <input type="button" name="back" value="" class="icon back" onclick="document.location.href='<?php echo $_SESSION['referer']; ?>'" />
    </div>
    
    <div id="stuff">
        <div id="info">
        
            
<?php if ($allowHttpTransfer) { 
    echo $instructions;
            } ?>
            
        </div>
        
        <div id="message">
<?php

echo $message;

?>
        </div>
        
    </div> <!-- stuff -->
    
<?php

include("footer.inc.php");

?>

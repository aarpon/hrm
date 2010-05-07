<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

include ("hrm_config.inc");
require_once("Database.inc");
require_once("Util.inc");

//!---------------------------------------------------------
// @class    FileSelection
// @desc     Allows the user to select image files and stores
//           the selection.
//!---------------------------------------------------------
Class Fileserver {
  
  public $username;              // @public username         String     The name of the user is his home directory in the same time
  public $files;                 // @public files            Array      The image files under the users home directory    
  public $imageExtensions;       // @public imageExtensions  Array      File extensions indicating an image file  
  public $selectedFiles;         // @public selectedFiles    Array      Files actually selected to be processed

  public $validImageExtensions =  array("tif", "tiff", "stk", "lsm", "ims", "pic", "ome", "lif", "ics", "dv", "h5", "zvi" );

  public $validImageExtensionsExtras =  array("ids", "ids.gz");

  public $multiImageExtensions =  array("lif");
  public $validArchiveExtensions =  array();
  public $expandSubImages = true;


  
  //!---------------------------------------------------------
  // @function    Fileserver::Fileserver
  // @desc        Konstruktor. Creates a new Fileserver.
  // @return      void
  //!---------------------------------------------------------
  function Fileserver($name) { 
    global $image_folder;
    global $image_source;
    global $decompressBin;
    $this->username = $name;
    $this->files = NULL;
    $this->selectedFiles = NULL;
    $this->destFiles = NULL;
    $this->imageExtensions = NULL;

    // Only valid archive types are those for which decompression commands are
    // specified.
    $this->validArchiveExtensions = array_keys($decompressBin);
  }
  
  function isReachable() {
    $result = file_exists($this->sourceFolder());
    $result = $result && file_exists($this->destinationFolder());
    return $result;
  } 

  //!---------------------------------------------------------
  // @function    Fileserver::username
  // @desc        Answer the name of the user 
  // @return      String
  //!---------------------------------------------------------
  function username() {
    return $this->username;
  } 
  //!---------------------------------------------------------
  // @function    Fileserver::sourceFolder
  // @desc        Answer the users home folder under which
  //              his image files are stored. This is the 
  //              absolute path to the folder. The folder may
  //              be on the local network.
  // @return      String
  //!---------------------------------------------------------
  function sourceFolder() { 
    global $image_folder;
    global $image_source;
    $folder = $image_folder . "/" . $this->username . "/" . $image_source;
    return $folder;
  }
  //!---------------------------------------------------------
  // @function    Fileserver::destinationFolder
  // @desc        Answer the users destination folder under which
  //              his image results are stored. This is the 
  //              absolute path to the folder. The folder may
  //              be on the local network.
  // @return      String
  //!---------------------------------------------------------
  function destinationFolder() { 
    global $image_folder;
    global $image_destination;
    $folder = $image_folder . "/" . $this->username . "/" . $image_destination;
    return $folder;
  }

  function destinationFolderFor($desc) {
    $folder = $this->destinationFolder() . "/" . $desc->relativeSourcePath();
    return $folder;
  }

  // Answer all files in the specified format under the users image
  // folder. Searches subdirectories recursively. 
  //!---------------------------------------------------------
  // @function    Fileserver::files
  // @desc        Answer all image files under the users image
  //              folder. Searches subdirectories recursively.  
  // @return      Array
  //!---------------------------------------------------------  
  function files() {
    switch(func_num_args()) {
      case 0:
        if ($this->files == NULL) $this->getFiles();
        return $this->files;
      case 1:
        if (!file_exists($this->sourceFolder())) return False;
        list($extension) = func_get_args();
        $files = $this->listFilesFrom($this->sourceFolder(), "", $extension);
        sort($files);
        return $files;
    }
  }

  function destFiles() {
    switch(func_num_args()) {
      case 0:
        if ($this->destFiles == NULL) $this->getDestFiles();
        return $this->destFiles;
      case 1:
        if (!file_exists($this->destinationFolder())) return False;
        list($extension) = func_get_args();
        $files = $this->listFilesFrom($this->destinationFolder(), "", $extension);
        sort($files);
        return $files;
    }
  }

  // A wrapper around the different functions to list files or certain type.
  function filesOfType( $format ) {

        if ($format == "ics") {
            $files = $_SESSION['fileserver']->files("ics");
        }
        else if ($format == "tiff" || $format == "tiff-single") {
            $files = $_SESSION['fileserver']->tiffFiles();
        }
        else if ($format == "tiff-series") {
            $files = $_SESSION['fileserver']->tiffSeriesFiles();
        }
        else if ($format == "tiff-leica") {
            $files = $_SESSION['fileserver']->tiffLeicaFiles();
        }
        else if ($format == "stk") {
            //if ($geometry->value() == "XY - time" || $geometry->value() ==
            //"XYZ - time") {
            if ($_SESSION['setting']->isTimeSeries()) {
                $files = $_SESSION['fileserver']->stkSeriesFiles();
            }
            else {
                $files = $_SESSION['fileserver']->stkFiles();
            }
            //}
            //else {
            //  $files = $_SESSION['fileserver']->files("stk");
            //}
        }
        else {
            $files = $_SESSION['fileserver']->files();
        }

        return $files;

  }
  
  // Answer all files under the users image folder. Searches
  // subdirectories recursively. 
  //!---------------------------------------------------------
  // @function    Fileserver::allFiles
  // @desc        Answer all image files under the users image
  //              folder. Searches subdirectories recursively.  
  // @return      Array
  //!---------------------------------------------------------  
  function allFiles() {
    if (!file_exists($this->sourceFolder())) return False;
    $files = $this->listFilesFrom($this->sourceFolder(), "", "");
    sort($files);
    return $files;
  }

  // convenient method to get TIFF files
  function tiffFiles() {
    $this->getFiles();
    $this->trimTiff();
    return $this->files;
  }
  
  // convenient method to get numbered TIFF series
  function tiffSeriesFiles() {
    $this->getFiles();
    // TODO refactor
    $this->trimTiffSeries();
    $this->condenseTimeSeries();
    return $this->files;
  }
  
  // convenient method to get TIFF series with Leica style numbering
  function tiffLeicaFiles() {
    $this->getFiles();
    // TODO refactor
    $this->trimTiffLeica();
    $this->condenseTiffLeica();
    return $this->files;
  }
  
  // convenient method to get STK files without series
  function stkFiles() {
    $this->getFiles();
    // TODO refactor
    $this->trimStkSeries();
    return $this->files;
  }
  
  // convenient method to get STK series
  function stkSeriesFiles() {
    $this->getFiles();
    // TODO refactor
    $this->trimStk();
    $this->condenseStkSeries();
    return $this->files;
  }

  //!---------------------------------------------------------
  // @function    Fileserver::updateAvailableFiles
  // @desc        Reset the list of available files. When the
  //              list is accessed the next time it is newley
  //              computed.
  // @return      void
  //!---------------------------------------------------------  
  function updateAvailableFiles() {
    $this->files = NULL;
  } 
  //!---------------------------------------------------------
  // @function    Fileserver::updateAvailableDestFiles
  // @desc        Reset the list of available result files. When the
  //              list is accessed the next time it is newley
  //              computed.
  // @return      void
  //!---------------------------------------------------------  
  function updateAvailableDestFiles() {
    $this->destFiles = NULL;
  } 

  function expandSubImages($value) {
      $this->expandSubImages = $value;
  }

  //!---------------------------------------------------------
  // @function    Fileserver::selectedFiles
  // @desc        Answer the actual selection of files for
  //              which the user is going to create a job.
  // @return      Array
  //!---------------------------------------------------------  
  function selectedFiles() { 
    if ($this->selectedFiles == NULL) $this->selectedFiles = array();
    return $this->selectedFiles;
  }

  //!---------------------------------------------------------
  // @function    Fileserver::addFilesToSelection
  // @desc        Add files to the actual selection if they 
  //              are not already contained. 
  // @param       files  Array  List of files to be added.
  // @return      Void
  //!---------------------------------------------------------  
  function addFilesToSelection($files) { 
      foreach ($files as $key => $file) {
          $files[$key] = stripslashes($file);
      }
    $selected = $this->selectedFiles();
    $new = array_diff($files, $selected);
    $this->selectedFiles = array_merge($new, $this->selectedFiles);
    sort($this->selectedFiles);
  }

  //!---------------------------------------------------------
  // @function    Fileserver::removeFilesFromSelection
  // @desc        Remove files from the selection if they are
  //              contained.
  // @param       files  Array  List of files to be removed
  //              from the selection
  // @return      Void
  //!---------------------------------------------------------  
  function removeFilesFromSelection($files) { 
      foreach ($files as $key => $file) {
          $files[$key] = stripslashes($file);
      }
    $this->selectedFiles = array_diff($this->selectedFiles, $files);
  }  

  //!---------------------------------------------------------
  // @function    Fileserver::downloadResults
  // @desc        Packs a series of files to download.
  // @param       files  Array  List of files to be added.
  // @return      Void
  //!---------------------------------------------------------  
  function downloadResults($files) { 
      global $compressBin, $compressExt, $dlMimeType, $packExcludePath;

      // Make sure that the script doesn't timeout before zipping and
      // reading the file to serve is completed.
      set_time_limit(0);
      
      $date = date("Y-m-d_His");
      $zipfile = "/tmp/download_".session_id().$date.$compressExt;
      $command = str_replace("%DEST%",
              $this->destinationFolder(), $compressBin);
      $command .= " ".$zipfile;

      foreach ($files as $file) {
          $path = preg_replace("/(.*)\.(.{3,4})/","\"\\1\".*",$file);
          $preview_path = dirname($file)."/hrm_previews/".
                  preg_replace("/(.*)\.(.{3,4})/","\"\\1\".*",$file);
          if (! $packExcludePath ) {
              $path = $this->destinationFolder()."/".$path;
              $preview_path = $this->destinationFolder()."/".$preview_path;
          }
          $command .= " ".$path." ".$preview_path;
      }
      
      $answer = exec($command , $output, $result);

      $size = filesize($zipfile);
      $type = $dlMimeType;
      $dlname = "hrm_results_$date$compressExt";

      if ($size) {
          header ("Accept-Ranges: bytes");
          header ("Connection: close");
          header ("Content-Disposition-type: attachment");
          header ("Content-Disposition: attachment; filename=\"$dlname\"");
          header ("Content-Length: $size"); 
          header ("Content-Type: $type; name=\"$dlname\"");
          ob_clean();
          flush();
          readfile_chunked($zipfile);
          unlink($zipfile);
          return "<p>OK</p>"; 
      } else {
          $error_msg = "No output from command $command.";
      }
      return "<p class=\"warning\">Problems with the packaging of the files:".
          " $error_msg</p>";
  }

  //!---------------------------------------------------------
  // @function    Fileserver::deleteFiles
  // @desc        Deletes a list of files and all dependent sub-files (like
  //              thumbnails and so) from a user directory.
  // @param       files  Array  List of files to be added.
  // @param       dir  'src' or 'dest'
  // @return      Void
  //!---------------------------------------------------------  
  function deleteFiles($files, $dir = "dest" ) { 

      if ( $dir == "src" ) {
          $pdir =  $this->sourceFolder();
      } else {
          $pdir =  $this->destinationFolder();
      }

      foreach ($files as $file) {
          // Delete all files name like this one, with all different extensions.
          $dirname = dirname($pdir."/".$file);
          $basename = basename($pdir."/".$file);
          $path = preg_replace("/(.*)\.(.{3,4})/","\\1.*",
                  $dirname."/".$basename);
          $allFiles = glob($path);
          foreach ($allFiles as $f) {
              unlink($f);
          }
          // Clean also the subdirectory hrm_previews
          $path = preg_replace("/(.*)\.(.{3,4})/","\\1.*",
                  $dirname."/hrm_previews/".$basename);
          $allFiles = glob($path);
          foreach ($allFiles as $f) {
              unlink($f);
          }
      }

      if ( $dir == "src" ) {
          $this->updateAvailableFiles();
      } else {
          $this->updateAvailableDestFiles();
      }


  }

  //!---------------------------------------------------------
  // @function    Fileserver::decompressArchive
  // @desc        Extracts files in compressed archives.
  // @param       file  String  archive name
  // @param       type  String  archive type (zip, tar, tgz...)
  // @param       dest  String  destination path
  // @param       okMsg String pointer A string to accumulate OK messages
  // @param       errMsg String pointer A string to accumulate error messages
  // @param       subdir String pointer An optional subdirectory under $dest to
  //              expand the files to.
  // @param       imagesOnly Boolean Apply a filter to delete non-image files
  //              after extraction.
  // @return      Void
  //!---------------------------------------------------------  
 
  function decompressArchive( $file, $type, $dest, &$okMsg, &$errMsg, 
          $subdir = "", $imagesOnly = true ) {

      global $decompressBin;

      if ( $imagesOnly && $subdir == "" ) {
          $errMsg .= "Can't decompress: filtering valid images requires ".
              "expanding the archive to a subdirectory.";
          return;
      }

      if ($subdir != "" ) {
          $dest = $dest."/".$subdir;
          @mkdir($dest, 0777);
      }

      $command = str_replace("%DEST%", $dest, $decompressBin[$type]).
          " \"$file\"";

      $answer = exec($command , $output, $result);

      # $okMsg .= "$type $command: $result";
      # foreach ($output as $line) {
          # $okMsg .= "\n<br>$line";
      # }

      if ($imagesOnly) {
          $deleted = "";
          $valid = "";
          $this->cleanNonImages($dest, "", $valid, $deleted);
          if ($deleted != "") {
              $errMsg .= "<br>\nThe following files, not being valid images,".
                  " were discarded: <kbd>$deleted</kbd>";
          }
          if ($valid != "") {
              $okMsg .= "<br>\nThe following images were extracted: ".
                  "<kbd>$valid</kbd><br>\n";
          }
      }

      return;


  }

  //!---------------------------------------------------------
  // @function    Fileserver::uploadFiles
  // @desc        Processes the $_FILES array posted when uploading files, 
  //              moving valid one to the specified directory. Compressed files
  //              are decompressed.  
  // @param       files  Array  List of files to be added. See PHP
  //              documentation: POST method uploads
  // @param       dir  'src' or 'dest'
  // @return      Message with details.
  //!---------------------------------------------------------  
 
  function uploadFiles($files, $dir) {

      if ( $dir == "src" ) {
          $uploaddir =  $this->sourceFolder();
      } else {
          $uploaddir =  $this->destinationFolder();
      }

      $max = getMaxFileSize() / 1024 / 1024;
      $maxFile = "$max MB";

      $ok = "";
      $err = "";
      $okCnt = 0;

      # print_r($files); exit;

      // This needs some file type validation: only images should be allowed.

      // decompression still pending.

      try {

      foreach ($files['name'] as $i => $name) {

          if ( $name == "" ) {
              // This is also error UPLOAD_ERR_NO_FILE;
              continue;
          }
          $basename = basename($name);
          $uploadfile = $uploaddir . "/" . $basename;
          $info = pathinfo($uploadfile);
          $file_name =  basename($uploadfile,'.'.$info['extension']);

          if ($files['error'][$i]) {
              $err .= "Invalid file <kbd>".$basename."</kbd>: <b>";
              switch ($files['error'][$i]) {
                  case UPLOAD_ERR_INI_SIZE:
                     $err .= "larger than $maxFile.";
                     break;
                  case UPLOAD_ERR_PARTIAL:
                     $err .= "file loaded only partially.";
                     break;
                  case UPLOAD_ERR_NO_TMP_DIR:
                     $err .= "missing a temporary folder.";
                     break;
                  case UPLOAD_ERR_CANT_WRITE:
                     $err .= "can't write to disk.";
                     break;
                  case UPLOAD_ERR_EXTENSION:
                     $err .= "upload stopped by extension.";
                     break;

              }
              $err .="</b><br>\n";
              continue;
          }

          $type = $this->getCompressedArchiveType($name);

          if ( $type != "" ) {
              # If this is a compressed archive, extract its files.
              $subdir = $file_name;
              $zsuffix = 0;
              $zmaxSuffix = 100;

              $testExpand = $uploaddir . "/" . $subdir;

              while (file_exists($testExpand)) {
                  $zsuffix ++;
                  $testExpand = $uploaddir . "/" . $file_name. "_$zsuffix" ;
                  if ($zsuffix > $zmaxSuffix) {
                      $err .= "Directory <kbd>".$filename.
                          "</kbd> exists, <b>can't store more ".
                          " than $zmaxSuffix versions.</b><br>\n";
                      break;
                  }
              }
              if ($zsuffix > $zmaxSuffix) {
                  continue;
              }

              $okCnt++;
              $ok .= "<br>Processed <kbd>".$basename."</kbd>.<br>\n";


              if ($zsuffix > 0) {
                  $subdir = $file_name."_".$zsuffix;
                  $ok .= "Extracting files to <kbd>$subdir</kbd>.<br>\n";
              }
              $this->decompressArchive($files['tmp_name'][$i], $type,
                      $uploaddir, $ok, $err, $subdir, true);
              continue;

          }

          if (!$this->isValidImage($name, true)) {
              $err .= "Skipped <kbd>".$basename."</kbd>: ";
              $err .= "<b>unknown image type</b><br>\n";
              continue;

          }

          $suffix = 0;
          $maxSuffix = 20;

          while (file_exists($uploadfile)) {
              $suffix ++;
              $uploadfile = $uploaddir . "/" . $file_name 
                  . "_$suffix." . $info['extension'];
              if ($suffix > $maxSuffix) {
                  $err .= "File <kbd>".$basename.
                      "</kbd> exists, <b>can't store more than $maxSuffix versions.</b>";
                  break;
              }
          }
          if ($suffix > $maxSuffix) {
              continue;
          }

          if (move_uploaded_file($files['tmp_name'][$i], $uploadfile)) {
              // echo "File is valid, and was successfully uploaded.\n";
              if ($suffix == 0) {
                  $ok .= "<kbd>".$basename."</kbd> uploaded <br>\n";
              } else {
                  $ok .= "<kbd>".$basename.
                      "</kbd> already exists, uploaded and <b>renamed</b> ".
                      "to <kbd>$file_name"
                      . "_$suffix." . $info['extension']. "</kbd><br>\n";
              }
              $okCnt++;
          } else {
              $err .= "Invalid file ".$basename."<br>\n";
          }
      } 
      } catch (Exception $e) {
          $err .= "Error uploading files: ".$e->getMessage();
      }

      $msg = "<h3>Upload report</h3>\n";

      if ($okCnt == 0) {
          $msg .= "<p>No files uploaded!<p>$err";
      } else {
          $plural = "";
          if ($okCnt > 1) {
              $plural = "s";
          }
          $msg .= "<p class=\"report\">$okCnt file$plural uploaded.</p><p class=\"report\">$ok</p><p class=\"report\">$err</p>";
      }

      if ( $dir == "src" ) {
          $this->updateAvailableFiles();
      } else {
          $this->updateAvailableDestFiles();
      }

      return $msg;

  }



  //!---------------------------------------------------------
  // @function    Fileserver::imageExtensions
  // @desc        Answer a list of file extensions of known
  //              images.
  // @return      Array
  //!---------------------------------------------------------  
  function imageExtensions() { 
    if ($this->imageExtensions == NULL) $this->setDefaultImageExtensions();
    return $this->imageExtensions;
  } 

  //!---------------------------------------------------------
  // @function    Fileserver::setImageExtensions
  // @desc        Set the list of image extensions. Files
  //              with these extensions under the user's source
  //              folder will be shown under available images.
  //              Whenever the image extensions are changed, the
  //              files and the selected files will be reset.
  //              Only exception is when the list of image extensions
  //              is replaced by itself. 
  // @param       extensions  Array  List of file extensions (strings),
  //              see setDefaultImageExtensions for an example.
  // @return      Void
  //!---------------------------------------------------------  
  function setImageExtensions($extensions) {
    if (implode('', $extensions) !=  implode('', $this->imageExtensions())) { 
      $this->selectedFiles = NULL;
      $this->files = NULL;
    } 
    $this->imageExtensions = $extensions;
  } 
  //!---------------------------------------------------------
  // @function    Fileserver::setDefaultImageExtensions
  // @desc        Creates the list of file extensions of known
  //              image formats.
  // @return      Void
  //!---------------------------------------------------------  
  function setDefaultImageExtensions() {
    // new file formats support
    $this->imageExtensions = $this->validImageExtensions;
  } 
  //!---------------------------------------------------------
  // @function    Fileserver::isImage
  // @desc        Answers True if filename has an extension
  //              which is among the extensions of the currently 
  //              relected file format, or any if not specified.
  // @param       filename  String  The filename to be checked
  // @return      Boolean
  //!---------------------------------------------------------  
  function isImage($filename) {
    $ext = substr(strrchr($filename, "."),1);
    $ext = strtolower($ext);
    $result = False;
    if (in_array($ext, $this->imageExtensions())) {
      $result = True;
    }
    return $result;
  }
  //!---------------------------------------------------------
  // @function    Fileserver::isValidImage
  // @desc        Answers True if filename has an extension
  //              which is among the extensions of known image
  //              formats. 
  // @param       filename  String  The filename to be checked
  //              boolean   alsoExtras: consider also extensions as ids
  // @return      Boolean
  //!---------------------------------------------------------  
  function isValidImage($filename, $alsoExtras = false) {
      $filename = strtolower($filename);
      $ext = substr(strrchr($filename, "."),1);
      if ( $ext == "gz" ) {
          // Use two suffixes as extension
          $filename  = basename($filename, ".gz");
          $ext = substr(strrchr($filename, "."),1) . ".gz";
      }
      $result = False;
      if (in_array($ext, $this->validImageExtensions)) {
          $result = True;
      }
      if ($alsoExtras && (in_array($ext, $this->validImageExtensionsExtras)) ) {
          $result = True;
      }
      return $result;
  }

  //!---------------------------------------------------------
  // @function    Fileserver::getCompressedArchiveType
  // @desc        Returns the archive type of a filename, if it is a valid
  //              known compressed archive.
  // @param       filename  String  The filename to be checked
  // @return      Type of archive, or "" if it is not an archive.
  //!---------------------------------------------------------  
  function getCompressedArchiveType($filename ) {

      if (stristr($filename, ".tar.gz")) {
          // This double extension is a special case.
          return "tar.gz";
      }
      $ext = substr(strrchr($filename, "."),1);
      $ext = strtolower($ext);
      $result = "";
      if (in_array($ext, $this->validArchiveExtensions)) {
          $result = $ext;
      }
      return $result;
  }

  function getValidArchiveTypesAsString() {
      $ret = ""; $sep = "";
      foreach ($this->validArchiveExtensions as $ext) {
          $ret .= $sep.".$ext";
          $sep = " ";
      }
      return $ret;
  }


  //!---------------------------------------------------------
  // @function    Fileserver::getSubImages
  // @desc        When the selected file type is one that can contain subimages
  //              (like LIF), the already built list of $this->files is 
  //              extended to show all the available subimages. 
  //              This is done by querying Huygens.
  // @return      Void
  //!---------------------------------------------------------    
  function getSubImages($files) {

      $i = 0;
      $imgList = "";
      foreach ($files as $path) {
          $imgList .= " -img_$i \"$path\"";
          $i ++;
      }

      $opt = "-count $i $imgList -dir \"". $this->sourceFolder() ."\"";

      $answer = huCoreTools( "reportSubImages", $opt);

      if (! $answer ) return;
      # printDebug ($answer);

      $lines = count($answer);

      $tree = array();
      $new_files = array();
      $cur = NULL;

      for ($i = 0; $i < $lines; $i++ ) {
          $key = $answer[$i];

          switch ($key) {
              case "BEGIN IMG":
                  $i ++;
                  $cur = $answer[$i];
                  break;
              case "ERROR":
                  $i ++;
                  echo($answer[$i]);
              case "END IMG":
                  $cur = NULL;
                  break;
              case "PATH":
                  if ($cur) {
                      $i ++;
                      $tree[$cur]['path'] = $answer[$i];
                  }
                  break;
              case "COUNT":
                  if ($cur) {
                      $i ++;
                      $tree[$cur]['count'] = $answer[$i];
                  }
                  break;
              case "TYPE":
                  if ($cur) {
                      $i ++;
                      $tree[$cur]['type'] = $answer[$i];
                  }
                  break;
              case "SUBIMG":
                  if ($cur) {
                      $i ++;
                      $tree[$cur]['subimg'][] = $answer[$i];
                      $new_files[] = $cur ." (". $answer[$i] .")";
                  }
                  break;

          }
      }

      return $new_files;

      # printDebug ($tree);

  }

  //!---------------------------------------------------------
  // @function    Fileserver::getMetaData
  // @desc        some files like ICS can report their metadata without having
  //              to open the whole image, which is good e.g. to see the
  //              compatibility of the selected PSF with the current Parameter
  //              Setting.  This is done by querying Huygens.
  // @return      Void
  //!---------------------------------------------------------    
  function getMetaData( $type = "ics", $file = "all" ) {

      $i = 0;
      $imgList = "";

      if ( $file == "all" ) {
          $files = $this->files($type);
      } else {
          $files[] = $file;
      }

      foreach ($files as $path) {
          $imgList .= " -img_$i \"$path\"";
          $i ++;
      }

      $opt = "-count $i $imgList -dir \"". $this->sourceFolder() ."\"";


      $answer = huCoreTools( "getMetaData", $opt);

      if (! $answer ) return;
      # printDebug ($answer);

      $lines = count($answer);

      $tree = array();
      $new_files = array();
      $cur = NULL;
      $param = NULL;

      for ($i = 0; $i < $lines; $i++ ) {
          $key = $answer[$i];

          switch ($key) {
              case "BEGIN IMG":
                  $i ++;
                  $cur = $answer[$i];
                  break;
              case "ERROR":
                  $i ++;
                  echo($answer[$i]);
              case "END IMG":
                  $cur = NULL;
                  $param = NULL;
                  $len = 1;
                  break;
              case "PATH":
                  if ($cur) {
                      $i ++;
                      $tree[$cur]['path'] = $answer[$i];
                  }
                  break;
              case "LENGTH":
                  if ($cur) {
                      $i ++;
                      $len = $answer[$i];
                  }
                  break;
              case "DATA":
                  if ($cur) {
                      $i ++;
                      $param= $answer[$i];
                      $tree[$cur]['parameters'][] = $param;
                  }
                  break;
              case "VALUE":
                  if ($cur && $param) {
                      $i ++;
                      // This is always an array even if $len == 1, because in
                      // other images this could be a multichannel parameter.
                      $tree[$cur][$param][] = $answer[$i];
                  }
                  break;

          }
      }


      # printDebug ($tree);
      return $tree;

  }



  //!---------------------------------------------------------
  // @function    Fileserver::getImageOptionLine
  // @desc        Generates a html line for a form listing images in the
  //              server.
  // @return      The html code for the form.
  //!---------------------------------------------------------    
 
  function getImageOptionLine ($file, $index, $dir, $type, $ref = 0, $data = 1) {
      $path = explode("/", $file);
      if (count($path) > 2)
          $filename = $path[0] . "/.../" . $path[count($path) - 1];
      else
          $filename = $file;

      return 
      "                        <option value=\"$file\">$filename</option>\n";
  }

  //!---------------------------------------------------------
  // @function    Fileserver::getImageAction
  // @desc        Generates a javascript command to show an image preview.
  // @return      The javascript code.
  //!---------------------------------------------------------    
 
  function getImageAction ($file, $index, $dir, $type, $ref = 0, $data = 1) {
      global $useThumbnails;
      global $genThumbnails;

      $path = explode("/", $file);
      if (count($path) > 2)
          $filename = $path[0] . "/.../" . $path[count($path) - 1];
      else
          $filename = $file;


      $mode = $this->imgPreviewMode($file, $dir, $type) ;
      if ( $ref ) {
          $referer = "?ref=". $_SESSION['referer'];
      } else {
          $referer = "";
      }

      // The first comparison mode is the 400x400 pixels preview.
      $compare = $this->imgCompareMode($file, $dir, "400") ;
      if ($compare > 0) { $compare = 400; }
      return 
          "imgPrev('".rawurlencode($file)."', $mode, ".
          "$genThumbnails, $compare, $index, '$dir', ".
          "'$referer', $data)";
  }



 
  //!---------------------------------------------------------
  // @function    Fileserver::getFiles
  // @desc        Create the list of image files of the user.
  //              Time series are represented by their first
  //              image file.
  // @return      Void
  //!---------------------------------------------------------    
  function getFiles() {
    $this->files = array();
    if (!file_exists($this->sourceFolder())) return False;
    $this->getFilesFrom($this->sourceFolder(), "");
    if (count($this->files) == 0) return False;
    $extArr = $this->imageExtensions();

    // When only one file type is listed, expand subimages if they exists.

    if ( count ( $extArr ) == 1 ) {
        $ext = $extArr[0];
        if ( in_array( $ext, $this->multiImageExtensions)) {
            $this->files = $this->getSubImages($this->files);
        }
    }

    // Later adition: if multiple explicitly given types are listed, expand
    // subimages. Therefore, subimages are only NOT listed when no explicit
    // extension is given (useful to handle FILES, not IMAGES, like in the file
    // manager).
    if ( $this->expandSubImages && count ( $extArr ) > 1 ) {
        $expandfiles = array();
        foreach ($extArr as $mfext) {
            if ( !in_array( $mfext, $this->multiImageExtensions)) { continue; }
            foreach ($this->files as $key => $file) {
                $ext = substr(strrchr($file, "."),1);
                $ext = strtolower($ext);
                if ($ext != $mfext) continue;
                $expandfiles[] = $file;
                unset ( $this->files[$key] );
            }
        }
        if ( count($expandfiles) > 0 ) {
          $this->files = array_merge($this->files,
                  $this->getSubImages($expandfiles));
        }
    }
    sort($this->files);
    // TODO refactor
    //$this->condenseTimeSeries();
    // trim TIFF series to the first file in the sequence
    //$this->condenseTiffSeries();
    return True;
  } 


  //!---------------------------------------------------------
  // @function    Fileserver::imgPreview
  // @desc        Generates a link to retreive an image thumbnail,
  //              which is a jpg file saved near the file itself.
  // @return      A <img src> link to securely get the thumbnail.
  //!---------------------------------------------------------    
  function imgPreview ($image, $dir, $type = "preview_xy", $escape = true ) {
      global $genThumbnails;

      if ( $dir == "src" ) {
          $pdir =  $this->sourceFolder();
      } else {
          $pdir =  $this->destinationFolder();
      }

      $dirname = dirname($pdir."/".$image);
      $base = basename($pdir."/".$image);

      // The thumbnail is saved in a subdirectory along with the image, and it
      // has a suffix indicating the thumbnail type plus the jpg extension.
      $path = $dirname."/hrm_previews/".$base.".".$type.".jpg";
      $thumb = rawurlencode(stripslashes($image).".".$type.".jpg");
      if (file_exists(stripslashes($path))) {
          $ret =  "<img src=\"file_management.php?getThumbnail=$thumb&amp;".
              "dir=$dir\" alt=\"preview\" />";
      } else {
           $imgsrc = "<img src=\"images/no_preview.jpg\" alt=\"No preview\" />";
           // $ret = "<p><center>No preview available.</center></p>";
           $ret .= "$imgsrc<br />No preview available";
      }

      if ($escape) {
          return escapeJavaScript($ret);
      } else {
          return $ret;
      }
  }

  //!---------------------------------------------------------
  // @function    Fileserver::imgPreviewMode
  // @desc        Checks whether an image preview is available
  // @return      Numeric code: 0 not available, 2 2D, 3 3D
  //!---------------------------------------------------------
  function imgPreviewMode ($image, $dir, $type) {
      global $genThumbnails;

      if ( $dir == "src" ) {
          $pdir =  $this->sourceFolder();
      } else {
          $pdir =  $this->destinationFolder();
      }

      $dir = dirname($pdir."/".$image);
      $base = basename($pdir."/".$image);
      // The thumbnail is saved in a subdirectory along with the image, and it
      // has a suffix indicating the thumbnail type plus the jpg extension.
      $path = stripslashes($dir."/hrm_previews/".$base.".".$type."_xy.jpg");

      # $path2 = $dir."/hrm_previews/".$base.".".$type."_xz.jpg";
      # unlink($path);
      # unlink($path2);
      # echo "Deleting $path2";
      # rmdir($dir."/hrm_previews/");
      # echo "Deleting dir";


      $ret = 0;
      if (file_exists($path)) {
          // 2D preview
          $ret = 2;
          $path2 = stripslashes($dir."/hrm_previews/".$base.".".$type."_xz.jpg");
          if (file_exists($path2)) {
          // 3D preview
              $ret = 3;
          }
      } else {
          // No preview available
          $ret = 0;
      }

      return $ret;

  }

  function findStrip ( $file, $type, $dir ) {
      if ( $dir == "src" ) {
          $pdir =  $this->sourceFolder();
      } else {
          $pdir =  $this->destinationFolder();
      }
      $dir = dirname($pdir."/".$file);
      $base = basename($pdir."/".$file.".".$type);
      $path = $dir."/hrm_previews/".$base;
      $path = stripslashes($path);

      $files = glob($path.".strip_*");
      return $files;

  }

  //!---------------------------------------------------------
  // @function    Fileserver::viewStrip
  // @desc        Shows stacks and time series saved as jpeg strips in a css
  //              container. Inspired by the paperbird code by Román Cortés:
  //              http://www.romancortes.com/blog/css-paper-bird/
  // @return      
  //!---------------------------------------------------------

  function viewStrip( $file, $type = "stack.comparison", $dir = "dest", $frame = false, $margin = 25 ) {
      global $allowHttpTransfer;

      $files = $this->findStrip($file, $type, $dir);
      if (count($files) != 1 ) {
          echo "<img src=\"images/no_preview.jpg\">";
          return;
      }
      preg_match ("/strip_(.+)x(.+)x(.+)_fr/", $files[0], $match);

      $thumb = strstr($files[0],$file);

      $sx = $match[1];
      $sy = $match[2];
      $width = $sx + $margin * 2;
      $height = $sy + $margin * 2;
      $jump = $height * 400;
      $fCnt = $match[3];

      if ( $frame ) {
          // Use this function in two steps: first to create the iframe with
          // the correct width and height, using a link that calls this
          // function again to generate the embedded slicer page.
          echo ' <iframe src="?viewStrip='.$file.'&amp;type='.$type.
          '&amp;dir='.$dir.'" width="'. ($width + 25). 
          '" height="'. ($height).'"> ';
          echo '</iframe>';
          return;
      }

      $borderColor = "#666";
      $textColor = "#bbb";

      $img = "file_management.php?getThumbnail=$thumb&amp;dir=$dir";

      $file = stripslashes($file);

      # $legend = $type. " ". $file;
      $legend = $type;
      if (strlen($legend) > 75) {
              $legend = substr($legend, 0, 70)."...";
      }

      echo '

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en"> 
<head> 
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /> 
        <title>' . $file . " ". $type. '</title> 
        <style type="text/css"> 
                body
                {
                       font-family: "verdana", "bitsream vera sans", sans-serif;
                       margin: 0;
                       padding: 0;
                       overflow: hidden;
                }

                #viewer
                {
                        width: '. ($width + $margin) .'px;
                        height: '. $height .'px;
                        background: '.$borderColor.';
                        overflow: auto;
                }

                #viewer div
                {
                        float: left;
                        width: '. $width .'px;
                        height: '.$jump.'px;
                        background-image: url('.$img.');
                        background-attachment: fixed;
                }

                .left
                {
                        position:absolute;
                        left: 0px;
                        top: 0px;
                        width: '.$margin.'px;
                        height: '.$height.'px;
                        background: '.$borderColor.';
                        z-index: 9998;
                }
                .right
                {
                        position:absolute;
                        left: '.( $sx + $margin).'px;
                        top: 0px;
                        width: '.$margin.'px;
                        height: '.$height.'px;
                        background: '.$borderColor.';
                        z-index: 9998;
                }
 
                .top
                {
                        position:absolute;
                        top: 0px;
                        left: 0px;
                        width: '.$width.'px;
                        height: '.$margin.'px;
                        background: '.$borderColor.';
                        z-index: 9999;
                }

                .bottom
                {
                        position:absolute;
                        left: 0px;
                        top: '.($height - $margin).'px;
                        color: '.$textColor.';
                        overflow: hidden;
                        font-size: 11px;
                        padding-left: '.$margin.'px;
                        width: '.($width - $margin).'px;
                        height: '.($margin ).'px;
                        background: '.$borderColor.';
                        z-index: 9999;
                }


';

      for ($n = 0; $n < $fCnt; $n++) {
          $pos = $sy * ($fCnt - $n  ) + $margin;
          echo "#f$n {background-position: ".$margin."px ".$pos."px;}";
      }


echo '  </style> 
    </head> 
    <body> 
    <div id="viewer"> ';
      for ($n = 0; $n < $fCnt; $n++) {
          echo "<div id=\"f$n\">";
          echo "</div>";
          }

      echo "</div>";
      echo "<div class=\"top\">&nbsp;</div><div
          class=\"bottom\">$legend</div><div
          class=\"left\">&nbsp;</div><div class=\"right\">&nbsp;</div>";

echo '</body></html>';


  }



  //!---------------------------------------------------------
  // @function    Fileserver::imgCompareMode
  // @desc        Checks whether a restored image preview is available for
  //              comparison with the original one.
  // @return      Numeric code: 0 not available, 2 2D, 3 3D
  //!---------------------------------------------------------
  function imgCompareMode ($image, $dir, $type) {
      global $genThumbnails;


      if ( $dir == "src" ) {
          // Only images in the destination directory, after deconvolution, can
          // be compared with the originals.
          return 0;

      }

      $pdest =  $this->destinationFolder();

      $pdir = dirname($pdest."/".$image);
      $basename = basename($pdest."/".$image);

      // The thumbnail is saved along with the image, and it has a suffix
      // indicating the thumbnail type plus the jpg extension.
      $path = $pdir."/hrm_previews/".$basename.".".$type."_xy.jpg";
      $path = stripslashes($path);
      $opath = $pdir."/hrm_previews/".$basename.".original.".$type."_xy.jpg";
      $opath = stripslashes($opath);
      $ret = 0;
      if (file_exists($path) && file_exists($opath) ) {
          // 2D preview
          $ret = 2;
          $path2 = $pdir."/hrm_previews/".$basename.".".$type."_xz.jpg";
          $path2 = stripslashes($path2);
          $opath2 = $pdir."/hrm_previews/".$basename.".original.".$type."_xz.jpg";
          $opath2 = stripslashes($opath2);
          if (file_exists($path2) && file_exists($opath2) ) {
          // 3D preview
              $ret = 3;
          }
      } else {
          // No preview available for comparison
          $ret = 0;
      }
      return $ret;

  }

  function previewPage ($file , $op = "close", $mode = "MIP", $size = 400) {
      global $allowHttpTransfer;

      $file = stripslashes($file);
      echo '<?xml version=\"1.0\" encoding=\"UTF-8\"?'.'>';

      echo ' <!DOCTYPE html 
          PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
          "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
          <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">

          <head>
          <title>Huygens Remote Manager</title>
          <link rel="SHORTCUT ICON" href="images/hrm.ico"/>
          <script type="text/javascript" src="scripts/common.js"></script>
          <style type="text/css">
          @import "stylesheets/default.css";
      </style>
          </head>
          <body>
          <script type="text/javascript" src="./scripts/wz_tooltip/wz_tooltip.js"></script>
          ';

      echo '
      <div id="prevBasket"> <!--basket-->
      <div id="title">
      <h1>HRM image preview</h1>
      </div>';


      $pdest =  $this->destinationFolder();
      if (!file_exists($pdest."/".$file)) {
          echo "File '$file' does not exist in the server.";
          echo "</body></html>";
          exit;
      }

      $dir = dirname($pdest."/".$file);
      $base = basename($pdest."/".$file);
      $prevBase = $dir."/hrm_previews/".$base;

      $fileBase = $dir."/".$base;
      $path_info = pathinfo($fileBase);
      $fileBase = $dir."/".$path_info['filename'];

      // Available views
      $path = array();
      $test = $prevBase.".".$size."_xy.jpg";
      if (file_exists($test)) {
          $path['MIP'] = $test;
      }
      $test = $prevBase.".sfp.jpg";
      if (file_exists($test)) {
          $path['SFP'] = $test;
      }
      $test = $this->findStrip($file, "stack.compare", $pdest);
      if (count($test) == 1) {
          $path['stack'] = $test[0];
      }
      $test =  $this->findStrip($file, "tSeries.compare", $pdest);
      if (count($test) == 1) {
          $path['tSeries'] = $test[0];
      }

      $test =  $prevBase.".stack.avi";
      if (file_exists($test)) {
          $path['stackMovie'] = $test;
          $movie['stackMovie'] = $file.".stack.avi";
          $msize['stackMovie'] = round(filesize($test) / 1024.0);
      }
      $test =  $pdest."/hrm_previews/".$file.".tSeries.avi";
      if (file_exists($test)) {
          $path['tSeriesMovie'] = $test;
          $movie['tSeriesMovie'] = $file.".tSeries.avi";
          $msize['tSeriesMovie'] = round(filesize($test) / 1024.0);
      }
      $test =  $pdest."/hrm_previews/".$file.".tSeries.sfp.avi";
      if (file_exists($test)) {
          $path['tSeriesSfpMovie'] = $test;
          $movie['tSeriesSfpMovie'] = $file.".tSeries.sfp.avi";
          $msize['tSeriesSfpMovie'] = round(filesize($test) / 1024.0);
      }

      $test = $pdest."/".$file.".remarks.txt";
      if (file_exists($test)) {
          $path['remarks'] = $test;

      $test = $fileBase.".log.txt";
      if (file_exists($test)) {
          $path['log'] = $test;
      }

      // Old history filename didn't contain image type extension.
      $test = $fileBase.".history.txt";
      if (file_exists($test)) {
          $path['history'] = $test;
      }

      // New filename for history includes the file destination extension.
      $test = $pdest."/".$file.".history.txt";
      if (file_exists($test)) {
          $path['history'] = $test;
      }
      }



      $desc['MIP'] = "MIP";
      $desc['SFP'] = "SFP";
      $desc['stack'] = "slicer";
      $desc['tSeries'] = "series";

      $desc['stackMovie'] = "stack movie";
      $desc['tSeriesMovie'] = "series movie";
      $desc['tSeriesSfpMovie'] = "series SFP movie";

      $desc['history'] = "history";
      $desc['remarks'] = "remarks";
      $desc['log'] = "log";

      $tip['MIP'] = "Compare Maximum Intensity Projections";
      $tip['SFP'] = "Compare Simulated Fluorescence renderings";
      $tip['stack'] = "Browse along the Z-planes";
      $tip['tSeries'] = "Browse along the time series";

      $tip['stackMovie'] = "Download Z-stack movie<br>(".$msize['stackMovie']." kB)";
      $tip['tSeriesMovie'] = "Download time series MIP movie<br>(".$msize['tSeriesMovie']." kB)";
      $tip['tSeriesSfpMovie'] = "Download time series SFP movie<br>(".$msize['tSeriesSfpMovie']." kB)";

      $tip['history'] = "See the image restoration history, the executed Huygens - Tcl commands.";
      $tip['remarks'] = "See the image restoration warnings.";
      $tip['log'] = "See the image restoration log file";


      $link = "file_management.php?compareResult=".rawurlencode($file).
          "&amp;op=$op&amp;mode=";
      $mlink = "file_management.php?getMovie=";

      echo "<div id=\"prevMenu\">\n";


      foreach ($path as $key => $val) {
          $class = 'menuEntry';
          $doLink = true;
          if ( $key == $mode ) {
              $doLink = false;
              $class = "menuEntryActive";
          }
          if ( isset($movie[$key]) ) {
              $bLink = $mlink.rawurlencode($movie[$key]);
          } else {
              $bLink = $link.$key;
          }
          echo "\n<div class=\"$class\"";
          if ($doLink) {
              echo " onclick=\"document.location.href='".$bLink."'\"";
          }
              echo " onmouseover=\"Tip('".$tip[$key]."')\" onmouseout=\"UnTip()\"";

          echo ">";
          if ($doLink) {
              echo "<a href=\"".$bLink."\">";
          }
          echo $desc[$key];
          if ($doLink) {
              echo "</a>";
          }
          echo "</div>";

      }

      if ( $allowHttpTransfer ) {
          echo "\n<div class=\"menuEntry\"
          onmouseover=\"Tip('Pack and download the restored image')\"
          onmouseout=\"UnTip()\"
          onclick=\"changeDiv('report','Packaging files, please wait'); setTimeout(smoothChangeDiv,5000,'report','',5000); document.location.href='file_management.php?download=".rawurlencode($file)."'\" ><a href='#'>download file</a></div>\n";
      }

      echo "\n<div class=\"menuEntry\" onclick=\"javascript:openWindow(".
          "'http://support.svi.nl/wiki/style=hrm&amp;".
          "help=HuygensRemoteManagerHelpCompareResult')\" ".
             "onmouseover=\"Tip('Open a pop up with help about this window.')\" onmouseout=\"UnTip()\">".
          "<a href=\"#\"><img src=\"images/help.png\" alt=\"help\" /> Help".
          "</a></div>";

      echo "\n<div class=\"menuEntry\" ";    
 
      switch ($op) {
          case "close":
             echo "onclick=\"window.close()\"".
             "onmouseover=\"Tip('Close this window and go back to the File Manager.')\" onmouseout=\"UnTip()\">".
             "<a href=\"#\">".
             "<img src=\"images/cancel_help.png\" alt=\"cancel\" /> Back".
             "</a>\n";
             break;     
          case "home":
             echo " onclick=\"document.location.href='home.php'\" ".
             "onmouseover=\"Tip('Go to your HRM home page.')\" ".
             " onmouseout=\"UnTip()\" ".
             "'select_parameter_settings.php'\">".
             "<a href=\"#\">".
             "<img src=\"images/home.png\" alt=\"home\" /> Home".
             "</a>\n";
             break;
      }
      echo "</div>\n";
      echo "</div>\n";
      echo "<div id=\"previewContents\">\n";

      if ($mode == "stack" || $mode == "tSeries" ) {
          $this->viewStrip( $file, "$mode.compare", "dest", true );
          echo "<div id=\"previewImg\">\n";
          echo "<center>Original - Restored<br>(drag scrollbar for browsing)</center>";
      } else if ( $mode == "log" || $mode == "history" || $mode == "remarks" ) {
          echo "<div id=\"logFile\">\n";
          print "<pre>";
          readfile ($path[$mode]);
          print "</pre>";
      } else {

      echo "<div id=\"previewImg\">\n";
      echo "\n<table>\n<tr>\n";

      echo "<td>Original</td><td>Restored</td>\n</tr>\n<tr>";

      $othumb_0 = rawurlencode($file.".original.".$size."_xy.jpg");
      $rthumb_0 = rawurlencode($file.".".$size."_xy.jpg");
      
      $osfp = rawurlencode($file.".original.sfp.jpg");
      $rsfp = rawurlencode($file.".sfp.jpg");

      // YZ slices not shown by now, but here they are:
      $othumb_2 = rawurlencode($file.".original.".$size."_xy.jpg");
      $rthumb_2 = rawurlencode($file.".".$size."_xy.jpg");

      if ( $mode == "MIP" ) {

          echo "\n<td><img src=\"file_management.php?getThumbnail=$othumb_0".
              "&amp;dir=dest\" alt=\"Original preview XY\" /></td>";

          echo "\n<td><img src=\"file_management.php?getThumbnail=$rthumb_0".
              "&amp;dir=dest\" alt=\"Restored preview XY\" /></td>";
      } else {
          echo "\n<td><img src=\"file_management.php?getThumbnail=$osfp".
              "&amp;dir=dest\" alt=\"Original SFP preview\" /></td>";

          echo "\n<td><img src=\"file_management.php?getThumbnail=$rsfp".
              "&amp;dir=dest\" alt=\"Restored SFP preview\" /></td>";

      }

      echo "\n</tr>";


      $othumb_1 = rawurlencode($file.".original.".$size."_xz.jpg");
      $rthumb_1 = $file.".".$size."_xz.jpg";
      $path = $pdest."/hrm_previews/".$rthumb_1;
      $path = stripslashes($path);
      $rthumb_1 = rawurlencode($rthumb_1);
      if ($mode == "MIP" && file_exists($path)) {
          // is a 3D image, so it has a lateral view.
          echo "\n<tr>";
          echo "\n<td><img src=\"file_management.php?getThumbnail=$othumb_1".
              "&amp;dir=dest\" alt=\"Original preview XZ\" /></td>";
          echo "\n<td><img src=\"file_management.php?getThumbnail=$rthumb_1".
              "&amp;dir=dest\" alt=\"Restored preview XZ\" /></td>";

          echo "\n</tr>";
      }
      
      echo "\n</table>\n\n";


      }
      echo "</div>\n";
      echo "<div id=\"report\"></div>";
      echo "</div>\n";
      include("footer.inc.php");
 
  }



  //!---------------------------------------------------------
  // @function    Fileserver::compareResult
  // @desc        Shows original/result previews side by side.
  // @return      
  //!---------------------------------------------------------

  function compareResult( $file, $size = "400", $op = "close", $mode="MIP" ) {
      global $allowHttpTransfer;

      $file = stripslashes($file);

      $excludeTitle = true;
      include("header.inc.php");

      if ( $mode == "MIP" ) {
          $altMode = "SFP";
      } else {
          $altMod = "MIP";
      }

      echo "</div>";

      echo "\n\n<h3>Image comparison ($mode)</h3>\n";

      $pdest =  $this->destinationFolder();

      if (!file_exists($pdest."/".$file)) {
          echo "File '$file' does not exist in the server.";
          echo "</body></html>";
          exit;
      }

      echo "\n<table>\n<tr>\n";

      echo "<td>Original</td><td>Restored</td>\n</tr>\n<tr>";

      $othumb_0 = rawurlencode($file.".original.".$size."_xy.jpg");
      $rthumb_0 = rawurlencode($file.".".$size."_xy.jpg");
      
      $osfp = rawurlencode($file.".original.sfp.jpg");
      $rsfp = rawurlencode($file.".sfp.jpg");

      if ( $mode == "MIP" ) {
          $altPath = $pdest."/hrm_previews/".$file.".sfp.jpg";
      } else {
          $altPath = $pdest."/hrm_previews/".$file.".".$size."_xy.jpg";
      }

      $altPath = stripslashes($altPath);
      // YZ slices not shown by now, but here they are:
      $othumb_2 = rawurlencode($file.".original.".$size."_xy.jpg");
      $rthumb_2 = rawurlencode($file.".".$size."_xy.jpg");

      if ( $mode == "MIP" ) {

          echo "\n<td><img src=\"file_management.php?getThumbnail=$othumb_0".
              "&amp;dir=dest\" alt=\"Original preview XY\" /></td>";

          echo "\n<td><img src=\"file_management.php?getThumbnail=$rthumb_0".
              "&amp;dir=dest\" alt=\"Restored preview XY\" /></td>";
      } else {
          echo "\n<td><img src=\"file_management.php?getThumbnail=$osfp".
              "&amp;dir=dest\" alt=\"Original SFP preview\" /></td>";

          echo "\n<td><img src=\"file_management.php?getThumbnail=$rsfp".
              "&amp;dir=dest\" alt=\"Restored SFP preview\" /></td>";

      }

      echo "\n</tr>";


      $othumb_1 = rawurlencode($file.".original.".$size."_xz.jpg");
      $rthumb_1 = $file.".".$size."_xz.jpg";
      $path = $pdest."/hrm_previews/".$rthumb_1;
      $path = stripslashes($path);
      $rthumb_1 = rawurlencode($rthumb_1);
      if ($mode == "MIP" && file_exists($path)) {
          // is a 3D image, so it has a lateral view.
          echo "\n<tr>";
          echo "\n<td><img src=\"file_management.php?getThumbnail=$othumb_1".
              "&amp;dir=dest\" alt=\"Original preview XZ\" /></td>";
          echo "\n<td><img src=\"file_management.php?getThumbnail=$rthumb_1".
              "&amp;dir=dest\" alt=\"Restored preview XZ\" /></td>";

          echo "\n</tr>";
      }
      
      echo "\n</table>\n\n";

      echo "\n<div id=\"message\"><br /><small>$file</small></div>\n";
      echo "\n<div id=\"info\">";

      if (file_exists($altPath)) {
          echo "\n<br /><small><a href=\"file_management.php?compareResult=".rawurlencode($file)."&amp;mode=$altMode&amp;op=$op\" >Compare images in $altMode view</a></small>\n";
      }



      $mpath =  $pdest."/hrm_previews/".$file.".stack.avi";
      $mpath = stripslashes($mpath);
      if ($mode == "MIP" && file_exists($mpath)) {
          $mSize = round(filesize($mpath) / 1024.0);
          echo "\n<br /><small><a href=\"file_management.php?getMovie=".rawurlencode($file.".stack.avi")."\" >Download stack preview video ($mSize kB) </a></small>\n";
      }
      if ( $mode == "MIP" ) {
          $tspath =  $pdest."/hrm_previews/".$file.".tSeries.avi";
          $vname = $file.".tSeries.avi";
      } else {
          $tspath =  $pdest."/hrm_previews/".$file.".tSeries.sfp.avi";
          $vname = $file.".tSeries.sfp.avi";
      }
      $tspath = stripslashes($tspath);
      if (file_exists($tspath)) {
          $tsSize = round(filesize($tspath) / 1024.0);
          echo "\n<br /><small><a href=\"file_management.php?getMovie=".rawurlencode($vname)."\" >Download time-series $mode preview video ($tsSize kB) </a></small>\n";
      }


      if ( $allowHttpTransfer ) {
          echo "\n<br /><small><a href=\"file_management.php?download=".rawurlencode($file)."\" onclick=\"changeDiv('info','Packaging files, please wait')\" >Download restored files</a></small>\n";
      }

      echo "</div>\n";
      echo "<div>\n";
      echo "\n<br /><br /><a href=\"javascript:openWindow(".
          "'http://support.svi.nl/wiki/style=hrm&amp;".
          "help=HuygensRemoteManagerHelpCompareResult')\">".
          "<img src=\"images/help.png\" alt=\"help\" />".
          "</a>";
 
      switch ($op) {
          case "close":
             echo " <a href=\"#\" onclick=\"window.close()\" ".
             "onmouseover=\"Tip('Close this window and go back to the File Manager.')\" onmouseout=\"UnTip()\">".
             "<img src=\"images/cancel_help.png\" alt=\"cancel\" />".
             "</a>\n";
             break;     
          case "home":
             echo " <a href=\"#\" onclick=\"document.location.href=".
             "'home.php'\">".
             "<img src=\"images/home_large.png\" alt=\"home\" />".
             "</a>\n";
             break;
      }
      echo "</div>\n";
      // echo "<script type=\"text/javascript\"> window.close(); <script>\n";
      echo "</body></html>";
      ob_flush();
      flush();
  }




  //!---------------------------------------------------------
  // @function    Fileserver::genPreview
  // @desc        Calls hucore to open an image and generate a jpeg preview.
  // @return      
  //!---------------------------------------------------------

  function genPreview( $file, $src, $dest, $index, $sizes = "preview", $data = 0 ) {

      $excludeTitle = true;
      include("header.inc.php");

      echo "</div><div id=\"info\">".
      "<img src=\"images/spin.gif\" alt=\"busy...\" /><br />".
      "Generating preview for $file, please wait...<br /><br />\n\n<pre>";
      ob_flush();
      flush();

      if ( $src == "src" ) {
          $psrc =  $this->sourceFolder();
      } else {
          $psrc =  $this->destinationFolder();
      }
      if ( $dest == "src" ) {
          $pdest =  $this->sourceFolder();
      } else {
          $pdest =  $this->destinationFolder();
      }

      $psrc = dirname($psrc."/".$file);
      $basename = basename($pdest."/".$file);
      $pdest = dirname($pdest."/".$file)."/hrm_previews";

      // echo "$file, $src, $dest, $sizes<br />";
      // flush;

      $extra = "";
      $series = "auto";

      if ( $data ) {
          $nchan = $_SESSION['setting']->NumberOfChannels();
          $lmbV = "\"";
          $lambda = $_SESSION['setting']->parameter("EmissionWavelength");
          $l = $lambda->value();
          for ( $i = 0; $i < $nchan; $i++ ) {
              $lmbV .= " ".$l[$i];
          }
          $lmbV .= "\"";

          $xy = $_SESSION['setting']->parameter("CCDCaptorSizeX");
          $z = $_SESSION['setting']->parameter("ZStepSize");
          $xy_s = $xy->value() / 1000.0;
          $z_s = $z->value() / 1000.0;
          $extra = " -emission $lmbV -sampling \"$xy_s $xy_s $z_s\"";

          // Enable the -series off option depending on the file type.
          if (stristr($file, ".stk")) {
              $geom = $_SESSION['setting']->parameter("ImageGeometry");
              $geometry = $geom->value();
              if ( !stristr($geometry, "time") ) {
                  $series = "off";
              }
          }
          $formatParam = $_SESSION['setting']->parameter('ImageFileFormat');
          $format = $formatParam->value();
          if ($format == "tiff" || $format == "tiff-single") {
              // Olympus FluoView, or single XY plane: always
              $series = "off";
          }

      }

      $opt = "-filename \"$basename\" -src \"$psrc\" -dest \"$pdest\" ".
             "-scheme auto -sizes \{$sizes\} -series $series $extra";

      $answer = huCoreTools( "generateImagePreview", $opt);

      # if (! $answer ) return;
      # printDebug ($answer);

      $lines = count($answer);
      $html = "";

      $tree = array();
      $new_files = array();
      $cur = NULL;

      $ok = true;
      for ($i = 0; $i < $lines; $i++ ) {
          $key = $answer[$i];

          switch ($key) {
              case "ERROR":
                  $i ++;
                  $html .= $answer[$i]."<br />";
                  $ok = false;
                  break;
              case "REPORT":
                  $i ++;
                  echo $answer[$i]."\n";
                  ob_flush();
                  flush();
              default :
                  # $html .= $answer[$i]."<br />";
                  break;

                  
          }
      }

      echo "Processing finished.\n";
      echo "</pre></div>";
      ob_flush();
      flush();

      $path = stripslashes($pdest."/".$basename.".preview_xy.jpg");
      if ($ok && ! file_exists($path)) {
          $ok = false;
          $html .= "$path does not exist.<br />";
      }

      if ($answer !== NULL) 
          echo "<script type=\"text/javascript\"> changeDiv('info','');".
               "</script>";
      echo $html;

      if ($ok) {
          $nMode = $this->imgPreviewMode($file, $dest, "preview");
          $img = $this->imgPreview($file, $dest, "preview_xy", false) ;
          if ($nMode == 3) {
              $img .= "<br />".
                      $this->imgPreview($file, $dest, "preview_xz", false) ;
          }
          # $img .= "<p><center><kbd>$file</kbd></center></p>";
          echo $img;
      }

      echo "\n\n<script type=\"text/javascript\"> ";
      if ($ok) {
          echo "\nsetPrevGen($index, $nMode);";
          echo "\nchangeOpenerDiv('info','". escapeJavaScript($img). "'); ";
      } else {
          echo "\nchangeOpenerDiv('info','Preview generation failed.<br /><br /><kbd>".escapeJavaScript($html)."</kbd>'); ";
      }
      // Close the popup after a short delay, otherwise the image may not load
      // in the parent window, with some browsers.
      if ($answer !== NULL) echo "\nsetTimeout(\"window.close()\",200);";
      echo "\n</script>\n\n";
      echo "<br /><br /><a href=\"#\" onclick=\"window.close()\">Close</a>\n";
      # echo "<script type=\"text/javascript\"> window.close(); <script>\n";
      echo "</body></html>";
      ob_flush();
      flush();
  }

  //!---------------------------------------------------------
  // @function    Fileserver::getThumbnail
  // @desc        Serve a certain file from the dest directory. Intended to
  //              serve jpg thumbails in combination with imgThumbnail.
  // @return      The binary file.
  //!---------------------------------------------------------    
 
  function getThumbnail($file, $dir) {
      // rawurldecode
      if ( $dir == "src" ) {
          $pdir =  $this->sourceFolder();
      } else {
          $pdir =  $this->destinationFolder();
      }
      $dir = dirname($pdir."/".$file);
      $base = basename($pdir."/".$file);
      $path = $dir."/hrm_previews/".$base;
      $path = stripslashes($path);
      if (!file_exists($path)) {
          $path = "images/no_preview.jpg";
      }
      Header("Content-Type: image/jpeg");
      readfile ($path);
  }

  //!---------------------------------------------------------
  // @function    Fileserver::getMovie
  // @desc        Serves an existing AVI movie.
  // @param       file and diretory label ('src' or 'dest').
  // @return      Void
  //!---------------------------------------------------------  
  function getMovie($file, $dir = "dest" ) { 

      if ( $dir == "src" ) {
          $pdir =  $this->sourceFolder();
      } else {
          $pdir =  $this->destinationFolder();
      }

      $dirname = dirname($pdir."/".$file);
      $basename = basename($pdir."/".$file);

      $path = stripslashes($dirname."/hrm_previews/".$basename);
      if (!file_exists($path)) {
          $path = "images/no_preview.jpg";
          Header("Content-Type: image/jpeg");
          readfile ($path);
      }

      $size = filesize($path);
      $type = "video/x-msvideo";

      if ($size) {
          header ("Accept-Ranges: bytes");
          header ("Connection: close");
          header ("Content-Disposition-type: attachment");
          header ("Content-Disposition: attachment; filename=\"$file\"");
          header ("Content-Length: $size"); 
          header ("Content-Type: $type; name=\"$file\"");
          readfile($path);
      }
  }


  //!---------------------------------------------------------
  // @function    Fileserver::getDestFiles
  // @desc        Create the list of restored image files of the user.
  //              Time series are represented by their first
  //              image file.
  // @return      Void
  //!---------------------------------------------------------    
  function getDestFiles() {
    $this->destFiles = array();
    if (!file_exists($this->destinationFolder())) return False;
    $this->getDestFilesFrom($this->destinationFolder(), "");
    if (count($this->destFiles) == 0) return False;
    sort($this->destFiles());
    // TODO refactor
    //$this->condenseTimeSeries();
    // trim TIFF series to the first file in the sequence
    //$this->condenseTiffSeries();
  } 
 
  //!---------------------------------------------------------
  // @function    Fileserver::basename
  // @desc        Answer the filename of the image without the
  //              numbers marking its position in a time 
  //              series. These numbers are expected to be 
  //              directly before the . of the file extension.
  // @param       filename  String  
  // @return      String
  //!---------------------------------------------------------  
  function basename($filename) {
    $basename = preg_replace("/(\w+|\/)([^0-9])([0-9]+)(\.)(\w+)/", "$1$2$4$5", $filename); 
    return $basename;
  }
  //!---------------------------------------------------------
  // @function    Fileserver::condenseTimeSeries
  // @desc        Remove all but the first file from each time
  //              series in the files attribute.
  // @return      Void
  //!---------------------------------------------------------      
  function condenseTimeSeries() {
    if (count($this->files)==0) return False;
    $time_series =  preg_grep("/\w+[0-9]+\.\w+/", $this->files);
    $lastValue = "";
    foreach ($time_series as $key => $value) { 
       if ($this->basename($lastValue)==$this->basename($value)) { 
           //echo $value;
	unset($this->files[$key]); 
      } 
      $lastValue = $value;
    } 
  }
  // TODO refactor
  // remove single TIFF and TIFF series with Leica style numbering from the file list
  function trimTiffSeries() {
    if (count($this->files)==0) return False;
    $tiff_series = preg_grep("/[^_]+_(T|t|Z|z|CH|ch)[0-9]+\w+\.\w+/", $this->files);
    foreach ($tiff_series as $key => $value) {
	unset($this->files[$key]);
    }
    $tiff_series = preg_grep("/\w+[0-9]+\.\w+/", $this->files, PREG_GREP_INVERT);
    foreach ($tiff_series as $key => $value) {
	unset($this->files[$key]);
    }
  }
  // TODO refactor
  // remove single TIFF and numbered TIFF series from the file list
  function trimTiffLeica() {
    if (count($this->files)==0) return False;
    $tiff = preg_grep("/[^_]+_(T|t|Z|z|CH|ch)[0-9]+\w+\.\w+/", $this->files, PREG_GREP_INVERT);
    foreach ($tiff as $key => $value) {
	unset($this->files[$key]);
    }
  }
  // TODO refactor
  // remove numbered TIFF series and TIFF series with Leica style numbering from the file list
  function trimTiff() {
    if (count($this->files)==0) return False;
    $tiff_series = preg_grep("/[^_]+_(T|t|Z|z|CH|ch)[0-9]+\w+\.\w+/", $this->files);
    foreach ($tiff_series as $key => $value) {
	unset($this->files[$key]);
    }
    /* too restrictive
    $tiff_series = preg_grep("/\w+[0-9]+\.\w+/", $this->files);
    foreach ($tiff_series as $key => $value) {
	unset($this->files[$key]);
    }*/
  }
  function trimStk() {
    if (count($this->files)==0) return False;
    $stk = preg_grep("/[^_]+_(T|t)[0-9]+\.\w+/", $this->files, PREG_GREP_INVERT);
    foreach ($stk as $key => $value) {
	unset($this->files[$key]);
    }
  }
  function trimStkSeries() {
    if (count($this->files)==0) return False;
    $stk = preg_grep("/[^_]+_(T|t)[0-9]+\.\w+/", $this->files);
    foreach ($stk as $key => $value) {
	unset($this->files[$key]);
    }
  }
  // condense TIFF series to the first file in the sequence
  //!---------------------------------------------------------
  // @function    Fileserver::leicaStyleNumberingBasename
  // @desc        Answer the filename of the image without the
  //              numbers marking its position in a TIFF 
  //              series. These numbers are expected to be 
  //              directly before the . of the file extension.
  // @param       filename  String  
  // @return      String
  //!---------------------------------------------------------  
  function leicaStyleNumberingBasename($filename) {
    $basename = preg_replace("/([^_]+|\/)(_)(T|t|Z|z|CH|ch)([0-9]+)(\w+)(\.)(\w+)/", "$1$6$7", $filename);
    return $basename;
  }
  // stack TIFF series to the first file in the sequence
  //!---------------------------------------------------------
  // @function    Fileserver::condenseTiffSeries
  // @desc        Remove all but the first file from each TIFF
  //              series in the files attribute.
  // @return      Void
  //!---------------------------------------------------------      
  function condenseTiffLeica() {
    if (count($this->files)==0) return False;
    $tiff_series =  preg_grep("/[^_]+_(T|t|Z|z|CH|ch)[0-9]+\w+\.\w+/", $this->files);
    $lastValue = "";
    foreach ($tiff_series as $key => $value) { 
       if ($this->leicaStyleNumberingBasename($lastValue)==$this->leicaStyleNumberingBasename($value)) { 
          //echo $value;
          unset($this->files[$key]); 
      } 
      $lastValue = $value;
    } 
  }
  // stack STK series to the first file in the sequence
  //!---------------------------------------------------------
  // @function    Fileserver::stkSeriesBasename
  // @desc        Answer the filename of the image without the
  //              numbers marking its time point in an STK 
  //              series. These numbers are expected to be 
  //              directly before the . of the file extension.
  // @param       filename  String  
  // @return      String
  //!---------------------------------------------------------  
  function stkSeriesBasename($filename) {
    $basename = preg_replace("/([^_]+|\/)(_)(T|t)([0-9]+)(\.)(\w+)/", "$1$5$6", $filename);
    return $basename;
  }
  // stack STK series to the first file in the sequence
  //!---------------------------------------------------------
  // @function    Fileserver::condenseStkSeries
  // @desc        Remove all but the first file from each STK
  //              series in the files attribute.
  // @return      Void
  //!---------------------------------------------------------      
  function condenseStkSeries() {
    if (count($this->files)==0) return False;
    $stk_series =  preg_grep("/[^_]+_(T|t)[0-9]+\.\w+/", $this->files);
    $lastValue = "";
    foreach ($stk_series as $key => $value) { 
       if ($this->stkSeriesBasename($lastValue)==$this->stkSeriesBasename($value)) { 
          //echo $value;
          unset($this->files[$key]); 
      } 
      $lastValue = $value;
    } 
  }
  //!---------------------------------------------------------
  // @function    Fileserver::relocateOldPreview
  // @desc        Since HRM 1.2, thumbnails and previews
  //              are located in a subdirectory hrm_previews.
  //              When and old preview is found in the way, we
  //              can use this function to move it to the new location. This
  //              code is mostly harmless, but we can remove it after a
  //              couple of releases.
  // @param       dir  Path to the old preview file's directory;
  // @param       entry File name;
  // @return      Void
  //!---------------------------------------------------------  
 
  function relocateOldPreview($dir, $entry) {
      if (strstr($entry, ".jpg") || strstr($entry, ".avi")) {
          // Relocate old HRM previews to the new subdirectory.
          // Since HRM 1.2, previews are all stored in a subdirectory
          // 'hrm_previews', but old images may remain along with
          // previous results.
          if (!file_exists($dir."/hrm_previews")) {
              // We keep doing things assuming a trusted environment,
              // but real security would require making all directories
              // accessible to the deamon only, that runs all file
              // management operation after the apache queries.
              // By now, grant 777 permissions.
              @mkdir($dir."/hrm_previews", 0777);
              // The creation mask doesn't seem to work correctly, chmod
              // now:
              @chmod($dir."/hrm_previews", 0777);
          }
          # echo "mv $dir/$entry -> $dir/hrm_previews/$entry <br>";
          @rename ($dir."/".$entry, $dir."/hrm_previews/".$entry);
          @chmod($dir."/hrm_previews/".$entry, 0666);
      }
  }

  //!---------------------------------------------------------
  // @function    Fileserver::getFilesFrom
  // @desc        The recursive function that collects the 
  //              image files from the user's image folder and
  //              its subfolders.
  // @param       startDir  String  The folder to start with  
  // @param       prefix    String  The actual path prefix 
  //              relative to the user's image folder. 
  // @return      Void
  //!---------------------------------------------------------  
  function getFilesFrom($startDir, $prefix) {
    $dir = dir($startDir);
    while ($entry = $dir->read()) { 
      if ($entry != "." && $entry != ".." && $entry != "hrm_previews") { 
	if (is_dir($startDir . "/" . $entry)) { 
	  $newDir = $startDir . "/" . $entry;
	  if ($prefix=="") { 
	    $newPrefix = $entry;
	  } else {  
	    $newPrefix = $prefix . "/" . $entry;
	  } 
	  $this->getFilesFrom($newDir, $newPrefix);
	} else {
            if (!$this->isValidImage($entry)) {
                $this->relocateOldPreview($startDir, $entry);
                continue;
            }
            // Skip also if the image is not of the currently selected type.
            if (!$this->isImage($entry)) continue;
            //echo $entry,$prefix,",";
	  if ($prefix=="") { 
	    $this->files[] = $entry;
	  } else { 
	    $this->files[] = $prefix . "/" . $entry;
	  } 
	}
      }
    }
    $dir->close();
  }
  //!---------------------------------------------------------
  // @function    Fileserver::getDestFilesFrom
  // @desc        The recursive function that collects the 
  //              image files from the user's destination image folder and
  //              its subfolders.
  // @param       startDir  String  The folder to start with  
  // @param       prefix    String  The actual path prefix 
  //              relative to the user's image folder. 
  // @return      Void
  //!---------------------------------------------------------  
  function getDestFilesFrom($startDir, $prefix) {
      $dir = dir($startDir);
      while ($entry = $dir->read()) { 
          if ($entry != "." && $entry != ".." && $entry != "hrm_previews") { 
              if (is_dir($startDir . "/" . $entry)) { 
                  $newDir = $startDir . "/" . $entry;
                  if ($prefix=="") { 
                      $newPrefix = $entry;
                  } else {  
                      $newPrefix = $prefix . "/" . $entry;
                  } 
                  $this->getDestFilesFrom($newDir, $newPrefix);
              } else {
                  if (!$this->isValidImage($entry)) {
                      $this->relocateOldPreview($startDir, $entry);
                      continue;
                  }
                  // echo $entry,$prefix," VALID,";
                  if ($prefix=="") { 
                      $this->destFiles[] = $entry;
                  } else { 
                      $this->destFiles[] = $prefix . "/" . $entry;
                  } 
              }
          }
      }
      $dir->close();
  }

  //!---------------------------------------------------------
  // @function    Fileserver::cleanNonImages
  // @desc        The recursive function that deletes all files in a directory
  //              that are not valid images.
  // @param       startDir  String  The folder to start with  
  // @param       prefix    String  The actual path prefix 
  //              relative to the user's image folder. 
  // @param       valid     String pointer, to accumulate extracted files.
  // @param       msg       String pointer, to accumulate messages.
  // @return      Void
  //!---------------------------------------------------------  
 
  function cleanNonImages($startDir, $prefix, &$valid, &$msg) {
    $dir = dir($startDir);
    while ($entry = $dir->read()) { 
        if ($entry != "." && $entry != ".." && $entry != "hrm_previews") { 
            if (is_dir($startDir . "/" . $entry)) { 
                $newDir = $startDir . "/" . $entry;
                if ($prefix=="") { 
                    $newPrefix = $entry;
                } else {  
                    $newPrefix = $prefix . "/" . $entry;
                }
                $this->cleanNonImages($newDir, $newPrefix, $valid, $msg);
            } else {
                if ($this->isValidImage($entry, true)) {
                    $valid .= " $entry";
                } else {
                    $msg .= " $entry";
                    unlink( $startDir . "/" .$entry);
                    continue;
                }
            }
        }
    }
    // Try to delete the directory: if it is empty, we'll succeed.
    // TODO: this removing still doesn't work well, debug.  j-)
    # $msg .= " removing $startDir";

    # $answer = exec($command , $output, $result);
    $dir->close();
    if ( @rmdir($startDir) ) {
        $msg .= " (empty dir '".basename($startDir). "' deleted)";
    }
  }
 
 
  // TODO refactor
  //!---------------------------------------------------------
  // @function    Fileserver::pointSpreadFunctionFilesFrom
  // @desc        The recursive function that collects the 
  //              ICS format files from the user's image folder and
  //              its subfolders.
  // @param       startDir  String  The folder to start with  
  // @param       prefix    String  The actual path prefix 
  //              relative to the user's image folder. 
  // @return      Array
  //!---------------------------------------------------------  
  function listFilesFrom($startDir, $prefix, $extension) {
    $files = array();
    $dir = dir($startDir);
    while ($entry = $dir->read()) { 
      if ($entry != "." && $entry != "..") { 
	if (is_dir($startDir . "/" . $entry)) { 
	  $newDir = $startDir . "/" . $entry;
	  if ($prefix=="") { 
	    $newPrefix = $entry;
	  } else {  
	    $newPrefix = $prefix . "/" . $entry;
	  }
	  $files = array_merge($files, $this->listFilesFrom($newDir, $newPrefix, $extension));
	} else {
          $ext = substr(strrchr($entry, "."),1);
          $ext = strtolower($ext);
	  if ($extension != "") {
            if ($ext != $extension) continue;
          }
      //echo $entry,$prefix,",";
	  if ($prefix=="") { 
	    $files[] = $entry;
	  } else { 
	    $files[] = $prefix . "/" . $entry;
	  } 
	}
      }
    }
    $dir->close();
    return $files;
  }
  
  //!---------------------------------------------------------
  // @function    Fileserver::hasSelection
  // @desc        Answer true if at least one file is selected.
  // @return      Bool
  //!---------------------------------------------------------      
  function hasSelection() {
    $selection = $this->selectedFiles();
    return (count($selection)>0);
  } 
  
  function folderContains($folder, $string) {
    if (!file_exists($folder)) {
      return False;
    } 
    $dir = opendir($folder);
    $result = False;
    while ($name = readdir($dir)) {
      if (strstr($name, $string)) { 
	$result = True;
      } 
    }
    closedir($dir);
    return $result;
  } 

  function folderContainsNewerFile($folder, $date) {
    if (!file_exists($folder)) {
      return False;
    } 
    $dir = opendir($folder);
    $result = False;
    $db = new DatabaseConnection();  
    while ($name = readdir($dir)) {
      $filename = $folder . '/' . $name;
      if (is_dir($filename)) continue;
      $filedate = filemtime($filename);
      $filedate = $db->fromUnixTime($filedate);
      if ($filedate > $date) $result = True;
    }
    closedir($dir);
    return $result;    
  } 
 }

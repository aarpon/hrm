<?php

// Module Fileserver.inc

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

include ("hrm_config.inc");
require_once("Database.inc");

//!---------------------------------------------------------
// @class    FileSelection
// @desc     Allows the user to select image files and stores
//           the selection.
//!---------------------------------------------------------
Class Fileserver {
  
  var $username;              // @var username         String     The name of the user is his home directory in the same time
  var $files;                 // @var files            Array      The image files under the users home directory    
  var $imageExtensions;       // @var imageExtensions  Array      File extensions indicating an image file  
  var $selectedFiles;         // @var selectedFiles    Array      Files actually selected to be processed

  var $validImageExtensions =  array("tif", "tiff", "stk", "lsm", "ims", "pic", "ome", "lif", "ics", "dv");

  var $multiImageExtensions =  array("lif");
  
  //!---------------------------------------------------------
  // @function    Fileserver::Fileserver
  // @desc        Konstruktor. Creates a new Fileserver.
  // @return      void
  //!---------------------------------------------------------
  function Fileserver($name) { 
    global $image_folder;
    global $image_source;
    $this->username = $name;
    $this->files = NULL;
    $this->selectedFiles = NULL;
    $this->destFiles = NULL;
    $this->imageExtensions = NULL;
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

      $date = date("Y-m-d_His");
      $zipfile = "/tmp/download_".session_id().$date.$compressExt;
      $command = str_replace("%DEST%",
              $this->destinationFolder(), $compressBin);
      $command .= " ".$zipfile;

      foreach ($files as $file) {
          $path = preg_replace("/(.*)\.(.{3,4})/","\"\\1\".*",$file);
          if (! $packExcludePath) {
              $path = $this->destinationFolder()."/".$path;
          }
          $command .= " ".$path;
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
          readfile($zipfile);
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
          $path = preg_replace("/(.*)\.(.{3,4})/","\\1.*",$file);
          $allFiles = glob($pdir."/".$path);
          foreach ($allFiles as $f) {
             unlink($f);
          }
      }
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
  // @return      Boolean
  //!---------------------------------------------------------  
  function isValidImage($filename) {
    $ext = substr(strrchr($filename, "."),1);
    $ext = strtolower($ext);
    $result = False;
    if (in_array($ext, $this->validImageExtensions)) {
      $result = True;
    }
    return $result;
  }

  //!---------------------------------------------------------
  // @function    Fileserver::getSubImages
  // @desc        When the selected file type is one that can contain subimages
  //              (like LIF), the already built list of $this->files is 
  //              extended to show all the available subimages. 
  //              This is done by querying Huygens.
  // @return      Void
  //!---------------------------------------------------------    
  function getSubImages() {

      $i = 0;
      $imgList = "";
      foreach ($this->files as $path) {
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

      $this->files = $new_files;

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
                      # This is always an array even if $len == 1, because in
                      # other images this could be a multichannel parameter.
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
    sort($this->files);
    if ( count ( $this->imageExtensions() ) == 1 ) {
        $extArr = $this->imageExtensions();
        $ext = $extArr[0];
        if ( in_array( $ext, $this->multiImageExtensions)) {
            $this->getSubImages();
        }
    }
    // TODO refactor
    //$this->condenseTimeSeries();
    // trim TIFF series to the first file in the sequence
    //$this->condenseTiffSeries();
  } 


  //!---------------------------------------------------------
  // @function    Fileserver::imgPreview
  // @desc        Generates a link to retreive an image thumbnail,
  //              which is a jpg file saved along with the file itself.
  // @return      A <img src> link to securely get the thumbnail.
  //!---------------------------------------------------------    
  function imgPreview ($image, $dir, $type = "preview_xy", $escape = true ) {
      global $genThumbnails;

      if ( $dir == "src" ) {
          $pdir =  $this->sourceFolder();
      } else {
          $pdir =  $this->destinationFolder();
      }

 
      # The thumbnail is saved along with the image, and it has a suffix
      # indicating the thumbnail type plus the jpg extension.
      $path = $pdir."/".$image.".".$type.".jpg";
      $thumb = rawurlencode($image.".".$type.".jpg");
      if (file_exists($path)) {
          $ret =  "<img src=\"file_management.php?getThumbnail=$thumb&amp;".
              "dir=$dir\" alt=\"Preview\" />";
      } else {
           $imgsrc = "<img src=\"images/no_preview.jpg\" alt=\"No preview\" />";
           # $ret = "<p><center>No preview available.</center></p>";
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

      # The thumbnail is saved along with the image, and it has a suffix
      # indicating the thumbnail type plus the jpg extension.
      $path = $pdir."/".$image.".".$type."_xy.jpg";
      $ret = 0;
      if (file_exists($path)) {
          // 2D preview
          $ret = 2;
          $path2 = $pdir."/".$image.".".$type."_xz.jpg";
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
      $pdir =  $this->destinationFolder();

      # The thumbnail is saved along with the image, and it has a suffix
      # indicating the thumbnail type plus the jpg extension.
      $path = $pdir."/".$image.".".$type."_xy.jpg";
      $opath = $pdir."/".$image.".original.".$type."_xy.jpg";
      $ret = 0;
      if (file_exists($path) && file_exists($opath) ) {
          // 2D preview
          $ret = 2;
          $path2 = $pdir."/".$image.".".$type."_xz.jpg";
          $opath2 = $pdir."/".$image.".original.".$type."_xz.jpg";
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



  //!---------------------------------------------------------
  // @function    Fileserver::compareResult
  // @desc        Shows original/result previews side by side.
  // @return      
  //!---------------------------------------------------------

  function compareResult( $file, $size = "400", $op = "close", $mode="MIP" ) {
      global $allowHttpTransfer;

      $excludeTitle = true;
      include("header.inc.php");

      if ( $mode == "MIP" ) {
          $altMode = "SFP";
      } else {
          $altMode = "MIP";
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
          $altPath = $pdest."/".$file.".sfp.jpg";
      } else {
          $altPath = $pdest."/".$file.".".$size."_xy.jpg";
      }

      # YZ slices not shown by now, but here they are:
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
      $path = $pdest."/".$rthumb_1;
      $rthumb_1 = rawurlencode($rthumb_1);
      if ($mode == "MIP" && file_exists($path)) {
          # is a 3D image, so it has a lateral view.
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



      $mpath =  $pdest."/".$file.".stack.avi";
      if ($mode == "MIP" && file_exists($mpath)) {
          $mSize = round(filesize($mpath) / 1024.0);
          echo "\n<br /><small><a href=\"file_management.php?getMovie=".rawurlencode($file.".stack.avi")."\" >Download stack preview video ($mSize kB) </a></small>\n";
      }
      if ( $mode == "MIP" ) {
          $tspath =  $pdest."/".$file.".tSeries.avi";
          $vname = $file.".tSeries.avi";
      } else {
          $tspath =  $pdest."/".$file.".tSeries.sfp.avi";
          $vname = $file.".tSeries.sfp.avi";
      }
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
             echo " <a href=\"#\" onclick=\"window.close()\">".
             "<img src=\"images/cancel_help.png\" alt=\"cancel\" />".
             "</a>\n";
             break;     
          case "home":
             echo " <a href=\"#\" onclick=\"document.location.href=".
             "'select_parameter_settings.php'\">".
             "<img src=\"images/restart_help.png\" alt=\"home\" />".
             "</a>\n";
             break;
      }
      echo "</div>\n";
      # echo "<script type=\"text/javascript\"> window.close(); <script>\n";
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

      # echo "$file, $src, $dest, $sizes<br />";
      # flush;

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

          # Enable the -series off option depending on the file type.
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

      $opt = "-filename \"$file\" -src \"$psrc\" -dest \"$pdest\" ".
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

      $path = $pdest."/".$file.".preview_xy.jpg";
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
      if ($answer !== NULL) echo "\nwindow.close();";
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
      if ( $dir == "src" ) {
          $pdir =  $this->sourceFolder();
      } else {
          $pdir =  $this->destinationFolder();
      }
      $path = $pdir."/".$file;
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

      $path = $pdir."/".$file;
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
      if ($entry != "." && $entry != "..") { 
	if (is_dir($startDir . "/" . $entry)) { 
	  $newDir = $startDir . "/" . $entry;
	  if ($prefix=="") { 
	    $newPrefix = $entry;
	  } else {  
	    $newPrefix = $prefix . "/" . $entry;
	  } 
	  $this->getFilesFrom($newDir, $newPrefix);
	} else {
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
      if ($entry != "." && $entry != "..") { 
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
          continue;
      }
      //echo $entry,$prefix,",";
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

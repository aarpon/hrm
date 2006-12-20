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

include "hrm_config.inc";
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

  //!---------------------------------------------------------
  // @function    Fileserver::files
  // @desc        Answer all image files under the users image
  //              folder. Searches subdirectories recursively.  
  // @return      Array
  //!---------------------------------------------------------  
  function files() { 
    if ($this->files == NULL) $this->getFiles();
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
    $this->imageExtensions = array("tif", "tiff", "stk", "lsm", "ims", "pic");
  } 
  //!---------------------------------------------------------
  // @function    Fileserver::isImage
  // @desc        Answers True if filename has an extension
  //              which is among the extensions of known image
  //              formats.
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
    sort($this->files());
    $this->condenseTimeSeries();
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
    if (count($this->files())==0) return False;
    $time_series =  preg_grep("/\w+[0-9]+\.\w+/", $this->files());
    $lastValue = "";
    foreach ($time_series as $key => $value) { 
       if ($this->basename($lastValue)==$this->basename($value)) { 
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
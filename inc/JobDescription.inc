<?php
// Module JobDescription.inc

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

require_once("Parameter.inc");
require_once("Database.inc");
require_once("User.inc");
require_once("hrm_config.inc");

//!---------------------------------------------------------
// @class    JobDescription
// @desc     Description of the job to be processed by
//           Huygens. Consisting of a parameter setting,
//           a task setting and a list of image files.
//           From the JobDescription a huygens parameter
//           set is created.
//!---------------------------------------------------------

Class JobDescription {
  var $id;                  // @var id               String            a unique id identifying the job    
  var $parameterSetting;    // @var parameterSetting ParameterSetting  the jobs parameter setting
  var $taskSetting;         // @var taskSetting      TaskSetting       the jobs task setting
  var $files;               // @var files            Array             the list of files to be processed by the job
  var $owner;               // @var owner            User              the user who created the job 
  var $message;             // @var message          String            error message.
  var $pass;                // @var pass             Integer           pass 1 or 2 of step combined processing
  var $credit;              // @var credit           String            name of the credit to be used
  var $group;               // @var group            String            name of the group to be used

  function JobDescription() {
    $this->id = (string)(uniqid(''));
    $this->message = "";
    $this->pass = 1;
  } 
  function message() {
    return $this->message;
  } 
  function id() {
    return $this->id;
  }
  function setId($anId) {
    $this->id = $anId;
  } 
  function owner() {
    return $this->owner;
  }
  function setOwner($owner) {
    $this->owner = $owner;
  } 
  function parameterSetting() {
    return $this->parameterSetting;
  }  
  function taskSetting() {
    return $this->taskSetting;
  } 
  function files() {
    return $this->files;
  } 
  function setParameterSetting($setting) {
    $this->parameterSetting = $setting;
    $this->owner = $setting->owner();
  } 
  function setTaskSetting($setting) {
    $this->taskSetting = $setting;
  } 
  function setFiles($files) {
    $this->files = $files;
  } 
  function credit() {
    return $this->credit;
  } 
  function setCredit($credit) {
    $this->credit = $credit;
  } 
  function group() {
    return $this->group;
  } 
  function setGroup($group) {
    $this->group = $group;
  }
  function createJob() {
    global $use_accounting_system;
    $result = True;
    $jobParameterSetting = new JobParameterSetting();
    $jobParameterSetting->setOwner($this->owner);
    $jobParameterSetting->setName($this->id);
    $jobParameterSetting->copyParameterFrom($this->parameterSetting);
    $result = $result && $jobParameterSetting->save();
    $taskParameterSetting = new JobTaskSetting();
    $taskParameterSetting->setOwner($this->owner);
    $taskParameterSetting->setName($this->id);
    $taskParameterSetting->copyParameterFrom($this->taskSetting);
    $result = $result && $taskParameterSetting->save();
    $db = new DatabaseConnection();
    $result = $result && $db->saveJobFiles($this->id, $this->owner, $this->files);
    if ($use_accounting_system) {
      $result = $result && $db->saveAccountingData($this->id, $this->credit, $this->group);
    }
    $queue = new JobQueue();
    $result = $result && $queue->queueJob($this);
    if (!$result) {
      $this->message = "create job - database error!";
    } 
    return $result;
  }
  function load() {
  	global $use_accounting_system;
    $db = new DatabaseConnection();
    $parameterSetting = new JobParameterSetting;
    $owner = new User;
    $name = $db->userWhoCreatedJob($this->id);
    $owner->setName($name);
    $parameterSetting->setOwner($owner);
    $parameterSetting->setName($this->id);
    $parameterSetting = $parameterSetting->load();
    $this->setParameterSetting($parameterSetting);
    $taskSetting = new JobTaskSetting;
    $taskSetting->setNumberOfChannels($parameterSetting->numberOfChannels());
    $taskSetting->setName($this->id);
    $taskSetting->setOwner($owner);
    $taskSetting = $taskSetting->load();
    $this->setTaskSetting($taskSetting);
    $this->setFiles($db->getJobFilesFor($this->id()));    
    if ($use_accounting_system) {
      $data = $db->loadAccountingData($this);
      $this->setCredit($data['credit']);
      $this->setGroup($data['group']);		
    }
  }

  function copyFrom($aJobDescription) {
    $this->setParameterSetting($aJobDescription->parameterSetting());
    $this->setTaskSetting($aJobDescription->taskSetting());
    $this->setOwner($aJobDescription->owner());
    $this->setCredit($aJobDescription->credit());
    $this->setGroup($aJobDescription->group());
  } 

  function isCompound() { 
    if (count($this->files)>1) {
      return True;
    } 
    if (count($this->rangeParameter())>0) {
      return True;
    } 
    return False;
  } 

  function rangeParameter() {
    $result = array();
    $setting = $this->taskSetting;
    $rangeParameter = $setting->rangeParameter();
    foreach ($rangeParameter as $parameter) {
      $useParameterName = $parameter->useParameterName();
      $useParameter = $setting->parameter($useParameterName);
      if ($useParameter->isTrue()) {
	$result[$parameter->name()] = $parameter;    
      } 
    } 
    return $result;
  } 

  function createSubJobsforFiles() {
    $result = True;
    foreach ($this->files as $file) {
      $newJobDescription = new JobDescription();
      $newJobDescription->copyFrom($this);
      $newJobDescription->setFiles(array($file));	
      $result = $result && $newJobDescription->createJob();
    }
    return $result;
  } 

  function createSubJobs() {
    $result = True;
    if (count($this->rangeParameterCombinations())==0) {
      return $this->createSubJobsforFiles();
    }  
    foreach ($this->files as $file) {
      foreach ($this->rangeParameterCombinations() as $combination) {
	$newJobDescription = new JobDescription();
	$newJobDescription->copyFrom($this);
	$newJobDescription->setFiles(array($file));	
	$index = 0;
	$setting = $newJobDescription->taskSetting();
	foreach ($this->rangeParameterNames() as $rangeParameterName) {
	  $rangeParameter = $setting->parameter($rangeParameterName);
	  $realParameterName = $rangeParameter->realParameterName();
	  $realParameter = $setting->parameter($realParameterName);
	  $useParameterName = $rangeParameter->useParameterName();
	  $useParameter = $setting->parameter($useParameterName);
	  $useParameter->reset();
	  $realParameter->setValue($combination[$index]);
	  $setting->set($realParameter);
	  $setting->set($useParameter);
	  $index++;
	}
	$newJobDescription->setTaskSetting($setting);
	$result = $result && $newJobDescription->createJob();

      } 
    } 
    return $result;
  } 

  function combine($firstArray, $secondArray) {  
    $result = array();
    $i=0;
    if (count($firstArray)==0) {
      return $secondArray;
    } 
    foreach ($firstArray as $firstElem) {
      foreach ($secondArray as $secondElem) {
	$newFirst = $firstElem;
	if(is_array($newFirst)) {
	  $newFirst[] = $secondElem;
	  $row = $newFirst;
	} else { 
	  $row = array($firstElem, $secondElem);
	}
	$result[] = $row; 
      } 
      $i++;
    } 
    return $result;
  } 

  function combineAll($anArray) {
    $result = array();
    foreach ($anArray as $row) {
      $result = $this->combine($result, $row);
    } 
    return $result;
  } 
  
  function rangeParameterCombinations() {
    $jobDescriptions = array();
    $rangeParameter = $this->rangeParameter();
    $matrix = array();
    if (count($rangeParameter)==1) {
      $range = array_pop($rangeParameter);
      $value = array_filter($range->value());
      foreach ($value as $number) {
	$matrix[count($matrix)] = array($number); 
      }
      return $matrix;
    } else { 
      foreach ($rangeParameter as $range) {
	$value = array_filter($range->value());
	$matrix[] = $value;
      }
      $combinations = $this->combineAll($matrix);
      return $combinations;
    } 
  } 

  function rangeParameterNames() {
    $names = array();
    foreach ($this->rangeParameter() as $parameter) {
      $names[] = $parameter->name(); 
    } 
    return $names;
  } 

  function putMultiChannelScriptOn($script) {
    $parameterSetting = $this->parameterSetting;
    $numberOfChannels = $parameterSetting->numberOfChannels();
    $files = $this->files();
    $newScript = $script;
    $newScript = $newScript . $this->loadImageString();
    $newScript = $newScript . '$imageName split -mode all' . "\n";    
    $newScript = $newScript . '$imageName del' . "\n"; 
    $fileBase = $this->sourceImageShortName();
    $newScript = $newScript . "catch {\n";
    for($channel=0; $channel < $numberOfChannels; $channel++) {
      $tmpfilename = $this->id() . $fileBase . "Ch$channel";
      // avoid redundant slashes in path
      $newScript = $newScript . '$imageName' . ":Ch$channel save " . '{' . $this->sourceFolder() . $this->relativeSourcePath() . $tmpfilename . '} ' . '-type ics' . "\n";
      $newScript = $newScript . '$imageName' . ":Ch$channel del " . "\n";
    } 
    $newScript = $newScript . "}\n";
    $dst_files = array();
    for($channel=0; $channel<$numberOfChannels; $channel++) {
      $newScript = $newScript . "catch {\n";
      $tmpfilename = $this->id() . $fileBase . "Ch$channel";
      // avoid redundant slashes in path
      $this->files = array($this->relativeSourcePath() . $tmpfilename . ".ics");
      // TODO refactor
      $newScript = $newScript . $this->putScriptOnForChannel('', $channel + 1);
      $filename = $this->sourceFolder() . $this->relativeSourcePath() . $tmpfilename . ".ics";
      $filename2 = $this->sourceFolder() . $this->relativeSourcePath() . $tmpfilename . ".ids";
      $dst_files[] = $this->destinationImageFullName() ;
      $newScript = $newScript . "}\n";
      $newScript = $newScript . "catch { exec rm -f " . '"' . $filename . '"' . "}\n";
      $newScript = $newScript . "catch { exec rm -f " . '"' . $filename2 . '"' . "}\n";
      $newScript = $newScript . 'c clear' . "\n";
      // manage measured PSF
      $psfParam = $parameterSetting->parameter('PointSpreadFunction');
      if ($psfParam->value() == "theoretical") {
        $newScript = $newScript . 'psf clear' . "\n";
      }
    } 
    $taskSetting = $this->taskSetting;
    $multichanneloutput = $taskSetting->parameter('MultiChannelOutput');
    $param = $taskSetting->parameter('OutputFileFormat');
    $type = $param->translatedValue();
    // Use appropriate output file format
    switch ($type) {
        case "imaris":
            $fileFormat = "ims";
            break;
        case "ics":
            $fileFormat = "ics";
            break;
        case "tiff":
        case "tiff16":
            $fileFormat = "tif";
    }
    if ($multichanneloutput->value()) {
        $newScript = $newScript . "# join\n";
        for($channel=0; $channel < $numberOfChannels; $channel++) {
            $newScript = $newScript . "set imageName$channel [ img open \"".$dst_files[$channel].".".$fileFormat."\"]\n";
            if ($channel > 1) {
                $newScript = $newScript . "c join \$imageName$channel -> c\n";
            } else if ($channel == 1) {
                $newScript = $newScript . "\$imageName0 join \$imageName1 -> c\n";
            }
        }
        $tmpfilename = $this->id() . $fileBase . "Ch_all";
        // avoid redundant slashes in path
        $this->files = array($this->relativeSourcePath() . $tmpfilename . ".ics");
        $dstfilename = $this->destinationImageFullName() ;
        $newScript = $newScript . "c save \"$dstfilename\" -type $type\n";
        foreach ($dst_files as $dst_file) {
            $newScript .= "catch { exec rm -f \"" . $dst_file . "." . $fileFormat . "\" }\n";
            if ($fileFormat == "ics") {
              $newScript .= "catch { exec rm -f \"" . $dst_file . ".ids\" }\n";
            }
        }
    }
    $this->files = $files;
    return $newScript;
  }

  function putScriptOnForChannel($script, $channel) {
    $parameterSetting = $this->parameterSetting;
    $psfParam = $parameterSetting->parameter('PointSpreadFunction');
    // save original excitation and emission wavelengths
    $excitationWavelength = $parameterSetting->parameter('ExcitationWavelength');
    $emissionWavelength = $parameterSetting->parameter('EmissionWavelength');
    // DEBUG
    $val = $excitationWavelength->value();
    $originalExcitationWavelengthValue = array();
    for ($i = 1; $i <= $parameterSetting->numberOfChannels(); $i++) {
      $originalExcitationWavelengthValue[$i] = $val[$i];
      //error_log("GENERATING SCRIPT (JobDescription): excitation wavelength -> ".$originalExcitationWavelengthValue[$i]." [".$i."]");
    }
    $val = $emissionWavelength->value();
    $originalEmissionWavelengthValue = array();
    for ($i = 1; $i <= $parameterSetting->numberOfChannels(); $i++) {
      $originalEmissionWavelengthValue[$i] = $val[$i];
      //error_log("GENERATING SCRIPT (JobDescription): emission wavelength -> ".$originalEmissionWavelengthValue[$i]." [".$i."]");
    }
    //error_log("GENERATING SCRIPT (JobDescription): channel -> ".$channel);
    // save original pinhole radii
    $pinholeSize = $parameterSetting->parameter('PinholeSize');
    $val = $pinholeSize->value();
    $originalPinholeSizeValue = array();
    for ($i = 1; $i <= $parameterSetting->numberOfChannels(); $i++) {
      $originalPinholeSizeValue[$i] = $val[$i];
    }
    // save original measured PSF
    if ($psfParam->value() == "measured") {
      $psf = $parameterSetting->parameter('PSF');
    }
    // make single channel setting for current channel
    $isMultiChannel = $parameterSetting->parameter('IsMultiChannel');
    $isMultiChannel->setValue('False');
    $parameterSetting->set($isMultiChannel);
    $newExcitationWavelength = $parameterSetting->parameter('ExcitationWavelength');
    $value = $newExcitationWavelength->value();
    //error_log("GENERATING SCRIPT (JobDescription): excitation wavelength (before trimming) -> ".$value[$channel]);
    $newExcitationWavelength->setValue(array('', $value[$channel]));
    $val = $newExcitationWavelength->value();
    //error_log("GENERATING SCRIPT (JobDescription): excitation wavelength (after trimming) -> ".$val[1]);
    $parameterSetting->set($newExcitationWavelength);
    $newEmissionWavelength = $parameterSetting->parameter('EmissionWavelength');    
    $value = $newEmissionWavelength->value();
    //error_log("GENERATING SCRIPT (JobDescription): emission wavelength (before trimming) -> ".$value[$channel]);
    $newEmissionWavelength->setValue(array('', $value[$channel]));
    $val = $newEmissionWavelength->value();
    //error_log("GENERATING SCRIPT (JobDescription): emission wavelength (after trimming) -> ".$val[1]);
    $parameterSetting->set($newEmissionWavelength);
    $newPinholeSize = $parameterSetting->parameter('PinholeSize');
    $value = $newPinholeSize->value();
    $newPinholeSize->setValue(array('', $value[$channel]));
    $parameterSetting->set($newPinholeSize);
    if ($psfParam->value() == "measured") {
      $newPSF = $parameterSetting->parameter('PSF');
      $value = $newPSF->value();
      $newPSF->setValue(array('', $value[$channel]));
      $parameterSetting->set($newPSF);
    }
    // ... and for task setting
    // save original RemoveBackgroundPercent and BackgroundOffsetPercent
    $taskSetting = $this->taskSetting;
    $numberOfChannels = $taskSetting->numberOfChannels();
    $removeBackgroundPercent = $taskSetting->parameter('RemoveBackgroundPercent');
    $val = $removeBackgroundPercent->internalValue();
    $originalRemoveBackgroundPercentValue = array();
    for ($i = 1; $i <= $numberOfChannels; $i++) {
      if ($val[1] == "auto") {
        $originalRemoveBackgroundPercentValue[$i] = "auto";
      }
      else {
        $originalRemoveBackgroundPercentValue[$i] = $val[$i];
      }
      //error_log("GENERATING SCRIPT (JobDescription): remove background percent -> ".$originalRemoveBackgroundPercentValue[$i]." [".$i."]");
    }
    $backgroundOffsetPercent = $taskSetting->parameter('BackgroundOffsetPercent');
    $val = $backgroundOffsetPercent->internalValue();
    $originalBackgroundOffsetPercentValue = array();
    for ($i = 1; $i <= $numberOfChannels; $i++) {
      if ($val[1] == "auto") {
        $originalBackgroundOffsetPercentValue[$i] = "auto";
      }
      else {
        $originalBackgroundOffsetPercentValue[$i] = $val[$i];
      }
      //error_log("GENERATING SCRIPT (JobDescription): background offset percent -> ".$originalBackgroundOffsetPercentValue[$i]." [".$i."]");
    }
    // make single channel setting for current channel
    $newRemoveBackgroundPercent = $taskSetting->parameter('RemoveBackgroundPercent');
    // DEBUG
    //$value = $newRemoveBackgroundPercent->value();
    $newRemoveBackgroundPercent->setValue(array('', $originalRemoveBackgroundPercentValue[$channel]));    
    $taskSetting->set($newRemoveBackgroundPercent);
    $newBackgroundOffsetPercent = $taskSetting->parameter('BackgroundOffsetPercent');
    // DEBUG
    //$value = $newBackgroundOffsetPercent->value();
    $newBackgroundOffsetPercent->setValue(array('', $originalBackgroundOffsetPercentValue[$channel]));    
    $taskSetting->set($newBackgroundOffsetPercent);
    $taskSetting->setNumberOfChannels(1);
    // Create script
    $this->setParameterSetting($parameterSetting);
    $this->setTaskSetting($taskSetting);
    $result = $this->putScriptOn($script);
    // restore multi channel setting with original excitation and emission wavelengths
    $parameterSetting = $this->parameterSetting;
    $isMultiChannel->setValue('True');
    $parameterSetting->set($isMultiChannel);
    // DEBUG
    $excitationWavelength->setValue($originalExcitationWavelengthValue);
    $parameterSetting->set($excitationWavelength);
    $emissionWavelength->setValue($originalEmissionWavelengthValue);
    $parameterSetting->set($emissionWavelength);
    // restore multi channel setting with original pinhole radii
    $pinholeSize->setValue($originalPinholeSizeValue);
    $parameterSetting->set($pinholeSize);
    if ($psfParam->value() == "measured") {
      // restore multi channel setting with original measured PSF
      $parameterSetting->set($psf);
    }
    $this->setParameterSetting($parameterSetting);
    // ... and for task setting
    $taskSetting = $this->taskSetting;
    $taskSetting->setNumberOfChannels($numberOfChannels);
    // DEBUG
    $removeBackgroundPercent->setValue($originalRemoveBackgroundPercentValue);
    $taskSetting->set($removeBackgroundPercent);
    $backgroundOffsetPercent->setValue($originalBackgroundOffsetPercentValue);
    $taskSetting->set($backgroundOffsetPercent);
    $this->setTaskSetting($taskSetting);
    // return result
    return $result;
  } 

  function endsWithNumber($string) {
    $last = $string[strlen($string)-1];
    return is_numeric($last);
  } 

  function mustReplaceSourceName() {
    $parameterSetting = $this->parameterSetting;
    if (!$parameterSetting->isThreeDimensional() && !$parameterSetting->isTimeSeries() && $parameterSetting->isTif()) {
      if ($this->endsWithNumber($this->sourceImageShortName())) {
	return True;
      } 	
    }
    return False;
  } 

  function newNameForSingleTif() {
    $files = $this->files();
    $name = end($files);
    $path = explode("/", $name);
    $shortName = array_pop($path);
    $path = implode("/", $path);
    $anArray = explode(".", $shortName);
    $baseName = $anArray[0];
    $extension = end($anArray);
    $result = $this->sourceFolder() . "." . $baseName . $this->id() . "s." . $extension;
    return $result;
  } 

  function putScriptOn($script) {
    global $resultImagesOwnedByUser;
    global $huygens_group;
    
    $newScript = $script;
    $newScript = $newScript . "if {![img exists a]} {img create a}\n";
    $newScript = $newScript . "if {![img exists b]} {img create b}\n";
    $newScript = $newScript . "if {![img exists c]} {img create c}\n";
    if ($this->mustReplaceSourceName()) { 
      $newScript = $newScript . "exec cp " . '"' . $this->sourceImageName() . '" "' . $this->newNameForSingleTif() . '"' . "\n";
    }  
    // loading and converting
    $newScript = $newScript . $this->loadImageString();
    $parameterSetting = $this->parameterSetting;
     if ($parameterSetting->isTimeSeries()) 
		$newScript = $newScript . 'catch {$imageName convertZ2T}' . "\n";
    else 
		$newScript = $newScript . 'catch {$imageName convertT2Z}' . "\n";
		
    $taskSetting = $this->taskSetting;
    $numberOfChannels = $parameterSetting->numberOfChannels();
    $taskSetting->setNumberOfChannels($numberOfChannels);
    
    // manage measured PSF
    $psfParam = $parameterSetting->parameter('PointSpreadFunction');
    if ($psfParam->value() == "measured") {
      $param = $parameterSetting->parameter('PSF');
      $value = $param->value();
      $filename = $this->sourceFolder() . $value[1];
      $newScript = $newScript . "set psf [img open \"".$filename."\"]\n";
    }
    else if ($psfParam->value() == "theoretical") {
      $newScript = $parameterSetting->putScriptOn($newScript);
    }
    
    $z2t = False;
    if (!$parameterSetting->isThreeDimensional() && $parameterSetting->isTimeSeries() && !$parameterSetting->isTif())
      $z2t = True;
    if ($taskSetting->isStepProcessing() && !$parameterSetting->isTimeSeries()) $z2t = True;
    if ($z2t) {
      $newScript = $newScript . 'catch {$imageName convertZ2T}' . "\n";
    }
    
    // manage measured PSF
    if ($psfParam->value() == "theoretical") {
      // operations
      $newScript = $taskSetting->putScriptOn($newScript);
    }
    else if ($psfParam->value() == "measured") {
      // operations
      $newScript = $taskSetting->putScriptForMeasuredPointSpreadFunctionOn($newScript);
    }
    
    // if sampling sizes had been adapted, reset the original values
    if ($parameterSetting->sampleSizeAdaptionFactor()!=1) {
      $newScript = $newScript . 'c setp -s {' . $parameterSetting->originalSampleSizesString() . "}\n";       
    } 
    // converting and saving
    $t2z = False;
    if ($z2t) $t2z = True;
    
    if (!$parameterSetting->isThreeDimensional() && $taskSetting->isOutputIms() && $parameterSetting->isTimeSeries()) $t2z = True;
    if ($parameterSetting->isThreeDimensional() && $taskSetting->isOutputIms() && !$parameterSetting->isTimeSeries()) $t2z = True;
    
    if ($t2z) {
      $newScript = $newScript . 'catch {c convertT2Z}' . "\n";
      $newScript = $newScript . 'c setp -s {' . $parameterSetting->originalSampleSizesString() . "}\n"; 
    } 
    if (!$parameterSetting->isThreeDimensional() && !$parameterSetting->isTimeSeries() && !$taskSetting->isOutputIms()) { 
      $newScript = $newScript . 'c convert3d22d' . "\n";   
    }
    
    $newScript = $newScript .   "catch { exec mkdir -m0777 -p " . '"' . $this->destinationFolder() . '"' . " }\n"; 
    if ($resultImagesOwnedByUser) {
      $user = $this->owner();
      $newScript = $newScript .   "catch { exec chown -Rf " . $user->name() . ':' . $huygens_group . ' "' . $this->destinationFolderTop() . '"' . " }\n"; 
    } 
    $newScript = $newScript . $this->saveImageString();
    if ($taskSetting->isStepCombinedProcessing()) {
      $newScript = $newScript . $this->passTwoScript();
    }
    if ($this->mustReplaceSourceName()) { 
      $newScript = $newScript . "catch { exec rm -f " . '"' . $this->newNameForSingleTif() . '"' . " }\n";
    }  
    return $newScript;
  }

  function passTwoScript() {
    $this->pass = 2;
    $script = '';
    $script = $script . 'if {[img exists $imageName]} {$imageName del}' . "\n";
    $script = $script . 'c genpsf -> psf -dims fullpadpar -z -reflCorr' . "\n";
    $script = $script . 'c cmle psf -> c -sn {80} -it 7 -bgMode object -bg {-100} -blMode auto -q 0 -mode highQ -pad fullpadpar' . "\n";
    $script = $script . $this->saveImageString();
    $this->pass = 1;
    return $script;
  } 

  function loadImageString() {
    if ($this->mustReplaceSourceName()) { 
      $imageFileName = $this->newNameForSingleTif();
    } else { 
      $imageFileName = $this->sourceImageName(); 
    } 
    return 'set imageName [img open "' . $imageFileName . '"]' . "\n" . '$imageName lundo -off' . "\n";
  } 

  function saveImageString() {
    global $resultImagesOwnedByUser;
    $result = '';
    $setting = $this->taskSetting;
    $parameterSetting = $this->parameterSetting;
    $param = $setting->parameter('OutputFileFormat');
    $type = $param->translatedValue();
    if ($type=='ics') {
       $result = $result . "c convert -type auto\n";
    }
    $imageFileName = $this->destinationImageFullName();
    if ($type== 'tiff' || $type == 'tiff16') {
    	$imageFileName = $imageFileName . '.tif';
    }
    $result = $result . "c save " . '"' . $imageFileName .'"' . " -type $type";
    if ($type == 'tiff' || $type == 'tiff16') {
        $result = $result . " -tiffMultiDir";
    }
    $result = $result . "\n";
    if ($resultImagesOwnedByUser) {
      $user = $this->owner();
      $username = $user->name();
      $result = $result . 'set modfiles [glob -nocomplain ' . '"' . $this->destinationFolder() . '*' . $this->sourceImageShortName() . '*' . '"]' . "\n";
      $result = $result . 'foreach modfile $modfiles { catch { exec chown ' . "$username " . '$modfile } }' . "\n";
    }
    return $result;
  } 

  function sourceImageName() {
    $files = $this->files();
    // avoid redundant slashes in path
    $result = $this->sourceFolder() . ereg_replace("^/", "", end($files));
    return $result;
  } 

  function relativeSourcePath() {
    $files = $this->files();
    $inputFile = end($files);
    $inputFile = explode("/", $inputFile); 
    array_pop($inputFile);
    $path = implode("/", $inputFile);
    // avoid redundant slashes in path
    if (strlen($path) > 0) $path = ereg_replace("([^/])$", "\\1/", $path);
    return $path;
  } 

  function sourceImageShortName() {
    $files = $this->files();
    $inputFile = end($files);
    $inputFile = explode("/", $inputFile);
    // remove file extension
    //$inputFile = explode(".", end($inputFile));
    //$inputFile = $inputFile[0];
    $inputFile = substr(end($inputFile), 0, strrpos(end($inputFile), "."));
    return $inputFile;
  } 

  function sourceFolder() {
    global $huygens_server_image_folder;
    global $image_source;
    $user = $this->owner();
    $result = $huygens_server_image_folder . $user->name() . "/" . $image_source . "/";
    return $result;
  } 

  function destinationImageName() {
    $taskSetting = $this->taskSetting();
    $files = $this->files();
    $outputFile = $this->sourceImageShortName();
    $outputFile = end(explode($taskSetting->name(), $this->sourceImageShortName()));
    $result = $outputFile . "_" . $taskSetting->name() . "_hrm";
        # Add a non-numeric string at the end: if the task name ends with a
        # number, that will be removed when saving using some file formats that
        # use numbers to identify Z planes. Therefore the result file won't
        # be found later and an error will be generated.

    if ($taskSetting->isStepCombinedProcessing()) {
      if ($this->pass == 1) {
          $extension = 'A';
      } else {
          $extension = 'B';
      } 
      $result = $result . "_step" . $extension;
    } 
    return $result; 
  } 

  function destinationImageFullName() {
    global $resultImagesRenamed;
    // use source filename as destination filename
    if (!$resultImagesRenamed) {
      $files = $this->files();
      $result = $this->destinationFolder() . ereg_replace("^/", "", end($files));
      $result = substr($result, 0, strrpos($result, "."));
    }
    else {
      $result = $this->destinationFolder() . $this->destinationImageName();
    }
    return $result;
  }

  function destinationFolder() {
    global $huygens_server_image_folder;
    global $image_destination;

    $user = $this->owner();
    // avoid redundant slashes in path
    $result = $huygens_server_image_folder . $user->name() . "/" . $image_destination . "/" . $this->relativeSourcePath();
    
    return $result;
  } 

  function destinationFolderTop() {
    global $huygens_server_image_folder;
    global $image_destination;

    $user = $this->owner();
    $path = $this->relativeSourcePath();
    $pathComponents = explode("/", $path);
    $top = $pathComponents[0]; 
    $result = $huygens_server_image_folder . $user->name() . "/" . $image_destination . "/" . $top . "/"; 
    
    return $result;    
  } 
} 


Class JobQueue {
  function JobQueue() {
    
  } 
  
  function timestampNowString() {
    $db = new DatabaseConnection();
    $date = $db->now();
    $ms = microtime();
    $ms = explode(" ", $ms);
    $ms = $ms[0];
    return $date . "." . substr($ms,2); 
  } 
  
  function availableServer() {
    $db = new DatabaseConnection();
    $result = $db->availableServer();
    return $result;
  } 

  function getContents() {
    $db = new DatabaseConnection();
    $rows = $db->getQueueContents();    
    return $rows;
  } 

  function getJobFilesFor($id) {
    $db = new DatabaseConnection();
    $files = $db->getJobFilesFor($id);    
    return $files;    
  } 

  function queueJob($jobDescription) {
    $owner = $jobDescription->owner;
    $ownerName = $owner->name();
    $db = new DatabaseConnection();
    $result = $db->queueJob($jobDescription->id(), $ownerName);
    return $result;
  } 

  function startJob($job) {
    $db = new DatabaseConnection();
    $pid = $job->pid();
    $result = $db->reserveServer($job->server(), $pid); 
    $result = $result && $db->startJob($job);
    return $result;
   }

  function getNextJobDescription() {
    $db = new DatabaseConnection();
    $id = $db->getNextIdFromQueue();
    if ($id == NULL) {
      return NULL;
    } 
    $jobDescription = new JobDescription;
    $jobDescription->setId($id);
    $jobDescription->load();
    return $jobDescription;
  }

  function getCompoundJobs() {
    $db = new DatabaseConnection();
    $jobDescriptions = array();
    $rows = $db->getQueueContents();
    foreach ($rows as $row) {
      $jobDescription = new JobDescription;
      $jobDescription->setId($row['id']);
      $jobDescription->load();
      if ($jobDescription->isCompound()) {
	$jobDescriptions[] = $jobDescription; 
      }  
    } 
    return $jobDescriptions;
  } 

  function removeJob($jobDescription) {
    $id = $jobDescription->id();
    $result = $this->removeJobWithId($id);
    return $result;
  } 

  function markJobsAsRemoved($ids) {
    $result = True;
    if (count($ids)==0) return $result;
    $db = new DatabaseConnection();
    foreach($ids as $id) {
      $result = $result && $db->markJobAsRemoved($id);
    } 
    return $result;
  } 

  function removeMarkedJobs() {
    $db = new DatabaseConnection();
    $ids = $db->getMarkedJobIds();
    foreach ($ids as $id) {
      $this->removeJobWithId($id);
    } 
  } 

  function removeJobWithId($id) {
  	global $use_accounting_system;
    $result = True;
    $db = new DatabaseConnection();
    $tables = array('job_queue', 'job_files', 'job_parameter', 'job_parameter_setting', 'job_task_parameter', 'job_task_setting');
    $columns = array('id', 'job', 'setting', 'name', 'setting', 'name');
     if ($use_accounting_system) {
      $tables[] = 'job_accounting_data';
      $columns[] = 'id'; 
    } 
    $result = $result && $db->deleteFromTablesWhereColumnEquals($tables, $columns, $id);
    return $result; 
  } 
  
  function stopJob($job) {
    $db = new DatabaseConnection();
    $db->resetServer($job->server(), $job->pid());
    $this->removeJob($job->description());
    return $this->timestampNowString();
  } 

  function runningJobs() {
    $db = new DatabaseConnection();
    $jobs = $db->getRunningJobs();
    return $jobs;
  }

  function startTime($job) {
    $db = new DatabaseConnection();
    $date = $db->startTimeOf($job);    
    return $date;
  }
  
  function pauseJob($jobDescription) {
    $db = new DatabaseConnection();
    $result = $db->pauseJob($jobDescription->id());
    return $result;
  } 

  function restartPausedJobs() {
    $db = new DatabaseConnection();
    $result = $db->restartPausedJobs();
    return $result;    
  }
  
  function isServerBusy($name) {
    $db = new DatabaseConnection();
    $result = $db->isServerBusy($name);
    return $result;
  }  
} 
?>

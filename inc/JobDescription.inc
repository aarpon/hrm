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
require_once("Shell.inc");
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
  //var $rangeParameters;     // why not use a global variable from the beginning?!

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
    //global $rangeParameters;
    $result = array();
    $setting = $this->taskSetting;
    $rangeParameter = $setting->rangeParameter();
    foreach ($rangeParameter as $parameter) {
      $useParameterName = $parameter->useParameterName();
      $useParameter = $setting->parameter($useParameterName);
      if ($useParameter->isTrue()) {
	$result[$parameter->name()] = $parameter;
        //$this->rangeParameters[] = $parameter;
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
    //global $rangeParameters;
    
    $parameterSetting = $this->parameterSetting;
    $numberOfChannels = $parameterSetting->numberOfChannels();
    
    $rangeParameters = $this->rangeParameter();
    $rangeParameterCombinations = $this->rangeParameterCombinations($rangeParameters);
    $result = True;
    if (count($rangeParameterCombinations)==0) {
      return $this->createSubJobsforFiles();
    }  
    foreach ($this->files as $file) {
      error_log("range combinations = " . count($rangeParameterCombinations));
      foreach ($rangeParameterCombinations as $combination) {
        error_log("combination [" . implode(" ", $combination) . "]");
	$newJobDescription = new JobDescription();
	$newJobDescription->copyFrom($this);
	$newJobDescription->setFiles(array($file));	
	$index = 0;
	$setting = $newJobDescription->taskSetting();
	//foreach ($this->rangeParameterNames() as $rangeParameterName) {
        foreach ($rangeParameters as $rangeParameter) {
          $rangeParameterName = $rangeParameter->name();
	  //$rangeParameter = $setting->parameter($rangeParameterName);
	  $realParameterName = $rangeParameter->realParameterName();
	  $realParameter = $setting->parameter($realParameterName);
	  $useParameterName = $rangeParameter->useParameterName();
	  $useParameter = $setting->parameter($useParameterName);
	  $useParameter->reset();
          //error_log("range parameter " . $realParameterName . " = " . $combination[$index]);
          if ($realParameterName == "SignalNoiseRatio") {
            $newVal = array(null, null, null, null, null);
            for ($i = 0; $i < $numberOfChannels; $i++) {
              $newVal[$i] = $combination[$i];
            }
          }
          else if ($realParameterName == "NumberOfIterations") {
            if (count($combination) > 1)
              $newVal = $combination[$numberOfChannels];
            else
              $newVal = $combination[0];
          }
	  $realParameter->setValue($newVal);
	  $setting->set($realParameter);
	  $setting->set($useParameter);
	  //$index++;
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
  
  function rangeParameterCombinations($rangeParameter) {
    $parameterSetting = $this->parameterSetting;
    $numberOfChannels = $parameterSetting->numberOfChannels();
    
    $jobDescriptions = array();
    //$rangeParameter = $this->rangeParameter();
    $matrix = array();
    //if (count($rangeParameter)==1) {
      $range = array_pop($rangeParameter);
      $value = array_filter($range->value());
      $snrValue = null;
      $iterationValue = null;
      // process SignalNoiseRatioRange, if applicable
      if ($range->name() == "SignalNoiseRatioRange") {
        error_log("SignalNoiseRatio range");
        $snrRange = $range;
        $snrValue = $value;
      }
      else if ($range->name() == "NumberOfIterationsRange") {
        error_log("NumberOfIterations range");
        $iterationRange = $range;
        $iterationValue = $value;
      }
      // process NumberOfIterationsRange, if applicable
      if (count($rangeParameter) > 0) {
        $range = array_pop($rangeParameter);
        $value = array_filter($range->value());
        if ($range->name() == "SignalNoiseRatioRange") {
          error_log("SignalNoiseRatio range");
          $snrRange = $range;
          $snrValue = $value;
        }
        else if ($range->name() == "NumberOfIterationsRange") {
          error_log("NumberOfIterations range");
          $iterationRange = $range;
          $iterationValue = $value;
        }
      }
      // what is the form of matrix?
      // compute matrix dimensions
      $dim = 1;
      if ($snrValue != null) {
        for ($i = 0; $i < $numberOfChannels; $i++) {
          $dim *= count($snrValue[$i]);
        }
      }
      if ($iterationValue != null) {
        $dim *= count($iterationValue);
      }
      error_log("dimension = " . $dim);
      for ($i = 0; $i < $dim; $i++) {
        $row = array();
        $size = 1;
        if ($snrValue != null) {
          for ($j = 0; $j < $numberOfChannels; $j++) {
            $val = $snrValue[$j];
            if (is_array($val)) {
              $val = array_filter($val);
              $row[] = $val[($i/$size) % count($val)];
              $size *= count($val);
            }
            else {
              $row[] = $val;
            }
          }
        }
        if ($iterationValue != null) {
          $val = $iterationValue;
          $val = array_filter($val);
          $row[] = $val[($i/$size) % count($val)];
          error_log("index = " . ($i/$size) % count($val));
          error_log("value = " . $val[($i/$size) % count($val)]);
          $size *= count($val);
        }
        $matrix[$i] = $row;
        $s = "";
        for ($k = 0; $k < count($row); $k++) {
          $s .= $row[$k] . " ";
        }
        error_log($s);
      }
      return $matrix;
    /*} else { 
      foreach ($rangeParameter as $range) {
	$value = array_filter($range->value());
	$matrix[] = $value;
      }
      $combinations = $this->combineAll($matrix);
      return $combinations;
    }*/
  } 

  function rangeParameterNames() {
    $names = array();
    foreach ($this->rangeParameters as $parameter) {
      $names[] = $parameter->name(); 
    } 
    return $names;
  } 

  function putMultiChannelScriptOn($script, $isOriginal = false) {
    global $useThumbnails;
    $parameterSetting = $this->parameterSetting;
    $numberOfChannels = $parameterSetting->numberOfChannels();
    $files = $this->files();
    $newScript = $script;
    $loadOriginalFile =  $this->loadImageString( $isOriginal );
    $newScript = $newScript . $loadOriginalFile;
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
      // j-) removed + 1
      $newScript = $newScript . $this->putScriptOnForChannel('', $channel);
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
    $fileFormat = $param->extension( );
    if ($multichanneloutput->value()) {
        $newScript = $newScript . "# join\n";
        for($channel=0; $channel < $numberOfChannels; $channel++) {
            $newScript = $newScript . "set imageName$channel [ img open \"".$dst_files[$channel]. "." . $fileFormat ."\"]\n";
            if ($channel > 1) {
                $newScript = $newScript . "c join \$imageName$channel -> c\n";
            } else if ($channel == 1) {
                $newScript = $newScript . "\$imageName0 join \$imageName1 -> c\n";
            }
        }
        $tmpfilename = $this->id() . $fileBase; // . "Ch_all";
        // avoid redundant slashes in path
        $this->files = array($this->relativeSourcePath() . $tmpfilename . "." . $fileFormat );
        $dstfilename = $this->destinationImageFullName() ;
        // If the user chose CoverslipRelativePosition to be 'farthest', we z-mirror the dataset back
        $coverslipParameter=$parameterSetting->parameter('CoverslipRelativePosition');
        $coverslipParameterValue=$coverslipParameter->value();
        if ( $coverslipParameterValue == 'farthest' ) {
          $newScript = $newScript . 'catch { c mir z }' . "\n";
        }
        // Save
        switch ($type) {
          case "tiff":
          case "tiff16":
            $newScript = $newScript . "c save \"$dstfilename.$fileFormat\" -type $type -tiffMultiDir\n";
            break;
          default:
            $newScript = $newScript . "c save \"$dstfilename.$fileFormat\" -type $type\n";
        }

        if ( $useThumbnails ) {
            $newScript = $newScript . 
'catch { 
    # Previews: Huygens Core 3.3.1 required for this to work.
    set path "'.$dstfilename.".".$fileFormat.'"
    set ddir [file dirname $path]
    set fn [file tail $path]
    ::WebTools::savePreview c $ddir $fn {preview 400}
    '."\n";
             // Save also a preview of the original image, adopting the same
             // parameters as in the result.
            $newScript = $newScript . "    ".
            'set orig [eval img open \"$originalFile\" $originalOptions]'."\n";

            $newScript = $newScript . "    ". 'c adopt -> $orig' . "\n"; 

            $newScript = $newScript . '
    set path "'.$dstfilename.".".$fileFormat.".original".'"
    set ddir [file dirname $path]
    set fn [file tail $path]
    ::WebTools::savePreview $orig $ddir $fn {preview 400}
    # Save preview of the original also in source directory
    set ddir [file dirname $hrmFileName]
    set fn [file tail $hrmFileName]
    ::WebTools::savePreview $orig $ddir $fn {preview}'.
    "\n" ;
            $newScript = $newScript . '$orig del' . "\n"; 
            $newScript = $newScript . '}'."\n";

        }
        // Delete temporaty files
        foreach ($dst_files as $dst_file) {
          if ( $type == "ics" ) {
            $newScript .= "catch { exec rm -f \"" . $dst_file . ".ics\" }\n";
            $newScript .= "catch { exec rm -f \"" . $dst_file . ".ids\" }\n";
          }
          else {
            $newScript .= "catch { exec rm -f \"" . $dst_file . "." . $fileFormat ."\" }\n";
          }
        }
        // Save processing history
        // j-)
        $newScript = $newScript . "\n" . 'catch { c history -format txt -save "' . $imageFileName. '_history.txt" }' . "\n";
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
    for ($i = 0; $i < $parameterSetting->numberOfChannels(); $i++) {
      $originalExcitationWavelengthValue[$i] = $val[$i];
      //error_log("GENERATING SCRIPT (JobDescription): excitation wavelength -> ".$originalExcitationWavelengthValue[$i]." [".$i."]");
    }
    $val = $emissionWavelength->value();
    $originalEmissionWavelengthValue = array();
    for ($i = 0; $i < $parameterSetting->numberOfChannels(); $i++) {
      $originalEmissionWavelengthValue[$i] = $val[$i];
      //error_log("GENERATING SCRIPT (JobDescription): emission wavelength -> ".$originalEmissionWavelengthValue[$i]." [".$i."]");
    }
    //error_log("GENERATING SCRIPT (JobDescription): channel -> ".$channel);
    // save original pinhole radii
    $pinholeSize = $parameterSetting->parameter('PinholeSize');
    $val = $pinholeSize->value();
    $originalPinholeSizeValue = array();
    for ($i = 0; $i < $parameterSetting->numberOfChannels(); $i++) {
      $originalPinholeSizeValue[$i] = $val[$i];
    }
    // save original measured PSF (file names)
    if ($psfParam->value() == "measured") {
      $psf = $parameterSetting->parameter('PSF');
      $val = $psf->value();
      $originalPSFValue = array();
      for ($i = 0; $i < $parameterSetting->numberOfChannels(); $i++) {
        $originalPSFValue[$i] = $val[$i];
      }
    }
    // make single channel setting for current channel
    $isMultiChannel = $parameterSetting->parameter('IsMultiChannel');
    $isMultiChannel->setValue('False');
    $parameterSetting->set($isMultiChannel);
    $newExcitationWavelength = $parameterSetting->parameter('ExcitationWavelength');
    $value = $newExcitationWavelength->value();
    //error_log("GENERATING SCRIPT (JobDescription): excitation wavelength (before trimming) -> ".$value[$channel]);
    $newExcitationWavelength->setValue($value[$channel]);
    $val = $newExcitationWavelength->value();
    //error_log("GENERATING SCRIPT (JobDescription): excitation wavelength (after trimming) -> ".$val[0]);
    $parameterSetting->set($newExcitationWavelength);
    $newEmissionWavelength = $parameterSetting->parameter('EmissionWavelength');    
    $value = $newEmissionWavelength->value();
    //error_log("GENERATING SCRIPT (JobDescription): emission wavelength (before trimming) -> ".$value[$channel]);
    $newEmissionWavelength->setValue($value[$channel]);
    $val = $newEmissionWavelength->value();
    //error_log("GENERATING SCRIPT (JobDescription): emission wavelength (after trimming) -> ".$val[0]);
    $parameterSetting->set($newEmissionWavelength);
    $newPinholeSize = $parameterSetting->parameter('PinholeSize');
    $value = $newPinholeSize->value();
    $newPinholeSize->setValue($value[$channel]);
    $parameterSetting->set($newPinholeSize);
    if ($psfParam->value() == "measured") {
      $newPSF = $parameterSetting->parameter('PSF');
      $value = $newPSF->value();
      $newPSF->setValue($value[$channel]);
      $parameterSetting->set($newPSF);
    }
    // ... and for task setting
    // save original RemoveBackgroundPercent and BackgroundOffsetPercent
    //WARNING do not confuse REMOVEBackgroundPercent and BackgroundOffsetPercent
    $taskSetting = $this->taskSetting;
    $numberOfChannels = $taskSetting->numberOfChannels();
    $removeBackgroundPercent = $taskSetting->parameter('RemoveBackgroundPercent');
    $val = $removeBackgroundPercent->internalValue();
    $originalRemoveBackgroundPercentValue = array();
    //TODO remove RemoveBackgroundPercent
    for ($i = 0; $i < $numberOfChannels; $i++) {
      $originalRemoveBackgroundPercentValue[$i] = $val[$i];
    }
    //error_log("GENERATING SCRIPT (JobDescription): remove background percent -> ".$originalRemoveBackgroundPercentValue[$i]." [".$i."]");
    $backgroundOffsetPercent = $taskSetting->parameter('BackgroundOffsetPercent');
    //$val = $backgroundOffsetPercent->internalValue();
    //$originalBackgroundOffsetPercentValue = array();
    $originalBackgroundOffsetPercentValue = $backgroundOffsetPercent->internalValue();
    //for ($i = 0; $i < $numberOfChannels; $i++) {
    //  $originalBackgroundOffsetPercentValue[] = $val[$i];
    //}
    // make single channel setting for current channel
    $newRemoveBackgroundPercent = $taskSetting->parameter('RemoveBackgroundPercent');
    // DEBUG
    //$value = $newRemoveBackgroundPercent->value();
    $newRemoveBackgroundPercent->setValue($originalRemoveBackgroundPercentValue[$channel]);
    $taskSetting->set($newRemoveBackgroundPercent);
    // Backgriund Offset Percent
    $newBackgroundOffsetPercent = $taskSetting->parameter('BackgroundOffsetPercent');
    // DEBUG
    //$value = $newBackgroundOffsetPercent->value();
    if ($originalBackgroundOffsetPercentValue[0] == "auto" || $originalBackgroundOffsetPercentValue[0] == "object") {
      $newBackgroundOffsetPercent->setValue($originalBackgroundOffsetPercentValue[0]);
      $test = $newBackgroundOffsetPercent->internalValue();
      error_log("auto/object ".$test[0]);
    }
    else {
      $newBackgroundOffsetPercent->setValue($originalBackgroundOffsetPercentValue[$channel]);
    }
    $taskSetting->set($newBackgroundOffsetPercent);
    // Signal to noise ratio
    $newSignalNoiseRatio = $taskSetting->parameter('SignalNoiseRatio');
    $originalSignalNoiseRatioValue = $newSignalNoiseRatio->value();
    $newSignalNoiseRatio->setValue($originalSignalNoiseRatioValue[$channel]);
    $taskSetting->set($newSignalNoiseRatio);
    //
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
      $psf->setValue( $originalPSFValue );
      $parameterSetting->set($psf);
    }
    $this->setParameterSetting($parameterSetting);
    // ... and for task setting
    $taskSetting = $this->taskSetting;
    $newSignalNoiseRatio->setValue( $originalSignalNoiseRatioValue );
    $taskSetting->set($newSignalNoiseRatio);
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

  function putScriptOn($script, $isOriginal = false ) {
    global $resultImagesOwnedByUser;
    global $huygens_group;
    
    $newScript = $script;
    $newScript = $newScript . "if {![img exists a]} {img create a -logEnable} else { a keepLog }\n";
    $newScript = $newScript . "if {![img exists b]} {img create b -logEnable} else { b keepLog }\n";
    $newScript = $newScript . "if {![img exists c]} {img create c -logEnable} else { c keepLog }\n";
    $newScript = $newScript . "if {![img exists psf]} {img create psf}\n";
    if ($this->mustReplaceSourceName()) {     // if it is not 3D && is not time serie && is Tif && ends with number     
      $newScript = $newScript . "exec cp " . '"' . $this->sourceImageName() . '" "' . $this->newNameForSingleTif() . '"' . "\n";
    }  
    // loading and converting
    $newScript = $newScript . $this->loadImageString( $isOriginal );
    $parameterSetting = $this->parameterSetting;
    // This test is PARTIALLY overlapping with the test a few lines below
    // TODO Refactor
    if ( $parameterSetting->isTimeSeries() && !$parameterSetting->isThreeDimensional() )
	$newScript = $newScript . 'catch {$imageName convertZ2T}' . "\n";

    $taskSetting = $this->taskSetting;
    $numberOfChannels = $parameterSetting->numberOfChannels();
    $taskSetting->setNumberOfChannels($numberOfChannels);

    // manage measured PSF
    $psfParam = $parameterSetting->parameter('PointSpreadFunction');
    if ($psfParam->value() == "measured") {
      $param = $parameterSetting->parameter('PSF');
      $value = $param->value();
      if ( is_array( $value ) ) {
        // This is the case if the number of channel is == 1
        $filename = $this->sourceFolder() . $value[0];
      } else {
        // This is the case if the number of channel is > 1
        $filename = $this->sourceFolder() . $value;
      }
      $newScript = $newScript . "set psf [img open \"".$filename."\"]\n";
    }
    else if ($psfParam->value() == "theoretical") {
      $newScript = $parameterSetting->putScriptOn($newScript);
    }
    
    $z2t = False;
    if (!$parameterSetting->isThreeDimensional() && $parameterSetting->isTimeSeries() && !$parameterSetting->isTif())
      $z2t = True;
    if ($taskSetting->isStepProcessing() && !$parameterSetting->isTimeSeries())
      $z2t = True;
    if ($z2t) {
      $newScript = $newScript . 'catch {$imageName convertZ2T}' . "\n";
    }
    
    // We need to pass the the position of the coverslip selected by the user as
    // an input parameter to JobTaskSetting::putScriptOn( )
    $coverslipParameter=$parameterSetting->parameter('CoverslipRelativePosition');
    $coverslipParameterValue=$coverslipParameter->value();
    // manage measured PSF
    if ($psfParam->value() == "theoretical") {
      // operations
      $newScript = $taskSetting->putScriptOn($newScript, $coverslipParameterValue);
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
    $newScript = $newScript . $this->saveImageString( $isOriginal );
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
    $script = $script . 'c cmle psf -> c -sn {80} -it 7 -bgMode object -bg {-100} -blMode auto -q 0 -mode fast -pad fullpadpar' . "\n";
    $script = $script . $this->saveImageString();
    $this->pass = 1;
    return $script;
  } 

  function loadImageString( $isOriginal = false ) {
    global $useThumbnail;
    if ($this->mustReplaceSourceName()) { 
      $imageFileName = $this->newNameForSingleTif();
    } else { 
      $imageFileName = $this->sourceImageName();
    }
    // TODO we should provide more convenience methods in ParameterSettings
    $options = "";
    if (stristr($imageFileName, ".stk")) {
      // Metamorph: if not time series
      if (!$this->parameterSetting->isTimeSeries()) {
        $options .= " -series off";
      }
    }
    $formatParam = $this->parameterSetting->parameter('ImageFileFormat');
    $format = $formatParam->value();
    if ($format == "tiff" || $format == "tiff-single") {
      // Olympus FluoView, or single XY plane: always
      $options .= " -series off";
    }
    if ( preg_match("/^(.*\.lif)\s\((.*)\)/i", $imageFileName, $match) ) {
        // If a (string) comes after the file name, it is interpreted as a sub
        // image. Currently this is for LIF files only.
        $huygensFileName = $match[1];
        $options .= " -subImage \"$match[2]\"";
    } else {
        $huygensFileName = $imageFileName;
    }
    // Turn on history
    $options .= " -logEnable";

    // If the user specified that the coverslip is 'farthest' we need to mirror
    // the image. But this must be done only when the original dataset is
    // loaded, not when the splitted channels are loaded. Therefore, by default
    // this function does check for mirroring only if its input parameter
    // $isOriginal is true, and this occurs only when this function is called
    // from Job::createScript( ).
    // We use the same hack to keep the name of the original image, in order
    // to generate its preview later.
    $mirror = "";
    $thumbn = "";
    if ( $isOriginal == true ) {
      $coverslipParameter=$this->parameterSetting->parameter('CoverslipRelativePosition');
      $coverslipParameterValue=$coverslipParameter->value();
      if ( $coverslipParameterValue == 'farthest' ) {
        $mirror = 'catch { $imageName mir z }' . "\n";
      }
      $thumbn = "\nset originalFile \"$huygensFileName\"".
                "\nset hrmFileName \"$imageFileName\"".
                "\nset originalOptions [list $options]\n";
    }
    return 'set imageName [img open "' . $huygensFileName . '"' . $options . ']' . "\n" . '$imageName lundo -off' . "\n" . $mirror. $thumbn;
  }

  // TODO refactor this part
  function saveImageString( $isResult = false ) {
    global $resultImagesOwnedByUser;
    global $useThumbnails;
    $result = '';
    $setting = $this->taskSetting;
    $parameterSetting = $this->parameterSetting;
    $param = $setting->parameter('OutputFileFormat');
    $type = $param->translatedValue();
    $extension = $param->extension( );
    if ($type=='ics') {
       $result = $result . "c convert -type auto\n";
    }
    $imageFileName = $this->destinationImageFullName();
//    if ($type== 'tiff' || $type == 'tiff16') {
//    	$imageFileName = $imageFileName . '.tif';
//    }
    $result = $result . "c save " . '"' . $imageFileName . "." . $extension .'"' . " -type $type";

    if ($type == 'tiff' || $type == 'tiff16') {
        $result = $result . " -tiffMultiDir";
    }

    if ( $isResult ) {
        // Save preview.
        if ( $useThumbnails ) {
            $newScript = "\n".
'catch { 
    # Previews: Huygens Core 3.3.1 required for this to work.
    set path "'.$imageFileName.".".$extension.'"
    set ddir [file dirname $path]
    set fn [file tail $path]
    ::WebTools::savePreview c $ddir $fn {preview 400}
    '."\n";
             // Save also a preview of the original image, adopting the same
             // parameters as in the result.
            $newScript = $newScript . "    ".
            'set orig [eval img open \"$originalFile\" $originalOptions]'."\n";

            $newScript = $newScript . "    ". 'c adopt -> $orig' . "\n"; 

            $newScript = $newScript . '
    set path "'.$imageFileName.".".$extension.".original".'"
    set ddir [file dirname $path]
    set fn [file tail $path]
    ::WebTools::savePreview $orig $ddir $fn {preview 400}
    # Save preview of the original also in source directory
    set ddir [file dirname $hrmFileName]
    set fn [file tail $hrmFileName]
    ::WebTools::savePreview $orig $ddir $fn {preview}'.
    "\n" ;
            $newScript = $newScript . '$orig del' . "\n"; 
            $newScript = $newScript . '}'."\n";
            $result = $result . $newScript;

        }


    }
    // Save processing history
    // $result = $result . "\n" . 'c history -format txt -save "' . $imageFileName. '_history.txt"';
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

  function sourceImageNameWithoutPath() {
    $name = $this->sourceImageName();
    $pos = strrpos( $name, '/' );
    if ( $pos ) {
      return ( substr( $name, ( $pos + 1 ) ) );
    } else {
      return $name;
    }
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
    $parameterSetting = $this->parameterSetting;
    $parameter = $parameterSetting->parameter('ImageFileFormat');
	$fileFormat = $parameter->value();
    if ( strcasecmp( $fileFormat, 'lif' ) == 0 ) {
      if ( preg_match("/^(.*)\.lif\s\((.*)\)/i", $inputFile[0], $match) ) {
        $inputFile = $match[ 1 ] . '_' . $match[ 2 ];
      } else {
        $inputFile = substr(end($inputFile), 0, strrpos(end($inputFile), ".")); }
    } else {
      $inputFile = substr(end($inputFile), 0, strrpos(end($inputFile), "."));
    }
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

  function destinationImageNameWithoutPath() {
    $name = $this->destinationImageName();
    $pos = strrpos( $name, '/' );
    if ( $pos ) {
      $name = substr( $name, ( $pos + 1 ) );
    }
    // Append extension
    $taskSetting = $this->taskSetting();
    $param = $taskSetting->parameter('OutputFileFormat');
    $fileFormat = $param->extension( );
    return ( $name . "." . $fileFormat );
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
    foreach($ids as $id) {  // loop through all the jobs selected, which have to be deleted
      $row = $db->getQueueContentsForId($id);
      if ($row['status'] == 'started') {  // if the job is started, kill the process and update the databese (job_queue and server)  
        $pid = $row['process_info'];
        $server = $row['server'];
        $proc = $this->newExternalProcess($server, $server . "_out.txt", $server . "_error.txt");
        $proc->killHucoreProcess($pid);
        $result = $result && $db->markJobAsRemoved($id);
        $result = $result && $db->markServerAsFree($server);
      }
      else {  // if the job is not started, update the database (job_queue)
        $result = $result && $db->markJobAsRemoved($id);   
      }
    } 
    return $result;
  }
  
  function newExternalProcess($host, $logfilename, $errfilename) {
	global $imageProcessingIsOnQueueManager;
	$db = new DatabaseConnection();
	$huscript_path = $db->huscriptPathOn($host);
	if ($imageProcessingIsOnQueueManager)
		$shell = new LocalExternalProcess($host, $huscript_path, $logfilename, $errfilename);
	else
		$shell = new ExternalProcess($host, $huscript_path, $logfilename, $errfilename);
	return $shell;
  } 

  function removeMarkedJobs() {
    $db = new DatabaseConnection();
    $ids = $db->getMarkedJobIds();
    foreach ($ids as $id) {
      $this->removeJobWithId($id);
    }
    if ($ids != null && count($ids) > 0)
      return True;
    return False;
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

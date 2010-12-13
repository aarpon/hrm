<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("Parameter.inc");
require_once("Database.inc");
require_once("User.inc");
require_once("Shell.inc");
require_once("hrm_config.inc");
require_once("Job.inc");
require_once("JobQueue.inc");

/*!
  \class  JobDescription
  \brief  Collects all information for a deconvolution Job to be created
  
  Description of the job to be processed by HuCore Consisting of owner 
  information, a parameter setting, a task setting and a list of image files.
*/
class JobDescription {
  
  /*!
    \var    $id
    \brief  A unique id identifying the job
  */
  private $id;
  
  /*!
    \var    $parameterSetting
    \brief  The Job's ParameterSetting
  */
  public $parameterSetting;

  /*!
    \var    $taskSetting
    \brief  The Job's TaskSetting
  */
  public $taskSetting;
  
  /*!
    \var    $files
    \brief  The list of files to be processed by the Job
  */
  private $files;

  /*!
    \var    $owner
    \brief  The user who created the Job 
  */
  private $owner;               // owner            User              

  /*!
    \var    $message
    \brief  The last error message
  */
  private $message;
  
  /*!
    \var    $pass (integer)
    \brief  Pass 1 or 2 of step combined processing
    \todo   Check: this is not used any more!
  */
  private $pass;
  
  /*!
    \var    $group
    \brief  Name of the group to be used
  */
  private $group;
  
  //public $rangeParameters;     // why not use a global variable from the beginning?!

  /*!
    \brief Constructor
  */
  public function __construct() {
    $this->id = (string)(uniqid(''));
    $this->message = "";
    $this->pass = 1;
  } 

  /*!
    \brief Returns last error message
    \return last error message
  */
  public function message() {
    return $this->message;
  } 

  /*!
    \brief Returns the unique id that identifies the Job
    \return unique id
  */
  public function id() {
    return $this->id;
  }

  /*!
    \brief Sets the (unique) id of the Job
    \param  $id Unique id
  */
  public function setId($id) {
    $this->id = $id;
  } 

  /*!
    \brief Returns the name of owner of the job
    \return name of the owner
  */
  public function owner() {
    return $this->owner;
  }

  /*!
    \brief Sets the owner of the Job
    \param  $owner Name of the owner of the Job
  */
  public function setOwner($owner) {
    $this->owner = $owner;
  } 

  /*!
    \brief Returns the ParameterSetting associated with the job
    \return a ParameterSetting object
  */
  public function parameterSetting() {
    return $this->parameterSetting;
  }  

  /*!
    \brief Returns the TaskSetting associated with the job
    \return a TaskSetting object
  */
  public function taskSetting() {
    return $this->taskSetting;
  }
  
  /*!
    \brief Returns the files associated with the job
    \return array of file names
  */
  public function files() {
    return $this->files;
  } 

  /*!
    \brief Sets the ParameterSetting for the job
    \param $setting A ParameterSetting object
  */
  public function setParameterSetting( ParameterSetting $setting) {
    $this->parameterSetting = $setting;
    $this->owner = $setting->owner();
  } 

  /*!
    \brief Sets the TaskSetting for the job
    \param $setting A TaskSetting object
  */
  public function setTaskSetting( TaskSetting $setting) {
    $this->taskSetting = $setting;
  }
  
  /*!
    \brief Sets the list of files for the job
    \param $files Array of file names
  */
  public function setFiles($files) {
    $this->files = $files;
  } 

  /*!
    \brief Returns the group of the user associated with the job
    \return group of the user
  */
  public function group() {
    return $this->group;
  }
  
  /*!
    \brief Sets the group of the user associated with the job
    \param  $group  Group of the user
  */
  public function setGroup($group) {
    $this->group = $group;
  }

  /*!
    \brief Add a Job to the queue
    \return true if the Job could be added to the queue, false otherwise
  */
  public function addJob() {
    // =========================================================================
    //
    // In previous versions of the HRM, the web interface would create compound
    // jobs that the queue manager would then process. Now, this task has become
    // responsibility of the web interface.
    //
    // =========================================================================

    $result = True;

    $lqueue = new JobQueue();
    $lqueue->lock();

    // createJob() function was originally called directly
    $result = $result && $this->createJob();

    if ( $result ) {

      // Process compound jobs
      $this->processCompoundJobs( );

      // Assign priorities
      $db = new DatabaseConnection();
      $result = $db->setJobPriorities();
      if ( !$result ) {
        error_log( "Could not set job priorities!" );
      }
    }

    $lqueue->unlock();

    return $result;
  }
     
  /*!
    \brief Create a Job from this JobDescription
    \return true if the Job could be created, false otherwise
  */
  public function createJob() {
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
    $queue = new JobQueue();
    $result = $result && $queue->queueJob($this);
    if (!$result) {
      $this->message = "create job - database error!";
    } 
    return $result;
  }
  
  /*!
    \brief Processes compound Jobs to deliver elementary Jobs
    
    A compound job contains multiple files.
  */
  public function processCompoundJobs() {
    $queue = new JobQueue();
    $compoundJobs = $queue->getCompoundJobs();
    foreach ($compoundJobs as $jobDescription) {
      $job = new Job($jobDescription);
      $job->createSubJobsOrScript();
    }
  }
  
  /*!
    \brief Loads a JobDescription from the database for the user set in
          this JobDescription
  */
  public function load() {
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
  }

  /*!
    \brief Copies from another JobDescription into this JobDescription
    \param  $aJobDescription  Another JobDescription
  */
  public function copyFrom( JobDescription $aJobDescription ) {
    $this->setParameterSetting($aJobDescription->parameterSetting());
    $this->setTaskSetting($aJobDescription->taskSetting());
    $this->setOwner($aJobDescription->owner());
    $this->setGroup($aJobDescription->group());
  } 

  /*!
    \brief Checks whether the JobDescription describes a compound Job
    \return true if the Job is compound (i.e. contains more than one file),
    false otherwise
  */
  public function isCompound() { 
    if (count($this->files)>1) {
      return True;
    } 
    return False;
  } 

  /*!
    \brief Create elementare Jobs from compound Jobs
    \return true if elementary Jobs could be created, false otherwise
  */
  public function createSubJobs() {
    $parameterSetting = $this->parameterSetting;
    $numberOfChannels = $parameterSetting->numberOfChannels();
    return $this->createSubJobsforFiles();
  } 

  /*!
    \brief Add script for multi-channel images
    \param  $script Current script
    \param  $isOriginal This is a flag to make sure that the function loadImage()
            knows whether it is called from createScript() -- and in this case
            $isOriginal is true, or other functions -- in which case $isOriginal
            is false
    \return updated script
    \todo This function is obsolete and will be replace by the new Tcl templates
  */
  public function putMultiChannelScriptOn($script, $isOriginal = false) {
    global $useThumbnails;
    global $movieMaxSize, $saveSfpPreviews, $maxComparisonSize;
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
            if ($channel != 0) {
                $newScript = $newScript . "if { ! [";
            }
            $newScript = $newScript . "catch { img open \"".$dst_files[$channel]. "." . $fileFormat ."\"} imageName$channel ";
            if ($channel != 0) {
                $newScript = $newScript . "] } {\n";
            } else {
                $newScript = $newScript . "\n";
            }
            if ($channel > 1) {
                $newScript = $newScript . "    catch {c join \$imageName$channel -> c}\n}\n";
            } else if ($channel == 1) {
                $newScript = $newScript . "    catch {\$imageName0 join \$imageName1 -> c}\n}\n";
            }
        }
        $tmpfilename = $this->id() . $fileBase; // . "Ch_all";
        // avoid redundant slashes in path
        $this->files = array($this->relativeSourcePath() . $tmpfilename . "." . $fileFormat );
        $dstfilename = $this->destinationImageFullName();
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
            $newScript = $newScript . "c save \"$dstfilename.$fileFormat\" -type $type -tiffMultiDir -cmode scale\n";
            break;
          default:
            $newScript = $newScript . "c save \"$dstfilename.$fileFormat\" -type $type\n";
        }

        $newScript = $newScript . "\ncatch { exec chmod 777 \"$dstfilename.$fileFormat\"}";
        
        if ( $useThumbnails ) {
            $newScript = $newScript . 
'catch { 
    # Previews: Huygens Core 3.3.1 required for this to work.
    set path "'.$dstfilename.".".$fileFormat.'"
    set ddir [file dirname $path]
    set fn [file tail $path]
    catch { ::WebTools::savePreview c $ddir/hrm_previews $fn {preview 400} }
    '."\n";
            if ( $movieMaxSize > 0 ) {
                $newScript = $newScript . 
                '    catch {::WebTools::saveStackMovie c $ddir/hrm_previews ${fn}.stack '.
                $movieMaxSize . '}'.  "\n".
                '    catch {::WebTools::saveTimeSeriesMovie c $ddir/hrm_previews'.
                ' ${fn}.tSeries '.$movieMaxSize . '}'.  "\n\n";

            }
            if ( $saveSfpPreviews ) {
                $newScript = $newScript . 
                '    catch {::WebTools::saveTopViewSfp c $ddir/hrm_previews ${fn}.sfp } '.
                 "\n";
                if ( $movieMaxSize > 0 ) {
                    $newScript = $newScript . 
                '    catch {::WebTools::saveTimeSeriesMovie c $ddir/hrm_previews '
                . '${fn}.tSeries.sfp - SFP } '.  "\n";
                }
            }
             // Save also a preview of the original image, adopting the same
             // parameters as in the result.
            $newScript = $newScript . "    ".
            'set orig [eval img open \"$originalFile\" $originalOptions]'."\n";

            $newScript = $newScript . "    ". 'c adopt -> $orig' . "\n"; 

            $newScript = $newScript . '
    set path "'.$dstfilename.".".$fileFormat.".original".'"
    set ddir [file dirname $path]
    set ofn [file tail $path]
    catch { ::WebTools::savePreview $orig $ddir/hrm_previews $ofn {preview 400} }'."\n";

    if ($maxComparisonSize > 0 ) {
        // Since Huygens 3.5.1p3, stacks and time series can also be
        // saved in jpeg strips that can be viewed online with CSS
        // tricks.
            $newScript .= 'catch {
                ::WebTools::combineStrips [list $orig c] stack $ddir/hrm_previews ${fn} '. $maxComparisonSize .' auto 
                ::WebTools::combineStrips [list $orig c] tSeries $ddir/hrm_previews ${fn} '. $maxComparisonSize .' auto 
   }

                ';
    }

            if ( $saveSfpPreviews ) {
                $newScript = $newScript . 
                '    catch {::WebTools::saveTopViewSfp $orig $ddir/hrm_previews ${ofn}.sfp }'.
                 "\n";
            }
            $newScript = $newScript . '
    # Save preview of the original also in source directory
    set ddir [file dirname $hrmFileName]
    set fn [file tail $hrmFileName]
    catch { ::WebTools::savePreview $orig $ddir/hrm_previews $fn preview }'.
    "\n" ;
            $newScript = $newScript . '    $orig del' . "\n"; 
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
        $newScript = $newScript . "\n" . 'catch { c history -details -format txt -save "' . $dstfilename. '.history.txt" }' . "\n";
    }
    $this->files = $files;
    return $newScript;
  }

  /*!
    \brief  Pass parameters to and return the specialized Tcl template
    \return Tcl template
  */
  public function appendScriptParameters() {
      global $resultImagesOwnedByUser, $huygens_group ;
      global $useThumbnails;
      global $movieMaxSize, $saveSfpPreviews, $maxComparisonSize;

      $micr = $this->parameterSetting;
      $decon = $this->taskSetting;

      $data['jobID'] = $this->id;

      // File parameters -------------------------------------------------

      // The file may be in a subdirectory of the source folder.
      $imageFileName = $this->sourceImageName();
      $dirname = dirname($imageFileName);
      $basename = basename($imageFileName);

      $data['inputDir'] = "\"$dirname\"";
      $data['inputFile'] = "\"$basename\"";
      // But reporting goes always to the root of the source folder.
      $data['reportDir'] = "\"".$this->sourceFolder()."\"";

      $dstFileName = $this->destinationImageFullName();
      $dirname = dirname($dstFileName);
      $basename = basename($dstFileName);

      $oFileFormat = $decon->parameter('OutputFileFormat');
      $type = $oFileFormat->translatedValue();
      $extension = $oFileFormat->extension( );

      $data['outputDir'] = "\"$dirname\"";
      $data['outputFile'] = "\"$basename.$extension\"";
      $data['outputType'] = $type;

      $data['seriesOption'] = "auto";

      $formatParam = $micr->parameter('ImageFileFormat');
      $format = $formatParam->value();

      if ($format == "tiff" || $format == "tiff-single") {
          // Olympus FluoView, or single XY plane: always
          $data['seriesOption'] = "off";
      }

      $data['isTimeSeries'] = 1;

      if (!$micr->isTimeSeries()) {
          $data['isTimeSeries'] = 0;
          if ($format == "stk") {
              // Metamorph: if not time series
              $data['seriesOption'] = "off";
          }
      }

      $data['isThreeDimensional'] = 0;
      if ($micr->isThreeDimensional()) {
          $data['isThreeDimensional'] = 1;
      }


      // Microscopic parameters ------------------------------------------

      // By now, force parameters from template. In the future, we can allow
      // users to use some (those with value '-') or all parameters from each
      // file metadata. For the latter, we can simply set this to 'metadata';
      // TODO GUI 1.3
      $data['parametersFrom'] = "template";


      // Right now, microscope type is in the HRM a single value, but Huygens
      // prefers since version 3.5 to have one value per channel.
      $data['micr'] = 
          $this->getParamAsChannelTclList($micr, 'MicroscopeType', true);

      $data['dx'] = $micr->sampleSizeX();
      $data['dy'] = $micr->sampleSizeY();
      $data['dz'] = $micr->sampleSizeZ();
      $data['dt'] = $micr->sampleSizeT();

      // Right now, NA is in the HRM a single value, but Huygens prefers since
      // version 3.5 to have one value per channel. The same for the r.i.
      $data['na'] = $this->getParamAsChannelTclList($micr, 'NumericalAperture');

      $data['ex'] = $this->getParamAsTclList($micr, 'ExcitationWavelength');
      $data['em'] = $this->getParamAsTclList($micr, 'EmissionWavelength');

      $data['pr'] = $this->getParamAsTclList($micr, 'PinholeSize');

      if ( $data['micr'] != "nipkow" ) {
          $data['ps'] = $this->getEmptyParameterList($micr);
      } else {
          $data['ps'] = $this->getParamAsTclList($micr, 'PinholeSpacing');
      }


      // Offsets a currently not used by HRM, but may be useful in the future
      // for sticthing applications.
      $data['offX'] = 0;
      $data['offY'] = 0;
      $data['offZ'] = 0;
      $data['offT'] = 0;

      // Excitation beam fill is an important parameter that affects the
      // effective N.A., but most people don't know it. Leave it in a
      // reasonable default value by now.
      // TODO GUI
      $data['exBeamFill'] = 2.0;

      // Leave objective quality as 'perfect' for backwards compatibility, but
      // in fact something else would produce better results, as most
      // objectives very rarely are ideal.

      // Notice that if this is not perfect, the orientation of the sample
      // matters a lot, even if we deactivate S.A. correction: the PSF is not
      // symmetrical anymore for imperfect objectives.
      // TODO GUI
      $data['objQuality'] = "perfect";

      $data['ril'] =
          $this->getParamAsChannelTclList($micr, 'ObjectiveType', true);
      $data['ri'] =
          $this->getParamAsChannelTclList($micr, 'SampleMedium', true);

      // Number of photons could very well be a multichannel parameter!
      // Currently, HRM treats the two photon as a microscope type, not as a
      // channel parameter. You can make this parameter just a Tcl list to make
      // it multiphoton.
      if ($micr->isTwoPhoton()) {
          $data['pcnt'] = 2;
      } else {
          $data['pcnt'] = 1;
      }




      // Restoration parameters ------------------------------------------

      // Decide what to do with multichannel images: all in a go (all), or
      // split and one by one (split).
      $data['channelProcessing'] = "split";

      $data['method'] = $decon->parameter('DeconvolutionAlgorithm')->value();
      $data['sn'] = $this->getParamAsTclList($decon, 'SignalNoiseRatio');


      if ( $data['method'] == "qmle" ) {
          // For QMLE, the SNR values are given by keywords: replace them.
          // No automatic estimation in this case by now.
          $indexValues = array  (1, 2, 3, 4, 5);
          $snrValues = array  ("low", "fair", "good", "inf", "auto");
          $data['sn'] = str_replace($indexValues, $snrValues, $data['sn']);

          // In the QMLE, there's no q parameter, but iteration mode.
          $data['q'] = "-";
          // Currently, the mode is automatic, so the iteration limit is
          // disregarded.
          $data['itMode'] = "auto";
          $data['it'] = "10";
      } else {
          $data['itMode'] = "-";
          $data['it'] = $this->getParamAsTclList($decon, 'NumberOfIterations');
          $data['q'] =
              $decon->parameter("QualityChangeStoppingCriterion")->value();
      }

      // Background level
      $bgParam = $decon->parameter("BackgroundOffsetPercent");
      $bgValue = $bgParam->value();
      $internalValue = $bgParam->internalValue();
      if ($bgValue[0] == "auto" || $internalValue[0] == "auto") {
          $data['bgMode'] = "auto";
          $data['bg'] = 0;
      } else if ($value[0] == "object" || $internalValue[0] == "object") {
          $data['bgMode'] = "object";
          $data['bg'] = 0;
      } else {
          $data['bgMode'] = "manual";
          $data['bg'] = $this->getParamAsTclList($decon, 'BackgroundOffsetPercent');
      }

      // Bleaching correction.
      // TODO GUI
      $data['blMode'] = "auto";

      // Doing the deconvolution with 32-bit signed float images prevents the
      // use of any scaling factor. This is imporant for example ratiometric
      // analysis, but maybe also for consistent between-image comparison.
      // There should be an option in the interface to disable this.
      // TODO GUI 1.3
      $data['forceProcessAsFloat'] = 1;


      // Spherical Aberration (S.A.) correction

      // The various S.A. correction options result in a different value for
      // the -brMode deconvolution flag.
      $constant = false;
      $symmetrical = false;
      $depth = 0;
      $brMode = 'auto';

      $SAcorr = $micr->getAberractionCorrectionParameters();


      if ( $SAcorr[ 'AberrationCorrectionNecessary' ] == 1 ) {
          if ( $SAcorr[ 'PerformAberrationCorrection' ] == 0 ) {
              $brMode = 'one';
              $constant = true;
              $symmetrical = true;
              $coverslip = "(ignore)";
          } else {
              $depth = $SAcorr[ 'PSFGenerationDepth' ];
              if ( $SAcorr[ 'AberrationCorrectionMode' ] == 'automatic' ) {
                  $brMode = 'auto';
                  $coverslip = $SAcorr[ 'PSFGenerationDepth' ];
              } else {
                  if ( $SAcorr[ 'AdvancedCorrectionOptions' ] == 'user' ) {
                      $brMode = 'one';
                      $constant = true;
                      $coverslip = "(ignore)";
                  }
                  if ( $SAcorr[ 'AdvancedCorrectionOptions' ] == 'slice' ) {
                      $brMode = 'sliceBySlice';
                      $coverslip = $SAcorr[ 'PSFGenerationDepth' ];
                  }
                  if ( $SAcorr[ 'AdvancedCorrectionOptions' ] == 'few' ) {
                      $brMode = 'few';
                      $coverslip = $SAcorr[ 'PSFGenerationDepth' ];
                  }
              }
          }
      }

      $data['brMode'] = $brMode;

      // S.A. correction requires knowing what is the position of the
      // coverslip, and the orientation of the dataset. We don't need to mirror
      // the stack, the orientation is an image parameter since Huygens 3.5.

      // TODO GUI 1.3: ask the distance between the coverslip and the begining
      // of the stack; since Huygens 3.5 this is considered for a good S.A.
      // correction. Use the same 'PSFGenerationDepth' parameter here, but
      // notice that in the HRM 1.2 this parameter is only asked when the user
      // goes for a fixed-PSF. We need to know the distance to the coverslip
      // for the space-variant PSF!
      $data['coverslipDistanceFromStack'] = $coverslip;
      // Notice: internally, Huygens uses a parameter 'iFacePrim' that sets the
      // distance in micrometers, measured from the
      // plane Z = 0 of the stack (the bottom one). We will translate the
      // variable to make the interface easier for the user.

      // Some examples:

      // 1) The stack is imaged upward (plane 0 is closest to the coverlip)
      // but the image acquisition started 3 um away from the coverslip (so
      // that the coverslip is not visible in the image, but 3 um below the
      // stack).
      // Here iFacePrim should be 3, and imagingDir = 'upward'. We pass the
      // parameter coverslipDistanceFromStack = 3.

      // 2) The stack is imaged downward (plane 0 is farthest from the
      // coverslip), but the coverslip is 3 microns inside the stack (the
      // imaging started quite above the coverslip).  In this case we pass
      // coverslipDistanceFromStack = -3 (negative, because it is inside the
      // stack) and imagingDir = 'downward'.
      //
      // In this case, to calculate iFacePrim,  we need to know the size of the
      // stack, to set the position of the coverslip relative to plane 0. The
      // backend script will take care of computing iFacePrim correctly in both
      // cases.



      // When using a fixed PSF, the $depth is also the one to set on what
      // position we want to calculate it.
      // This is already asked by the current GUI.
      $data['psfDepth'] = $depth;

      // We don't need to mirror the image, since Huygens 3.5 the stack
      // orientation is an image parameter.
      $coverslipPos = $micr->parameter('CoverslipRelativePosition')->value();
      if ($coverslipPos == 'farthest' ) {
          $data['imagingDir'] = "downward";
      } else {
          $data['imagingDir'] = "upward";
      }

      // Point Spread Function (PSF) -------------------------------------

      $psfParam = $micr->parameter('PointSpreadFunction');
      $psfType = $psfParam->value();
      if ($psfType == "theoretical") {
          if ($symmetrical) {
              // A symmetrical PSF, no S.A. correction.
              $data['psf'] = "theoretical-symmetrical";
          } else {
              if ($constant) {
                  // Partial S.A. correction: asymmetrical PSF at a given
                  // depth.
                  $data['psf'] = "theoretical-fixed";
              } else {
                  // Variant PSF: better S.A. correction.
                  $data['psf'] = "theoretical-variant";
              }
          }
          $data['psfFile'] = "-";
      } else {
          $data['psf'] = "measured";
          // A list of files, one per channel (or just one multichannel image)
          $psfFiles = $micr->parameter('PSF')->value();
          if ( !is_array( $psfFiles ) ) {
              $data['psfFile'] = "\"".  $this->sourceFolder() .$psfFiles. "\"";
          } else {
              $data['psfFile'] = "{";
              foreach ( $psfFiles as $f ) {
                  $data['psfFile'] .= " \"".  $this->sourceFolder() . $f.  "\"";
              }
              $data['psfFile'] .= " }";
          }

      }


      // HRM parameters --------------------------------------------------

      if ($resultImagesOwnedByUser) {
          $data['imagesOwnedBy'] = $this->owner()->name();
      } else {
          $data['imagesOwnedBy'] = "-";
      }
      $data['imagesGroup'] = $huygens_group;

      if ($useThumbnails) {
          $data['useThumbnails'] = 1;
      } else {
          $data['useThumbnails'] = 0;
      }

      if ($saveSfpPreviews) {
          $data['saveSfpPreviews'] = 1;
      } else {
          $data['saveSfpPreviews'] = 0;
      }

      $data['maxComparisonSize'] = $maxComparisonSize;
      $data['movieMaxSize'] = $movieMaxSize;


      // Build settings procedure ----------------------------------------
      // Dump all the $data array in a convenient Tcl form.


      $s = "

proc DefineScriptParameters {} {

    global hrm

    set hrm_list [list \\\n";

    foreach ($data as $key => $val) {
        $s .= "      $key $val \\\n";
    }



       $s .= "]

    array set hrm \$hrm_list

}


# Start the job.
HRMrun deconvolution
";

    return $s;


    }

  /*!
    \brief  Add script for one channel (to current script)
    \param  $script Current script
    \param  $isOriginal This is a flag to make sure that the function loadImage()
            knows whether it is called from createScript() -- and in this case
            $isOriginal is true, or other functions -- in which case $isOriginal
            is false
    \return updated script
  */
  public function putScriptOn($script, $isOriginal = false ) {
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

    // Apply the parameters to the image in all cases:
    $newScript = $parameterSetting->putScriptOn($newScript);

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
      $newScript = $newScript . "set psf [img open \"". $filename.
                    "\" -logEnable]\n";
      $newScript = $newScript . "\$psf -> psf\n";
      $newScript = $newScript . "\$psf del\n";
    }
    
    $z2t = False;
    if (!$parameterSetting->isThreeDimensional() && $parameterSetting->isTimeSeries() && !$parameterSetting->isTif())
      $z2t = True;
    if ($taskSetting->isStepProcessing() && !$parameterSetting->isTimeSeries())
      $z2t = True;
    if ($z2t) {
      $newScript = $newScript . 'catch {$imageName convertZ2T}' . "\n";
    }
    
    // manage measured PSF
    if ($psfParam->value() == "theoretical") {
      // We need to pass the array of parameters for the aberraction correction
      // an input parameter to JobTaskSetting::putScriptOn( )
      $newScript = $taskSetting->putScriptOn($newScript,
          $this->parameterSetting->getAberractionCorrectionParameters( ) );
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
    # Shouldn't we give 666 permissions to the result files, to make sure this
    # works everywhere? Bad solution in general, but might be the most
    # practical thing with the current code for a trusted multiuser
    # environment. /// TODO ///
    $newScript = $newScript . $this->saveImageString( $isOriginal );
    /* TODO This is obsolete code that should be removed! isStepCombinedProcessing( ) always returns false.
    if ($taskSetting->isStepCombinedProcessing()) {
      $newScript = $newScript . $this->passTwoScript();
    }
    */
    if ($this->mustReplaceSourceName()) { 
      $newScript = $newScript . "catch { exec rm -f " . '"' . $this->newNameForSingleTif() . '"' . " }\n";
    }  
    return $newScript;
  }

  /*!
    \brief Returns the full file name without redundant slashes
    \return full file name without redundant slashes
    \todo Isn't this redundant? One could use the FileServer class
  */
  public function sourceImageName() {
    $files = $this->files();
    // avoid redundant slashes in path
    $result = $this->sourceFolder() . ereg_replace("^/", "", end($files));
    return $result;
  }

  /*!
    \brief Returns the file name without path
    \return file name without path
    \todo What about redundant slashes?
  */
  public function sourceImageNameWithoutPath() {
    $name = $this->sourceImageName();
    $pos = strrpos( $name, '/' );
    if ( $pos ) {
      return ( substr( $name, ( $pos + 1 ) ) );
    } else {
      return $name;
    }
  }

  /*!
    \brief Returns relative source path (under the image source path)
    \return relative source path
  */
  public function relativeSourcePath() {
    $files = $this->files();
    $inputFile = end($files);
    $inputFile = explode("/", $inputFile); 
    array_pop($inputFile);
    $path = implode("/", $inputFile);
    // avoid redundant slashes in path
    if (strlen($path) > 0) $path = ereg_replace("([^/])$", "\\1/", $path);
    return $path;
  } 

  /*!
    \brief Returns the file base name with some special handling for Lif files
    \return file base name
  */
  public function sourceImageShortName() {
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

  /*!
    \brief Returns the source folder name
    \return source folder name
  */
  public function sourceFolder() {
    global $huygens_server_image_folder;
    global $image_source;
    $user = $this->owner();
    $result = $huygens_server_image_folder . $user->name() . "/" . $image_source . "/";
    return $result;
  } 


  /*!
    \brief Returns the destination image name without extension and without path
    \return destination image name without extenstion and without path
  */
  public function destinationImageName() {
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

  /*!
    \brief Returns the destination image name without path and with output file format extension
    \return destination image name without path and with output file format extension
  */
  public function destinationImageNameWithoutPath() {
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

  /*!
    \brief Returns the destination image name with selected output extension and relative path
    \return destination image name with selected output extension and relative path
  */
  public function destinationImageNameAndExtension() {
    $name = $this->destinationImageName();
    // Append extension
    $taskSetting = $this->taskSetting();
    $param = $taskSetting->parameter('OutputFileFormat');
    $fileFormat = $param->extension( );
    return ( $this->relativeSourcePath(). $name . "." . $fileFormat );
  }
  
  /*!
    \brief Returns the destination image file name with full path
    \return destination image file name with full path
  */
  public function destinationImageFullName() {
    global $resultImagesRenamed;
    // use source filename as destination filename
    if (!$resultImagesRenamed) {
      $files = $this->files();
      $result = $this->destinationFolder() . ereg_replace("^/", "", end($files));
      $result = substr($result, 0, strrpos($result, "."));
      if ( $this->endsWithNumber($result) ) {
          // Avoid output files ending in a number.
          $result .= "_hrm";
      }
    }
    else {
      $result = $this->destinationFolder() . $this->destinationImageName();
    }
    return $result;
  }

  /*!
    \brief  Returns the final destination folder name (also considering
            sub-folders created by the user in the image destination)
    \return destination folder name
  */
  public function destinationFolder() {
    global $huygens_server_image_folder;
    global $image_destination;

    $user = $this->owner();
    // avoid redundant slashes in path
    $result = $huygens_server_image_folder . $user->name() . "/" . $image_destination . "/" . $this->relativeSourcePath();
    
    return $result;
  } 

  /*!
    \brief  Returns the path to the first-level destination subfolder 
    \return path to the first-level destination subfolder 
  */
  public function destinationFolderTop() {
    global $huygens_server_image_folder;
    global $image_destination;

    $user = $this->owner();
    $path = $this->relativeSourcePath();
    $pathComponents = explode("/", $path);
    $top = $pathComponents[0]; 
    $result = $huygens_server_image_folder . $user->name() . "/" . $image_destination . "/" . $top . "/"; 
    
    return $result;    
  } 

/*
                              PRIVATE FUNCTIONS
*/

  /*!
    \brief Create elementare Jobs from multi-file compound Jobs
    \return true if elementary Jobs could be created, false otherwise
  */
  private function createSubJobsforFiles() {
    $result = True;
    foreach ($this->files as $file) {
      // error_log("file=".$file);
      $newJobDescription = new JobDescription();
      $newJobDescription->copyFrom($this);
      $newJobDescription->setFiles(array($file));	
      $result = $result && $newJobDescription->createJob();
    }
    return $result;
  } 

  /*!
    \brief Create all combinations of the elements of two arrays
    \return the combined arrays
  */
  private function combine($firstArray, $secondArray) {  
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
  
  /*!
    \brief Create all combinations of the elements of an array
    \return all combinations
  */
  private function combineAll($anArray) {
    $result = array();
    foreach ($anArray as $row) {
      $result = $this->combine($result, $row);
    } 
    return $result;
  }

  /*!
    \brief  Return all combinations of range parameters
    \return matrix of all combinations of range parameters
  */
/*  private function rangeParameterCombinations($rangeParameter) {
    $parameterSetting = $this->parameterSetting;
    $numberOfChannels = $parameterSetting->numberOfChannels();
    
    $jobDescriptions = array();
    //$rangeParameter = $this->rangeParameter();
    $matrix = array();
    //if (count($rangeParameter)==1) {
      $range = array_pop($rangeParameter);
      if ($range == null) {
          return;
      }
      $value = array_filter($range->value());
      $snrValue = null;
      $iterationValue = null;
      // process SignalNoiseRatioRange, if applicable
      if ($range->name() == "SignalNoiseRatioRange") {
        // // error_log("SignalNoiseRatio range");
        $snrRange = $range;
        $snrValue = $value;
      }
      else if ($range->name() == "NumberOfIterationsRange") {
        // error_log("NumberOfIterations range");
        $iterationRange = $range;
        $iterationValue = $value;
      }
      // process NumberOfIterationsRange, if applicable
      if (count($rangeParameter) > 0) {
        $range = array_pop($rangeParameter);
        $value = array_filter($range->value());
        if ($range->name() == "SignalNoiseRatioRange") {
          // error_log("SignalNoiseRatio range");
          $snrRange = $range;
          $snrValue = $value;
        }
        else if ($range->name() == "NumberOfIterationsRange") {
          // error_log("NumberOfIterations range");
          $iterationRange = $range;
          $iterationValue = $value;
        }
      }
      // COMBINATORIAL combinations
      // what is the form of matrix?
      // compute matrix dimensions
      /*$dim = 1;
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
      }*/
      // SIMPLE combinations
/*      if ($snrValue != null) {
          $val = array_filter($snrValue);
          $range = count($val[0]);
          for ($i = 0; $i < $range; $i++) {
              $matrix[$i] = array();
              for ($j = 0; $j < $numberOfChannels; $j++) {
                  $matrix[$i][] = $val[$j][$i];
              }
          }
      }
      if ($iterationValue != null) {
          $val = array_filter($iterationValue);
          if (count($matrix) != 0) {
              for ($j = 0; $j < count($iterationValue); $j++) {
                  for ($i = 0; $i < $range; $i++) {
                      if ($j == 0) {
                          $matrix[$i][] = $iterationValue[$j];
                      }
                      else {
                          $matrix[] = $matrix[$i];
                          $matrix[count($matrix) - 1][count($matrix[0]) - 1] = $iterationValue[$j];
                      }
                  }
              }
          }
          else {
              for ($j = 0; $j < count($iterationValue); $j++)
                $matrix[] = array($iterationValue[$j]);
          }
      }
      return $matrix;
    /*} else { 
      foreach ($rangeParameter as $range) {
	$value = array_filter($range->value());
	$matrix[] = $value;
      }
      $combinations = $this->combineAll($matrix);
      return $combinations;
    } */
//  } 

  /*!
    \brief  Returns the names of all range parameters
    \return array with the name of all range parameters
  */
/*  private function rangeParameterNames() {
    $names = array();
    foreach ($this->rangeParameters as $parameter) {
      $names[] = $parameter->name(); 
    } 
    return $names;
  }
*/

  /*!
    \brief Add script for single-channel images
    \param  $script Current script
    \param  $channel Number of the channel
    \return updated script
    \todo This function is obsolete and will be replace by the new Tcl templates
  */
  private function putScriptOnForChannel($script, $channel) {
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
////////    $removeBackgroundPercent = $taskSetting->parameter('RemoveBackgroundPercent');
////////    $val = $removeBackgroundPercent->internalValue();
////////    $originalRemoveBackgroundPercentValue = array();
////////    //TODO remove RemoveBackgroundPercent
////////    for ($i = 0; $i < $numberOfChannels; $i++) {
////////      $originalRemoveBackgroundPercentValue[$i] = $val[$i];
////////    }
////////////////    //error_log("GENERATING SCRIPT (JobDescription): remove background percent -> ".$originalRemoveBackgroundPercentValue[$i]." [".$i."]");

    $backgroundOffsetPercent = $taskSetting->parameter('BackgroundOffsetPercent');
    //$val = $backgroundOffsetPercent->internalValue();
    //$originalBackgroundOffsetPercentValue = array();
    $originalBackgroundOffsetPercentValue = $backgroundOffsetPercent->internalValue();
    //for ($i = 0; $i < $numberOfChannels; $i++) {
    //  $originalBackgroundOffsetPercentValue[] = $val[$i];
    //}
    
////////    // make single channel setting for current channel
////////    $newRemoveBackgroundPercent = $taskSetting->parameter('RemoveBackgroundPercent');
////////    // DEBUG
////////    //$value = $newRemoveBackgroundPercent->value();
////////    $newRemoveBackgroundPercent->setValue($originalRemoveBackgroundPercentValue[$channel]);
////////    $taskSetting->set($newRemoveBackgroundPercent);

    // Backgriund Offset Percent
    $newBackgroundOffsetPercent = $taskSetting->parameter('BackgroundOffsetPercent');
    // DEBUG
    //$value = $newBackgroundOffsetPercent->value();
    if ($originalBackgroundOffsetPercentValue[0] == "auto" || $originalBackgroundOffsetPercentValue[0] == "object") {
      $newBackgroundOffsetPercent->setValue($originalBackgroundOffsetPercentValue[0]);
      $test = $newBackgroundOffsetPercent->internalValue();
      //error_log("auto/object ".$test[0]);
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

////////    // DEBUG
////////    $removeBackgroundPercent->setValue($originalRemoveBackgroundPercentValue);
////////    $taskSetting->set($removeBackgroundPercent);
    $backgroundOffsetPercent->setValue($originalBackgroundOffsetPercentValue);
    $taskSetting->set($backgroundOffsetPercent);
    $this->setTaskSetting($taskSetting);
    // return result
    return $result;
  } 

  /*!
    \brief  Checks whether a string ends with a number
    \return true if the string ends with a number, false otherwise
  */
  private function endsWithNumber($string) {
    $last = $string[strlen($string)-1];
    return is_numeric($last);
  } 

  /*!
    \brief  Returns a new name for a single TIFF that must be renamed
    \return the new name
  */
  private function newNameForSingleTif() {
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

  /*!
    \brief  Prepares the part of the script that opens a file with options
    \return the script part for opening a file with options
  */
  private function loadImageString( $isOriginal = false ) {
    global $useThumbnails;
    if ($this->mustReplaceSourceName()) { 
      $imageFileName = $this->newNameForSingleTif();
    } else { 
      $imageFileName = $this->sourceImageName();
    }
    // TODO we should provide more convenience methods in ParameterSetting
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
      // Two additional conditions are (1) that the dataset needs correction
      // and (2) that the user decided to perform correction
      $absCorr=$this->parameterSetting->getAberractionCorrectionParameters( );
      if ( ( $absCorr[ 'AberrationCorrectionNecessary' ] == 1 ) &&
        ( $absCorr[ 'PerformAberrationCorrection' ] == 1 ) &&
        ( $absCorr[ 'CoverslipRelativePosition' ] == 'farthest' ) ) {
        $mirror = 'catch { $imageName mir z }' . "\n";
      }
      $thumbn = "\nset originalFile \"$huygensFileName\"".
                "\nset hrmFileName \"$imageFileName\"".
                "\nset originalOptions [list $options]\n";
    }
    return 'set imageName [img open "' . $huygensFileName . '"' . $options . ']' . "\n" . '$imageName lundo -off' . "\n" . $mirror. $thumbn;
  }

  /*!
    \brief  Returns a single value parameters as a Tcl list for all channels.
    
      Since version 3.5 of Huygens, many parameters that where global before, 
      can be now defined per channel, like the NA.
    
    \return empty parameter Tcl list
  */
  private function getParamAsChannelTclList ( $setting, $param, $translate = false, 
          $withQuotes = false ) {
      if ( $translate ) {
          $values = $setting->parameter($param)->translatedValue();
      } else {
          $values = $setting->parameter($param)->value();
      }
      $chanCnt = $setting->numberOfChannels();
      $retList = "";
      $quote == "";
      if ( $withQuotes ) { 
          $quote = "\"";
      }
      if ( is_array($values) ) {
          $sep = "";    
          foreach  ($values as $item) {
              $retList .= $sep.$quote.$item.$quote;
              $sep = " ";
          }
      } else {
          for ($i = 0; $i < $chanCnt; $i ++ ) {
              $retList .= $sep.$quote.$values.$quote;
              $sep = " ";
          }
      }
      $retList = trim($retList);
      return "{ $retList }";
  }

  /*!
    \brief  Returns an empty parameter Tcl list from current ParameterSetting
    \return empty parameter Tcl list
  */
  private function getEmptyParameterList ($parameterSetting) {

      $chanCnt = $parameterSetting->numberOfChannels();
      $retList = "";
      $sep = "";    
      for ($i = 0; $i < $chanCnt; $i++ ) {
          $retList .= $sep."(ignore)";
          $sep = " ";
      }
      return "{ $retList } ";

  }
  
  /*!
    \brief  Returns an array of parameters from a setting as a Tcl list. Elements are
            optionally enclosed in quotes.
    \return parameter Tcl list
  */
  private function getParamAsTclList ( $setting, $param, $withQuotes = false ) {
      $values = $setting->parameter($param)->value();
      $retList = "";
      $quote == "";
      if ( $withQuotes ) { 
          $quote = "\"";
      }
      if ( is_array($values) ) {
          $sep = "";    
          foreach  ($values as $item) {
              $retList .= $sep.$quote.$item.$quote;
              $sep = " ";
          }
      } else {
          $retList = $values;
      }
      $retList = trim($retList);
      return "{ $retList }";
  }

  /* OBSOLETE
  private function passTwoScript() {
    $this->pass = 2;
    $script = '';
    $script = $script . 'if {[img exists $imageName]} {$imageName del}' . "\n";
    $script = $script . 'c genpsf -> psf -dims auto -z -reflCorr' . "\n";
    $script = $script . 'c cmle psf -> c -sn {80} -it 7 -bgMode object -bg {-100} -blMode auto -q 0 -mode fast -pad auto' . "\n";
    $script = $script . $this->saveImageString();
    $this->pass = 1;
    return $script;
  } 
  */
  
  /*!
    \brief  Prepares the part of the script that writes the result file with
            all accessories (thumbnails, previews, reports, history)
    \return the script part for writing a result file with accessory files
  */
  // TODO refactor this part
  private function saveImageString( $isResult = false ) {
    global $resultImagesOwnedByUser;
    global $useThumbnails;
    global $movieMaxSize, $saveSfpPreviews, $maxComparisonSize;
    $result = '';
    $setting = $this->taskSetting;
    $parameterSetting = $this->parameterSetting;
    $param = $setting->parameter('OutputFileFormat');
    $type = $param->translatedValue();
    $extension = $param->extension( );
//    if ($type=='ics') {
//       $result = $result . "c convert -type auto\n";
//    }
    $imageFileName = $this->destinationImageFullName();
//    if ($type== 'tiff' || $type == 'tiff16') {
//    	$imageFileName = $imageFileName . '.tif';
//    }
    $result = $result . "c save " . '"' . $imageFileName . "." . $extension .'"' . " -type $type";

    // Set -cmode to 'scale' for 8 bit datasets
    if ($type == 'tiff' || $type == 'tiff16') {
        $result = $result . " -tiffMultiDir -cmode scale";
    }
    
    $result = $result . "\ncatch { exec chmod 777 \"$imageFileName.$extension\"}";
    
    if ( $isResult ) {
        // Save preview.
        if ( $useThumbnails ) {
            $newScript = "\n".
'catch { 
    # Previews: Huygens Core 3.3.1 required for this to work.
    set path "'.$imageFileName.".".$extension.'"
    set ddir [file dirname $path]
    set fn [file tail $path]
    catch { ::WebTools::savePreview c $ddir/hrm_previews $fn {preview 400} }
    '."\n";
            if ( $movieMaxSize > 0 ) {
                $newScript = $newScript . 
                '    catch {::WebTools::saveStackMovie c $ddir/hrm_previews ${fn}.stack '.
                $movieMaxSize . '}'.  "\n".
                '    catch {::WebTools::saveTimeSeriesMovie c $ddir/hrm_previews'.
                ' ${fn}.tSeries '. $movieMaxSize . '}'.  "\n\n";
            }
            if ( $saveSfpPreviews ) {
                $newScript = $newScript . 
                '    catch {::WebTools::saveTopViewSfp c $ddir/hrm_previews ${fn}.sfp } '.
                 "\n";
                if ( $movieMaxSize > 0 ) {
                    $newScript = $newScript . 
                '    catch {::WebTools::saveTimeSeriesMovie c $ddir/hrm_previews '
                . '${fn}.tSeries.sfp - SFP } '.  "\n";
                }
            }
             // Save also a preview of the original image, adopting the same
             // parameters as in the result.
            $newScript = $newScript . "    ".
            'set orig [eval img open \"$originalFile\" $originalOptions]'."\n";

            $newScript = $newScript . "    ". 'c adopt -> $orig' . "\n"; 

            $newScript = $newScript . '
    set path "'.$imageFileName.".".$extension.".original".'"
    set ddir [file dirname $path]
    set ofn [file tail $path]
    catch {::WebTools::savePreview $orig $ddir/hrm_previews $ofn {preview 400}}'.
    "\n";

    if ($maxComparisonSize > 0 ) {
        // Since Huygens 3.5.1p3, stacks and time series can also be
        // saved in jpeg strips that can be viewed online with CSS
        // tricks.
            $newScript .= 'catch {
                ::WebTools::combineStrips [list $orig c] stack $ddir/hrm_previews ${fn} '. $maxComparisonSize .' auto 
                ::WebTools::combineStrips [list $orig c] tSeries $ddir/hrm_previews ${fn} '. $maxComparisonSize .' auto 
   }

                ';
    }

 


            if ( $saveSfpPreviews ) {
                $newScript = $newScript . 
                '    catch {::WebTools::saveTopViewSfp $orig $ddir/hrm_previews ${ofn}.sfp }'.
                 "\n";
            }

            // We keep doing things assuming a trusted environment,
            // but real security would require making all directories
            // accessible to the deamon only, that runs all file
            // management operation after the apache queries.
            // By now, grant 777 permissions to the previews subdir.
            $newScript = $newScript . '
    # Save preview of the original also in source directory
    set sdir [file dirname $hrmFileName]
    set fn [file tail $hrmFileName]
    catch { ::WebTools::savePreview $orig $sdir/hrm_previews $fn {preview} }
    catch { exec chmod -R a+w "$sdir/hrm_previews" }
    catch { exec chmod -R a+w "$ddir/hrm_previews" }'.
    "\n" ;
            $newScript = $newScript . '    $orig del' . "\n"; 
            $newScript = $newScript . '}'."\n";
            $result = $result . $newScript;

        }


    }
    // Save processing history
    $result = $result . "\n" . 'catch { c history -details -format txt -save "' . $imageFileName. '.history.txt" }';
    $result = $result . "\n\n";
    if ($resultImagesOwnedByUser) {
      $user = $this->owner();
      $username = $user->name();
      $result = $result . 'set modfiles [glob -nocomplain ' . '"' . $this->destinationFolder() . '*' . $this->sourceImageShortName() . '*' . '"]' . "\n";
      $result = $result . 'foreach modfile $modfiles { catch { exec chown ' . "$username " . '$modfile } }' . "\n";
      $result = $result .
             'catch { exec chown -R '.$username.' "$ddir/hrm_previews" }'."\n";
      $result = $result .
             'catch { exec chown -R '.$username.' "$sdir/hrm_previews" }'."\n";
    }
    return $result;
  } 
  
  /*!
    \brief  Checks whether the source name must be replaced (for single TIFFs)
    \return true if the source name must be replaced, false otherwise
  */
  private function mustReplaceSourceName() {
    $parameterSetting = $this->parameterSetting;
    if (!$parameterSetting->isThreeDimensional() && !$parameterSetting->isTimeSeries() && $parameterSetting->isTif()) {
      if ($this->endsWithNumber($this->sourceImageShortName())) {
	return True;
      } 	
    }
    return False;
  }

} 

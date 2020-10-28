<?php
/**
 * JobDescription
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\job;

use hrm\DatabaseConnection;
use hrm\Log;
use hrm\param\OutputFileFormat;
use hrm\setting\AnalysisSetting;
use hrm\setting\JobAnalysisSetting;
use hrm\setting\JobParameterSetting;
use hrm\setting\JobTaskSetting;
use hrm\setting\ParameterSetting;
use hrm\setting\TaskSetting;
use hrm\user\UserV2;

require_once dirname(__FILE__) . '/../bootstrap.php';

/**
 * Collects all information for a deconvolution Job to be created.
 *
 * Description of the job to be processed by HuCore Consisting of owner
 * information, a parameter setting, a task setting and a list of image files.
 *
 * @package hrm
 */
class JobDescription
{

    /**
     * A unique id identifying the job.
     * @var string
     */
    private $id;

    /**
     * Id of the settings associated to the job.
     * @var string
     */
    private $settingsId;

    /**
     * The ID of the GPU card where to run the Job.
     * @var string
     */
    public $gpuId;

    /**
     * The Job's ParameterSetting.
     * @var ParameterSetting
     */
    public $parameterSetting;

    /**
     * The Job's TaskSetting.
     * @var TaskSetting
     */
    public $taskSetting;

    /**
     * The Job's AnalysisSetting.
     * @var AnalysisSetting
     */
    public $analysisSetting;

    /**
     * The list of files to be processed by the Job.
     * @var array
     */
    private $files;

    /**
     * Whether or not to load a series automatically.
     * @var bool
     */
    private $autoseries;

    /**
     * The user who created the Job.
     * @var UserV2
     */
    private $owner;

    /**
     * The last error message.
     * @var string
     */
    private $message;

    /**
     * Pass 1 or 2 of step combined processing.
     * @todo   Check: this is not used any more!
     * @var int
     */
    private $pass;

    /**
     * Name of the group to be used.
     * @var string
     */
    private $group;


    /**
     * JobDescription constructor.
     */
    public function __construct()
    {
        $this->id = self::newID();
        $this->settingsId = "";
        $this->message = "";
        $this->pass = 1;
    }

    /**
     * Generate a unique ID.
     * @return string Unique Id.
     */
    public static function newID()
    {
        return (string)(uniqid(''));
    }

    /**
     * Returns last error message.
     * @return string Last error message.
     */
    public function message()
    {
        return $this->message;
    }

    /**
     * Returns the unique id that identifies the Job.
     * @return string Unique id.
     */
    public function id()
    {
        return $this->id;
    }

    /**
     * Returns the id of the settings associated to the Job.
     * @return string Unique id.
     */
    public function settingsId()
    {
        return $this->settingsId;
    }

    /**
     * Sets the (unique) id of the Job.
     * @param  string $id Unique id.
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Sets the id of the settings associated to the Job.
     * @param  string $settingsId Id of the settings.
     */
    public function setSettingsId($settingsId)
    {
        $this->settingsId = $settingsId;
    }

    /**
     * Returns the GPU ID of the card where to run the Job.
     * @return string $gpuId.
     */
    public function gpu()
    {
        return $this->gpuId;
    }

    /**
     * Sets the GPU ID of the card where the Job runs.
     * @param  string $gpuId
     */
    public function setGpu($gpuId)
    {
        $this->gpuId = $gpuId;
    }

    /**
     * Returns the User owner of the job.
     * @return UserV2 Owner of the job.
     */
    public function owner()
    {
        return $this->owner;
    }

    /**
     * Sets the owner of the Job
     * @param UserV2 $owner Owner of the Job
     */
    public function setOwner(UserV2 $owner)
    {
        $this->owner = $owner;
    }

    /**
     * Returns the ParameterSetting associated with the job.
     * @return ParameterSetting A ParameterSetting object.
     */
    public function parameterSetting()
    {
        return $this->parameterSetting;
    }

    /**
     * Returns the TaskSetting associated with the job.
     * @return TaskSetting A TaskSetting object.
     */
    public function taskSetting()
    {
        return $this->taskSetting;
    }

    /**
     * Returns the AnalysisSetting associated with the job.
     * @return AnalysisSetting An AnalysisSetting object.
     */
    public function analysisSetting()
    {
        return $this->analysisSetting;
    }

    /**
     * Returns the files associated with the job.
     * @return array Array of file names.
     */
    public function files()
    {
        return $this->files;
    }

    /**
     * Returns the automatically load series mode of the job.
     * @return boolean True if series should be loaded automatically,
     * false otherwise.
     */
    public function autoseries()
    {
        return $this->autoseries;
    }

    /**
     * Sets the ParameterSetting for the job
     * @param ParameterSetting $setting A ParameterSetting object.
     */
    public function setParameterSetting(ParameterSetting $setting)
    {
        $this->parameterSetting = $setting;
        $this->owner = $setting->owner();
    }

    /**
     * Sets the TaskSetting for the job.
     * @param TaskSetting $setting A TaskSetting object.
     */
    public function setTaskSetting(TaskSetting $setting)
    {
        $this->taskSetting = $setting;
    }

    /**
     * Sets the AnalysisSetting for the job.
     * @param AnalysisSetting $setting An AnalysisSetting object.
     */
    public function setAnalysisSetting(AnalysisSetting $setting)
    {
        $this->analysisSetting = $setting;
    }

    /**
     * Sets the list of files for the job.
     * @param array $files Array of file names.
     * @param bool $autoseries True if the file series should be loaded automatically, false otherwise.
     */
    public function setFiles($files, $autoseries = false)
    {
        $this->files = $files;
        $this->autoseries = $autoseries;
    }

    /**
     * Returns the group of the user associated with the job.
     * @return string Group of the user.
     */
    public function group()
    {
        return $this->group;
    }

    /**
     * Sets the group of the user associated with the job.
     * @param string $group Group of the user.
     */
    public function setGroup($group)
    {
        $this->group = $group;
    }

    /**
     * Returns the Huygens template name containing the unique HRM id.
     * @return string The template name.
     */
    public function getHuTemplateName()
    {
        return ".hrm_" . $this->id() . ".hgsb";
    }

    /**
     * Add a Job to the queue
     * @return bool True if the Job could be added to the queue, false otherwise.
     */
    public function addJob()
    {
        $result = true;

        // Create a new JobQueue object
        $lqueue = new JobQueue();

        // Lock the queue
        $lqueue->lock();

        // Create and save Job Parameter Settings with current Job Description ID
        $jobParameterSetting = new JobParameterSetting();
        $jobParameterSetting->setOwner($this->owner);
        $jobParameterSetting->setName($this->id);
        $jobParameterSetting->copyParameterFrom($this->parameterSetting);
        $result &= $jobParameterSetting->save();

        // Create and save Job Task Settings with current Job Description ID
        $taskParameterSetting = new JobTaskSetting();
        $taskParameterSetting->setOwner($this->owner);
        $taskParameterSetting->setName($this->id);
        $taskParameterSetting->copyParameterFrom($this->taskSetting);
        $result &= $taskParameterSetting->save();

        // Create and save Job Analysis Settings with current Job Description ID
        $analysisParameterSetting = new JobAnalysisSetting();
        $analysisParameterSetting->setOwner($this->owner);
        $analysisParameterSetting->setName($this->id);
        $analysisParameterSetting->copyParameterFrom($this->analysisSetting);
        $result &= $analysisParameterSetting->save();

        // Settings id
        $settingsId = $this->id;

        // Get the DatabaseConnection object
        $db = DatabaseConnection::get();

        // Common Job properties
        $owner = $this->owner();
        $ownerName = $owner->name();

        // Now add a Job per file to the queue
        foreach ($this->files() as $file) {
            // Get a new id for the elementary job
            $id = JobDescription::newID();

            // Add a new Job with the newly generated ID that owns the file
            // and links to the master id of the compound job.
            $result &= $db->addFileToJob($id, $this->owner, $file, $this->autoseries);

            // Now add a Job to the queue for this file
            $result &= $db->queueJob($id, $settingsId, $ownerName);
        }

        // Assign priorities
        $db = DatabaseConnection::get();
        $result &= $db->setJobPriorities();

        if (!$result) {
            Log::error("Could not add Job to queue!");
        }

        $lqueue->unlock();

        return $result;
    }

    /**
     * Loads a JobDescription from the database for the user set in
     * this JobDescription.
     * @todo Retrieve the settings id if it is empty!
     * @todo Check that the ParameterSetting->numberOfChannels() exists!
     */
    public function load()
    {
        $db = DatabaseConnection::get();

        $parameterSetting = new JobParameterSetting();
        $owner = new UserV2();
        $name = $db->userWhoCreatedJob($this->id);
        $owner->setName($name);
        $parameterSetting->setOwner($owner);
        $parameterSetting->setName($this->settingsId);
        $parameterSetting = $parameterSetting->load();
        $this->setParameterSetting($parameterSetting);

        $taskSetting = new JobTaskSetting();
        $taskSetting->setNumberOfChannels($parameterSetting->numberOfChannels());
        $taskSetting->setName($this->settingsId);
        $taskSetting->setOwner($owner);
        $taskSetting = $taskSetting->load();
        $this->setTaskSetting($taskSetting);

        $analysisSetting = new JobAnalysisSetting();
        $analysisSetting->setNumberOfChannels($parameterSetting->numberOfChannels());
        $analysisSetting->setName($this->settingsId);
        $analysisSetting->setOwner($owner);
        $analysisSetting = $analysisSetting->load();
        $this->setAnalysisSetting($analysisSetting);

        $this->setFiles($db->getJobFilesFor($this->id()), $db->getSeriesModeForId($this->id()));
    }

    /**
     * Copies from another JobDescription into this JobDescription
     * @param JobDescription $aJobDescription Another JobDescription.
     */
    public function copyFrom(JobDescription $aJobDescription)
    {
        $this->setParameterSetting($aJobDescription->parameterSetting());
        $this->setTaskSetting($aJobDescription->taskSetting());
        $this->setAnalysisSetting($aJobDescription->analysisSetting());
        $this->setOwner($aJobDescription->owner());
        $this->setGroup($aJobDescription->group());
    }

    /**
     * Returns the full file name without redundant slashes.
     * @return string Full file name without redundant slashes.
     * @todo Isn't this redundant? One could use the FileServer class!
     */
    public function sourceImageName()
    {
        $files = $this->files();
        // avoid redundant slashes in path
        $result = $this->sourceFolder() . preg_replace("#^/#", "", end($files));
        return $result;
    }

    /**
     * Returns the file name without path.
     * @return string File name without path.
     * @todo What about redundant slashes?
     * @todo Isn't this redundant? One could use the FileServer class!
     */
    public function sourceImageNameWithoutPath()
    {
        $name = $this->sourceImageName();
        $pos = strrpos($name, '/');
        if ($pos) {
            return (substr($name, ($pos + 1)));
        } else {
            return $name;
        }
    }

    /**
     * Returns relative source path (under the image source path).
     * @return string Relative source path.
     * @todo This must go into the Fileserver class!
     */
    public function relativeSourcePath()
    {
        $files = $this->files();
        $inputFile = end($files);
        $inputFile = explode("/", $inputFile);
        array_pop($inputFile);
        $path = implode("/", $inputFile);
        if (strlen($path) > 0) {
            // make sure to have exactly ONE slash at the end of $path:
            $path = preg_replace("#/*$#", "/", $path);
        }
        return $path;
    }

    /**
     * Returns the file base name. Special handling for LIF, LOF and CZI files.
     * @return string File base name.
     * @todo This must go into the Fileserver class!
     */
    public function sourceImageShortName()
    {
        $files = $this->files();
        $inputFile = end($files);
        $inputFile = explode("/", $inputFile);
        // remove file extension
        //$inputFile = explode(".", end($inputFile));
        //$inputFile = $inputFile[0];
        //$parameterSetting = $this->parameterSetting;
        //$parameter = $parameterSetting->parameter('ImageFileFormat');
        //$fileFormat = $parameter->value();
        if (preg_match("/^(.*)\.(lif|lof|czi)\s\((.*)\)/i", $inputFile[0], $match)) {
            $inputFile = $match[1] . '_'.$match[2] . '_' . $match[3];
        } else {
            $inputFile = substr(end($inputFile), 0, strrpos(end($inputFile), "."));
        }

        return $inputFile;
    }

    /**
     * Returns the source folder name.
     * @return string Source folder name.
     * @todo This must go into the Fileserver class!
     */
    public function sourceFolder()
    {
        global $huygens_server_image_folder;
        global $image_source;
        $user = $this->owner();
        $result = $huygens_server_image_folder . "/" .
            $user->name() . "/" . $image_source . "/";
        return $result;
    }


    /**
     * Returns the destination image name without extension and without path.
     * @return string Destination image name without extension and without path.
     * @todo This must go into the Fileserver class!
     */
    public function destinationImageName()
    {
        $taskSetting = $this->taskSetting();
        //$files = $this->files();
        //$outputFile = $this->sourceImageShortName();
        // work around the fact that end() requires a reference, but the result of
        // explode() cannot be turned into one, so use a temporary variable instead
        // (see http://stackoverflow.com/questions/4636166/ for more details)
        $tmp = explode($taskSetting->name(), $this->sourceImageShortName());
        $outputFile = end($tmp);
        $outputFile = str_replace(" ", "_", $outputFile);
        $result = $outputFile . "_" . $this->id() . "_hrm";
        # Add a non-numeric string at the end: if the task name ends with a
        # number, that will be removed when saving using some file formats that
        # use numbers to identify Z planes. Therefore the result file won't
        # be found later and an error will be generated.
        return $result;
    }

    /**
     * Returns the destination image name without path and with output file format extension.
     * @return string Destination image name without path and with output file format extension.
     */
    public function destinationImageNameWithoutPath()
    {
        $name = $this->destinationImageName();
        $pos = strrpos($name, '/');
        if ($pos) {
            $name = substr($name, ($pos + 1));
        }
        // Append extension
        $taskSetting = $this->taskSetting();
        $param = $taskSetting->parameter('OutputFileFormat');
        $fileFormat = $param->extension();
        return ($name . "." . $fileFormat);
    }

    /**
     * Returns the destination image name with selected output extension and relative path.
     * @return string Destination image name with selected output extension and relative path.
     */
    public function destinationImageNameAndExtension()
    {
        $name = $this->destinationImageName();
        // Append extension
        $taskSetting = $this->taskSetting();
        /** @var OutputFileFormat $param */
        $param = $taskSetting->parameter('OutputFileFormat');
        $fileFormat = $param->extension();
        return ($this->relativeSourcePath() . $name . "." . $fileFormat);
    }

    /**
     * Returns the destination image file name with full path.
     * @return string Destination image file name with full path.
     */
    public function destinationImageFullName()
    {
        $result = $this->destinationFolder() . $this->destinationImageName();
        return $result;
    }

    /**
     * Returns the final destination folder name (also considering
     * sub-folders created by the user in the image destination)
     * @return string Destination folder name.
     */
    public function destinationFolder()
    {
        global $huygens_server_image_folder;
        global $image_destination;

        $user = $this->owner();

        // Make sure to get rid of blank spaces in the folder name!
        $relSrcPath = $this->relativeSourcePath();
        $relSrcPath = str_replace(" ", "_", $relSrcPath);

        // avoid redundant slashes in path
        $result = $huygens_server_image_folder . "/" . $user->name() . "/" . $image_destination . "/" . $relSrcPath;

        return $result;
    }

    /**
     * Convenience function to get all the parameters of the task setting.
     * @return string A string with all the parameters of the task setting.
     */
    public function taskSettingAsString()
    {
        $numChannels = $this->parameterSetting->numberOfChannels();
        $micrType = $this->parameterSetting->microscopeType();
        $timeInterval = $this->parameterSetting->sampleSizeT();

        return $this->taskSetting()->displayString($numChannels, $micrType, $timeInterval);
    }
}

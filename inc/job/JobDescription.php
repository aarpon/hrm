<?php
/**
 * JobDescription
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm;

use hrm\setting\AnalysisSetting;
use hrm\setting\JobAnalysisSetting;
use hrm\setting\JobParameterSetting;
use hrm\setting\JobTaskSetting;
use hrm\setting\ParameterSetting;
use hrm\setting\TaskSetting;
use hrm\user\User;

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
     * The Job's ParameterSetting.
     * @var \hrm\setting\ParameterSetting
     */
    public $parameterSetting;

    /**
     * The Job's TaskSetting.
     * @var \hrm\setting\TaskSetting
     */
    public $taskSetting;

    /**
     * The Job's AnalysisSetting.
     * @var \hrm\setting\AnalysisSetting
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
     * @var User
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

    //public $rangeParameters;     // why not use a global variable from the beginning?!

    /**
     * JobDescription constructor.
     */
    public function __construct()
    {
        $this->id = (string)(uniqid(''));
        $this->message = "";
        $this->pass = 1;
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
     * Sets the (unique) id of the Job.
     * @param  string $id Unique id.
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Returns the User owner of the job.
     * @return User Owner of the job.
     */
    public function owner()
    {
        return $this->owner;
    }

    /**
     * Sets the owner of the Job
     * @param User $owner Owner of the Job
     */
    public function setOwner(User $owner)
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
    public function setFiles($files, $autoseries = FALSE)
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

        if ($result) {

            // Process compound jobs
            $this->processCompoundJobs();

            // Assign priorities
            $db = new DatabaseConnection();
            $result = $db->setJobPriorities();
            if (!$result) {
                Log::error("Could not set job priorities!");
            }
        }

        $lqueue->unlock();

        return $result;
    }

    /**
     * Create a Job from this JobDescription.
     * @return bool True if the Job could be created, false otherwise.
     */
    public function createJob()
    {
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

        $analysisParameterSetting = new JobAnalysisSetting();
        $analysisParameterSetting->setOwner($this->owner);
        $analysisParameterSetting->setName($this->id);
        $analysisParameterSetting->copyParameterFrom($this->analysisSetting);
        $result = $result && $analysisParameterSetting->save();

        $db = new DatabaseConnection();
        $result = $result && $db->saveJobFiles($this->id,
                $this->owner,
                $this->files,
                $this->autoseries);

        $queue = new JobQueue();
        $result = $result && $queue->queueJob($this);
        if (!$result) {
            $this->message = "Could not create job!";
        }
        return $result;
    }

    /**
     * Processes compound Jobs to deliver elementary Jobs.
     *
     * A compound job contains multiple files.
     */
    public function processCompoundJobs()
    {
        $queue = new JobQueue();
        $compoundJobs = $queue->getCompoundJobs();
        foreach ($compoundJobs as $jobDescription) {
            $job = new Job($jobDescription);
            $job->createSubJobsOrHuTemplate();
        }
    }

    /**
     * Loads a JobDescription from the database for the user set in
     * this JobDescription.
     * @todo Check that the ParameterSeggins->numberOfChannels() exists!
     */
    public function load()
    {
        $db = new DatabaseConnection();

        $parameterSetting = new JobParameterSetting;
        $owner = new User();
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

        $analysisSetting = new JobAnalysisSetting;
        $analysisSetting->setNumberOfChannels($parameterSetting->numberOfChannels());
        $analysisSetting->setName($this->id);
        $analysisSetting->setOwner($owner);
        $analysisSetting = $analysisSetting->load();
        $this->setAnalysisSetting($analysisSetting);

        $this->setFiles($db->getJobFilesFor($this->id()),
            $db->getSeriesModeForId($this->id()));
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
     * Checks whether the JobDescription describes a compound Job.
     * @return bool True if the Job is compound (i.e. contains more than one file),
     * false otherwise.
     */
    public function isCompound()
    {
        if (count($this->files) > 1) {
            return True;
        }
        return False;
    }

    /**
     * Creates elementary Jobs from compound Jobs.
     * @return bool True if elementary Jobs could be created, false otherwise.
     */
    public function createSubJobs()
    {
        $parameterSetting = $this->parameterSetting;
        $numberOfChannels = $parameterSetting->numberOfChannels();
        return $this->createSubJobsforFiles();
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
     * Returns the file base name. Special handling for LIF and CZI files.
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
        $parameterSetting = $this->parameterSetting;
        $parameter = $parameterSetting->parameter('ImageFileFormat');
        $fileFormat = $parameter->value();
        if (preg_match("/^(.*)\.(lif|czi)\s\((.*)\)/i", $inputFile[0], $match)) {
            $inputFile = $match[1] . '_' . $match[2];
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
        $result = $huygens_server_image_folder .
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
        $files = $this->files();
        $outputFile = $this->sourceImageShortName();
        // work around the fact that end() requires a reference, but the result of
        // explode() cannot be turned into one, so use a temporary variable instead
        // (see http://stackoverflow.com/questions/4636166/ for more details)
        $tmp = explode($taskSetting->name(), $this->sourceImageShortName());
        $outputFile = end($tmp);
        $outputFile = str_replace(" ", "_", $outputFile);
        $result = $outputFile . "_" . $taskSetting->name() . "_hrm";
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
        /** @var \hrm\param\OutputFileFormat $param */
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
        $result = $huygens_server_image_folder . $user->name() . "/" . $image_destination . "/" . $relSrcPath;

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

        return $this->taskSetting()->displayString($numChannels, $micrType);
    }


    /*
                                  PRIVATE FUNCTIONS
    */

    /**
     * Create elementary Jobs from multi-file compound Jobs
     * @return bool True if elementary Jobs could be created, false otherwise.
     */
    private function createSubJobsforFiles()
    {
        $result = True;
        foreach ($this->files as $file) {
            // Log::error("file=".$file);
            $newJobDescription = new JobDescription();
            $newJobDescription->copyFrom($this);
            $newJobDescription->setFiles(array($file), $this->autoseries);
            $result = $result && $newJobDescription->createJob();
        }
        return $result;
    }

    /**
     * Checks whether a string ends with a number.
     * @param string $string String to be checked.
     * @return bool True if the string ends with a number, false otherwise.
     * @todo This seems to be unused. Can it be removed?
     */
    private function endsWithNumber($string)
    {
        $last = $string[strlen($string) - 1];
        return is_numeric($last);
    }

}

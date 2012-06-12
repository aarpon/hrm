<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once ("Setting.inc.php");
require_once ("Database.inc.php");
require_once ("JobDescription.inc.php");
require_once ("hrm_config.inc.php");
require_once ("Fileserver.inc.php");
require_once ("Shell.inc.php");
require_once ("Mail.inc.php");
require_once ("HuygensTemplate.inc.php");
require_once ("System.inc.php");

/*!
  \class Job
  \brief	Stores all information for a deconvolution Job
 */
class Job {

    /*!
      \var      $script
      \brief    Contains a Huygens Batch template
      \todo     Rename as 'template' across the HRM code.
    */
    private $script;

    /*!
     \var      $jobDescription
     \brief    JobDescription object: microscopic & restoration data.
    */
    private $jobDescription;

    /*!        
     \var      $server
     \brief    The server where the deconvolution job will be processed.
    */
    private $server;

    /*!
     \var      $pid
     \brief    Process identifier associated with the deconvolution job.
    */
    private $pid;

    /*!
     \var      $status
     \brief    The processing status of the deconvolution job.
    */
    private $status;

    /*!
     \var      $pipeProducts
     \brief    Array with file suffixes of HuCore and HRM.
    */
    private $pipeProducts;

    /*!
     \var      $shell
     \brief    An ExternalProcessFor object.
    */
    private $shell;

    /*!
     \var     $imgParam
     \brief   Array mapping microscopic parameters and descriptions. 
    */
    private $imgParam;

    /*!
     \var    $restParam
     \brief  Array mapping restoration parameters and descriptions.
    */
    private $restParam;

    /*!
     \var    $destImage
     \brief  Deconvolved image name and path (no extension).
    */
    private $destImage;

    /* ------------------------------------------------------------------------ */
    
    /*!
     \brief	Constructor
     \param	$jobDescription	JobDescrition object
    */
    public function __construct($jobDescription) {
        $this->initialize($jobDescription);
        $this->setJobDestImage();
    }

    /*!
     \brief       Sets general class properties to initial values
     \param       $jobDescription A jobDescription object
    */
    private function initialize($jobDescription) {

        $this->script = '';
        $this->jobDescription = $jobDescription;
        
        $this->pipeProducts = array ( 'main'       => 'scheduler_client0.log',
                                      'history'    => '_history.txt',
                                      'tmp'        => '.tmp.txt',
                                      'parameters' => '.parameters.txt',
                                      'out'        => '_out.txt',
                                      'error'      => '_error.txt' );

        $this->imgParam = array ( 'dx'             => 'X pixel size (&mu;m)',
                                  'dy'             => 'Y pixel size (&mu;m)',
                                  'dz'             => 'Z step size  (&mu;m)',
                                  'dt'             => 'Time interval (s)',
                                  'iFacePrim'      => '',
                                  'iFaceScnd'      => '',
                                  'objQuality'     => '',
                                  'exBeamFill'     => '',
                                  'imagingDir'     => '',
                                  'pcnt'           => '',
                                  'na'             => 'Numerical aperture',
                                  'ri'             => 'Sample refractive index',
                                  'ril'            => 'Lens refractive index',
                                  'pr'             => 'Pinhole size (nm)',
                                  'ps'             => 'Pinhole spacing (nm)',
                                  'ex'             => 'Excitation wavelength (nm)',
                                  'em'             => 'Emission wavelength (nm)',
                                  'micr'           => 'Microscope type' );

        $this->restParam = array( 'algorithm'      =>'Deconvolution algorithm',
                                  'iterations'     =>'Number of iterations',
                                  'quality'        =>'Quality stop criterion',
                                  'format'         =>'Output file format',
                                  'absolute'       =>'Background absolute value',
                                  'estimation'     =>'Background estimation',
                                  'ratio'          =>'Signal/Noise ratio' );
    }

    /*!
     \brief       Gets the path and name of the output image (no extension).
    */
    private function setJobDestImage( ) {
        $destImage = $this->jobDescription->destinationImageName();
        $destImage = $this->jobDescription->destinationFolder() . $destImage;
        $this->destImage = $destImage;
    }

    /*!
     \brief	Returns the JobDescription associated with the Job
     \return	JobDescription object
    */
    public function description() {
        return $this->jobDescription;
    }

    /*!
     \brief	Sets the server which will run the Job
     \param	$server	Server name
    */
    public function setServer($server) {
        $this->server = $server;
    }

    /*!
     \brief	Returns the name of the server associated with the Job
     \return	server name
    */
    public function server() {
        return $this->server;
    }
    
    /*!
     \brief	Returns the script generated for the Job
     \return	script
    */
    public function script() {
        return $this->script;
    }
    
    /*!
     \brief	Returns the process identifier associated with the Job
     \return	process identifier
    */
    public function pid() {
        return $this->pid;
    }
    
    /*!
     \brief	Returns the Job id
     \return	Job id
    */
    public function id() {
        $desc = $this->description();
        return $desc->id();
    }
    
    /*!
     \brief	Sets the process identifier associated with the Job
     \param	$pid	Process identifier
    */
    public function setPid($pid) {
        $this->pid = $pid;
    }
    
    /*!
     \brief	Returns the Job status
     \return	Job status
    */
    public function status() {
        return $this->status;
    }

    /*!
     \brief	Sets the status of the Job
     \param	$status	Status of the Job
    */
    public function setStatus($status) {
        $this->status = $status;
    }

    /*!
     \brief	Creates a script
    */
    public function createScript() {   
        $jobDescription = $this->description();
        $jobTranslation = new HuygensTemplate($jobDescription);
        $this->script = $jobTranslation->template;
    }

    /*!
     \brief	Returns the script name (it contains the id to make it univocal)
     \return	the sript name
    */
    public function scriptName() {
        $desc = $this->description();
        $result = ".hrm_" . $desc->id() . ".tcl";
        return $result;
    }
    
    /*!
     \brief	Creates a script for elementary jobs or splits compound jobs
     \return	for elementary jobs, returns true if the script was generated
     successfully, or false otherwise; for compound jobs, it always
     returns false
    */
    public function createSubJobsOrScript() {
        $result = True;
        $desc = $this->jobDescription;

        if ($desc->isCompound()) {
            $result = $result && $desc->createSubJobs();
            if ($result) {
                error_log("created sub jobs");
                report("created sub jobs", 1);
            }
            if ($result) {
                $queue = new JobQueue();
                $result = $result && $queue->removeJob($desc);
                if ($result)
                    error_log("removed compound job");
                report("removed compound job\n", 1);
                // TODO: check if this does fix compound job processing
                $result = False;
            }
        } else {
            report("Job is elementary", 1);
            $this->createScript();
            report("Created script", 1);
            $result = $result && $this->writeScript();
        }
        return $result;
    }

    /*!
     \brief	Writes the script to the user's source folder
     \return	true if the script could be written, false otherwise
    */
    public function writeScript() {
        $result = True;
        $desc = $this->description();
        $scriptName = $this->scriptName();
        $user = $desc->owner();
        $username = $user->name();
        $fileserver = new Fileserver($username);
        $scriptPath = $fileserver->sourceFolder();
        $scriptFile = $scriptPath . "/" . $scriptName;
        $file = fopen($scriptFile, "w");
        if (! $file ) {
            report ("Error opening file $scriptFile, verify permissions!", 0);
            report ("Waiting 15 seconds...", 1);
            sleep(15);
            return False;
        } else {
            $result = $result && (fwrite($file, $this->script) > 0);
            fclose($file);
            report("Wrote script $scriptFile", 1);
        }
        return $result;
    }

    /*!
     \brief	Checks whether the result image is present in the destination directory
     \return	true if the result image could be found, false otherwise
     \todo Refactor
    */
    public function checkResultImage() {
        global $imageProcessingIsOnQueueManager;
        global $copy_images_to_huygens_server;
        global $huygens_user;
        global $huygens_group;
        global $huygens_server_image_folder;
        global $image_destination;
        
        clearstatcache();

        // Server name without proc number
        $server = $this->server;
        $s = split(" ", $server);
        $server_hostname = $s[0];
        
        $desc = $this->description();
        $user = $desc->owner();
        
        $fileserver = new Fileserver($user->name());
        $path = $fileserver->destinationFolderFor($desc);

        // TODO refactor JobDescription
        $destFileName = $desc->destinationImageNameWithoutPath();
        //$resultImage = $desc->sourceImageShortName() . "*" . "_" .
        //$desc->id() . "*";

        // If fileshare is not on the same host as Huygens
        if (!$imageProcessingIsOnQueueManager && $copy_images_to_huygens_server) {
            $image = $huygens_server_image_folder . $user->name() .
            	"/" . $image_destination . "/" .
                $desc->relativeSourcePath() . $destFileName .  "*";
            $previews = $huygens_server_image_folder;
            $previews .= $user->name() . "/" . $image_destination . "/";
            $previews .= $desc->relativeSourcePath() . "hrm_previews/";
            $previews .= "*" . $desc->id() . "_hrm*";
            // escape special characters in image path
            $image = eregi_replace(" ", "\\ ", $image);
            $image = str_replace(".ics",".i*s", $image);
            $previews = eregi_replace(" ", "\\ ", $previews);

            $result = exec("sudo mkdir -p " . escapeshellarg($path));
            $result = exec("sudo mkdir -p " . escapeshellarg($path)
                           . "/hrm_previews");
            
            $result = exec("(cd " . escapeshellarg($path) . 
                    " && sudo scp " . $huygens_user . "@" . $server_hostname . 
                    ":" . escapeshellarg($image) . " .)");
            $result = exec("(cd " . escapeshellarg($path) .
                    "/hrm_previews && sudo scp " . $huygens_user . "@" . 
                    $server_hostname . ":" . escapeshellarg($previews) . " .)");
            
            //error_log($result);
        }
        
        // TODO is checking for job id only a good idea?
        // HuCore replaces blanks with underscores.
        $path = str_replace(" ","_",$path);
        $fileNameExists = $fileserver->folderContains($path, $destFileName);
        
        // TODO is checking for new files a relevant criterion?
        $result = $fileNameExists/* || $newFileWritten*/;
        if (!$result) {
            report("Problem: no result file $destFileName in destination directory $path", 0);
        } else { report("File $destFileName available", 2); }
        return $result;
    }

    /*!
     \brief	Checks if the process is finished
     \return	true if the process is finished, false otherwise
     \todo Refactor
    */
    public function checkProcessFinished() {
        global $imageProcessingIsOnQueueManager;
        global $huygens_user;
        global $huygens_server_image_folder;
        global $image_source, $image_destination;
        
        clearstatcache();

        $this->shell = newExternalProcessFor($this->server(), 
                                             $this->server() . 
                                             "_" .$this->id() . "_out.txt", 
                                             $this->server() .  "_"
                                             . $this->id(). "_error.txt");
        $proc = $this->shell;
        $proc->runShell();

        // Server name without proc number
        $server = $this->server;
        $s = split(" ", $server);
        $server_hostname = $s[0];

        $desc = $this->description();
        $user = $desc->owner();
        
        $fileserver = new Fileserver($user->name());
        $path = $fileserver->sourceFolder();
        $dpath = $fileserver->destinationFolderFor($desc);

        $finishedMarker = $desc->destinationImageName() . ".hgsb";
        $endTimeMarker = ".EstimatedEndTime_" . $desc->id();



        // If fileshare is not on the same host as Huygens.
        if (!$imageProcessingIsOnQueueManager) {

            // Old code: to be removed.
            // $marker = $huygens_server_image_folder . $user->name() .
            // "/" . $image_destination . "/" . $finishedMarker;

            // Copy the finished marker
            $marker = $dpath . $finishedMarker;
            $remoteFile = exec("ssh " . $huygens_user . "@" .
                               $server_hostname . " ls " . $marker);

            // Old code: to be removed.
            //error_log("ssh " . $huygens_user . "@" . $server_hostname . "
            //ls " . $marker);
            //error_log($result);

            // TODO: is the queue manager a sudoer?
            if ($remoteFile == $marker) {
                if (!file_exists($dpath)) {
                    $result = exec("sudo mkdir -p " . escapeshellarg($dpath));
                }
                exec("(cd " . $dpath . " && sudo scp " . $huygens_user . "@"
                     . $server_hostname . ":" . $marker . " .)");

                $this->filterHuygensOutput();
            }
        }

        $result = file_exists($dpath . $finishedMarker);

        if ($imageProcessingIsOnQueueManager) {

            $result = !$proc->existsHuygensProcess($this->pid());
            
            // Notice that the job is finished if $result = true.
            if (!$result && $proc->isHuygensProcessSleeping($this->pid())) {
                $proc->rewakeHuygensProcess($this->pid());
            } elseif ($result) {
                $this->filterHuygensOutput();
            }
        }
        
        if ( !$result && file_exists($path . '/' . $endTimeMarker) ) {

            // Tasks may report an estimated end time, whenever they can.
            $estEndTime = file_get_contents($path . '/' . $endTimeMarker);
            report("Estimated end time for ". $desc->id(). ": $estEndTime", 1);
            $queue = new JobQueue();
            $queue->updateEstimatedEndTime($desc->id(), $estEndTime );
            
            // Delete the end time file, to only look at it when the
            // estimation is updated.
            @unlink($path . '/' . $endTimeMarker);
        }

        return $result;
    }


    /* -------- Utilities for renaming and formatting job specific files ------ */

    /*!
     \brief       Filters the Huygens deconvolution output leaving
     \brief       one file containing html parsed deconvolution parameters.
    */
    private function filterHuygensOutput( ) {

        /* Set file names. */
        $historyFile = $this->destImage . $this->pipeProducts["history"];
        $tmpFile     = $this->destImage . $this->pipeProducts["tmp"];
        $paramFile   = $this->destImage . $this->pipeProducts["parameters"];
        $huygensOut  = dirname($this->destImage)."/".$this->pipeProducts["main"];

        /* The Huygens history file will be removed. */
        $this->shell->removeFile($historyFile);

        /* The Huygens job main file will be given a job id name. */
        $this->shell->renameFile($huygensOut,$tmpFile);

        /* TODO: find workaround. The multiserver configuration has latency 
         between renaming and reading files. A sleep(5) fixes it. */
        sleep(1);

        /* Read the Huygens output file and make an html table from it. */
        $jobReport = $this->shell->readFile($tmpFile);
        if (!empty($jobReport)) {
            $parsedParam = $this->HuReportFile2Html($jobReport);
            $this->copyString2File($parsedParam,$paramFile);
            $this->shell->copyFile2Host($paramFile);
            $this->shell->removeFile($tmpFile);
        }
    }

    /*!
     \brief       Formats data from the Huygens report file as html output.
     \param       $reportFile The contents of the report file in an array.
     \return      The parameters in a formatted way.
    */
    private function HuReportFile2Html ($reportFile) {
        
        /* Insert a title and an explanation. */
        $title = "<b><u>Parameters used during deconvolution</u></b><br />";
        $text  = "<br />Those parameters that were <b>missing</b> in your ";
        $text .= "settings are highlighted in <b>green</b>. Alternative <br />";
        $text .= "values found in the metadata of the image were used ";
        $text .= "instead, please examine their <b>validity</b>.<br />";

        $div   = $title;
        $div  .= $text;
        $html  = $this->writeWarning($reportFile);
        $html .= $this->insertDiv($div);

        /* Insert the summary tables. */
        $div   = $this->writeImageParamTable($reportFile);
        $div  .= $this->writeRestoParamTable();
        $html .= $this->insertDiv($div,"jobParameters");

        return $html;
    }

    /*!
     \brief       Parses the Huygens deconvolution output to look for warnings.
     \param       $reportFile An array with the contents of the file.
     \return      A string with the formatted table.
    */
    private function writeWarning($reportFile) {

        $warning = "";

        /* Extract data from the file and into the table. */
        $pattern  = "/{Microscope conflict for channel ([0-9]):(.*)}/";
        foreach ($reportFile as $reportEntry) {
            if (!preg_match($pattern,$reportEntry,$matches)) {
                continue;
            }

            $channel = $matches[1];
            $warning .= "<p><b><u>WARNING</u>:</b>";
            $warning .= " The <b>microscope type</b> selected in this ";
            $warning .= "deconvolution job <b>may be<br />incorrect</b> as it ";
            $warning .= "does not match the microscope type stored in the file";
            $warning .= "<br />metadata. Notice that the restoration process";
            $warning .= "may lead to <b>wrong results</b><br />if the ";
            $warning .= "microscope type is not selected properly.";
            $warning  = $this->insertCell($warning,"text"); 
            $warning  = $this->insertTable($warning);
            $warning  = $this->insertDiv($warning,"warning");
            $warning .= "<br />";
            break;
        }   

        return $warning;
    }
    
    /*!
     \brief       Parses the Huygens deconvolution output file into a table.
     \param       $reportFile An array with the contents of the file.
     \return      A string with the formatted table.
    */
    private function writeImageParamTable($reportFile) {

        /* Insert the column titles. */
        $row   = $this->insertCell("Image Parameters","header",4);
        $table = $this->insertRow($row);

        $row    = $this->insertCell("Parameter","param");
        $row   .= $this->insertCell("Channel","channel");
        $row   .= $this->insertCell("Source","source");
        $row   .= $this->insertCell("Value","value");
        $table .= $this->insertRow($row);
     
        /* Extract data from the file and into the table. */
        $pattern  = "/{Parameter ([a-z]+?) (of channel ([0-9])\s|)(.*) ";
        $pattern .= "(template|metadata): (.*).}}/";

        foreach ($reportFile as $reportEntry) {
            if (!preg_match($pattern,$reportEntry,$matches)) {
                continue;
            }

            $paramName = $matches[1];
            $paramText = $this->imgParam[$paramName];
            $channel   = $matches[3];
            $source    = $matches[5];
            $value     = $matches[6];

            /* The parameter has no counterpart in the HRM GUI yet. */
            if ($paramText == "") {
                continue;
            }
                
            if ($source == "template") {
                $source = "User defined";
                $style = "userdef";
            } else {
                $source = "File metadata";
                $style = "metadata";
            }
            if ($channel == "") {
                $channel = "All";
            }
                
            /* Insert data into the table. */
            $row  = $this->insertCell($paramText,$style);
            $row .= $this->insertCell($channel,$style);
            $row .= $this->insertCell($source,$style);
            $row .= $this->insertCell($value,$style);
            $table .= $this->insertRow($row);
        }

        return $this->insertTable($table);
    }

    /*!
     \brief      Parses restoration data set by the user into a table.
     \return     A string with the formatted table.
    */
    private function writeRestoParamTable( ) {

        /* Retrieve restoration data set by the user. */
        $taskSettings = $this->jobDescription->taskSetting()->displayString();
        
        /* Insert the column titles. */
        $row   = $this->insertCell("Restoration Parameters","header",4);
        $table = $this->insertRow($row);

        $row    = $this->insertCell("Parameter","param");
        $row   .= $this->insertCell("Channel","channel");
        $row   .= $this->insertCell("Source","source");
        $row   .= $this->insertCell("Value","value");
        $table .= $this->insertRow($row);

        /* This table contains no metadata. It's all defined by the user. */
        $source = "User defined";
        $style  = "userdef";

        /* Extract data from the restoration parameters and into the table. */
        foreach ($this->restParam as $paramName => $paramText) {

            $pattern  = "/(.*)$paramName(.*):";
            $pattern .= "\s+([0-9\,\s]+|[a-zA-Z0-9]+\s?[a-zA-Z0-9]*)/";
            if (!preg_match($pattern,$taskSettings,$matches)) {
                continue;
            }

            /* Multichannel parameters are separated by ','. */
            $paramValues = explode(",",$matches[3]);

            if (count($paramValues) == 1) {
                $channel = "All";
            } else {
                $channel = 0;
            }

            /* Insert data into the table. */
            foreach ($paramValues as $paramValue) {
                $row    = $this->insertCell($paramText,$style);
                $row   .= $this->insertCell($channel,$style);
                $row   .= $this->insertCell($source,$style);
                $row   .= $this->insertCell(trim($paramValue),$style);
                $table .= $this->insertRow($row);    

                if (is_int($channel)) {
                    $channel++;
                }
            }
        }

        return $this->insertTable($table);
    }

    /* ----------------------- HTML formatting utilities ----------------------- */

    /*!
     \brief       Adds a new cell to an html table.
     \param       $content The data that will be shown in the cell.
     \param       $style   One of the cell styles specified in the CSS files.
     \colspan     $colspan The number of columns that will be taken up by the cell.
     \return      A string with the formatted html code.
    */
    private function insertCell($content,$style,$colspan=null) {

        if (!isset($colspan)) {
            $colspan = 1;
        }
        
        $cell  = "<td class=\"$style\" colspan=\"$colspan\">";
        $cell .= $content;
        $cell .= "</td>";

        return $cell;
    }

    /*!
     \brief       Adds a new row to an html table.
     \param       $content The data that will be shown in the row.
     \return      A string with the formatted html code.
    */
    private function insertRow($content) {
        $row  = "<tr>";
        $row .= $content;
        $row .= "</tr>";

        return $row;
    }

    /*!
     \brief       Adds a new html table.
     \param       $content The data that will be shown in the table.
     \return      A string with the formatted html code.
    */
    private function insertTable($content) {
        $table  = "<br /><table>";
        $table .= $content;
        $table .= "</table><br />";

        return $table;
    }

    /*!
     \brief       Adds a new html div.
     \param       $content The data that will be shown in the table.
     \param       $id The id for the div.
     \return      A string with the formatted html code.
    */
    private function insertDiv($content,$id=null) {

        if (!isset($id)) {
            $div  = "<div>";
            $div .= $content;
            $div .= "</div>";
        } else {
            $div  = "<div id=\"$id\">";
            $div .= $content;
            $div .= "</div>";
            $div .= " <!-- $id -->";
        }

        return $div;
    }

    /* --------------------------- Small utilities --------------------------- */

    /*!
     \brief       Copies a string to a file.
     \param       $string A variable containing a string.
     \param       $file The path and file where to copy the string.
    */
    private function copyString2File($string,$file) {
        $copy2File = fopen($file, 'w');
        fwrite($copy2File,$string);
        fclose($copy2File);
    }
}

?>

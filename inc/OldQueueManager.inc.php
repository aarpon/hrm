<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt


/* -------------------------- IMPORTANT ----------------------------
The code in this  file  is legacy code which will be replaced
by the new QM implemented  in Python/GC3Pie. Most of the original
code of this QueueManager has been removed to avoid confusion.
The remaining code will eventually be removed as well, although
it has been temporarily saved to use the functions that
populated the database on the first Queue Manager run. Other functions
related to notifications and emails can still be found below for
future reference. */

require_once("System.inc.php");
require_once("Database.inc.php");
require_once("JobQueue.inc.php");


/*!
 \class  QueueManager
 \brief  Creates Jobs from JobDescriptions and manages them in a priority queue
 */
class QueueManager {
    
    /*!
     \var   $queue
     \brief A JobQueue object
     */
    private $queue;


    /*!
    \brief	Constructor: creates an initializes the QueueManager
    */
    public function __construct() {
        $this->runningJobs = array();
        $this->queue = new JobQueue();
        $this->shallStop = False;
        $this->nping = array();
    }

    /*!
 	\brief	Runs the QueueManager
 	*/
 	public function run() {
        global $imageProcessingIsOnQueueManager;
        global $logdir;

        $queue = $this->queue;

        // Make sure that we have access to the database
        $this->waitForDatabaseConnection();

        if (!$this->askHuCoreVersionAndStoreIntoDB()) {
            error_log("An error occurred while reading HuCore version");
            return;
        }

        if (!$this->storeHuCoreLicenseDetailsIntoDB()) {
            error_log("An error occurred while saving HuCore license details");
            return;
        }

        if (!$this->storeConfidenceLevelsIntoDB()) {
            error_log("An error occurred while storing the confidence " .
                "levels in the database");
            return;
        }
    }

    /*
        PRIVATE FUNCTIONS
 	*/

    /*!
 \brief	Waits until a DatabaseConnection could be established
 */
    private function waitForDatabaseConnection() {
        $isDatabaseReachable = False;
        while (!$isDatabaseReachable) {
            $db = new DatabaseConnection();
            if ($db->isReachable()) {
                $isDatabaseReachable = True;
            }
        }
    }

    /*!
 	\brief	Asks HuCore to provide its version number and store it in the DB
 	\return true if asking the version and storing it in the database was
            successful, false otherwise
    */
 	private function askHuCoreVersionAndStoreIntoDB() {
            $huversion = askHuCore("reportVersionNumberAsInteger");
            $huversion = $huversion["version"];
            report("HuCore version = " . $huversion . "\n", 2);
            if (!System::setHuCoreVersion($huversion)) {
                return false;
            }
            return true;
        }

    /*!
         \brief   Gets license details from HuCore and saves them into the db.
         \return  Boolean: true if everything went OK.
        */
    private function storeHuCoreLicenseDetailsIntoDB( ) {
        $licDetails = askHuCore("reportHuCoreLicense");

        // Store the license details in the database.
        $db = new DatabaseConnection();
        if (!$db->storeLicenseDetails($licDetails['license'])) {
            report("Could not store license details in the database!\n", 1);
            return false;
        }

        return true;
    }
            

    /*!
 	\brief	Store the confidence levels returned by huCore into the database
            for faster retrieval
 	\return true if asking the version and storing it in the database was
            successful, false otherwise
 	*/
	private function storeConfidenceLevelsIntoDB() {

        // Get the confidence levels string from HuCore
        $result = askHuCore("reportFormatInfo");
        $confidenceLevelString = $result["formatInfo"];

        // Parse the confidence levels string
        $confidenceLevels =
            $this->parseConfidenceLevelString($confidenceLevelString);

        // Store the confidence levels in the database
        $db = new DatabaseConnection();
        if (!$db->storeConfidenceLevels($confidenceLevels)) {
            report("Could not store confidence levels to the database!\n", 1);
            return false;
        }
        return true;
    }

    /*!
 \brief	Prepares the text with the summary of the Job parameters to be sent
        to the user
 \param	$job		A Job object
 \return	the text to be later sent by email
*/
    private function parameterText(Job $job) {
        $desc = $job->description();
        $result = '';

        $result = $result . "\nImage parameters:\n\n";
        $parameterSetting = $desc->parameterSetting();
        $parameterSettingString = $parameterSetting->displayString();
        $result = $result . $parameterSettingString;

        $result = $result . "\nRestoration parameters:\n\n";
        $taskSetting = $desc->taskSetting();
        $numberOfChannels = $taskSetting->numberOfChannels();
        $taskSettingString = $taskSetting->displayString($numberOfChannels);
        $result = $result . $taskSettingString;

        $result = $result . "\nAnalysis parameters:\n\n";
        $analysisSetting = $desc->analysisSetting();
        $analysisSettingString = $analysisSetting->displayString();
        $result = $result . $analysisSettingString;

        return $result;
    }

    /*!
    \brief	Parse the string returned by HuCore containing the confidence
            levels for parameters and return them in an array
    \return an array containing the parameter confidence levels
 	*/
	private function parseConfidenceLevelString($confidenceLevelString) {

        // Break down the string into per-file substrings
        $confidenceLevelString = str_replace('}}', '}}<CUT_HERE>',
            $confidenceLevelString);

        $groups = explode('<CUT_HERE> ', $confidenceLevelString);

        // Prepare the output array
        $confidenceLevels = array();

        // Confidence level regexp
        $levelRegExp = '(asIs|reported|estimated|default|verified)';

        // Process the substrings
        foreach ($groups as $group) {

            $match = array();
            preg_match("/(\A\w{2,16})(\s{1,2})(\{sampleSizes\s\{.+)/",
                $group, $match);

            // Get the parts
            if ((!isset($match[1]) ) || (!isset($match[3]) )) {
                $msg = "Could not parse confidence levels!";
                report($msg, 1);
                exit($msg);
            }
            $fileFormat = $match[1];
            $parameters = $match[3];

            // Prepare the parameter array
            $params = array(
                "fileFormat" 	 => "none",
                "sampleSizesX"	 => "default",
                "sampleSizesY"	 => "default",
                "sampleSizesZ"	 => "default",
                "sampleSizesT"	 => "default",
                "iFacePrim" 	 => "default",
                "iFaceScnd" 	 => "default",
                "pinhole"        => "default",
                "chanCnt"        => "default",
                "imagingDir" 	 => "default",
                "pinholeSpacing" => "default",
                "objQuality" 	 => "default",
                "lambdaEx" 	     => "default",
                "lambdaEm"       => "default",
                "mType"          => "default",
                "NA"             => "default",
                "RIMedia" 	     => "default",
                "RILens"         => "default",
                "photonCnt" 	 => "default",
                "exBeamFill" 	 => "default",
                "stedMode"       => "default",
                "stedLambda"     => "default",
                "stedSatFact"    => "default",
                "stedImmunity"   => "default",
                "sted3D"         => "default");

            // To enable parsing of sted parameters in a loop.
            $stedParams = array("stedMode",
                                "stedLambda",
                                "stedSatFact",
                                "stedImmunity",
                                "sted3D");

            // Store the file format
            $params['fileFormat'] = $fileFormat;

            // Parse the parameters
            // Sample sizes
            $exp = "/\{sampleSizes\s\{" . $levelRegExp . "\s" . $levelRegExp .
                    "\s" . $levelRegExp . "\s" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['sampleSizesX'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter sampleSizesX!";
                report($msg, 1);
                exit($msg);
            }
            if (isset($match[2])) {
                $params['sampleSizesY'] = $match[2];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter sampleSizesY!";
                report($msg, 1);
                exit($msg);
            }
            if (isset($match[3])) {
                $params['sampleSizesZ'] = $match[3];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter sampleSizesZ!";
                report($msg, 1);
                exit($msg);
            }
            if (isset($match[4])) {
                $params['sampleSizesT'] = $match[4];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter sampleSizesT!";
                report($msg, 1);
                exit($msg);
            }

            // iFacePrim
            $exp = "/iFacePrim\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['iFacePrim'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter iFacePrim!";
                report($msg, 1);
                exit($msg);
            }

            // iFaceScnd
            $exp = "/iFaceScnd\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['iFaceScnd'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter iFaceScnd!";
                report($msg, 1);
                exit($msg);
            }

            // pinhole
            $exp = "/pinhole\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['pinhole'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter pinhole!";
                report($msg, 1);
                exit($msg);
            }

            // chanCnt
            $exp = "/chanCnt\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['chanCnt'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter chanCnt!";
                report($msg, 1);
                exit($msg);
            }

            // imagingDir
            $exp = "/imagingDir\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['imagingDir'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter imagingDir!";
                report($msg, 1);
                exit($msg);
            }

            // pinholeSpacing
            $exp = "/pinholeSpacing\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['pinholeSpacing'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter pinholeSpacing!";
                report($msg, 1);
                exit($msg);
            }

            // objQuality
            $exp = "/objQuality\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['objQuality'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter objQuality!";
                report($msg, 1);
                exit($msg);
            }

            // lambdaEx
            $exp = "/lambdaEx\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['lambdaEx'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter lambdaEx\!";
                report($msg, 1);
                exit($msg);
            }

            // lambdaEm
            $exp = "/lambdaEm\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['lambdaEm'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter lambdaEm\!";
                report($msg, 1);
                exit($msg);
            }

            // mType
            $exp = "/mType\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['mType'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter mType\!";
                report($msg, 1);
                exit($msg);
            }

            // NA
            $exp = "/NA\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['NA'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter NA\!";
                report($msg, 1);
                exit($msg);
            }

            // RIMedia
            $exp = "/RIMedia\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['RIMedia'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter RIMedia\!";
                report($msg, 1);
                exit($msg);
            }

            // RILens
            $exp = "/RILens\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['RILens'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter RILens\!";
                report($msg, 1);
                exit($msg);
            }

            // photonCnt
            $exp = "/photonCnt\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['photonCnt'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter photonCnt\!";
                report($msg, 1);
                exit($msg);
            }

            // exBeamFill
            $exp = "/exBeamFill\s\{" . $levelRegExp . "\}/";
            $match = array();
            preg_match($exp, $parameters, $match);
            if (isset($match[1])) {
                $params['exBeamFill'] = $match[1];
            } else {
                $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter exBeamFill\!";
                report($msg, 1);
                exit($msg);
            }

            // sted parameters
            foreach ($stedParams as $stedParam) {
                $exp = "/$stedParam\s\{" . $levelRegExp . "\}/";
                $match = array();
                preg_match($exp, $parameters, $match);
                if (isset($match[1])) {
                    $params[$stedParam] = $match[1];
                } else {
                    $msg = "Could not find confidence level for file format " .
                        $fileFormat . " and parameter $stedParam\!";
                    report($msg, 1);
                    exit($msg);
                }
            }

            // Store the parameters for current file format
            $confidenceLevels[$fileFormat] = $params;
        }

        return $confidenceLevels;
    }

    /* ---------------------- Start of legacy code. -------------------- */


    /*!
  \brief	Assembles the job log to be displayed in the file manager.

  The job log contains the HuCore output and error log and some additional
 information.

  \param	$job		A Job object
  \param	$startTime	Start time of the Job
  \param	$logFile	Full path to the log file
  \param	$errorFile	Full path to the errorlog file
  */
    public function assembleJobLogFile($job, $startTime, $logFile, $errorFile) {
        global $imageProcessingIsOnQueueManager;
        $result = False;
        $desc = $job->description();
        $imageName = $desc->destinationImageName();
        $id = $desc->id();
        $pid = $job->pid();
        $server = $job->server();
        $user = $desc->owner();
        $username = $user->name();
        $fileserver = new Fileserver($username);
        $path = $fileserver->destinationFolderFor($desc);
        $path = str_replace(" ", "_", $path); // HuCore path naming standard.

        // Message
        $text = '';
        $text .= "Job id: $id (pid $pid on $server), started " .
            "at $startTime and finished at " . date("Y-m-d H:i:s") . "\n\n";

        if (file_exists($errorFile))
            $text .= "- HUYGENS ERROR REPORT (stderr) --------------\n\n" .
                file_get_contents($errorFile);
        if (file_exists($logFile))
            $text .= "- HUYGENS REPORT (stdout) --------------------\n\n" .
                file_get_contents($logFile);

        // Save the log to file
        $parameterFileName = $path . $imageName . '.log.txt';
        $file = fopen($parameterFileName, "w");
        $result = !$result && (fwrite($file, $text) > 0);
        fclose($file);

        if (!$imageProcessingIsOnQueueManager) {
            $this->restoreOwnership($username);
        }

        return $result;
    }

    /*!
 	\brief	Sends an e-mail to the User notifying a successful Job
 	\param	$job		A Job object
    \param	$startTime	Start time of the Job
    */
    public function notifySuccess($job, $startTime) {
        global $email_sender;
        global $hrm_url;
        global $useThumbnails;
        $desc = $job->description();
        $user = $desc->owner();
        $emailAddress = $user->emailAddress();
        $sourceFileName = $desc->sourceImageNameWithoutPath();
        $destFileName = $desc->destinationImageNameWithoutPath();
        $text = "This is a mail generated automatically by " .
            "the Huygens Remote Manager.\n\n";
        $text .= "The image $sourceFileName was successfully " .
            "processed by Huygens.\n";
        $text .= "Your job started at $startTime and finished at " .
            date("Y-m-d H:i:s") . ".\n";
        $text .= "You will find the resulting image ($destFileName) " .
            "in your destination folder.\n";

        // Create link based on the job id.
        $imageName = $desc->destinationImageNameAndExtension();
        $namePattern = "/(.*)_(.*)_(.*)\.(.*)$/";
        preg_match($namePattern, $imageName, $matches);
        $jobName = str_replace($matches[1] . "_", "", $imageName);

        // Stick to the HuCore path naming standard.
        $dirname = str_replace(" ", "_", dirname($imageName));
        $linkName = $dirname . "/" . $jobName;

        if ($useThumbnails) {
            $link = $hrm_url . "/file_management.php?compareResult=" .
                urlencode($linkName);
            $text .= "\nDirect link to your deconvolution result: " . $link .
                " (login required).\n\n";
        }
        $text .= "Best regards,\nHuygens Remote Manager\n\n";

        // Send an email
        $mail = new Mail($email_sender);
        $mail->setReceiver($emailAddress);
        $mail->setSubject('Your HRM job finished successfully');
        $mail->setMessage($text);
        $mail->send();
    }

    /*!
    \brief	Sends an e-mail to the User and Admin notifying a failed Job
    \param	$job		A Job object
    \param	$startTime	Start time of the Job
    */
    public function notifyError($job, $startTime) {
        global $email_sender;
        global $email_admin;
        global $logdir;


        /* Definitions: relevant files. */
        $basename  = $logdir . "/" . $job->server() . "_" . $job->id();
        $errorFile = $basename . "_error.txt";
        $logFile   = $basename . "_out.txt";

        /* Definitions: dataset name. */
        $desc = $job->description();
        $sourceFileName = $desc->sourceImageNameWithoutPath();

        // Definitions: job id, pid, server. */
        $id     = $desc->id();
        $pid    = $job->pid();
        $server = $job->server();
        $template = $job->createTemplate();

        /* Email destination. */
        $user = $desc->owner();
        $emailAddress = $user->emailAddress();


        $mailContent  = "\nThis is a mail generated automatically by ";
        $mailContent .= "the Huygens Remote Manager.\n\n";

        $mailContent .= "Sorry, the processing of the image \n";
        $mailContent .= $sourceFileName . "\nhas been terminated with ";
        $mailContent .= "an error.\n\n";

        $mailContent .= "Best regards,\nHuygens Remote Manager\n";

        /* The error should be shown up in the email. */
        if (file_exists($errorFile)) {
            $mailContent .= "\n\n-HUYGENS ERROR REPORT (stderr) --------------";
            $mailContent .= "\n\n" . file_get_contents($errorFile);
        }

        $mailContent .= "\n\n- USER PARAMETERS -----------------------------";
        $mailContent .= "------\n\n";
        $mailContent .= "These are the parameters you set in the HRM:\n\n";
        $mailContent .= $this->parameterText($job);

        $mailContent .= "\n\n-TEMPLATE -------------------------------------";
        $mailContent .= "------\n\n";
        $mailContent .= "What follows is the Huygens Core template executed ";
        $mailContent .= "when the error occured:\n\n";
        $mailContent .= $job->template();
        $mailContent .= "\n\n-----------------------------------------------";
        $mailContent .= "------\n\n";

        if (file_exists($logFile)) {
            $mailContent .= "\n\n-HUYGENS REPORT (stdout) --------------------";
            $mailContent .= "\n\n" . file_get_contents($logFile);
        }

        $mailContent .= "\n\n-PROCESS DETAILS-------------------------------";
        $mailContent .= "------\n\n";

        $mailContent .= "Your job started on $startTime and failed ";
        $mailContent .= "on " . date("Y-m-d H:i:s") . ".\n";

        $mailContent .= "Job id: $id (pid $pid on $server)\n";


        /* Send the error mail to the user. */
        $mail = new Mail($email_sender);
        $mail->setReceiver($emailAddress);
        $mail->setSubject('Your HRM job finished with an error');
        $mail->setMessage($mailContent);
        $mail->send();

        /* Also notify the error to the admin. */
        $mail->setReceiver($email_admin);
        $mail->setSubject('An HRM job from user "' . $user->name() .
            '" finished with an error.');

        $mail->send();
    }

}
?>

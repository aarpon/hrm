<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("hrm_config.inc");
require_once("Fileserver.inc");

global $hucore, $hutask, $logdir;

$hucore = "hucore";
$hutask = "-noExecLog -checkUpdates disable -template";

/*
 ============================================================================
 */

/*!
 \class	newExternalProcessFor
 \brief Runs a new shell either with or without secure connection between the
 		queue manager and the Image area
 		
 Which of the two modes is chosen depends on the value of the configuration 
 variable $imageProcessingIsOnQueueManager.
 
 \todo	Implement better management of multiple hosts
 */
function newExternalProcessFor($host, $logfilename, $errfilename) {
  global $imageProcessingIsOnQueueManager;
  $db = new DatabaseConnection();
  $huscript_path = $db->huscriptPathOn($host);
  if ($imageProcessingIsOnQueueManager)
  $shell = new LocalExternalProcess($host, $huscript_path, $logfilename, $errfilename);
  else
  $shell = new ExternalProcess($host, $huscript_path, $logfilename, $errfilename);
  return $shell;
}

/*
 ============================================================================
 */

/*!
 \class  ExternalProcess
 \brief  Launches tasks on a shell on another host (via secure connection)
 */
class ExternalProcess {

  /*!
   \var 	$pid
   \brief	OS Identifier for the Process
   */
  public $pid;

  /*!
   \var 	$host
   \brief	Host on which the process will be started
   \todo	Implement better management of multiple hosts
   */
  public $host;

  /*!
   \var 	$huscript_path
   \brief	HuCore full executable path on host
   \todo	For historical reason, HuCore is still referred to as huscript
   */
  public $huscript_path;

  /*!
   \var 	$pipes
   \brief	Pipes for communication with the process
   */
  public $pipes;

  /*!
   \var 	$shell
   \brief	The shell process resource
   */
  public $shell;

  /*!
   \var 	$logfileName
   \brief	Name of the process log (relative to the gloabl $logdir)
   */
  public $logfileName;

  /*!
   \var 	$errfileName
   \brief	Name of the process error log (relative to the gloabl $logdir)
   */
  public $errfileName;

  /*!
   \var 	$out_file
   \brief	Handle for the output file
   */
  public $out_file;

  /*!
   \var 	$descriptorSpec
   \brief	File descriptors to open in the shell
   */
  public $descriptorSpec;

  /*!
   \brief	Constructor: sets all shell pipes and file descriptors for given host
   \param	$host	Host on which the process will be started. All communication
   					to the host will happen via secure connection
   \param	$huscript_path	HuCore full executable path on host
   \param	$logfileName	Name of the process log (relative to the gloabl $logdir)
   \param	$errfileName	Name of the process error log (relative to the gloabl $logdir)
   */
  public function __construct($host, $huscript_path, $logfileName, $errfileName) {
    global $logdir;
    // Make sure to save into the log dir
    $this->logfileName = $logdir . "/" . $logfileName;
    $this->errfileName = $logdir . "/" . $errfileName;
    $this->descriptorSpec = array(
    0 => array("pipe", "r"),                      		// STDIN
    1 => array("file", $this->logfileName, "a"),      	// STDOUT
    2 => array("file", $this->errfileName, "a") );		// STDERR
    $this->host = $host;
    $this->huscript_path = $huscript_path;
    if (strpos($host, " ")) {
      $components = explode(" ", $host);
      array_pop($components);
      $realHost = implode("", $components);
      $this->host = $realHost;
    }
    $this->pid = NULL;
  }

  /*!
   \brief	Checks whether an Huygens Process with given Process IDentifier exists
   \param	$pid	Process identifier as returned by the OS
   \return 	true if the process exists, false otherwise
   \todo	Refactor
   */
  public function existsHuygensProcess($pid) {
    global $logdir, $hucore;
    global $huygens_user;
    $answer = system("ssh $huygens_user" . '@' . $this->host . " " . "ps -p $pid | grep -e $hucore > " . $logdir . "/hrm_tmp" , $result); // -p
    if ($result==0) {return True;}
    return False;
  }

  /*!
   \brief	Checks whether the Huygens Process with given Process IDentifier is sleeping
   \param	$pid	Process identifier as returned by the OS
   \return 	true if the process is sleeping, false otherwise
   \todo	Refactor: why is this saving to hrm_tmp?
   */
  public function isHuygensProcessSleeping($pid) {
    global $logdir, $hucore;
    global $huygens_user;
    $answer = system("ssh $huygens_user" . '@' . $this->host . " " . "ps -lf -p " . "$pid | grep -e $hucore | grep -e S > " . $logdir . "/hrm_tmp",  $result);
    if ($result==0) {return True;}
    return False;
  }

  /*!
   \brief	Wakes up the Huygens Process with given Process IDentifier
   \param	$pid	Process identifier as returned by the OS
   */
  public function rewakeHuygensProcess($pid) {
    global $huygens_user;
    $answer = "none";
    ob_start();
    while ($answer != "") {
      $answer = system("ssh $huygens_user" . '@' . $this->host . " '" . "ps -Alfd | sort | grep sshd | grep $huygens_user" . "'", $result);
      $array = split('[ ]+', $answer);
      $pid = $array[3];
      $answer = system("ssh $huygens_user" . '@' . $this->host . " '" . "kill $pid" . "'", $result);
      if (!$this->existsHuygensProcess($pid)) { break; }
    }
    ob_end_clean();
  }

  /*!
   \brief	Pings the host
   \return 	true if pinging the host was successful, false otherwise
   \todo	Refactor: why is this saving to hrm_tmp?
   */
  public function ping() {
    global $logdir;
    global $ping_command;
    global $ping_parameter;
     
    $result = "";
    $command = $ping_command . " " . $this->host . " " . $ping_parameter . " > ". $logdir . "/hrm_tmp";
    $answer = system($command, $result);
    if ($result == 0)
    return True;
    return False;
  }

  /*!
   \brief	Returns the Process IDentifier of the Huygens process
   \return 	the pid of the process
   */
  public function pid() {
    return $this->pid;
  }

  /*!
   \brief	Starts the shell
   \return 		true if the shell started successfully, false otherwise
   */
  public function runShell() {
    global $huygens_user;

    $this->shell = proc_open("bash", $this->descriptorSpec, $this->pipes);
    if (!is_resource($this->shell)) {
      return False;
    }
    return True;
  }

  /*!
   \brief	Executes a command
   \param	$command	Command to be executed on the host
   \return 	true if the command was executed, false otherwise
   */
  public function execute($command) {
    global $huygens_user;

    $cmd = 'ssh -f ' . $huygens_user . "@" . $this->host . " '" . $command . " ' \n";

    $ret = fwrite($this->pipes[0], $cmd );
    fflush($this->pipes[0]);
    report ("$cmd: $ret", 2);
    if ( $ret ) {
      // Why exiting here? This is commented out by now.
      // $ret = fwrite($this->pipes[0], "exit\n");
    }

    if ( $ret === False ) {
      return False;
    } else {
      // Asume execution success!!
      return True;
    }
  }

  /*!
   \brief	Reads from STDOUT (the log file)
   \return 	the read buffer
   */
  public function read() {
    return fgets($this->pipes[1], 2048);
  }

  /*!
   \brief	Runs the Huygens template with a given name in the shell
   \param	$templateName	File name of the Huygens template
   \return 	the Process Identifier of the running task
   \todo        Improve the pid acquisition process
   */
  public function runHuygensTemplate($templateName) {
    global $hutask;
  
      $command = $this->huscript_path." $hutask \"".$templateName."\"";
      $command .= ' > /dev/null 2>&1 & echo $!'; 

      // TODO better management of file handles
      //$out_file = fopen($this->descriptorSpec[1][1], "r");
      $this->out_file = fopen($this->descriptorSpec[1][1], "r");
      fseek($this->out_file, 0, SEEK_END);
      
      $this->execute($command);
      sleep(1);
      $found = False;
      while(!$found) {
          // TODO refactor
          if (feof($this->out_file)) return False;
          $pid = fgets($this->out_file, 1024);
          $pid = intval($pid);
          if ($pid != -1 && $pid != 0) { 
            $found = True;
            $this->pid = $pid;
          }
      }

      return $pid;
  }

  /*!
   \brief	Check whether a Job with given Process IDentifier is running
   \param	$pid	Process IDentifier of the Job
   \return 	the PID if the Job is running, null otherwise
   */
  public function isJobWithPidRunning($pid) {
    $command = "ps -p $pid; ps -p $pid \n"; // -p
    $this->execute($command);
    $answer = '';
    $pipe = fopen($this->descriptorSpec[1][1], "r");
    fseek($pipe, 0, SEEK_END);
    $line = fgets($pipe, 1024);
    $answer = $answer . $line;
    if (!feof($pipe)) {
      $line = fgets($pipe, 1024);
      $answer = $answer . $line;
    }
    $result = (strstr($answer, "\n" . $pid . " "));
    return $result;
  }

  /*!
   \brief	Releases all files and pipes and closes the shell
   */
  public function release() {
    // close pipes
    fclose($this->pipes[0]);
    // TODO check why stdout and stderr are not closed
    @fclose($this->pipes[1]);
    @fclose($this->pipes[2]);
    // TODO better management of file handles
    @fclose($this->out_file);
    $result = proc_close($this->shell);
    report("released external process", 2);
  }

  /*!
   \brief	Kill the Huygens process with given Process IDentifier
   \param	$pid	Process IDentifier of the Job
   \return	true if the Job was killed, false otherwise
   */
  public function killHucoreProcess($pid) {
    $command = "kill " . $pid;
    $result = True;
    $result = $result && $this->runShell();
    if ($result) {
      $result = $result && $this->execute($command);
    }
    return $result;
  }
} // End of ExternalProcess class

/*
 ============================================================================
 */

/*!
 \class  LocalExternalProcess
 \brief  Launches tasks on a shell on this machine

 All command are local to the (queue manager) machine.
 */
class LocalExternalProcess extends ExternalProcess {

  /*!
   \brief	Constructor: sets all shell pipes and file descriptors
   \param	$host	This is not used (is only passed on to the parent constructor)
   \param	$huscript_path	HuCore full executable
   \param	$logfileName	Name of the process log (relative to the gloabl $logdir)
   \param	$errfileName	Name of the process error log (relative to the gloabl $logdir)
   */
  public function __construct($host, $huscript_path, $logfileName, $errfileName) {
    parent::__construct($host, $huscript_path, $logfileName, $errfileName);
  }

   
  /*!
   \brief	Checks whether an Huygens Process with given Process IDentifier exists
   \param	$pid	Process identifier as returned by the OS
   \return true if the process exists, false otherwise
   \todo	Refactor
   */

  public function existsHuygensProcess($pid) {
    global $huygens_user, $hucore;
    global $logdir;
    $answer = system("ps -p $pid | grep -e $hucore > ".$logdir."/hrm_tmp" , $result);
    if ($result==0) {
      return True;
    }
    return False;
  }

  /*!
   \brief	Checks whether the Huygens Process with given Process IDentifier is sleeping
   \param	$pid	Process identifier as returned by the OS
   \return true if the process is sleeping, false otherwise
   \todo	Refactor: why is this saving to hrm_tmp?
   */
  public function isHuygensProcessSleeping($pid) {
    //    global $huygens_user, $hucore;
    //    $answer = system("ps -lf -p " ."$pid | grep -e $hucore | grep -e S > hrm_tmp",  $result);
    //    if ($result==0) {return True;}
    return False;
  }

  /*!
   \brief	Wakes up the Huygens Process with given Process IDentifier
   \param	$pid	Process identifier as returned by the OS
   \todo	This function is currenly doing nothing.
   */
  public function rewakeHuygensProcess($pid) {
    // global $huygens_user;
    // hang up shouldn't happen with local external process
    // therefore nothing to do
  }

  /*!
   \brief	Executes a command
   \param	$command	Command to be executed on the host
   \return 	true if the command was executed, false otherwise
   */
  public function execute($command) {
    global $huygens_user;

    $ret = fwrite($this->pipes[0], $command . " & \n" );
    fflush($this->pipes[0]);
    if ($ret === false) {
      // Can't write to pipe.
      return False;
    } else {
      sleep(5);
      // Asume execution success!!
      return True;
    }

  }

  /*!
   \brief	Pings the host
   \return 	true always, since a machine should always be able to reach itself
   */
  public function ping() {
    // machine can always reach itself.
    return True;
  }

  /*!
   \brief	Starts the shell
   \return 	true if the shell started successfully, false otherwise
   */
  public function runShell() {
    global $huygens_user;
    $this->shell = proc_open("sh", $this->descriptorSpec, $this->pipes);
    if (!is_resource($this->shell)) {
      return False;
    }
    return True;
  }

  /*!
   \brief	Check whether a Job with given Process IDentifier is running
   \param	$pid	Process IDentifier of the Job
   \return 	the PID if the Job is running, null otherwise
   */
  public function isJobWithPidRunning($pid) {
    $command = "ps -p $pid; ps -p $pid \n";
    $this->execute($command);
    $answer = '';
    $pipe = $this->pipes[1];
    $line = fgets($pipe, 1024);
    $answer = $answer . $line;
    if (!feof($pipe)) {
      $line = fgets($pipe, 1024);
      $answer = $answer . $line;
    }
    $result = (strstr($answer, "\n" . $pid . " "));
    return $result;
  }

  /*!
   \brief	Kill the Huygens process with the given Process IDentifier and 
                its child, if it exists
   \param	$pid	Process IDentifier of the Job
   \return	true if the Job was killed, false otherwise
   */
  public function killHucoreProcess($pid) {

      // Kill the child, if it exists.
      $noChild = $this->killHucoreChild($pid);
      
      // Kill the parent.
      $noParent = posix_kill($pid,15);

      return ($noParent && $noChild);
  }

  /*!
   \brief       Kill the child of the Huygens process if it exists
   \param       $ppid Process Identifier of the parent
   \return      true if a child was killed or didn't exist, false otherwise
   */
  public function killHucoreChild($ppid) {

      // Get the pid of the child
      exec("ps -ef| awk '\$3 == '$ppid' { print  \$2 }'", $child, $error);

      if (!$error) {

          // Kill the child if it exists. Return true if it does not exist.
          if (array_key_exists(0,$child)) {
              $childPid = $child[0];
              if ($childPid > 0) {
                  $dead = posix_kill($childPid,15);  
              } else {
                  $dead = true;
              }
          } else {
              $dead = true;
          }
      } else {
          $dead = false;
      }

      return $dead;
  }

}  // End of LocalExternalProcess class

?>

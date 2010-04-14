<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("hrm_config.inc");
require_once("Fileserver.inc");

global $isHrmDevelop, $hucore, $hutask, $logdir;

if (!isset($isHrmDevelop) || $isHrmDevelop == false) {
    $hucore = "hucore";
    $hutask = "-checkUpdates disable -task";
} else {
    $hucore = "huscript";
    $hutask = "-noTk -script";
}

Class ExternalProcess {
    var $pid;                         // os pid of the process
    var $host;                        // host on which the process will be started
    // TODO better manage multiple hosts
    var $huscript_path;               // HuScript executable path on host
    var $pipes;                       // pipes for commiunication with the process
    var $shell;                       // The shell process resource
    var $logfileName;                 // Name of the process error log
    var $descriptorSpec;
    // TODO better manage file handles
    var $out_file;                    // handle for the output file


    // This is temporary, remove before the final release and use the
    // first case only.
        
    
    function ExternalProcess($host, $huscript_path, $logfileName, $errfileName) {
        global $logdir;
        // Make sure to save into the log dir
        $logfileName = $logdir . "/" . $logfileName;
        $errfileName = $logdir . "/" . $errfileName;
        $this->descriptorSpec = array(
                                      0 => array("pipe", "r"),
                                      1 => array("file", $logfileName, "a"),
                                      2 => array("file", $errfileName, "a")
                                      );
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
    
    // TODO refactor
    function existsHuygensProcess($pid) {
        global $logdir, $hucore;
        global $huygens_user;
        $answer = system("ssh $huygens_user" . '@' . $this->host . " " . "ps -p $pid | grep -e $hucore > " . $logdir . "/hrm_tmp" , $result); // -p
        if ($result==0) {return True;} 
        return False;
    } 
    
    function isHuygensProcessSleeping($pid) {
        global $logdir, $hucore;
        global $huygens_user;
        $answer = system("ssh $huygens_user" . '@' . $this->host . " " . "ps -lf -p " . "$pid | grep -e $hucore | grep -e S > " . $logdir . "/hrm_tmp",  $result);
        if ($result==0) {return True;} 
        return False;    
    } 
    
    function rewakeHuygensProcess($pid) {
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
    
    function ping() {
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

    function pid() {
        return $this->pid;
    } 
    
    function runShell() {
        global $huygens_user;
        
        $this->shell = proc_open("bash", $this->descriptorSpec, $this->pipes);
        if (!is_resource($this->shell)) {
            return False;
        } 
        return True;
    } 

    function execute($command) {
        global $huygens_user;

        $cmd = 'ssh -f ' . $huygens_user . "@" . $this->host . " '" . $command . " ' \n";

        $ret = fwrite($this->pipes[0], $cmd );
        fflush($this->pipes[0]);
        report ("$cmd: $ret", 2);
        if ( $ret ) {
            // Why exiting here? This is commented out by now.
            # $ret = fwrite($this->pipes[0], "exit\n");
        }

        if ( $ret === False ) {
            return False;
        } else {
            // Asume execution success!!
            return True;
        }
    } 
    
    function pidFromString($input) {
        $result = -1;
        $numberOfHits = preg_match('/pid=([0-9]+)/', $input, $hits);
        if ($numberOfHits>0) {
            $result = $hits[1]; 
        }
        return $result;
    } 

  function read() {
    return fgets($this->pipes[1], 2048);      
  } 

  function runHuygensScript($scriptName) {
      global $hutask;
      $command = $this->huscript_path." $hutask \"".$scriptName."\"";
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
          $input = fgets($this->out_file, 1024);
          $pid = $this->pidFromString($input);
          if ($pid != -1) { 
            $found = True;
            $this->pid = $pid;
          }
      }
      return $pid;
  }

  // TODO refactor
  function isJobWithPidRunning($pid) {
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

  function release() {
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
  
  function killHucoreProcess($pid) {    
    $command = "kill " . $pid;
    $result = True;
    $result = $result && $this->runShell();
    if ($result) {
        $result = $result && $this->execute($command);
    }
    return $result;
  }
}

Class LocalExternalProcess extends ExternalProcess {

  function LocalExternalProcess($host, $huscript_path, $logfileName, $errfileName) {
    $this->ExternalProcess($host, $huscript_path, $logfileName, $errfileName);
  }
 
  function existsHuygensProcess($pid) {
    global $huygens_user, $hucore;
    global $logdir;
    $answer = system("ps -p $pid | grep -e $hucore > ".$logdir."/hrm_tmp" , $result);
    if ($result==0) {return True;} 
    return False;
  } 

  function isHuygensProcessSleeping($pid) {
    //    global $huygens_user, $hucore;
    //    $answer = system("ps -lf -p " ."$pid | grep -e $hucore | grep -e S > hrm_tmp",  $result);
    //    if ($result==0) {return True;} 
    return False;    
  }  

  function rewakeHuygensProcess($pid) {
    // global $huygens_user;
    // hang up shouldn't happen with local external process
    // therefore nothin to do
   
  }

  function execute($command) {
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

  function ping() {
      // machine can always reach itself.
      return True;
  }  

  function runShell() {
      global $huygens_user;
      $this->shell = proc_open("sh", $this->descriptorSpec, $this->pipes);
      if (!is_resource($this->shell)) {
          return False;
      } 
      return True;
  }

  function isJobWithPidRunning($pid) {
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

  function killHucoreProcess($pid) {    
      $dead = posix_kill($pid,15);
      return $dead;
  }

}  

?>

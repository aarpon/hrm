<?php
// Module Shell.inc

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

require_once("hrm_config.inc");
require_once("Fileserver.inc");

global $isHrmDevelop, $hucore, $hutask;

if (!isset($isHrmDevelop) || $isHrmDevelop == false) {
    $hucore = "hucore";
    $hutask = "-task";
} else {
    $hucore = "huscript";
    $hutask = "-script";
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
        global $hrm_path, $hucore;
        global $huygens_user;
        $answer = system("ssh $huygens_user" . '@' . $this->host . " " . "ps -p $pid | grep -e $hucore > " . $hrm_path . "/run/hrm_tmp" , $result); // -p
        if ($result==0) {return True;} 
        return False;
    } 
    
    function isHuygensProcessSleeping($pid) {
        global $hrm_path, $hucore;
        global $huygens_user;
        $answer = system("ssh $huygens_user" . '@' . $this->host . " " . "ps -lf -p " . "$pid | grep -e $hucore | grep -e S > " . $hrm_path . "/run/hrm_tmp",  $result);
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
        global $hrm_path;
    	global $ping_command;
    	global $ping_parameter;
    	
    	$result = "";
    	$command = $ping_command . " " . $this->host . " " . $ping_parameter . " > ". $hrm_path . "/run/hrm_tmp";
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
        fwrite($this->pipes[0], 'ssh -f ' . $huygens_user . "@" . $this->host . " '" . $command . " &' \n" );
        fwrite($this->pipes[0], "exit\n");
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
    $answer = '';
    $answer = $answer . fgets($this->pipes[1], 2048);      
    return $answer;
  } 

  function runHuygensScript($scriptName) {
      global $hutask;
      $command = $this->huscript_path." $hutask ".$scriptName;
      // TODO better management of file handles
      //$out_file = fopen($this->descriptorSpec[1][1], "r");
      $this->out_file = fopen($this->descriptorSpec[1][1], "r");
      fseek($this->out_file, 0, SEEK_END);
      
      $this->execute($command);
      sleep(5);
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
    //fclose($this->pipes[1]);
    // TODO better management of file handles
    fclose($this->out_file);
    $result = proc_close($this->shell);
  } 
}

Class LocalExternalProcess extends ExternalProcess {

  function LocalExternalProcess($host, $huscript_path, $logfileName, $errfileName) {
    $this->ExternalProcess($host, $huscript_path, $logfileName, $errfileName);
  }
 
  function existsHuygensProcess($pid) {
    global $huygens_user, $hucore;
    global $hrm_path;
    $answer = system("ps -p $pid | grep -e $hucore > ".$hrm_path."/run/hrm_tmp" , $result);
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
    fwrite($this->pipes[0], $command . " & \n" );
    fflush($this->pipes[0]);
    sleep(5);
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
}  

?>

<?php
/**
 * ExternalProcessFactory
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\shell;

require_once dirname(__FILE__) . "/../bootstrap.php";

global $hucore, $hutask;

/** @todo Why is $hucore forced here? */

$hucore = "hucore";
$hutask = "-noExecLog -checkUpdates disable -template";

/**
 * Launches (local) tasks on a shell on the queue manager machine.
 *
 * @package hrm
 */
class LocalExternalProcess extends ExternalProcess
{

    /**
     * LocalExternalProcess constructor.
     *
     * Sets all shell pipes and file descriptors.
     * @param string $host This is not used (is only passed on to the parent constructor).
     * @param string $huscript_path HuCore full executable.
     * @param string $logfileName Name of the process log (relative to the global $logdir).
     * @param string $errfileName Name of the process error log (relative to the global $logdir).
     */
    public function __construct($host,
                                $huscript_path,
                                $logfileName,
                                $errfileName)
    {
        parent::__construct($host, $huscript_path, $logfileName, $errfileName);
    }

    /**
     * Checks whether an Huygens Process with given Process IDentifier exists.
     * @param int $pid Process identifier as returned by the OS.
     * @return bool True if the process exists, false otherwise.
     * @todo Refactor!
     */
    public function existsHuygensProcess($pid)
    {
        global $hucore, $logdir;

        $answer = system("ps -p $pid | grep -e $hucore > " . $logdir .
            "/hrm_tmp", $result);
        if ($result == 0) {
            return True;
        }
        return False;
    }

    /**
     * Attempts to read a file, if existing.
     * @param string $fileName The name of the file including its path.
     * @return string The contents of the file in an array.
     */
    public function readFile($fileName)
    {

        // Build a read command involving the file.
        $cmd = "if [ -f \"" . $fileName . "\" ]; ";
        $cmd .= "then ";
        $cmd .= "cat \"" . $fileName . "\"; ";
        $cmd .= "fi";

        $answer = exec($cmd, $result);

        return $result;
    }

    /**
     * The function does not do anything, since there is no need to copy the file to a remote host.
     * @param string $fileName File name (ignored)
     * @return bool|void
     */
    public function copyFile2Host($fileName)
    {
        return true;
    }

    /**
     * Checks whether the Huygens Process with given Process IDentifier is sleeping.
     * @param int $pid Process identifier as returned by the OS.
     * @return bool True if the process is sleeping, false otherwise. Always returns false.
     * @todo Is it correct that this always return false?
     * @todo Refactor: why is this saving to hrm_tmp?
     */
    public function isHuygensProcessSleeping($pid)
    {
        //    global $huygens_user, $hucore;
        //    $answer = system("ps -lf -p " ."$pid | grep -e $hucore | grep -e S > hrm_tmp",  $result);
        //    if ($result==0) {return True;}
        return False;
    }

    /**
     * Wakes up the Huygens Process with given Process IDentifier.
     * @param int $pid Process identifier as returned by the OS.
     * @todo    This function is currently doing nothing.
     */
    public function rewakeHuygensProcess($pid)
    {
        // global $huygens_user;
        // hang up shouldn't happen with local external process
        // therefore nothing to do
    }

    /**
     * Executes a command.
     * @param string $command Command to be executed on the host.
     * @return bool True if the command was executed, false otherwise.
     * @todo Why sleeping 5 seconds?
     */
    public function execute($command)
    {

        $ret = fwrite($this->pipes[0], $command . " & echo $! \n");
        fflush($this->pipes[0]);
        if ($ret === false) {
            // Can't write to pipe.
            return False;
        } else {
            sleep(5);
            // Assume execution success!!
            return True;
        }
    }

    /**
     * Pings the host.
     * @return bool True always, since a machine should always be able to reach itself.
     */
    public function ping()
    {
        // machine can always reach itself.
        return True;
    }

    /**
     * Starts the shell.
     * @return bool True if the shell started successfully, false otherwise.
     */
    public function runShell()
    {

        $this->shell = proc_open("sh", $this->descriptorSpec, $this->pipes);

        if (!is_resource($this->shell) || !$this->shell) {
            $this->release();
            return False;
        }

        return True;
    }

    /**
     * Queries the OS for the amount of free memory currently available.
     * @return int The amount of free memory in MB. -1 when unknown.
     */
    public function getFreeMem()
    {
        // Initialize with unknown free memory. 
        $freeMem = -1;
        
        // Build a command to inquire 'free' about the total free memory available.        
        $cmd = "free -m | awk \"/Mem:/ {print \$7}\"";  

        // We don't use the HRM 'execute' utility because it doesn't
        // return results.
        exec($cmd, $result);

        // Check if the result is consistent.
        if (array_key_exists(0, $result)) {
            if (is_numeric($result[0])) {
                $freeMem = $result[0];
            }
        }

        return $freeMem;        
    }
}

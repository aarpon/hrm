<?php
/**
 * ExternalProcess
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\shell;

use hrm\Log;

global $hucore, $hutask;

/** @todo Why is $hucore forced here? */

$hucore = "hucore";
$hutask = "-noExecLog -checkUpdates disable -template";

/**
 * Launches tasks on a shell on another host (via secure connection).
 *
 * @package hrm
 */
class ExternalProcess
{

    /**
     * OS Identifier for the Process
     * @var int
     */
    public $pid;

    /**
     * Host on which the process will be started.
     * @var string
     * @todo Implement better management of multiple hosts
     */
    public $host;

    /**
     * HuCore full executable path on host.
     *
     * For historical reason, HuCore is still referred to as huscript.
     *
     * @var string
     */
    public $huscript_path;

    /**
     * Pipes for communication with the process.
     * @var array
     */
    public $pipes;

    /**
     * The shell process resource.
     * @var resource
     */
    public $shell;

    /**
     * Name of the process log (relative to the global $logdir)
     * @var string
     */
    public $logfileName;

    /**
     * Name of the process error log (relative to the global $logdir)
     * @var string
     */
    public $errfileName;

    /**
     * Handle for the output file.
     * @var resource
     */
    public $out_file;

    /**
     * File descriptors to open in the shell.
     * @var array
     */
    public $descriptorSpec;

    /**
     * ExternalProcess constructor.
     *
     * Sets all shell pipes and file descriptors for given host.
     * @param string $host Host on which the process will be started. All communication
     * to the host will happen via secure connection.
     * @param string $huscript_path HuCore full executable path on host.
     * @param string $logfileName Name of the process log (relative to the global $logdir)
     * @param string $errfileName Name of the process error log (relative to the global $logdir)
     */
    public function __construct($host,
                                $huscript_path,
                                $logfileName,
                                $errfileName)
    {
        global $logdir;


        $this->huscript_path = $huscript_path;

        if (strpos($host, " ") !== False) {
            $components = explode(" ", $host);
            array_pop($components);
            $realHost = implode("", $components);
            $this->host = $realHost;
        } else {
            $this->host = $host;
        }
        $this->pid = NULL;

        // Make sure to save into the log dir
        $this->logfileName = $logdir . "/" . $logfileName;
        $this->errfileName = $logdir . "/" . $errfileName;
        $this->descriptorSpec = array(
            0 => array("pipe", // STDIN
                "r"),
            1 => array("file", // STDOUT
                $this->logfileName,
                "a"),
            2 => array("file", // STDERR
                $this->errfileName,
                "a"));
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->release();
    }


    /**
     * Checks whether an Huygens Process with given Process IDentifier exists.
     * @param int $pid Process identifier as returned by the OS.
     * @return bool True if the process exists, false otherwise.
     * \todo    Refactor
     */
    public function existsHuygensProcess($pid)
    {
        global $logdir, $hucore, $huygens_user;

        $answer = system("ssh $huygens_user" . '@' . $this->host . " " .
            "ps -p $pid | grep -e $hucore > " . $logdir . "/hrm_tmp",
            $result);
        if ($result == 0) {
            return True;
        }
        return False;
    }

    /**
     * Checks whether the Huygens Process with given Process IDentifier
     * is sleeping.
     * @param int $pid Process identifier as returned by the OS.
     * @return bool True if the process is sleeping, false otherwise.
     * \todo    Refactor: why is this saving to hrm_tmp?
     */
    public function isHuygensProcessSleeping($pid)
    {
        global $logdir, $hucore, $huygens_user;

        $answer = system("ssh $huygens_user" . '@' . $this->host . " " .
            "ps -lf -p " . "$pid | grep -e $hucore | grep -e S > " . $logdir .
            "/hrm_tmp", $result);
        if ($result == 0) {
            return True;
        }
        return False;
    }

    /**
     * Wakes up the Huygens Process with given Process IDentifier
     * @param int $pid Process identifier as returned by the OS.
     * @todo Replace deprecated split() call. 
     */
    public function rewakeHuygensProcess($pid)
    {
        global $huygens_user;

        $answer = "none";
        ob_start();
        while ($answer != "") {
            $answer = system("ssh $huygens_user" . '@' . $this->host . " '" .
                "ps -Alfd | sort | grep sshd | grep $huygens_user" . "'",
                $result);
            $array = preg_split('/[ ]+/', $answer);
            $pid = $array[3];
            $answer = system("ssh $huygens_user" . '@' . $this->host . " '" .
                "kill $pid" . "'", $result);
            if (!$this->existsHuygensProcess($pid)) {
                break;
            }
        }
        ob_end_clean();
    }

    /**
     * Pings the host.
     * @return bool True if pinging the host was successful, false otherwise.
     * @todo Refactor: why is this saving to hrm_tmp?
     */
    public function ping()
    {
        global $logdir, $ping_command, $ping_parameter;

        $result = "";
        $command = $ping_command . " " . $this->host . " " . $ping_parameter .
            " > " . $logdir . "/hrm_tmp";
        $answer = system($command, $result);
        if ($result == 0)
            return True;
        return False;
    }

    /**
     * Returns the Process IDentifier of the Huygens process.
     * @return int The pid of the process.
     */
    public function pid()
    {
        return $this->pid;
    }

    /**
     * Starts the shell.
     * @return bool True if the shell started successfully, false otherwise.
     */
    public function runShell()
    {

        $this->shell = proc_open("bash", $this->descriptorSpec, $this->pipes);

        if (!is_resource($this->shell) || !$this->shell) {
            $this->release();
            return False;
        }
        return True;
    }

    /**
     * Executes a command.
     * @param string $command Command to be executed on the host.
     * @return bool True if the command was executed, false otherwise.
     */
    public function execute($command)
    {
        global $huygens_user;

        /* Some versions or configurations of SSH close the connection
           right away if there's no redirection of stderr to stdout. */
        $cmd = 'ssh -f ' . $huygens_user . "@" . $this->host . " '" .
            $command . " 2>&1";
        $cmd .= " & echo $!'\n";



        $ret = fwrite($this->pipes[0], $cmd);
        fflush($this->pipes[0]);
        Log::info("$cmd: $ret");

        if ($ret) {
            // Why exiting here? This is commented out by now.
            // $ret = fwrite($this->pipes[0], "exit\n");
        }

        if ($ret === False) {
            return False;
        } else {
            // Assume execution success!!
            return True;
        }
    }

    /**
     * Attempts to remove a file, if existing.
     * @param string $fileName The name of the file including its path.
     */
    public function removeFile($fileName)
    {

        // Build a remove command involving the file.
        $cmd = "if [ -f \"" . $fileName . "\" ]; ";
        $cmd .= "then ";
        $cmd .= "rm \"" . $fileName . "\"; ";
        $cmd .= "fi";

        $this->execute($cmd);
    }

    /**
     * Attempts to rename a file, if existing.
     * @param string $oldName The name of the file including its path.
     * @param string $newName The new name of the file including its path.
     */
    public function renameFile($oldName, $newName)
    {

        // Build a rename command involving the old and new names.
        $cmd = "if [ -f \"" . $oldName . "\" ]; ";
        $cmd .= "then ";
        $cmd .= "mv \"" . $oldName . "\" \"" . $newName . "\"; ";
        $cmd .= "fi";

        $this->execute($cmd);
    }

    /**
     * Attempts to read a file, if existing.
     * @param string $fileName The name of the file including its path.
     * @return string The contents of the file in an array.
     */
    public function readFile($fileName)
    {
        global $huygens_user;

        // Build a read command involving the file.
        $cmd = "if [ -f \"" . $fileName . "\" ]; ";
        $cmd .= "then ";
        $cmd .= "cat \"" . $fileName . "\"; ";
        $cmd .= "fi";
        $cmd = "ssh " . $huygens_user . "@" . $this->host . " " . "'$cmd'";

        $answer = exec($cmd, $result);

        return $result;
    }

    /**
     * Copies a local file to another server.
     * @param string $fileName The name of the file including its path.
     * @return bool True if succeeded, false otherwise.
     */
    public function copyFile2Host($fileName)
    {
        global $huygens_user;

        // Build a copy command involving the file.
        $cmd = "scp " . $fileName . " " . $huygens_user . "@";
        $cmd .= $this->host . ":" . $fileName;
        $answer = exec($cmd);

        return $answer;
    }

    /**
     * Runs the Huygens template with a given name in the shell.
     * @param string $templateName File name of the Huygens template.
     * @return int The Process Identifier of the running task
     * @todo Improve the pid acquisition process.
     * @todo Better management of file handles.
     * @todo Refactor!
     */
    public function runHuygensTemplate($templateName)
    {
        global $hutask;

        $pid = "";

        $this->out_file = fopen($this->descriptorSpec[1][1], "r");

        fseek($this->out_file, 0, SEEK_SET);


        $command = $this->huscript_path . " $hutask \"" . $templateName . "\"";
        $result = $this->execute($command);

        sleep(1);

        $found = False;
        while (!$found) {
            if (feof($this->out_file)) {
                fclose($this->out_file);
                break;
            }

            $pid = fgets($this->out_file, 1024);
            $pid = intval($pid);
            if ($pid != -1 && $pid != 0) {
                $found = True;
                $this->pid = $pid;
            }
        }

        fclose($this->out_file);

        return $pid;
    }

    /**
     * Releases all files and pipes and closes the shell.
     */
    public function release()
    {

        /* TODO better management of file handles. */

        /* Close pipes. Check first if they are proper handlers. If, for
           example, opening them did not work out, the handlers won't exist. */
        if (is_resource($this->pipes[0])) {
            fclose($this->pipes[0]);
        }

        if (is_resource($this->out_file)) {
            fclose($this->out_file);
        }

        if (is_resource($this->shell)) {
            $result = proc_close($this->shell);
        }

        //Report
        if (isset($result) && $result == -1) {
            Log::error("Error releasing shell.");
        }
    }

    /**
     * Kill the Huygens process with the given Process IDentifier and its child, if it exists.
     * @param int $pid Process IDentifier of the Job,
     * @return bool True if the Job was killed, false otherwise.
     */
    public function killHucoreProcess($pid)
    {

        // Kill the child, if it exists.
        $noChild = $this->killHucoreChild($pid);

        if ($noChild == False) {
            Log::error('Failed killing child process.');
        }

        // Kill the parent.
        $noParent = posix_kill($pid, SIGKILL);

        if ($noParent == False) {
            Log::error('Failed killing parent process.');
            
            //Good to report, but does that mean the parent cannot be killed or is already dead?
            //TODO make a better test
            $noParent = True;
        }

        return ($noParent && $noChild);
    }

    /**
     * Kill the child of the Huygens process if it exists.
     * @param int $ppid Process Identifier of the parent.
     * @return bool True if a child was killed or didn't exist, false otherwise.
     */
    public function killHucoreChild($ppid)
    {

        // Get the pid of the child
        exec("ps -ef| awk '\$3 == '$ppid' { print  \$2 }'", $child, $error);

        if (!$error) {

            // Kill the child if it exists. Return true if it does not exist.
            if (array_key_exists(0, $child)) {
                $childPid = $child[0];
                if ($childPid > 0) {
                    $dead = posix_kill($childPid, SIGKILL);
                } else {
                    $dead = true;
                }
            } else {
                $dead = true;
            }
        } else {
            $dead = true;
        }

        return $dead;
    }

    /**
     * Queries the OS for the amount of free memory currently available.
     * @return int The amount of free memory in MB. -1 when unknown.
     */
    public function getFreeMem()
    {
        global $huygens_user;

        // Initialize with unknown free memory.
        $freeMem = -1;

        // Build a command to inquire 'free' about the total free memory available.        
        $cmd = "free -m | awk \"/Mem:/ {print \$7}\"";

        // Create a command to be executed remotely.
        $cmd = "ssh " . $huygens_user . "@" . $this->host . " " . "'$cmd'";

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

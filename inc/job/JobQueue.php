<?php
/**
 * JobQueue
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\job;

use hrm\DatabaseConnection;
use hrm\shell\ExternalProcessFactory;
use hrm\Log;

require_once dirname(__FILE__) . '/../bootstrap.php';


/**
 * Manages the queue of deconvolution Jobs.
 *
 * @package hrm
 */
class JobQueue
{

    /**
     * JobQueue constructor.
     */
    public function __construct()
    {
    }

    /**
     * Return the timestamp of current time.
     * @return string Timestamp of current time.
     */
    public function timestampNowString()
    {
        $db = DatabaseConnection::get();
        $date = $db->now();
        $ms = microtime();
        $ms = explode(" ", $ms);
        $ms = $ms[0];
        return $date . "." . substr($ms, 2);
    }

    /**
     * Get names of all processing servers (independent of their status).
     * @return array Array of server names.
     */
    function availableServer()
    {
        $db = DatabaseConnection::get();
        $result = $db->availableServer();
        return $result;
    }

    /**
     * Returns all jobs from the queue, both compound and simple,
     * and the associated file names, ordered by priority.
     * @return array All jobs.
     */
    function getContents()
    {
        $db = DatabaseConnection::get();
        $rows = $db->getQueueContents();
        return $rows;
    }

    /**
     * Returns all file names associated to a job with given id.
     * @param string $id Job id.
     * @return array Array of file names.
     */
    public function getJobFilesFor($id)
    {
        $db = DatabaseConnection::get();
        $files = $db->getJobFilesFor($id);
        return $files;
    }

    /**
     * Adds a Job for a JobDescription to the queue.
     * @param JobDescription $jobDescription A JobDescription object.
     * @return bool True if queuing the Job succeeded, false otherwise.
     */
    public function queueJob(JobDescription $jobDescription)
    {
        $owner = $jobDescription->owner();
        $ownerName = $owner->name();
        $db = DatabaseConnection::get();
        $result = $db->queueJob($jobDescription->id(), $ownerName);
        return $result;
    }

    /**
     * Starts a Job.
     * @param Job $job A Job object.
     * @return bool True if starting the Job succeeded, false otherwise.
     */
    public function startJob(Job $job)
    {
        $db = DatabaseConnection::get();
        $pid = $job->pid();
        $result = $db->reserveServer($job->server(), $pid);
        $result = $result && $db->startJob($job);
        return $result;
    }

    /**
     * Gets the JobDescription for the next Job id from the database.
     * @return JobDescription|null A loaded JobDescription or null if no more jobs are in the queue.
     */
    public function getNextJobDescription()
    {
        $db = DatabaseConnection::get();
        $id = $db->getNextIdFromQueue();
        if ($id == NULL) {
            return NULL;
        }
        $jobDescription = new JobDescription();
        $jobDescription->setId($id);
        $jobDescription->load();
        return $jobDescription;
    }

    /**
     * Gets the compound jobs from the queue.
     * @return array Array of JobDescriptions for compound Jobs.
     */
    public function getCompoundJobs()
    {
        $db = DatabaseConnection::get();
        $jobDescriptions = array();
        $rows = $db->getQueueJobs();
        foreach ($rows as $row) {
            $jobDescription = new JobDescription();
            $jobDescription->setId($row['id']);
            $jobDescription->load();
            if ($jobDescription->isCompound()) {
                $jobDescriptions[] = $jobDescription;
            }
        }
        return $jobDescriptions;
    }

    /**
     * Removes the Job that correspond o a given JobDescription.
     * @param JobDescription $jobDescription A JobDescription object.
     * @return bool True if Job removal was successful, false otherwise.
     */
    public function removeJob(JobDescription $jobDescription)
    {
        $id = $jobDescription->id();
        $result = $this->removeJobWithId($id);
        return $result;
    }

    /**
     * Marks Jobs with given ids as 'broken' (i.e. to be removed).
     * @param array $ids Job ids.
     * @param string $owner Name of the user who owns the Job.
     * @param bool $isAdmin True if the owner is an admin (default = false).
     * @return bool True if Job the job could be marked, false otherwise.
     */
    function markJobsAsRemoved(array $ids, $owner, $isAdmin=false)
    {
        $result = True;
        if (count($ids) == 0) {
            return $result;
        }
        $db = DatabaseConnection::get();
        foreach ($ids as $id) {
            // loop through all the jobs selected, which have to be deleted
            if (!$isAdmin && $db->getJobOwner($id) != $owner) {
                continue;
            }
            $result = $result && $db->markJobAsRemoved($id);

            // The front end should NOT try to kill the job, it may not work.
            // The Queue Manager will take care of it.
        }
        return $result;
    }

    /**
     * Kills Jobs with given id.
     * @param array|string $ids Job ids (string or array of strings).
     * @return bool True if all Jobs were killed, false otherwise.
     */
    function killJobs($ids)
    {
        global $logdir;

        $result = True;
        if (count($ids) == 0) return $result;
        
        $db = DatabaseConnection::get();

        // Loop through all the jobs selected, which have to be killed and
        // deleted.
        foreach ($ids as $id) {            
            $row = $db->getQueueContentsForId($id);
            $pid = $row['process_info'];
            $server = $row['server'];
            $proc = ExternalProcessFactory::getExternalProcess($server,
                $server . "_" . $id . "_out.txt",
                $server . "_" . $id . "_error.txt");
            $killed = $proc->killHucoreProcess($pid);
            $result = $killed && $result;

            // Clean the database and the error file.
            $result = $this->removeJobWithId($id)    && $result;
            $result = $db->markServerAsFree($server) && $result;
            $errorFile = $logdir . "/" . $server . "_" . $id . "_error.txt";
            if (file_exists($errorFile)) {
                unlink($errorFile);
            }

            $proc->release();
        }
        
        return $result;
    }

    /**
     * Kills marked Jobs (i.e. those with status 'kill').
     * @return bool True if all marked Jobs were killed, false otherwise.
     */
    function killMarkedJobs()
    {
        $db = DatabaseConnection::get();
        $ids = $db->getJobIdsToKill();
        if ($ids != null && count($ids) > 0) {
            if ($this->killJobs($ids)) {
                Log::info("running broken jobs killed and removed");
                return True;
            } else {
                Log::error("killing running broken jobs failed");
                return False;
            }
        } else {
            return False;
        }
    }

    /**
     * Remove marked Jobs from the Queue (i.e. those 'kill'ed).
     * @return bool True if all marked Jobs were removed, false otherwise.
     */
    function removeMarkedJobs()
    {
        $db = DatabaseConnection::get();
        $ids = $db->getMarkedJobIds();
        foreach ($ids as $id) {
            $this->removeJobWithId($id);
        }
        if ($ids != null && count($ids) > 0) {
            return True;
        }
        return False;
    }

    /**
     * Remove Job with given id from the database.
     * @param string $id Job id.
     * @return bool True if the Job were removed, false otherwise.
     */
    function removeJobWithId($id)
    {
        $db = DatabaseConnection::get();
        return $db->deleteJobFromTables($id);
    }

    /**
     * Stops and removes a Job from the Queue and the database.
     * @param Job $job Job object.
     * @return bool True if the Job were removed, false otherwise.
     */
    function stopJob(Job $job)
    {
        $db = DatabaseConnection::get();
        $db->resetServer($job->server(), $job->pid());
        $this->removeJob($job->description());
        return $this->timestampNowString();
    }

    /**
     * Returns all running jobs from the database.
     * @return array Array of Job objects.
     */
    function runningJobs()
    {
        $db = DatabaseConnection::get();
        $jobs = $db->getRunningJobs();
        return $jobs;
    }

    /**
     * Returns the start time of a given Job object.
     * @param Job $job Job object.
     * @return string Start time.
     */
    function startTime(Job $job)
    {
        $db = DatabaseConnection::get();
        $date = $db->startTimeOf($job);
        return $date;
    }

    /**
     * Updates the estimated end time in the database.
     * @param string $id Job id.
     * @param  string $date Estimated end time (string).
     * @return \ADORecordSet_empty|\ADORecordSet_mysql|False Query result.
     */
    function updateEstimatedEndTime($id, $date)
    {
        $db = DatabaseConnection::get();
        return $db->setJobEndTime($id, $date);
    }


    /**
     * Pauses the Job described by the given JobDescription.
     * @param JobDescription $jobDescription JobDescription object.
     * @return \ADORecordSet_empty|\ADORecordSet_mysql|False Query result.
     */
    function pauseJob(JobDescription $jobDescription)
    {
        $db = DatabaseConnection::get();
        $result = $db->pauseJob($jobDescription->id());
        return $result;
    }

    /**
     * Restarts all paused Jobs.
     * @return \ADORecordSet_empty|\ADORecordSet_mysql|False Query result.
     */
    function restartPausedJobs()
    {
        $db = DatabaseConnection::get();
        $result = $db->restartPausedJobs();
        return $result;
    }

    /**
     * Checks whether server is busy.
     * @param string $name Name of the server.
     * @return bool True if the server is busy, false otherwise.
     */
    function isServerBusy($name)
    {
        $db = DatabaseConnection::get();
        $result = $db->isServerBusy($name);
        return $result;
    }

    /**
     * Checks whether the QueueManager is locked.
     * @return bool True if the QueueManager is locked, false otherwise.
     */
    function isLocked()
    {
        $db = DatabaseConnection::get();
        $ans = $db->getSwitchStatus();
        $result = false;
        if ($ans == "lck") {
            $result = true;
        }
        return $result;
    }

    /**
     * Locks the QueueManager.
     * @return    \ADORecordSet_empty|\ADORecordSet_mysql|False Query result.
     */
    function lock()
    {
        $db = DatabaseConnection::get();
        $result = $db->setSwitchStatus("lck");
        return $result;
    }

    /**
     * Unlocks the QueueManager.
     * @return \ADORecordSet_empty|\ADORecordSet_mysql|False Query result.
     */
    function unlock()
    {
        $result = false;
        if ($this->isLocked()) {
            $db = DatabaseConnection::get();
            $result = $db->setSwitchStatus("on");
        }
        return $result;
    }

}

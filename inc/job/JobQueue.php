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
use hrm\Log;
use hrm\shell\ExternalProcessFactory;

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
    public function availableServer()
    {
        $db = DatabaseConnection::get();
        return $db->availableServer();
    }

    /**
     * Returns all jobs from the queue, both compound and simple,
     * and the associated file names, ordered by priority.
     * @return array All jobs.
     */
    public function getContents()
    {
        $db = DatabaseConnection::get();
        return $db->getQueueContents();
    }

    /**
     * Returns all file names associated to a job with given id.
     * @param string $id Job id.
     * @return array Array of file names.
     */
    public function getJobFilesFor($id)
    {
        $db = DatabaseConnection::get();
        return $db->getJobFilesFor($id);
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
        $ids = $db->getNextIdFromQueue();
        if ($ids == null) {
            return null;
        }
        $jobDescription = new JobDescription();
        $jobDescription->setId($ids['id']);
        $jobDescription->setSettingsId($ids['settings_id']);
        $jobDescription->load();
        return $jobDescription;
    }

    /**
     * Removes the Job that correspond o a given JobDescription.
     * @param JobDescription $jobDescription A JobDescription object.
     * @return bool True if Job removal was successful, false otherwise.
     */
    public function removeJob(JobDescription $jobDescription)
    {
        $id = $jobDescription->id();
        $settingsId = $jobDescription->settingsId();

        $result = true;
        if ($this->canJobSettingsBeDeleted($jobDescription)) {
            Log::info("Jobs settings $settingsId are no longer referenced by any Job and can be removed.");
            $result &= $this->removeJobSettingsWithId($settingsId);

            if ($result) {
                Log::info("Jobs settings $settingsId successfully removed.");
            } else {
                Log::error("Failed removing Job settings $settingsId!");
            }
        } else {
            Log::info("Jobs settings $settingsId cannot be removed yet because they are still referenced.");
        }
        $result &= $this->removeJobWithId($id);
        return $result;
    }

    /**
     * Marks Jobs with given ids as 'delete' (i.e. to be removed).
     * @param array $ids Job ids.
     * @param string $owner Name of the user who owns the Job.
     * @param bool $isAdmin True if the owner is an admin (default = false).
     * @return bool True if Job the job could be marked, false otherwise.
     */
    public function markJobsAsRemoved(array $ids, $owner, $isAdmin = false)
    {
        $result = true;
        if (count($ids) == 0) {
            return $result;
        }
        $db = DatabaseConnection::get();
        foreach ($ids as $id) {
            // loop through all the jobs selected, which have to be deleted
            if (!$isAdmin && $db->getJobOwner($id) != $owner) {
                continue;
            }
            $result &= $db->markJobAsRemoved($id);

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
    public function killJobs($ids)
    {
        global $logdir;

        $result = true;
        if (count($ids) == 0) {
            return $result;
        }

        // Get DatabaseConnection object
        $db = DatabaseConnection::get();

        // Loop through all the jobs selected, which have to be killed and
        // deleted.
        foreach ($ids as $id) {
            $row = $db->getQueueContentsForId($id);
            $pid = $row['process_info'];
            $server = $row['server'];
            $proc = ExternalProcessFactory::getExternalProcess(
                $server,
                $server . "_" . $id . "_out.txt",
                $server . "_" . $id . "_error.txt"
            );
            $killed = $proc->killHucoreProcess($pid);
            $result = $killed && $result;

            // Clean the database and the error file.
            $settingsId = $db->getSettingsIdForJobId($id);
            $result &= $this->removeJobWithId($id);
            $jobDescription = new JobDescription();
            $jobDescription->setId($id);
            $jobDescription->setSettingsId($settingsId);
            $jobDescription->load();
            if ($this->canJobSettingsBeDeleted($jobDescription)) {
                $result &= $this->removeJobSettingsWithId($settingsId);
            }
            $result &= $db->markServerAsFree($server);
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
     * These are jobs that were deleted after they started.
     *
     * @return bool True if all marked Jobs were killed, false otherwise.
     */
    public function killMarkedJobs()
    {
        $db = DatabaseConnection::get();
        $ids = $db->getJobIdsToKill();

        // If there are no jobs we return success
        if (count($ids) == 0) {
            return true;
        }

        return $this->killJobs($ids);
    }

    /**
     * Remove marked Jobs from the Queue (i.e. those with status 'delete').
     * These are jobs that have been deleted before they started.
     *
     * @return bool True if all marked Jobs were removed, false otherwise.
     */
    public function removeMarkedJobs()
    {
        $db = DatabaseConnection::get();
        $ids = $db->getMarkedJobIds();

        // If there are no jobs we return success
        if (count($ids) == 0) {
            return true;
        }

        // There are jobs, lets remove them
        $success = true;
        foreach ($ids as $id) {
            $jobDescription = new JobDescription();
            $jobDescription->setId($id);
            $settingsId = DatabaseConnection::get()->getSettingsIdForJobId($id);
            $jobDescription->setSettingsId($settingsId);
            $jobDescription->load();
            $success &= $this->removeJobWithId($id);
            if ($success) {
                Log::info("Job $id successfully removed.");
            } else {
                Log::error("Failed removing Job $id!");
            }
            if ($this->canJobSettingsBeDeleted($jobDescription)) {
                Log::info("Jobs settings $settingsId are no longer referenced by any Job and can be removed.");
                $success &= $this->removeJobSettingsWithId($settingsId);
                if ($success) {
                    Log::info("Jobs settings $settingsId successfully removed.");
                } else {
                    Log::error("Failed removing Job settings $settingsId!");
                }
            } else {
                Log::info("Jobs settings $settingsId cannot be removed yet because they are still referenced.");
            }
        }

        // If some removal failed, $success is false;
        // otherwise we can return success
        return $success;
    }

    /**
     * Remove Job with given id from the database.
     * @param string $id Job id.
     * @return bool True if the Job were removed, false otherwise.
     */
    public function removeJobWithId($id)
    {
        $db = DatabaseConnection::get();
        return $db->deleteJobFromTables($id);
    }

    /**
     * Remove Job with given id from the database.
     * @param string $settingsId Settings id.
     * @return bool True if the Job were removed, false otherwise.
     */
    public function removeJobSettingsWithId($settingsId)
    {
        $db = DatabaseConnection::get();
        return $db->deleteJobSettingsFromTables($settingsId);
    }

    /**
     * Stops and removes a Job from the Queue and the database.
     * @param Job $job Job object.
     * @return bool True if the Job were removed, false otherwise.
     */
    public function stopJob(Job $job)
    {
        $db = DatabaseConnection::get();
        $db->resetServer($job->server(), $job->pid());
        $this->removeJob($job->description());
        return $this->timestampNowString();
    }

    /**
     * Check whether the settings referenced by the Job are still needed
     * by at least another Job.
     * @param $jobDescription JobDescription object.
     * @return True if the referenced settings can be deleted, false otherwise.
     */
    private function canJobSettingsBeDeleted($jobDescription)
    {
        $jobId = $jobDescription->id();
        $settingsId = $jobDescription->settingsId();
        $db = DatabaseConnection::get();
        $query = "SELECT id FROM job_queue WHERE settings_id='$settingsId' AND id!='$jobId';";
        $result = $db->query($query);
        return count($result) == 0;
    }

    /**
     * Returns all running jobs from the database.
     * @return array Array of Job objects.
     */
    public function runningJobs()
    {
        $db = DatabaseConnection::get();
        return $db->getRunningJobs();
    }

    /**
     * Returns the start time of a given Job object.
     * @param Job $job Job object.
     * @return string Start time.
     */
    public function startTime(Job $job)
    {
        $db = DatabaseConnection::get();
        return $db->startTimeOf($job);
    }

    /**
     * Updates the estimated end time in the database.
     * @param string $id Job id.
     * @param  string $date Estimated end time (string).
     * @return array|False Query result.
     */
    public function updateEstimatedEndTime($id, $date)
    {
        $db = DatabaseConnection::get();
        return $db->setJobEndTime($id, $date);
    }


    /**
     * Pauses the Job described by the given JobDescription.
     * @param JobDescription $jobDescription JobDescription object.
     * @return array|False Query result.
     */
    public function pauseJob(JobDescription $jobDescription)
    {
        $db = DatabaseConnection::get();
        return $db->pauseJob($jobDescription->id());
    }

    /**
     * Restarts all paused Jobs.
     * @return array|False Query result.
     */
    public function restartPausedJobs()
    {
        $db = DatabaseConnection::get();
        return $db->restartPausedJobs();
    }

    /**
     * Checks whether server is busy.
     * @TODO This function seems to be unused.
     * @param string $name Name of the server.
     * @return bool True if the server is busy, false otherwise.
     */
    public function isServerBusy($name)
    {
        $db = DatabaseConnection::get();
        return $db->isServerBusy($name);
    }

    /**
     * Checks whether the QueueManager is locked.
     * @return bool True if the QueueManager is locked, false otherwise.
     */
    public function isLocked()
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
     * @return array|False Query result.
     */
    public function lock()
    {
        $db = DatabaseConnection::get();
        return $db->setSwitchStatus("lck");
    }

    /**
     * Unlocks the QueueManager.
     * @return array|False Query result.
     */
    public function unlock()
    {
        $result = false;
        if ($this->isLocked()) {
            $db = DatabaseConnection::get();
            $result = $db->setSwitchStatus("on");
        }
        return $result;
    }
}

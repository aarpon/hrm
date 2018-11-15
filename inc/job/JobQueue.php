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
        // TODO: the "run" directory will be configurable eventually, so we
        // have to respect the path to the JSON file here as well:
        /* TODO: read this file from the configuration. */
        $this->queueFile =  "/opt/spool/snijder/queue/status/hucore.json";
        
        if (!file_exists($this->queueFile)) {
            /* TODO: handle error. */
        }
    }

    /**
     * JobQueue destructor.
     */
    public function __destruct() {
 
    }

    /**
     * Returns all jobs from the queue, both compound and simple,
     * and the associated file names, ordered by priority.
     * @return array All jobs.
     */
    function getContents()
    {
        $contents = file_get_contents($this->queueFile);
        $contentArr = json_decode($contents, true);
        return $contentArr["jobs"];
    }

  /**
   * Issues a remove operation via GC3 controller files.
   * $ids   IDS of jobs to remove from the queue.
   * $owner User who ownes the jobs.
   * Boolean: true upon success, false otherwise.
   */
    public function removeJobs($ids, $owner) {
        $result = True;
        if (count($ids) == 0) return $result;
        
        $JobDescription = new JobDescription();
        $JobDescription->setOwner( $owner );
        $JobDescription->setTaskType( "deletejobs" );        
        
        $JobDescription->setJobID( implode(', ', $ids) );
        $GC3PieController = new GC3PieController( $JobDescription );
        $result &= $GC3PieController->write2Spool();

        Log::info("Removing jobs " .  $JobDescription->getJobID(), 1);
        
        return $result;
    }

  /**
   * Adds a Job for a JobDescription to the queue.
   * @param JobDescription $jobDescription A JobDescription object.
   * @return bool True if queuing the Job succeeded, false otherwise.
   */
    public function queueJob(JobDescription $jobDescription) {

        /* Dummy function. See if it needs to be implemented when the new
        GC3QM is finished. */
    }
}

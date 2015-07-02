<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt


class JobQueue {

    /*! 
      \var    $queueFile
      \brief  A file where GC3Pie dumps the job queue.
    */
    public $queueFile;
    

    /*!
      \brief   Constructor.
    */
    public function __construct() {
        $this->queueFile =  dirname(__FILE__) . "/../run/queue.json";
        
        if (!file_exists($this->queueFile)) {
            error_log("Impossible to reach queue file.");
            return;
        }
    }


    /*!
      \brief    A function to get queue contents in a convenient format. 
      \return   The queue as an key-value list.
    */
    public function getContents( ) {
        $contents = file_get_contents($this->queueFile);
        $contentArr = json_decode($contents, true);
        return $contentArr["jobs"];
    }


    /*!
      \brief       Issues a remove operation via GC3 controller files.
      \params      $ids   IDS of jobs to remove from the queue.
      \params      $owner User who ownes the jobs.
      \return      Boolean: true upon success, false otherwise.
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
        
        return $result;
    }


    public function __destruct() {
 
    }


}

?>

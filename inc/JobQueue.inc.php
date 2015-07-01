<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt


class JobQueue {

    public $queueFile;
    
    
    public function __construct() {
        $this->queueFile =  dirname(__FILE__) . "/../run/queue.json";
        
        if (!file_exists($this->queueFile)) {
            error_log("Impossible to reach queue file.");
            return;
        }
    }

    
    public function getContents( ) {
        $contents = file_get_contents($this->queueFile);
        $contentArr = json_decode($contents, true);
        return $contentArr["jobs"];
    }


    public function markJobsAsRemoved($ids, $owner) {
        $result = True;
        
        $JobDescription = new JobDescription();
        $JobDescription->setOwner( $owner );
        $JobDescription->setTaskType( "deleteJob" );

        $GC3PieController = new GC3PieController( $JobDescription );
        
        foreach ($ids as $id) {
            $JobDescription->setJobID( $id );

            $result &= $GC3PieController->write2Spool();    
        }
        

        if (count($ids)==0) return $result;
        
        return $result;
    }


    public function __destruct() {
 
    }


}

?>

<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt


class JobQueue {

    public $file = "../run/queue.json";
    
    /* File descriptor. */
    public $fD;
    
    public function __construct() {
        if (!file_exists($this->file)) {
            //error_log();
            return;
        }
    }

    
    public function getContents( ) {
        $contents = file_get_contents($this->file);
        $contentArr = json_decode($contents, true);
        return $contentArr["jobs"];
    }


    public function __destruct() {
 
    }


}

?>

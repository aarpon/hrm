<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

/* -------------------------- IMPORTANT ----------------------------
   This is a wrapper script for the new GC3Pie queue manager to help
   running all necessary startup tasks, which were previously done
   by the old queue manager. They are defined in the run() method of
   the old QM, in particular thats:

   * checking the HuCore version
   * checking the HuCore license details
   * checking the confidence levels for various file formats
   * storing all of the above information in the database

   TODO: Once the new QM can do all of the above mentioned tasks,
   this file and the corresponding "../inc/OldQueueManager.inc.php"
   should be removed!!

   See the corresponding tickets #132 and #412 for more details!
   -------------------------- IMPORTANT ---------------------------- */

global $isServer;
$isServer = true;

require_once('../inc/hrm_config.inc.php');
require_once('../inc/OldQueueManager.inc.php');

$manager = new QueueManager();
$manager->run();

?>

<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

global $isServer;
$isServer = true;
require_once('../inc/hrm_config.inc.php');
require_once('../inc/QueueManager.inc.php');
$manager = new QueueManager();
$manager->run();
?>
<?php
global $isServer;
$isServer = true;
require_once('../inc/hrm_config.inc');
require_once('../inc/QueueManager.inc');
$manager = new QueueManager();
$manager->run();
?>
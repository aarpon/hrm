<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

global $isServer;
use hrm\QueueManager;

$isServer = true;
require_once dirname(__FILE__) . '/../inc/bootstrap.inc.php';
$manager = new QueueManager();
$manager->run();

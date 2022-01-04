<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\QueueManager;

require_once dirname(__FILE__) . '/../inc/bootstrap.php';
$manager = new QueueManager();
$manager->run();

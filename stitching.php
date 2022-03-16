<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\DatabaseConnection;
use hrm\Nav;
use hrm\setting\TaskSetting;
use hrm\Util;

require_once dirname(__FILE__) . '/inc/bootstrap.php';



















/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */

if ($_SESSION['task_setting']->checkPostedTaskParameters($_POST)) {
    if ($_SESSION['user']->isAdmin()
    || $_SESSION['task_setting']->isEligibleForCAC()
    || $_SESSION['task_setting']->isEligibleForTStabilization($_SESSION['setting'])) {
        header("Location: " . "post_processing.php");
        exit();
    } else {
        header("Location: " . "select_hpc.php");
        exit();
    }
} else {
    $message = $_SESSION['task_setting']->message();
}



?>

<?php
/**
 * ExternalProcessFactory
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\shell;

use hrm\DatabaseConnection;

require_once dirname(__FILE__) . "/../bootstrap.php";

/**
 * Factory that returns an external process (Shell) that works either locally or on a remote server.
 *
 * The distinction is made by the value of the variable $imageProcessingIsOnQueueManager
 * from the settings.
 *
 * @package hrm
 */
class ExternalProcessFactory
{

    /**
     *
     * Runs a new shell either with or without secure connection between the
     * queue manager and the Image area.
     *
     * Which of the two modes is chosen depends on the value of the configuration
     * variable $imageProcessingIsOnQueueManager.
     *
     * @param string $host Host name.
     * @param string $logfilename Log file name.
     * @param string $errfilename Error log file name.
     * @return ExternalProcess|LocalExternalProcess External process object.
     */
    public static function getExternalProcess($host, $logfilename, $errfilename)
    {
        global $imageProcessingIsOnQueueManager;

        $db = new DatabaseConnection();
        $huscript_path = $db->huscriptPathOn($host);

        if ($imageProcessingIsOnQueueManager) {
            $shell = new LocalExternalProcess($host,
                $huscript_path,
                $logfilename,
                $errfilename);
        } else {
            $shell = new ExternalProcess($host,
                $huscript_path,
                $logfilename,
                $errfilename);
        }

        return $shell;
    }
}

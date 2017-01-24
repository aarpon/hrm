<?php
/**
 * Created by PhpStorm.
 * User: pontia
 * Date: 1/24/17
 * Time: 10:45 AM
 */

use hrm\Log;

require_once dirname(__FILE__) . '/../../inc/bootstrap.php';

class TestLogger extends PHPUnit_Framework_TestCase
{

    /**
     * Test info logging
     */
    public function testInfoLogging()
    {
        global $log_verbosity;
        $log_verbosity = 2;
        Log::info("This is an INFO log.");
    }

    /**
     * Test warning logging
     */
    public function testWarningLogging()
    {
        global $log_verbosity;
        $log_verbosity = 2;
        Log::warning("This is a WARNING log.");
    }

    /**
     * Test error logging
     */
    public function testErrorLogging()
    {
        global $log_verbosity;
        $log_verbosity = 2;
        Log::error("This is an ERROR log.");
    }

}

<?php
/**
 * Log
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm;

// Set up logging
use Monolog\Formatter\LineFormatter;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

require_once dirname(__FILE__) . '/bootstrap.php';

/**
 * Singleton class that sets up the logging facility (using Monolog) once.
 *
 * Usage:
 *
 * Log::info("An info message.");
 * Log::warning("A warning message.");
 * Log::error("An error message.");
 * 
 * @package hrm
 */
class Log
{
    /**
     * Log instance (singleton)
     * @var Log
     */
    private static $instance;

    /**
     * Instance of the Monolog::Logger class.
     * @var Logger
     */
    private static $monologger;

    /**
     * Private Log constructor.
     *
     * Instantiates a static instance of the Log class and of the
     * Monolog::Logger class.
     */
    private function __construct()
    {
        // Retrieve settings from configuration files
        global $log_verbosity, $logdir, $logfile;

        // Debug level
        switch ($log_verbosity) {
            case 0:
                $level = Logger::ERROR;
                break;
            case 1:
                $level = Logger::WARNING;
                break;
            case 2:
                $level = Logger::INFO;
                break;
            default:
                $level = Logger::WARNING;
                break;
        }

        // Initialize and configure Monolog::StreamHandler
        $handler = new StreamHandler($logdir . '/' . $logfile, $level);
        $formatter = new LineFormatter(null, null, false, true);
        $handler->setFormatter($formatter);

        // Instantiate Monolog::Logger
        self::$monologger = new Logger('hrm');
        self::$monologger->pushHandler($handler);

        // Log initialization
        self::$monologger->addInfo("Initialized logging.");
    }

    /**
     * Returns the logger (after initializing it, if needed)
     * @return Logger Monolog::Logger object.
     */
    private static function getMonoLogger()
    {
        // Initialize if needed
        if (is_null(self::$instance) && is_null(self::$monologger)) {
            self::$instance = new self();
        }

        // Return the logger instance
        return self::$monologger;
    }

    /**
     * Log info message.
     * @param string $message Info message.
     */
    public static function info($message)
    {
        self::getMonoLogger()->addInfo($message);
    }

    /**
     * Log warning message.
     * @param string $message Warning message.
     */
    public static function warning($message)
    {
        self::getMonoLogger()->addWarning($message);
    }

    /**
     * Log error message.
     * @param string $message Error message.
     */
    public static function error($message)
    {
        self::getMonoLogger()->addError($message);
    }
}

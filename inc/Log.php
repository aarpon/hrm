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
use Exception;
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
    private static $instance = null;

    /**
     * Instance of the Monolog::Logger class.
     * @var Logger
     */
    private static $mono_logger = null;

    /**
     * Private Log constructor.
     *
     * Instantiates a static instance of the Log class and of the
     * Monolog::Logger class.
     * @throws Exception If the StreamHandler object cannot be initialized.
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
                throw new Exception("Invalid log verbosity value.");
        }

        // Initialize and configure Monolog::StreamHandler
        $handler = new StreamHandler($logdir . '/' . $logfile, $level);
        $formatter = new LineFormatter(null, null, false, true);
        $handler->setFormatter($formatter);

        // Instantiate Monolog::Logger
        self::$mono_logger = new Logger('hrm');
        self::$mono_logger->pushHandler($handler);

        // Log initialization
        self::$mono_logger->addInfo("Initialized logging.");
    }

    /**
     * Do not allow the object to be copied.
     */
    private function __clone()
    {
    }

    /**
     * Returns the logger (after initializing it, if needed)
     * @return Logger Logger object.
     * @throws Exception If the Logger cannot be instantiated.
     */
    private static function getMonoLogger(): Logger
    {
        // Initialize if needed
        if ((!is_object(self::$instance)) || (!is_object(self::$mono_logger))) {
            self::$instance = new Log();
        }

        // Return the logger instance
        return self::$mono_logger;
    }

    /**
     * Log info message.
     * @param string|array $message Info message.
     * @throws Exception If the Logger cannot be instantiated.
     */
    public static function info($message): void
    {
        self::getMonoLogger()->addInfo(self::stringify($message));
    }

    /**
     * Log warning message.
     * @param string|array  $message Warning message.
     * @throws Exception If the Logger cannot be instantiated.
     */
    public static function warning($message): void
    {
        self::getMonoLogger()->addWarning(self::stringify($message));
    }

    /**
     * Log error message.
     * @param string|array $message Error message.
     * @throws Exception If the Logger cannot be instantiated.
     */
    public static function error($message): void
    {
        self::getMonoLogger()->addError(self::stringify($message));
    }

    /**
     * Takes either a string or an array of strings and returns a string.
     * @param string|array $message Message
     * @return string Message as a string.
     * @throws Exception If the input message is neither a string nor an array of strings.
     */
    private static function stringify($message): string
    {
        // Do we have a string? Nothing to do.
        if (is_string($message)) {
            return $message;
        }

        // Do we have an array? We create a comma-separated expansion of it.
        if (is_array($message)) {
            return implode(", ", $message);
        }

        // Other types are not supported.
        throw new Exception("Log functions expect a string or an array.");
    }
}

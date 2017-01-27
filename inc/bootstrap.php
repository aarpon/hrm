<?php
/**
 * Bootstrap
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 *
 * Sets up class path and imports settings.
 */

// Set up Composer's autoloading
require_once dirname(__FILE__) . '/../vendor/autoload.php';

// Load configuration
require_once dirname(__FILE__) . '/hrm_config.inc.php';

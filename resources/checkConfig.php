<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// $ php checkConfig.php /path/to/config/file
//
// Example: php checkConfig.php /var/www/html/hrm/config/hrm_config.inc

require_once dirname(__FILE__) . '/../inc/bootstrap.php';

use hrm\System;

switch ($argc) {
    case 1:
        displayUsage();
        return;
    case 2:
        checkConfigFile($argv[1]);
        return;
    default:
        echo PHP_EOL . "Error: wrong number of input arguments!" . PHP_EOL;
        displayUsage();
        return;
}

    // END

function displayUsage()
{
    echo PHP_EOL . "Usage: php checkConfig.php /path/to/config/file" . PHP_EOL . PHP_EOL .
        "Example: php checkConfig.php /var/www/html/hrm/config/hrm_config.inc" .
    PHP_EOL . PHP_EOL;
}

function checkConfigFile($configFile)
{
    if (! file_exists($configFile)) {
        echo "File " . $configFile . " not found!" . PHP_EOL . PHP_EOL;
        return;
    }

     echo "Checking against HRM v" . System::getHRMVersionAsString() . "." . PHP_EOL;

     include($configFile);

    // Variables that must exist
    $variables = array(
        "db_type", "db_host", "db_name", "db_user", "db_password",
        "huygens_user", "huygens_group", "local_huygens_core",
        "image_host", "image_user", "image_group",
        "image_folder", "image_source", "image_destination",
        "huygens_server_image_folder", "allowHttpTransfer",
        "allowHttpUpload", "max_upload_limit", "max_post_limit",
        "compressExt", "compressBin", "packExcludePath",
        "dlMimeType", "decompressBin", "hrm_url", "hrm_path",
        "log_verbosity", "logdir", "logfile", "logfile_max_size",
        "send_mail", "email_sender", "email_admin",
        "email_list_separator", "authenticateAgainst",
        "imageProcessingIsOnQueueManager",
        "copy_images_to_huygens_server", "useThumbnails",
        "genThumbnails", "movieMaxSize", "saveSfpPreviews",
        "maxComparisonSize", "ping_command", "ping_parameter",
        "omero_transfers", "default_output_format",
        "min_free_mem_launch_requirement");

    // Variables that were removed
    $variablesRemoved = array("internal_link", "external_link",
        "adodb", "enableUserAdmin", "allow_reservation_users",
        "resultImagesOwnedByUser", "resultImagesRenamed",
        "runningLocation", "convertBin", "enable_code_for_huygens",
        "change_ownership", "useDESEncryption");

    // Check for variables that must exist
    $numMissingVariables = 0;
    foreach ($variables as &$variable) {
        if (! isset($$variable)) {
               echo "* * * Error: variable $variable not set or empty." . PHP_EOL;
               $numMissingVariables++;
        }
    }

    // Check for variables that must be removed
    $numVariablesToRemove = 0;
    foreach ($variablesRemoved as &$variable) {
        if (isset($$variable)) {
               echo "* * * Error: variable $variable must be removed from the configuration files!" . PHP_EOL;
               $numVariablesToRemove++;
        }
    }

    // Check the values of the $authenticateAgainst variable
    $numVariableToFix = 0;
    if (!is_array($authenticateAgainst)) {
        echo "* * * Error: variable 'authenticateAgainst' must be an array!" . PHP_EOL;
        if ($authenticateAgainst == "MYSQL") {
            echo "* * * Moreover, please change 'MYSQL' into 'integrated'." . PHP_EOL;
        } elseif ($authenticateAgainst == "ACTIVE_DIR") {
            echo "* * * Moreover, please change 'ACTIVE_DIR' into 'active_dir'." . PHP_EOL;
        } elseif ($authenticateAgainst == "LDAP") {
            echo "* * * Moreover,  please change 'LDAP' into 'ldap'." . PHP_EOL;
        } else {
            //
        }
        $numVariableToFix = 1;
    }

    if ($numMissingVariables + $numVariablesToRemove + $numVariableToFix == 0) {
        echo "Check completed successfully! Your configuration file is valid!" . PHP_EOL;
    } else {
        echo "Check completed with errors! Please fix your configuration!" . PHP_EOL;
    }
}

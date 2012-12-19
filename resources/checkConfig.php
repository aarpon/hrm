<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

    // To use: execute from bash
    // $ php checkConfig.php /path/to/config/file
    //
    // Example: php checkConfig.php /var/www/hrm/config/hrm_server.config.inc
    
    switch ( $argc ) {
        case 1:
            displayUsage();
            return;
        case 2:
            checkConfigFile( $argv[ 1 ] );
            return;
        default:
            echo PHP_EOL . "Error: wrong number of input arguments!" . PHP_EOL;
            displayUsage();
            return;
    }    

    // END
    
    function displayUsage( ) {
        echo PHP_EOL . "Usage: php check.php /path/to/config/file" . PHP_EOL . PHP_EOL . 
    		"Example: php check.php /var/www/html/hrm/config/hrm_server_config.inc" .
        PHP_EOL . PHP_EOL;
    }
    
    function checkConfigFile( $configFile ) {
        if ( ! file_exists( $configFile ) ) {
            echo "File " . $configFile . " not found!" . PHP_EOL . PHP_EOL;
            return;
         }
         
         echo "Check against HRM v2.2.x." . PHP_EOL;
         
         require_once $configFile;
         
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
			"useDESEncryption", "imageProcessingIsOnQueueManager",
			"copy_images_to_huygens_server", "useThumbnails",
			"genThumbnails", "movieMaxSize", "saveSfpPreviews",
			"maxComparisonSize", "ping_command", "ping_parameter",
            "omero_transfers", "omero_server");

        // Variables that were removed
		$variablesRemoved = array( "internal_link", "external_link",
			"adodb", "enableUserAdmin", "allow_reservation_users",
			"resultImagesOwnedByUser", "resultImagesRenamed",
			"runningLocation", "convertBin", "enable_code_for_huygens" );
        
		// Check for variables that must exist
		$numMissingVariables = 0;
        foreach ( $variables as &$variable ) {
             if ( ! isset( $$variable ) ) {
                 echo "* * * Error: variable $variable not set or empty." . PHP_EOL;
				 $numMissingVariables++;
             }
        }
		
		// Check for variables that must be removed
		$numVariablesToRemove = 0;
        foreach ( $variablesRemoved as &$variable ) {
             if ( isset( $$variable ) ) {
                 echo "* * * Error: variable $variable must be removed from the configuration files!" . PHP_EOL;
				 $numVariablesToRemove++;
             }
        }

		if ( $numMissingVariables + $numVariablesToRemove == 0 ) {
		    echo "Check completed succesfully! Your configuration file is valid!" . PHP_EOL;
		} else {
			echo "Check completed with errors! Please fix your configuration!" . PHP_EOL;
		}
    }
?>

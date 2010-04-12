<?php
// This script checks the configuration files for completeness

// First version: Aaron Ponti, 2010/04/12
// Checks against HRM version 1.2.x

// This file is part of huygens remote manager.

// Copyright: Montpellier RIO Imaging (CNRS)

// contributors :
// 	     Pierre Travo	(concept)
// 	     Volker Baecker	(concept, implementation)

// email:
// 	pierre.travo@crbm.cnrs.fr
// 	volker.baecker@crbm.cnrs.fr

// Web:     www.mri.cnrs.fr

// Author: Aaron Ponti

// huygens remote manager is a software that has been developed at 
// Montpellier Rio Imaging (mri) in 2004 by Pierre Travo and Volker 
// Baecker. It allows running image restoration jobs that are processed 
// by 'Huygens professional' from SVI. Users can create and manage parameter 
// settings, apply them to multiple images and start image processing 
// jobs from a web interface. A queue manager component is responsible for 
// the creation and the distribution of the jobs and for informing the user 
// when jobs finished.

// This software is governed by the CeCILL license under French law and 
// abiding by the rules of distribution of free software. You can use, 
// modify and/ or redistribute the software under the terms of the CeCILL 
// license as circulated by CEA, CNRS and INRIA at the following URL 
// "http://www.cecill.info".

// As a counterpart to the access to the source code and  rights to copy, 
// modify and redistribute granted by the license, users are provided only 
// with a limited warranty and the software's author, the holder of the 
// economic rights, and the successive licensors  have only limited 
// liability.

// In this respect, the user's attention is drawn to the risks associated 
// with loading, using, modifying and/or developing or reproducing the 
// software by the user in light of its specific status of free software, 
// that may mean that it is complicated to manipulate, and that also 
// therefore means that it is reserved for developers and experienced 
// professionals having in-depth IT knowledge. Users are therefore encouraged 
// to load and test the software's suitability as regards their requirements 
// in conditions enabling the security of their systems and/or data to be 
// ensured and, more generally, to use and operate it in the same conditions 
// as regards security.

// The fact that you are presently reading this means that you have had 
// knowledge of the CeCILL license and that you accept its terms.

    // To use: execute from bash
    // $ php check.php /path/to/config/file
    //
    // Example: php check.php /var/www/html/hrm/inc/hrm_server.config.inc
    
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
    		"Example: php check.php /var/www/html/hrm/inc/hrm_server_config.inc" .
        PHP_EOL . PHP_EOL;
    }
    
    function checkConfigFile( $configFile ) {
        if ( ! file_exists( $configFile ) ) {
            echo "File " . $configFile . " not found!" . PHP_EOL . PHP_EOL;
            return;
         }
         
         echo "Check against HRM v1.2.x." . PHP_EOL;
         
         require_once $configFile;
         
         // Variables that must exist
        $variables = array( 
			"db_type", "db_host", "db_name", "db_user", "db_password",
        	"huygens_user", "huygens_group", "local_huygens_core", 
			"enable_code_for_huygens", "image_host", "image_user", 
			"image_group", "image_folder", "image_source",
			"image_destination", "huygens_server_image_folder",
			"allowHttpTransfer", "allowHttpUpload", "compressExt",
			"compressBin", "packExcludePath", "dlMimeType",
			"decompressBin", "hrm_url", "hrm_path", "log_verbosity",
			"logdir", "logfile", "logfile_max_size", "send_mail",
			"email_sender", "email_admin", "authenticateAgainst",
			"useDESEncryption", "imageProcessingIsOnQueueManager",
			"copy_images_to_huygens_server", "resultImagesOwnedByUser",
			"resultImagesRenamed", "useThumbnails", "genThumbnails",
			"movieMaxSize", "saveSfpPreviews", "maxComparisonSize",
			"ping_command", "ping_parameter" );
         
         foreach ( $variables as &$variable ) {
             if ( ! isset( $$variable ) ) {
                 echo "Error: variable $variable not set or empty." . PHP_EOL;
             }
         }
         
         echo "Check completed." . PHP_EOL;
    }
?>

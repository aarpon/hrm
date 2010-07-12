<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("Util.inc");
require_once("Database.inc");
require_once("hrm_config.inc");

class System {
    
    /*
        This is a commodity class for inspecting the system. 
        It returns values in human-readable form (e.g. memory quantities as
        "134'217'728 bytes").
        To get values to use for calculations, use the corresponding functions
        (see code).
    */

    // HuCore version as a string, e.g. 3.6.0-p2
    public function huCoreVersion ( ) {
        $db = new DatabaseConnection();
        return getHuCoreVersionAsString( $db->getHuCoreVersion() );
    }

    // Apache version
    public function apacheVersion( ) {
        if (preg_match('|Apache\/(\d+)\.(\d+)\.(\d+)|',
            apache_get_version(), $apver)) {
            return "${apver[1]}.${apver[2]}.${apver[3]}";
        } else {
            return "Unknown";
        }
    }

    // Database type (e.g. mysql, postgresql)
    public function databaseType( ) {
        $db = new DatabaseConnection();
        return $db->type();
    }
    
    // Database version
    public function databaseVersion( ) {
        $db = new DatabaseConnection();
        if (preg_match('|(\d+)\.(\d+)\.(\d+)|',
            $db->version(), $dbver)) {
            return "${dbver[1]}.${dbver[2]}.${dbver[3]}";
        } else {
            return "Unknown";
        }
    }
    
    // PHP version
    public function phpVersion( ) {
        if (preg_match('|(\d+)\.(\d+)\.(\d+)|',
            phpversion( ), $dbver)) {
            return "${dbver[1]}.${dbver[2]}.${dbver[3]}";
        } else {
            return "Unknown";
        }
    }
    
    // Memory limit (as set in php.ini)
    public function memoryLimit( ) {
        return ( number_format( let_to_num(
            ini_get( 'memory_limit' ) ), 0, '.', '\'') . " bytes" );
    }
    
    // Post max size (as set in php.ini)
    public function postMaxSizeFromIni( ) {
        return ( number_format( let_to_num(
            ini_get( 'post_max_size' ) ), 0, '.', '\'') . " bytes" );
    }

    // Post max size (as set in the configuration files)
    public function postMaxSizeFromConfig( ) {
        global $max_post_limit;
        if ( isset( $max_post_limit ) ) {
            if ( $max_post_limit == 0 ) {
                return "Limited by php.ini.";
            } else {
                return ( number_format(
                    $max_post_limit * 1024 * 1024, 0, '.', '\'') . " bytes" );
            }
        } else {
            return "Not defined!";
        }
    }

    // Post max size in use
    public function postMaxSize( ) {
        return ( number_format( getMaxPostSize( ), 0, '.', '\'') . " bytes" );
    }
    
    // Upload max file size (as set in php.ini)
    public function uploadMaxFileSizeFromIni( ) {
        return ( number_format( let_to_num(
            ini_get( 'upload_max_filesize' ) ), 0, '.', '\'') . " bytes" );
    }


    // Upload max file size (as set in the configuration files)
    public function uploadMaxFileSizeFromConfig( ) {
        global $max_upload_limit;
        if ( isset( $max_upload_limit ) ) {
            if ( $max_upload_limit == 0 ) {
                return "Limited by php.ini.";
            } else {
                return ( number_format(
                    $max_upload_limit * 1024 * 1024, 0, '.', '\'') . " bytes" );
            }
        } else {
            return "Not defined!";
        }        
    }
    
    // Upload max file size in use
    public function uploadMaxFileSize( ) {
        return ( number_format( getMaxFileSize( ), 0, '.', '\'') . " bytes" );
    }

};

?>

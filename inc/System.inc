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
        134'217'728 bytes, or 128MB).
        To get values to use for calculations, use the corresponding functions
        (see code).
    */

	//!------------------------------------------------------------------------
	// @function    System::operatingSystem
	// @desc        Returns information about operating system and machine architecture
	// @return      Returns a string with OS and architecture (e.g. GNU/Linux x86/64)
	//!------------------------------------------------------------------------
    public function operatingSystem( ) {
        try {
            return ( exec( 'uname --operating-system' ) . " " .
                     exec( 'uname --hardware-platform' ) );
        } catch ( Exception $e ) {
            return "Unknown.";
        }
    }

	//!------------------------------------------------------------------------
	// @function    System::kernelRelease
	// @desc        Returns the kernel release number
	// @return      Returns the kernel release number (e.g. 2.6.33)
	//!------------------------------------------------------------------------
    public function kernelRelease( ) {
        try {
            return ( exec( 'uname --kernel-release' ) );
        } catch ( Exception $e ) {
            return "Unknown.";
        }
    }
 
	//!------------------------------------------------------------------------
	// @function    System::huCoreVersion
	// @desc        Returns a string containig the HuCore version
	//              as read from the database.
	// @return      HuCore version as a string, e.g. 3.6.0-p2
	//!------------------------------------------------------------------------
    public function huCoreVersion ( ) {
        $db = new DatabaseConnection();
        return getHuCoreVersionAsString( $db->getHuCoreVersion() );
    }

	//!------------------------------------------------------------------------
	// @function    System::apacheVersion
	// @desc        Returns a string containig the version of the Apache 
	//              web server.
	// @return      Apache version as a string, e.g. 2.2.14
	//!------------------------------------------------------------------------
    public function apacheVersion( ) {
        if (preg_match('|Apache\/(\d+)\.(\d+)\.(\d+)|',
            apache_get_version(), $apver)) {
            return "${apver[1]}.${apver[2]}.${apver[3]}";
        } else {
            return "Unknown";
        }
    }

	//!------------------------------------------------------------------------
	// @function    System::databaseType
	// @desc        Returns the database type.
	// @return      Database type, one of the types supported by ADOdb.
	//              To be compatible with the HRM it should be one of 
	//              'mysql', 'postgresql'
	//!------------------------------------------------------------------------
    public function databaseType( ) {
        $db = new DatabaseConnection();
        return $db->type();
    }
    
	//!------------------------------------------------------------------------
	// @function    System::databaseVersion
	// @desc        Returns the database version.
	// @return      Database version as a string, e.g. 5.1.44
	//!------------------------------------------------------------------------
    public function databaseVersion( ) {
        $db = new DatabaseConnection();
        if (preg_match('|(\d+)\.(\d+)\.(\d+)|',
            $db->version(), $dbver)) {
            return "${dbver[1]}.${dbver[2]}.${dbver[3]}";
        } else {
            return "Unknown";
        }
    }
    
	//!------------------------------------------------------------------------
	// @function    System::phpVersion
	// @desc        Returns the php version (for the Apache PHP module).
	// @return      PHP version, e.g. 5.31
	//!------------------------------------------------------------------------
    public function phpVersion( ) {
        if (preg_match('|(\d+)\.(\d+)\.(\d+)|',
            phpversion( ), $dbver)) {
            return "${dbver[1]}.${dbver[2]}.${dbver[3]}";
        } else {
            return "Unknown";
        }
    }
    
	//!------------------------------------------------------------------------
	// @function    System::memoryLimit
	// @desc        Memory limit as set in php.ini
	// @param       $unit. One of 'B' for bytes, 'M' for Megabytes, or 'G' for 
	//              Gigabytes. Default is 'M'. Omit the parameter to use the 
	//              default. 
	// @return      Memory limit in bytes      
	//!------------------------------------------------------------------------
    public function memoryLimit( $unit = 'M' ) {
        return System::formatMemoryStringByUnit(
            let_to_num( ini_get( 'memory_limit' ) ), $unit );
    }
   
	//!------------------------------------------------------------------------
	// @function    System::postMaxSizeFromIni
	// @desc        Max allowed size for an HTTP post as set in php.ini
	// @return      max allowed size for an HTTP post in bytes      
	//!------------------------------------------------------------------------
    public function postMaxSizeFromIni( $unit = 'M' ) {
        return System::formatMemoryStringByUnit(
            let_to_num( ini_get( 'post_max_size' ) ), $unit );
    }

	//!------------------------------------------------------------------------
	// @function    System::postMaxSizeFromConfig
	// @desc        Max allowed size for an HTTP post as set in the HRM 
	//              configuration files
	// @return      Max allowed size for an HTTP post in bytes      
	//!------------------------------------------------------------------------
    public function postMaxSizeFromConfig( $unit = 'M' ) {
        global $max_post_limit;
        if ( isset( $max_post_limit ) ) {
            if ( $max_post_limit == 0 ) {
                return "Limited by php.ini.";
            } else {
                return System::formatMemoryStringByUnit(
                    let_to_num( ini_get( '$max_post_limit' ) ), $unit );
            }
        } else {
            return "Not defined!";
        }
    }

	//!------------------------------------------------------------------------
	// @function    System::postMaxSize
	// @desc        Max allowed size for an HTTP post currently in use
	// @return      Max allowed size for an HTTP post in bytes      
	//!------------------------------------------------------------------------
    public function postMaxSize( $unit = 'M' ) {
        return System::formatMemoryStringByUnit( getMaxPostSize( ), $unit );
    }
    
	//!------------------------------------------------------------------------
	// @function    System::uploadMaxFileSizeFromIni
	// @desc        Max allowed size for a file upload as set in php.ini
	// @return      Max allowed size for a file upload in bytes      
	//!------------------------------------------------------------------------
    public function uploadMaxFileSizeFromIni( $unit = 'M' ) {
        return System::formatMemoryStringByUnit(
            let_to_num( ini_get( 'upload_max_filesize' ) ), $unit );
    }

	//!------------------------------------------------------------------------
	// @function    System::uploadMaxFileSizeFromConfig
	// @desc        Max allowed size for a file upload as set in the HRM 
	//              configuration files
	// @return      Max allowed size for a file upload in bytes      
	//!------------------------------------------------------------------------
    public function uploadMaxFileSizeFromConfig( $unit = 'M' ) {
        global $max_upload_limit;
        if ( isset( $max_upload_limit ) ) {
            if ( $max_upload_limit == 0 ) {
                return "Limited by php.ini.";
            } else {
                return System::formatMemoryStringByUnit(
                    let_to_num( $max_upload_limit ), $unit );
            }
        } else {
            return "Not defined!";
        }        
    }
    
	//!------------------------------------------------------------------------
	// @function    System::uploadMaxFileSize
	// @desc        Max allowed size for a file upload currently in use
	// @return      Max allowed size for a file upload in bytes
	//!------------------------------------------------------------------------
    public function uploadMaxFileSize( ) {
         return System::formatMemoryStringByUnit(
            getMaxFileSize(), $unit );
    }
   
	//!------------------------------------------------------------------------
	// @function    [Private] System::formatMemoryStringByUnit( $value, $unit = 'M' )
	// @desc        Formats a number (in bytes) into a string with the desired unit.
	//              For example, System::formatMemoryStringByUnit( 134, $unit = 'M' ) 
	//              returns '128 MB'.
	// @param       $value: memory amount in bytes.
	// @param       $unit:  One of 'B' for bytes, 'M' for Megabytes, or 'G' for 
	//              Gigabytes. Default is 'M'. Omit the parameter to use the 
	//              default. 
	// @return      Memory amount with the requested format.
	//!------------------------------------------------------------------------
    private function formatMemoryStringByUnit( $value, $unit = 'M' ) {
        switch ( $unit ) {
            case 'G' :
                $factor      = 1024 * 1024 * 1024;
                $digits      = 3;
                $unit_string = "GB";
                break;
            case 'B' :
                $factor      = 1;
                $digits      = 0;
                $unit_string = " bytes";
                break;
            default: // Includes 'M'
                $factor      = 1024 * 1024;
                $digits      = 0;
                $unit_string = 'MB';
                break;
        }       
        return ( number_format( $value / $factor, $digits, '.', '\'') . $unit_string );  
    }

};

?>

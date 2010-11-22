<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("Util.inc");
require_once("Database.inc");

class System {
    
    /*
        This is a commodity class for inspecting the system. 
        It returns values in human-readable form (e.g. memory quantities as
        134'217'728 bytes, or 128MB).
        To get values to use for calculations, use the corresponding functions
        (see code).
    */

    // These fields have to be updated by the developers!
    const HRM_VERSION      = "1.3.0";
    const DB_LAST_REVISION = 7;
    
	//!------------------------------------------------------------------------
	// @function    System::getHRMVersion
	// @desc        Return the HRM version
	// @return      Returns the HRM version number (e.g. 1.3.0)
	//!------------------------------------------------------------------------
    public static function getHRMVersion( ) {
        return self::HRM_VERSION;
    }

	//!------------------------------------------------------------------------
	// @function    System::printHRMVersion
	// @desc        Prints the HRM version
	// @return      Prints the HRM version number (e.g. 1.3.0)
	//!------------------------------------------------------------------------
    public static function printHRMVersion( ) {
        print self::HRM_VERSION;
    }
    
	//!------------------------------------------------------------------------
	// @function    System::getDBLastRevision
	// @desc        Returns DB revision expected by this version of the HRM
	// @return      Returns DB revision expected by this version of the HRM (e.g. 7)
	//!------------------------------------------------------------------------
    public static function getDBLastRevision( ) {
        return self::DB_LAST_REVISION;
    }
  
	//!------------------------------------------------------------------------
	// @function    System::getDBCurrentRevision
	// @desc        Returns DB revision from the database
	// @return      Returns DB revision from the database (e.g. 6)
	//!------------------------------------------------------------------------
    public static function getDBCurrentRevision( ) {
        $db   = new DatabaseConnection();
        $rows = $db->query(
            "SELECT * FROM global_variables WHERE name LIKE 'dbrevision';");
        if ( !$rows ) {
            return 0;
        } else {
            return $rows[0]['value'];
        }
    }

	//!------------------------------------------------------------------------
	// @function    System::isDBUpToDate
	// @desc        Checks whether the database is up-to-date
	// @return      Returns true if the database is up-to-date, false otherwise
	//!------------------------------------------------------------------------
    // Return true if the database is up-to-date
    public static function isDBUpToDate( ) {
        return ( self::getDBLastRevision( ) == self::getDBCurrentRevision( ) );
    }

	//!------------------------------------------------------------------------
	// @function    System::huCoreVersion
	// @desc        Returns the HuCore version in integer notation
	// @return      HuCore version as a integer, e.g. 
	//!------------------------------------------------------------------------
    public static function huCoreVersion ( ) {
        $db = new DatabaseConnection();
        $version = $db->getHuCoreVersion();
		if ( $version == false ) {
			return 0;
		} else {
			return $version;
		}
    }

	//!---------------------------------------------------------
	// @function    System::getHucoreVersionAsString
	// @desc        Returns the hucore version as its string representation
	// @return      The version number as string
	//!---------------------------------------------------------
	public static function hucoreVersionAsString( ) {
	  $version = self::huCoreVersion( );
	  if ( $version == false ) {
		return '0.0.0;';
	  }
	  $major      = floor( $version / 1000000 );
	  $version    = $version - $major * 1000000;
	  $minor      = floor( $version / 10000 );
	  $version    = $version - $minor * 10000;
	  $minorminor = floor( $version / 100 );
	  $version    = $version - $minorminor * 100;
	  $patch      = $version;
	  if ( $version != 0 ) {
		$versionString = $major . '.' . $minor . '.' . $minorminor . '-p' . $patch;
	  } else {
		$versionString = $major . '.' . $minor . '.' . $minorminor;
	  }
	  return $versionString;
	}

	//!------------------------------------------------------------------------
	// @function    System::operatingSystem
	// @desc        Returns information about operating system and machine architecture
	// @return      Returns a string with OS and architecture (e.g. GNU/Linux x86/64)
	//!------------------------------------------------------------------------
    public static function operatingSystem( ) {
        try {
            return ( exec( 'uname -s' ) . " " . exec( 'uname -p' ) );
        } catch ( Exception $e ) {
            return "Unknown.";
        }
    }

	//!------------------------------------------------------------------------
	// @function    System::kernelRelease
	// @desc        Returns the kernel release number
	// @return      Returns the kernel release number (e.g. 2.6.33)
	//!------------------------------------------------------------------------
    public static function kernelRelease( ) {
        try {
            return ( exec( 'uname -r' ) );
        } catch ( Exception $e ) {
            return "Unknown.";
        }
    }

	//!------------------------------------------------------------------------
	// @function    System::apacheVersion
	// @desc        Returns a string containig the version of the Apache 
	//              web server.
	// @return      Apache version as a string, e.g. 2.2.14
	//!------------------------------------------------------------------------
    public static function apacheVersion( ) {
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
    public static function databaseType( ) {
        $db = new DatabaseConnection();
        return $db->type();
    }
    
	//!------------------------------------------------------------------------
	// @function    System::databaseVersion
	// @desc        Returns the database version.
	// @return      Database version as a string, e.g. 5.1.44
	//!------------------------------------------------------------------------
    public static function databaseVersion( ) {
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
    public static function phpVersion( ) {
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
    public static function memoryLimit( $unit = 'M' ) {
        return System::formatMemoryStringByUnit(
            let_to_num( ini_get( 'memory_limit' ) ), $unit );
    }
   
	//!------------------------------------------------------------------------
	// @function    System::postMaxSizeFromIni
	// @desc        Max allowed size for an HTTP post as set in php.ini
	// @return      max allowed size for an HTTP post in bytes      
	//!------------------------------------------------------------------------
    public static function postMaxSizeFromIni( $unit = 'M' ) {
        return System::formatMemoryStringByUnit(
            let_to_num( ini_get( 'post_max_size' ) ), $unit );
    }

	//!------------------------------------------------------------------------
	// @function    System::postMaxSizeFromConfig
	// @desc        Max allowed size for an HTTP post as set in the HRM 
	//              configuration files
	// @return      Max allowed size for an HTTP post in bytes      
	//!------------------------------------------------------------------------
    public static function postMaxSizeFromConfig( $unit = 'M' ) {
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
    public static function postMaxSize( $unit = 'M' ) {
        return System::formatMemoryStringByUnit( getMaxPostSize( ), $unit );
    }

	//!------------------------------------------------------------------------
	// @function    System::doqnloadEnabledFromConfig
	// @desc        Returns the status of file downloads through HTTP as set
    //              in the HRM configuration
	// @return      'enabled' if the download is enabled; 'disabled' otherwise
	//!------------------------------------------------------------------------
    public static function downloadEnabledFromConfig( ) {
        global $allowHttpTransfer;
        if ( $allowHttpTransfer == true ) {
            return "enabled";
        } else {
            return "disabled";
        }
    }
  
	//!------------------------------------------------------------------------
	// @function    System::uploadEnabledFromConfig
	// @desc        Returns the status of file uploads through HTTP as set
    //              in the HRM configuration
	// @return      'enabled' if the upload is enabled; 'disabled' otherwise
	//!------------------------------------------------------------------------
    public static function uploadEnabledFromConfig( ) {
        global $allowHttpUpload;
        if ( $allowHttpUpload == true ) {
            return "enabled";
        } else {
            return "disabled";
        }
    }
  
	//!------------------------------------------------------------------------
	// @function    System::uploadMaxFileSizeFromIni
	// @desc        Max allowed size for a file upload as set in php.ini
	// @return      Max allowed size for a file upload in bytes      
	//!------------------------------------------------------------------------
    public static function uploadMaxFileSizeFromIni( $unit = 'M' ) {
        return System::formatMemoryStringByUnit(
            let_to_num( ini_get( 'upload_max_filesize' ) ), $unit );
    }

	//!------------------------------------------------------------------------
	// @function    System::uploadMaxFileSizeFromConfig
	// @desc        Max allowed size for a file upload as set in the HRM 
	//              configuration files
	// @return      Max allowed size for a file upload in bytes      
	//!------------------------------------------------------------------------
    public static function uploadMaxFileSizeFromConfig( $unit = 'M' ) {
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
    public static function uploadMaxFileSize( ) {
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

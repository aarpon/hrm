<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("Util.inc.php");
require_once("Database.inc.php");

/*!
 \class	System
 \brief	Commodity class for inspecting the System.

 This <b>static</b> class provides several commodity functions to inspect
 system and configuration parameters and to format their input in various
 ways.
 */
class System {

	/*!
		\var 	HRM_VERSION
		\brief	Current HRM version

		This value has to be set by the developers!
		*/
	const HRM_VERSION = "3.0";

	/*!
		\var 	DB_LAST_REVISION
		\brief	Database revision needed by current HRM version

		This value has to be set by the developers!
		*/
	const DB_LAST_REVISION = 10;

	/*!
		\var 	MIN_HUCORE_VERSION
		\brief	Minimum HuCore version number to be compatible with the HRM

		This value has to be set by the developers!
		*/
	const MIN_HUCORE_VERSION = 4020105;

	/*!
		\brief	Returns the HRM version
		\return	the HRM version number (e.g. 2.1)
		*/
	public static function getHRMVersion( ) {
		return self::HRM_VERSION;
	}

	/*!
		\brief	Prints the HRM version directly to the page
		*/
	public static function printHRMVersion( ) {
		print self::HRM_VERSION;
	}

	/*!
		\brief	Returns the DB revision expected by this version of the HRM
		\return expected DB revision (e.g. 8)
		*/
	public static function getDBLastRevision( ) {
		return self::DB_LAST_REVISION;
	}

	/*!
		\brief	Returns current DB revision from the database
		\return	DB revision from the database (e.g. 8)
		*/
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

	/*!
		\brief	Checks whether the database is up-to-date
		\return true if the database is up-to-date, false otherwise
		*/
	public static function isDBUpToDate( ) {
		return ( self::getDBLastRevision( ) == self::getDBCurrentRevision( ) );
	}

	/*!
		\brief	Returns the HuCore version in integer notation
		\return	HuCore version as an integer (e.g. 4010002)
		*/
	public static function huCoreVersion ( ) {
		$db = new DatabaseConnection();
		$query = "SELECT value FROM global_variables WHERE name= 'huversion'";
		$version = $db->queryLastValue( $query );
		if ( $version == false ) {
			return 0;
		} else {
			return $version;
		}
	}

	/*!
		\brief	Returns the minimum acceptable HuCore version in string notation
		\return	Minimum HuCore version as a string (e.g. 4.1.0-p2)
		*/
	public static function minHuCoreVersion ( ) {
		$a = self::hucoreVersionAsString( self::MIN_HUCORE_VERSION );
		return ( $a );
	}

	/*!
		\brief	Checks whether the HuCore version is recent enough
		\return	true if HuCore is recent enough to run the HRM, false otherwise
		*/
	public static function isMinHuCoreVersion ( ) {
		$db = new DatabaseConnection();
		$query = "SELECT value FROM global_variables WHERE name= 'huversion'";
		$version = $db->queryLastValue( $query );
		if ( $version == false ) {
			return false;
		} else {
			return ( $version >= self::MIN_HUCORE_VERSION );
		}
	}

	/*!
		\brief	Stores the HuCore version in integer notation into the DB
		\param 	$value	hucore version in integer notation (e.g. 4010002)
		\return	true if success, false otherwise
		*/
	public static function setHuCoreVersion( $value ) {
		$db = new DatabaseConnection();
		$rs = $db->query("SELECT * FROM global_variables WHERE name = 'huversion'");
		if ( !$rs ) {
			$query = "INSERT INTO global_variables VALUES ('huversion', '" . $value . "')";
		} else {
			$query = "UPDATE global_variables SET value = '" . $value . "' WHERE name = 'huversion'";
		}
		$rs = $db->execute( $query );

		if ( !$rs ) {
			return false;
		}
		return true;
	}

	/*!
		\brief	Returns the hucore version as its string representation
		\return	the version number as string (e.g. 4.1.0-p2)
		*/
	public static function hucoreVersionAsString( $version = -1 ) {
		if ( $version == -1 ) {
			$version = self::huCoreVersion( );
		}
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
			$versionString =
                $major . '.' . $minor . '.' . $minorminor . '-p' . $patch;
		} else {
			$versionString = $major . '.' . $minor . '.' . $minorminor;
		}
		return $versionString;
	}

	/*!
		\brief	Returns information about operating system and machine
		architecture
		\return	a string with OS and architecture (e.g. GNU/Linux x86-64)
		*/
	public static function operatingSystem( ) {
		// Get and interpret some information
		$s = php_uname( 's' );
		$m = php_uname( 'm' );

		if ( stristr( $s, "Linux" ) ) {
			$os = "Linux " . $m;
		} elseif ( stristr( $s, "Darwin" ) ) {
			$os = "Mac OS X " . $m;
		} else {
			$os = $s;
		}

		return $os;
	}

	/*!
		\brief	Returns the kernel release number
		\return	the kernel release number (e.g. 2.6.35)
		*/
	public static function kernelRelease( ) {
		// Get and interpret some information
		$s = php_uname( 's' );
		$r = php_uname( 'r' );

		if ( stristr( $s, "Linux" ) ) {
			return $r;
		} elseif ( stristr( $s, "Darwin" ) ) {
			$match = array();
			preg_match ("/^(\d+)\.\d+/", $r, $match );
			if ( isset( $match[ 1 ] ) ) {
				switch ( $match[ 1 ] ) {
					case '8':  return ( $r . " (Tiger)" );
					case '9':  return ( $r . " (Leopard)" );
					case '10': return ( $r . " (Snow Leopard)" );
					case '11': return ( $r . " (Lion)" );
					case '12': return ( $r . " (Mountain Lion)" );
					default:   return ( $r );
				}
			} else {
				return ( $r );
			}
		} else {
			return $r;
		}
	}

	/*!
		\brief	Returns a string containing the version of the Apache web server
		\return	Apache version as a string (e.g. 2.2.14)
		*/
	public static function apacheVersion( ) {
		if (preg_match('|Apache\/(\d+)\.(\d+)\.(\d+)|',
		apache_get_version(), $apver)) {
			return "${apver[1]}.${apver[2]}.${apver[3]}";
        } else {
        	return "Unknown";
        }
    }

    /*!
     \brief	Returns the database type as reported by ADOdb. To be compatible
     with the HRM it should be one of 'mysql' or 'postgresql'
     \return	database type (e.g. postgresql)
     */
    public static function databaseType( ) {
    	$db = new DatabaseConnection();
    	return $db->type();
    }

    /*!
     \brief	Returns the database version
     \return	database version as a string (e.g. 5.1.44)
     */
    public static function databaseVersion( ) {
    	$db = new DatabaseConnection();
    	if (preg_match('|(\d+)\.(\d+)\.(\d+)|',
    	$db->version(), $dbver)) {
    		return "${dbver[1]}.${dbver[2]}.${dbver[3]}";
        } else {
        	return "Unknown";
        }
    }

    /*!
     \brief	Returns the php version (for the Apache PHP module)
     \return	PHP version (e.g. 5.31)
     */
    public static function phpVersion( ) {
    	if (preg_match('|(\d+)\.(\d+)\.(\d+)|',
    	phpversion( ), $dbver)) {
    		return "${dbver[1]}.${dbver[2]}.${dbver[3]}";
        } else {
        	return "Unknown";
        }
    }

    /*!
     \brief	Memory limit as set in php.ini
     \param	$unit	One of 'B' for bytes, 'M' for Megabytes, or 'G' for
     Gigabytes. Default is 'M'. Omit the parameter to use the default
     \return	Memory limit in the requested unit
     */
    public static function memoryLimit( $unit = 'M' ) {
    	return System::formatMemoryStringByUnit(
    	let_to_num( ini_get( 'memory_limit' ) ), $unit );
    }

    /*!
     \brief	Max allowed size for an HTTP post as set in php.ini
     \param	$unit	One of 'B' for bytes, 'M' for Megabytes, or 'G' for
     Gigabytes. Default is 'M'. Omit the parameter to use the default
     \return	max allowed size for an HTTP post in the requested unit
     */
    public static function postMaxSizeFromIni( $unit = 'M' ) {
    	return System::formatMemoryStringByUnit(
    	let_to_num( ini_get( 'post_max_size' ) ), $unit );
    }

    /*!
     \brief	Max allowed size for an HTTP post as set in the HRM
     configuration files
     \param	$unit	One of 'B' for bytes, 'M' for Megabytes, or 'G' for
     Gigabytes. Default is 'M'. Omit the parameter to use the default
     \return	max allowed size for an HTTP post in the requested unit
     */
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

    /*!
     \brief	Max allowed size for an HTTP post currently in use
     \param	$unit	One of 'B' for bytes, 'M' for Megabytes, or 'G' for
     Gigabytes. Default is 'M'. Omit the parameter to use the default
     \return	max allowed size for an HTTP post in the requested unit
     */
    public static function postMaxSize( $unit = 'M' ) {
    	return System::formatMemoryStringByUnit( getMaxPostSize( ), $unit );
    }

    /*!
     \brief	Returns the status of file downloads through HTTP as set in the
     HRM configuration
     \return	'enabled' if the download is enabled; 'disabled' otherwise
     */
    public static function downloadEnabledFromConfig( ) {
    	global $allowHttpTransfer;
    	if ( $allowHttpTransfer == true ) {
    		return "enabled";
    	} else {
    		return "disabled";
    	}
    }

    /*!
     \brief	Returns the status of file uploads through HTTP as set in the
     HRM configuration
     \return	'enabled' if the upload is enabled; 'disabled' otherwise
     */
    public static function uploadEnabledFromConfig( ) {
    	global $allowHttpUpload;
    	if ( $allowHttpUpload == true ) {
    		return "enabled";
    	} else {
    		return "disabled";
    	}
    }

    /*!
     \brief	Returns the status of file uploads through HTTP as set in php.ini
     \return	'enabled' if the upload is enabled; 'disabled' otherwise
     */
    public static function uploadEnabledFromIni( ) {
        $upload = ini_get( 'file_uploads' );
    	if ( $upload == "1" ) {
            return "enabled";
        } else {
            return "disabled";
        }
    }

    /*!
     \brief	Returns the status of file uploads through HTTP as set in php.ini
            and the HRM configuration
     \return	'enabled' if the upload is enabled; 'disabled' otherwise
     */
    public static function uploadEnabled( ) {
        if ( System::uploadEnabledFromConfig() == "enabled" &&
                System::uploadEnabledFromIni() == "enabled" ) {
            return "enabled";
        } else {
            return "disabled";
        }
    }

    /*!
     \brief	Max allowed size for a file upload as set in php.ini
     \param	$unit	One of 'B' for bytes, 'M' for Megabytes, or 'G' for
     Gigabytes. Default is 'M'. Omit the parameter to use the default
     \return	max allowed size for a file upload in the requested unit
     */
    public static function uploadMaxFileSizeFromIni( $unit = 'M' ) {
    	return System::formatMemoryStringByUnit(
    	let_to_num( ini_get( 'upload_max_filesize' ) ), $unit );
    }

    /*!
     \brief	Max allowed size for a file upload as set in the HRM
     configuration files
     \param	$unit	One of 'B' for bytes, 'M' for Megabytes, or 'G' for
     Gigabytes. Default is 'M'. Omit the parameter to use the default
     \return	max allowed size for a file upload in the requested unit
     */
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

    /*!
     \brief	Max allowed size for a file upload currently in use
     configuration files
     \return	max allowed size for a file upload in bytes
     */
    public static function uploadMaxFileSize( $unit = 'M' ) {
    	return System::formatMemoryStringByUnit(
    	getMaxFileSize(), $unit );
    }

    /*!
     \brief     Max execution time in seconds for scripts as set in php.ini
     \return	max execution time in seconds
     */
    public static function maxExecutionTimeFromIni( ) {
    	return ini_get( 'max_execution_time' ) . "s";
    }

    /*!
     \brief	Formats a number (in bytes) into a string with the desired unit.
     For example, System::formatMemoryStringByUnit( 134, $unit = 'M' )
     returns '128 MB'.
     \param	$value	memory amount in bytes
     \param	$unit	one of 'B' for bytes, 'M' for Megabytes, or 'G' for
     Gigabytes. Default is 'M'. Omit the parameter to use the default
     \return	memory amount in the requested unit
     */
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
    	return ( number_format( $value / $factor, $digits, '.', '\'') .
                $unit_string );
    }

};

?>

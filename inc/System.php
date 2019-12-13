<?php
/**
 * System
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm;

require_once dirname(__FILE__) . '/bootstrap.php';

/**
 * Commodity class for inspecting the System.
 *
 * This **static** class provides several commodity functions to inspect
 * system and configuration parameters and to format their input in various
 * ways.
 *
 * @package hrm
 */
class System
{

    /**
     * Current HRM major version. This value has to be set by the developers!
     * @var int
     */
    const HRM_VERSION_MAJOR = 3;

    /**
     * Current HRM minor version. This value has to be set by the developers!
     * @var int
     */
    const HRM_VERSION_MINOR = 7;

    /**
     * Current HRM maintenance (patch) version. This value has to be set by the
     * developers!
     * @var int
     */
    const HRM_VERSION_MAINTENANCE = 0;

    /**
     * Database revision needed by current HRM version. This value has to be
     * set by the developers!
     * @var int
     */
    const DB_LAST_REVISION = 18;

    /**
     * Minimum HuCore (major) version number to be compatible with HRM.
     * This value has to be set by the developers!
     * @var int
     */
    const MIN_HUCORE_VERSION_MAJOR = 14;

    /**
     * Minimum HuCore (minor) version number to be compatible with HRM.
     * This value has to be set by the developers!
     * @var int
     */
    const MIN_HUCORE_VERSION_MINOR = 6;

    /**
     * Minimum HuCore (maintenance) version number to be compatible with HRM.
     * This value has to be set by the developers!
     * @var int
     */
    const MIN_HUCORE_VERSION_MAINTENANCE = 1;

    /**
     * Minimum HuCore (patch) version number to be compatible with HRM.
     * This value has to be set by the developers!
     * @var int
     */
    const MIN_HUCORE_VERSION_PATCH = 7;

    /**
     * Returns the HRM version.
     * @param int $version (Optional) Version as integer to be converted to its
     * string counterpart. If not passed, current HRM version is returned.
     * @return  string The HRM version number as string (e.g. 3.3)
     */
    public static function getHRMVersionAsString($version = -1)
    {

        if ($version == -1) {
            // Return current HRM version
            $version = self::HRM_VERSION_MAJOR . "." . self::HRM_VERSION_MINOR;
            if (self::HRM_VERSION_MAINTENANCE > 0) {
                $version = $version . "." . self::HRM_VERSION_MAINTENANCE;
            }
        } else {
            // Format the passed version
            $version = self::parseHRMVersionIntegerToString($version);
        }
        return $version;
    }

    /**
     * Returns the HRM version.
     *
     * @return int The HRM version number as integer.
     */

    public static function getHRMVersionAsInteger()
    {
        return (1000000 * self::HRM_VERSION_MAJOR +
            10000 * self::HRM_VERSION_MINOR +
            100 * self::HRM_VERSION_MAINTENANCE);
    }

    /**
     * Returns the latest released HRM version as integer.
     *
     * The huygens-rm.org server is queried.
     *
     * @return int The latest released HRM version number as integer.
     */
    public static function getLatestHRMVersionFromRemoteAsInteger()
    {
        $version = file_get_contents('http://www.huygens-rm.org/updates/VERSION');
        if ($version === false) {
            return -1;
        }
        return $version;
    }

    /**
     *
     * Returns the latest HRM version in its string representation.
     *
     * The huygens-rm.org server is queried.
     *
     * @return string The HRM version number as string (e.g. 3.3)
     */
    public static function getLatestHRMVersionFromRemoteAsString()
    {
        $version = self::getLatestHRMVersionFromRemoteAsInteger();
        if ($version === -1) {
            return "-1";
        }
        return self::parseHRMVersionIntegerToString($version);
    }

    /**
     * Checks whether there is a new HRM release.
     *
     * isThereNewHRMRelease() throws an Exception if the version could not
     * be obtained from the remote server.
     *
     * @return bool True if a newer HRM release exist, false otherwise; if no
     * version information could be retrieved, an Exception is thrown.
     * @throws \Exception If the remove server could not be reached.
     */
    public static function isThereNewHRMRelease()
    {
        $latestVersion = self::getLatestHRMVersionFromRemoteAsInteger();
        if ($latestVersion === -1) {
            throw new \Exception("Could not retrieve version information!");
        }
        if (self::getHRMVersionAsInteger() < $latestVersion) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns the DB revision expected by this version of HRM.
     * @return int DB_LAST_REVISION expected DB revision (e.g. 14).
     */
    public static function getDBLastRevision()
    {
        return self::DB_LAST_REVISION;
    }

    /**
     * Returns current DB revision from the database.
     * @return int DB revision from the database (e.g. 14).
     */
    public static function getDBCurrentRevision()
    {
        $db = DatabaseConnection::get();
        $rows = $db->query(
            "SELECT * FROM global_variables WHERE name LIKE 'dbrevision';");
        if (!$rows) {
            return 0;
        } else {
            return $rows[0]['value'];
        }
    }

    /**
     * Checks whether the database is up-to-date.
     * @return bool True if the database is up-to-date, false otherwise.
     */
    public static function isDBUpToDate()
    {
        return (self::getDBLastRevision() == self::getDBCurrentRevision());
    }

    /**
     * Returns the HuCore version in integer notation
     * @rreturn int HuCore version as an integer (e.g. 4010002)
     */
    public static function getHuCoreVersionAsInteger()
    {
        $db = DatabaseConnection::get();
        $query = "SELECT value FROM global_variables WHERE name= 'huversion';";
        $version = $db->queryLastValue($query);
        if ($version == false) {
            return 0;
        } else {
            return $version;
        }
    }

    /**
     * Returns the minimum acceptable HuCore version in string notation.
     * @return string Minimum HuCore version as a string (e.g. 4.1.0-p2).
     */
    public static function getMinHuCoreVersionAsString()
    {
        $v = self::MIN_HUCORE_VERSION_MAJOR . "." .
            self::MIN_HUCORE_VERSION_MINOR . "." .
            self::MIN_HUCORE_VERSION_MAINTENANCE;
        if (self::MIN_HUCORE_VERSION_PATCH > 0) {
            $v = $v . "-p" . self::MIN_HUCORE_VERSION_PATCH;
        }
        return $v;
    }

    /**
     * Returns the minimum acceptable HuCore version in integer notation.
     * @return int Minimum HuCore version as an integer (e.g. 4010002)
     */
    public static function getMinHuCoreVersionAsInteger()
    {
        $v = self::MIN_HUCORE_VERSION_MAJOR * 1000000 +
            self::MIN_HUCORE_VERSION_MINOR * 10000 +
            self::MIN_HUCORE_VERSION_MAINTENANCE * 100 +
            self::MIN_HUCORE_VERSION_PATCH;
        return $v;
    }

    /**
     * Checks whether the HuCore version is recent enough.
     * @return bool True if HuCore is recent enough to run HRM, false
     * otherwise.
     */
    public static function isMinHuCoreVersion()
    {
        $db = DatabaseConnection::get();
        $query = "SELECT value FROM global_variables WHERE name= 'huversion';";
        $version = $db->queryLastValue($query);
        if ($version == false) {
            return false;
        } else {
            return ($version >= self::getMinHuCoreVersionAsInteger());
        }
    }

    /**
     * Stores the HuCore version in integer notation into the DB.
     * @param int $value HuCore version in integer notation (e.g. 4010002)
     * @return bool true if success, false otherwise.
     */
    public static function setHuCoreVersion($value)
    {
        $db = DatabaseConnection::get();
        $rs = $db->query("SELECT * FROM global_variables WHERE name = 'huversion';");
        if (!$rs) {
            $query = "INSERT INTO global_variables VALUES ('huversion', '" . $value . "');";
        } else {
            $query = "UPDATE global_variables SET value = '" . $value . "' WHERE name = 'huversion';";
        }
        $rs = $db->execute($query);
        if (!$rs) {
            return false;
        }
        return true;
    }

    /**
     * Returns the hucore version as its string representation.
     * @param int $version (Optional) Version as integer to be converted to its
     * string counterpart. If not passed, current Hucore version is returned.
     * @return string The version number as string (e.g. 4.1.0-p2)
     */
    public static function getHucoreVersionAsString($version = -1)
    {
        if ($version == -1) {
            $version = self::getHuCoreVersionAsInteger();
        }
        if ($version == false) {
            return '0.0.0;';
        }
        $version = self::parseHucoreVersionIntegerToString($version);
        return $version;
    }

    /**
     * Return a dictionary (array) with all known licenses.
     *
     * The key is the hucore license name, the value is the human-friendly module name.
     *
     * Notice that the option 'maxGBIndexed-{value}' is omitted.
     *
     * @return array with all known licenses.
     */
    public static function getAllLicenses()
    {
        $allLicenses = array(
            "microscopes" => array(
                "confocal" => "Confocal",
                "multi-photon" => "Multi-photon",
                "nipkow-disk" => "Spinning-disk",
                "spim" => "SPIM",
                "sted" => "STED",
                "sted3D" => "STED 3D",
                "widefield" => "Widefield",
                "rescan" => "Rescan",
                "detector-array" => "Array detector confocal"
            ),
            "file_formats" => array(
                "all-formats-reader" => "Additional file readers"
            ),
            "computing" => array(
                "gpuMaxCores-1024" => "GPU max cores: 1024",
                "gpuMaxCores-3072" => "GPU max cores: 3072",
                "gpuMaxCores-8192" => "GPU max cores: 8192",
                "gpuMaxCores-24576" => "GPU max cores: 24576",
                "gpuMaxMemory-2048" => "GPU max memory: 2GB",
                "gpuMaxMemory-4096" => "GPU max memory: 4GB",
                "gpuMaxMemory-24576" => "GPU max memory: 24GB",
                "gpuMaxMemory-65536" => "GPU max memory: 64GB",
                "server=desktop" => "Server type: desktop",
                "server=small" => "Server type: small",
                "server=medium" => "Server type: medium",
                "server=large" => "Server type: large",
                "server=extreme" => "Server type: extreme"
            ),
            "options" => array(
                "analysis" => "Object analyzer",
                "coloc" => "Colocalization analysis",
                "chromaticS" => "Chromatic aberration correction",
                "floating-license" => "Floating license",
                "fusion" => "Light-sheet fusion",
                "movie" => "Movie",
                "psf" => "PSF distilller",
                "stabilizer" => "Object stabilizer",
                "stitcher" => "Stitcher",
                "time" => "Time",
                "tracker" => "Tracker",
                "unmixing" => "Unmixing",
                "visu" => "Visualization"
            )
        );

        return $allLicenses;
    }

    /**
     * Return an array with the active licenses (hucore names) on this system.
     *
     * @return array with all active licenses.
     */
    public static function getActiveLicenses()
    {
        $db = DatabaseConnection::get();
        $activeLicenses = $db->getActiveLicenses();
        return $activeLicenses;
    }

    /**
     * Checks whether Huygens Core has a valid license.
     * @return bool True if the license is valid, false otherwise.
     */
    public static function hucoreHasValidLicense()
    {
        $db = DatabaseConnection::get();
        return $db->hucoreHasValidLicense();
    }

    /**
     * Finds out whether a Huygens module is supported by the license.
     * @param string $feature The module to find out about. It can use (SQL)
     * wildcards.
     * @return boolean True if the module is supported by the license.
     */
    public static function hasLicense($feature)
    {
        $db = DatabaseConnection::get();
        return $db->hasLicense($feature);
    }

    /**
     * Gets the licensed server type for Huygens Core.
     * @return string One of desktop, small, medium, large, extreme.
     */
    public static function getHucoreServerType()
    {
        $db = DatabaseConnection::get();
        return $db->hucoreServerType();
    }

    /**
     * Returns information about operating system and machine architecture.
     * @return String A string with OS and architecture (e.g. GNU/Linux x86-64).
     */
    public static function getOperatingSystem()
    {
        // Get and interpret some information
        $s = php_uname('s');
        $m = php_uname('m');

        if (stristr($s, "Linux")) {
            $os = "Linux " . $m;
        } elseif (stristr($s, "Darwin")) {
            $os = "Mac OS X " . $m;
        } else {
            $os = $s;
        }

        return $os;
    }

    /**
     * Returns the kernel release number.
     * @return string The kernel release number (e.g. 2.6.35).
     */
    public static function getKernelRelease()
    {
        // Get and interpret some information
        $s = php_uname('s');
        $r = php_uname('r');

        if (stristr($s, "Linux")) {
            return $r;
        } elseif (stristr($s, "Darwin")) {
            $match = array();
            preg_match("/^(\d+)\.\d+/", $r, $match);
            if (isset($match[1])) {
                switch ($match[1]) {
                    case '8':
                        return ($r . " (Tiger)");
                    case '9':
                        return ($r . " (Leopard)");
                    case '10':
                        return ($r . " (Snow Leopard)");
                    case '11':
                        return ($r . " (Lion)");
                    case '12':
                        return ($r . " (Mountain Lion)");
                    case '13':
                        return ($r . " (Mavericks)");
                    case '14':
                        return ($r . " (Yosemite)");
                    case '15':
                        return ($r . " (El Capitan)");
                    default:
                        return ($r);
                }
            } else {
                return ($r);
            }
        } else {
            return $r;
        }
    }

    /**
     * Returns a string containing the version of the Apache web server.
     * @return string Apache version as a string (e.g. 2.2.14).
     */
    public static function getApacheVersion()
    {
        $apver = "";
        if (preg_match('|Apache\/(\d+)\.(\d+)\.(\d+)|', apache_get_version(), $apver)) {
            return "${apver[1]}.${apver[2]}.${apver[3]}";
        } else {
            return "Unknown";
        }
    }

    /**
     * Returns the database type as reported by ADOdb. To be compatible
     * with HRM it should be one of 'mysql' or 'postgresql'.
     * @return string Database type (e.g. postgresql).
     */
    public static function getDatabaseType()
    {
        $db = DatabaseConnection::get();
        return $db->type();
    }

    /**
     * Returns the database version.
     * @return string Database version as a string (e.g. 5.1.44).
     */
    public static function getDatabaseVersion()
    {
        $dbver = "";
        $db = DatabaseConnection::get();
        if (preg_match('|(\d+)\.(\d+)\.(\d+)|', $db->version(), $dbver)) {
            return "${dbver[1]}.${dbver[2]}.${dbver[3]}";
        } else {
            return "Unknown";
        }
    }

    /**
     * Returns the php version (for the Apache PHP module).
     * @return string PHP version (e.g. 5.31).
     */
    public static function getPHPVersion()
    {
        return phpversion();
    }

    /**
     * Memory limit as set in php.ini.
     * @param string $unit One of 'B' for bytes, 'M' for Megabytes, or 'G' for
     * Gigabytes. Default is 'M'. Omit the parameter to use the default.
     * @return string Memory limit in the requested unit.
     */
    public static function getMemoryLimit($unit = 'M')
    {
        return System::formatMemoryStringByUnit(
            UtilV2::let_to_num(ini_get('memory_limit')), $unit);
    }

    /**
     * Max allowed size for an HTTP post as set in php.ini.
     * @param string $unit One of 'B' for bytes, 'M' for Megabytes, or 'G' for
     * Gigabytes. Default is 'M'. Omit the parameter to use the default.
     * @return string Max allowed size for an HTTP post in the requested unit.
     */

    public static function getPostMaxSizeFromIni($unit = 'M')
    {
        return System::formatMemoryStringByUnit(
            UtilV2::let_to_num(ini_get('post_max_size')), $unit);
    }

    /**
     * Max allowed size for an HTTP post as set in the HRM configuration files.
     * @param string $unit One of 'B' for bytes, 'M' for Megabytes, or 'G' for
     * Gigabytes. Default is 'M'. Omit the parameter to use the default.
     * @return string Max allowed size for an HTTP post in the requested unit.
     */
    public static function getPostMaxSizeFromConfig($unit = 'M')
    {
        global $max_post_limit;
        if (isset($max_post_limit)) {
            if ($max_post_limit == 0) {
                return "limited by php.ini.";
            } else {
                return System::formatMemoryStringByUnit(
                    UtilV2::let_to_num(ini_get('$max_post_limit')), $unit);
            }
        } else {
            return "Not defined!";
        }
    }

    /**
     * Max allowed size for an HTTP post currently in use.
     * @param string $unit One of 'B' for bytes, 'M' for Megabytes, or 'G' for
     * Gigabytes. Default is 'M'. Omit the parameter to use the default.
     * @return string Max allowed size for an HTTP post in the requested unit.
     */
    public static function getPostMaxSize($unit = 'M')
    {
        return System::formatMemoryStringByUnit(UtilV2::getMaxPostSize(), $unit);
    }

    /**
     * Returns the status of file downloads through HTTP as set in the HRM
     * configuration.
     * @return string 'enabled' if the download is enabled; 'disabled' otherwise.
     */
    public static function isDownloadEnabledFromConfig()
    {
        global $allowHttpTransfer;
        if ($allowHttpTransfer == true) {
            return "enabled";
        } else {
            return "disabled";
        }
    }

    /**
     * Returns the status of file uploads through HTTP as set in the HRM
     * configuration.
     * @return string 'enabled' if the upload is enabled; 'disabled' otherwise.
     */
    public static function isUploadEnabledFromConfig()
    {
        global $allowHttpUpload;
        if ($allowHttpUpload == true) {
            return "enabled";
        } else {
            return "disabled";
        }
    }

    /**
     * Returns the status of file uploads through HTTP as set in php.ini.
     * @return string 'enabled' if the upload is enabled; 'disabled' otherwise.
     */
    public static function isUploadEnabledFromIni()
    {
        $upload = ini_get('file_uploads');
        if ($upload == "1") {
            return "enabled";
        } else {
            return "disabled";
        }
    }

    /**
     * Returns the status of file uploads through HTTP as set in php.ini
     * and the HRM configuration.
     * @return string 'enabled' if the upload is enabled; 'disabled' otherwise.
     */
    public static function isUploadEnabled()
    {
        if (System::isUploadEnabledFromConfig() == "enabled" &&
            System::isUploadEnabledFromIni() == "enabled"
        ) {
            return "enabled";
        } else {
            return "disabled";
        }
    }

    /**
     * Max allowed size for a file upload as set in php.ini
     * @param string $unit One of 'B' for bytes, 'M' for Megabytes, or 'G' for
     * Gigabytes. Default is 'M'. Omit the parameter to use the default.
     * @return string Max allowed size for a file upload in the requested unit.
     */
    public static function isUploadMaxFileSizeFromIni($unit = 'M')
    {
        return System::formatMemoryStringByUnit(
            UtilV2::let_to_num(ini_get('upload_max_filesize')), $unit);
    }

    /**
     * Max allowed size for a file upload as set in the HRM configuration files.
     * @param string $unit One of 'B' for bytes, 'M' for Megabytes, or 'G' for
     * Gigabytes. Default is 'M'. Omit the parameter to use the default.
     * @return string Max allowed size for a file upload in the requested unit.
     */
    public static function isUploadMaxFileSizeFromConfig($unit = 'M')
    {
        global $max_upload_limit;
        if (isset($max_upload_limit)) {
            if ($max_upload_limit == 0) {
                return "limited by php.ini.";
            } else {
                return System::formatMemoryStringByUnit(
                    UtilV2::let_to_num($max_upload_limit), $unit);
            }
        } else {
            return "Not defined!";
        }
    }

    /**
     * Max allowed size for a file upload currently in use.
     * @param string $unit One of 'B' for bytes, 'M' for Megabytes, or 'G' for
     * Gigabytes. Default is 'M'. Omit the parameter to use the default.
     * @return string Max allowed size for a file upload in bytes.
     */
    public static function getUploadMaxFileSize($unit = 'M')
    {
        return System::formatMemoryStringByUnit(UtilV2::getMaxUploadFileSize(), $unit);
    }

    /**
     * Max execution time in seconds for scripts as set in php.ini.
     * @return string Max execution time in seconds.
     */
    public static function getMaxExecutionTimeFromIni()
    {
        $maxExecTime = ini_get('max_execution_time');
        if ($maxExecTime == 0) {
            return "default";
        }
        return "" . $maxExecTime . "s";
    }

    /**
     * Formats a number (in bytes) into a string with the desired unit.
     * For example, System::formatMemoryStringByUnit( 134, $unit = 'M' )
     * returns '128 MB'.
     * @param int $value Memory amount in bytes.
     * @param string $unit One of 'B' for bytes, 'M' for Megabytes, or 'G' for
     * Gigabytes. Default is 'M'. Omit the parameter to use the default.
     * @return string Memory amount in the requested unit.
     */
    private function formatMemoryStringByUnit($value, $unit = 'M')
    {
        switch ($unit) {
            case 'G' :
                $factor = 1024 * 1024 * 1024;
                $digits = 3;
                $unit_string = "GB";
                break;
            case 'B' :
                $factor = 1;
                $digits = 0;
                $unit_string = " bytes";
                break;
            default: // Includes 'M'
                $factor = 1024 * 1024;
                $digits = 0;
                $unit_string = 'MB';
                break;
        }
        return (number_format($value / $factor, $digits, '.', '\'') .
            $unit_string);
    }

    /**
     * Parses an integer HRM version number into its string representation.
     * @param int $version String representation of the version number.
     * @return int HRM version as integer.
     */
    private static function parseHRMVersionIntegerToString($version)
    {
        $major = floor($version / 1000000);
        $version = $version - $major * 1000000;
        $minor = floor($version / 10000);
        $version = $version - $minor * 10000;
        $maintenance = floor($version / 100);
        if ($maintenance != 0) {
            $version = $major . '.' . $minor . '.' . $maintenance;
        } else {
            $version = $major . '.' . $minor;
        }
        return $version;
    }

    /**
     * Parses an integer HuCore version number into its string representation.
     * @param string $version String representation of the version number.
     * @return string String representation of the version number.
     */
    private static function parseHucoreVersionIntegerToString($version)
    {
        $major = floor($version / 1000000);
        $version = $version - $major * 1000000;
        $minor = floor($version / 10000);
        $version = $version - $minor * 10000;
        $maintenance = floor($version / 100);
        $version = $version - $maintenance * 100;
        $patch = $version;
        if ($version != 0) {
            $versionString =
                $major . '.' . $minor . '.' . $maintenance . '-p' . $patch;
        } else {
            $versionString = $major . '.' . $minor . '.' . $maintenance;
        }
        return $versionString;
    }

}

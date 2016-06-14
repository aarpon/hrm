<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once "hrm_config.inc.php";

/**
 * Util v2 class.
 *
 * This class contains only static methods!
 */
class UtilV2
{

    /**
     * Return the relative path to the file uploader.
     * @return string relative path.
     */
    public static function getRelativePathToFileUploader() {

        global $hrm_url;

        // Parse the URL to make sure we handle the relative HRM document path
        $c = parse_url($hrm_url);
        if (isset($c["path"])) {
            return ($c["path"] . "/upload/FileUploader.inc.php");
        } else {
            return "/upload/FileUploader.inc.php";
        }
    }

    /**
     * Report maximum upload size, in bytes, for concurrent upload.
     * @return int maximum upload size in bytes.
     */
    public static function getNumberConcurrentUploads() {

        global $httpNumberOfConcurrentUploads;

        // Get the number of concurrent uploads from the configuration files
        if (!isset($httpNumberOfConcurrentUploads)) {
            $httpNumberOfConcurrentUploads = 4;
        }

        return $httpNumberOfConcurrentUploads;
    }

    /**
     * Report maximum upload size, in bytes, for concurrent uploads.
     *
     *    The maximum (chunk) concurrent upload file is calculated as a
     *    function of the max post size (from php.ini) and the configured
     *    number of concurrent uploads. To avoid choking the server when
     *    several users are uploading at the same size, the upload size is
     *    capped to 16MB.

     * @param int $nConcurrentUploads Maximum number of concurrent uploads
     *                                (optional, default is 4).
     * @return int maximum upload size in bytes.
     */
    public static function getMaxConcurrentUploadSize($nConcurrentUploads = 4) {

        global $max_post_limit;

        // Get max post size from php.ini
        $post_max_size = let_to_num(ini_get('post_max_size'));

        // Divide it in (n+1) parts
        $theoretical_limit = intval(floor((
            floatval($post_max_size) / ($nConcurrentUploads + 1))));

        // Cap it: use $max_post_limit from the configuration files, if set and larger than 0
        $capSize = 16777216;
        if (isset($max_post_limit) && $max_post_limit > 0) {
            $capSize = min($max_post_limit * 1024 * 1024, $capSize);
        }
        if ($theoretical_limit > $capSize) {
            $theoretical_limit = $capSize;
        }
        return $theoretical_limit;
    }


}

<?php

/**
 * UtilV2
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

//declare(strict_types=1);

namespace hrm;

require_once dirname(__FILE__) . '/bootstrap.php';

/**
 * Static class with some commodity functionality (version 2).
 */
class UtilV2
{

    /**
     * Return the relative path to the file uploader.
     * @return string relative path.
     */
    public static function getRelativePathToFileUploader(): string
    {

        global $hrm_url;

        // Parse the URL to make sure we handle the relative HRM document path
        $c = parse_url($hrm_url);
        if (isset($c["path"]) && $c["path"] != "/") {
            return ($c["path"] . "/upload/FileUploader.inc.php");
        } else {
            return "/upload/FileUploader.inc.php";
        }
    }

    /**
     * Report maximum upload size, in bytes, for concurrent upload.
     * @return int maximum upload size in bytes.
     */
    public static function getNumberConcurrentUploads(): int
    {

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
     * The maximum (chunk) concurrent upload file is calculated as a
     * function of the max post size (from php.ini) and the configured
     * number of concurrent uploads. To avoid choking the server when
     * several users are uploading at the same size, the upload size is
     * capped to 16MB.
     *
     * Additionally, if the variable $max_post_limit is > 0, this will
     * also be used. However, if both are set, the smallest will be
     * returned (i.e. 16MB is a hard cap).
     *
     * @param int $nConcurrentUploads Maximum number of concurrent uploads
     *                                (optional, default is 4).
     * @return int maximum upload size in bytes.
     */
    public static function getMaxConcurrentUploadSize(int $nConcurrentUploads = 4): int
    {

        global $max_post_limit;

        // Get max post size from php.ini
        $post_max_size = self::let_to_num(ini_get('post_max_size'));

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


    /**
     * This function transforms the php.ini notation for memory amount to an
     * integer number of bytes.
     *
     * @example
     *
     * '2M' -> 2 x 1024 x 1024 = 2,097,152
     *
     * @param string @v Memory amount in php.ini notation
     * @return int Integer version in bytes
     */
    public static function let_to_num(string $v): int
    {
        // Extract the unit
        $l = substr($v, -1);

        // Extract the amount
        $ret = intval(substr($v, 0, -1));

        // Notice that the switch cases do not have a break statement to
        // cause cascade multiplication all the way down to bytes.
        switch (strtoupper($l)) {
            case 'P':
                $ret *= 1024;
                // Allow fall-through to next case
            case 'T':
                $ret *= 1024;
                // Allow fall-through to next case
            case 'G':
                $ret *= 1024;
                // Allow fall-through to next case
            case 'M':
                $ret *= 1024;
                // Allow fall-through to next case
            case 'K':
                $ret *= 1024;
                break;
        }

        // Return the number of bytes
        return $ret;
    }

    /**
     * Report maximum file size that can be uploaded, in bytes.
     *
     * The value is defined by 'upload_max_filesize' from php.ini and (optionally)
     * from the variable $max_upload_limit from the configuration files, if it
     * is > 0. If both are set, 'upload_max_filesize' from the configuration files
     * will be returned.

     * @return int Maximum file size that can be uploaded in bytes.
     *
     * @todo Does the php.ini value play a role in the chunk uploader
     * (i.e., does it apply to the file **chunk** size)?
     */
    public static function getMaxUploadFileSize(): int
    {
        global $max_upload_limit;

        // Do not touch the global value of $max_upload_limit!
        $local_max_upload_limit = 0;
        if (isset($max_upload_limit)) {
            $local_max_upload_limit = $max_upload_limit;
        }

        $ini_value = self::let_to_num(ini_get('upload_max_filesize'));
        if ($local_max_upload_limit == 0) {
            return $ini_value;
        } else {
            $local_max_upload_limit = 1024 * 1024 * $local_max_upload_limit;
            return $local_max_upload_limit;
        }
    }

    /**
     * Report maximum post size, in bytes.
     *
     * The value is defined by 'post_max_size' from php.ini and (optionally)
     * from the variable $max_post_limit from the configuration files, if it
     * is > 0. If both are set, the smallest will be returned.
     *
     * @return int Maximum upload post in bytes.
     */
    public static function getMaxPostSize(): int
    {
        global $max_post_limit;

        // Do not touch the global value of $max_upload_limit!
        $local_max_post_limit = 0;
        if (isset($max_post_limit)) {
            $local_max_post_limit = $max_post_limit;
        }

        $ini_value = self::let_to_num(ini_get('post_max_size'));
        if ($local_max_post_limit == 0) {
            return $ini_value;
        }
        $local_max_post_limit = 1024 * 1024 * $local_max_post_limit;
        return $local_max_post_limit;
    }

    /**
     * Report maximum upload size, in bytes.
     * @return int Maximum upload size in bytes.
     */
    public static function getMaxSingleUploadSize(): int
    {

        $max_upload_size = min(
            self::let_to_num(ini_get('post_max_size')),
            self::let_to_num(ini_get('upload_max_filesize'))
        );

        return $max_upload_size;
    }
}

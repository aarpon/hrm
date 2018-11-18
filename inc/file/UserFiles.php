<?php
/**
 * Class ImagePreviews
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\file;


/**
 * Class UserFiles
 *
 * Handles the user directory space.
 *
 * @package hrm\file
 */
class UserFiles
{
    /**
     * Name of the user and of his home directory.
     * @var string
     */
    private $username;

    /**
     * Image input directory
     * @var string
     */
    private $src_dir;

    /**
     * Image output directory
     * @var string
     */
    private $dst_dir;

    /**
     * User root directory
     * @var string
     */
    private $root_dir;

    /**
     * UserFiles constructor.
     *
     * @param $current_username
     */
    function __construct($current_username)
    {
        global $image_folder, $image_source, $image_destination;

        $this->username = $current_username;
        $this->root_dir = $image_folder . "/" . $this->username;
        $this->src_dir = $this->root_dir. "/" . $image_source;
        $this->dst_dir = $this->root_dir . "/" . $image_destination;
    }

    /**
     * Checks whether the user directories are reachable
     *
     * @return bool True if the source and destination directories exist and are writable
     */
    public function isReachable()
    {
        $result = file_exists($this->src_dir);
        $result = $result && is_writable($this->src_dir);
        $result = $result && file_exists($this->dst_dir);
        $result = $result && is_writable($this->dst_dir);

        return $result;
    }

    /**
     * Check if the input directory is the root directory
     *
     * @param string $dir input directory
     * @return bool
     */
    public function isRootDirectory($dir)
    {
        return ($dir == $this->root_dir);
    }

    /**
     * Get the user name
     *
     * @return string
     */
    public function getUserName()
    {
        return $this->username;
    }

    /**
     * Getter for the user root directory
     *
     * @return string
     */
    public function getRootDirecotry()
    {
        return $this->root_dir;
    }

    /**
     * Getter for the input directory
     *
     * @return string
     */
    public function getSourceDirectory()
    {
        return $this->src_dir;
    }

    /**
     * Getter for the output directory
     *
     * @return string
     */
    public function getDestinationDirectory()
    {
        return $this->dst_dir;
    }

    /**
     * Get the path relative to the users root directory
     *
     * @param $path
     * @return mixed
     */
    public function getRelativePath($path)
    {
        return str_ireplace($this->root_dir.'/', '', $path);
    }

    /**
     * Get the absolute path on the local file system for a relative path in the user space.
     * No tailing slash!
     *
     * @param $path
     * @return string
     */
    public function getAbsolutePath($path)
    {
        return rtrim(stripslashes($this->root_dir . '/' . $path), '/');
    }

    /**
     * Split the path in directory path and filename
     *
     * @param $path
     * @return array
     */
    protected function splitPath($path)
    {
        $filename = basename($path);
        $filedir = dirname($path);

        return array($filedir, $filename);
    }
}
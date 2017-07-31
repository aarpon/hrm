<?php
/**
 * Class FileServer
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\file;

require_once dirname(__FILE__) . '/../bootstrap.php';

/**
 * Class FileServer
 * @package hrm\file
 *
 * Simple interface to the file system.
 * It scans with fast php functions and keeps the directory tree in memory.
 *
 * @link FileServer::$tree maps directories and optionally time-series to the corresponding file lists.
 *
 *      dir 1 => [file 1, file 2, ... ]
 *      dir 2 => [file 1, time-series-basename => [file 1, file 2 ...], file 2, ...]
 *      ...
 *
 * In this nested array the first keys are all directories (of different depth). The values are arrays with
 * the contained files. If there is a time-series the index is replaced with the time-series base name:
 *      "0"=>"file1.tif"
 *      "1"=>"file2.tif"
 *      "ts-basename.stk" => ["file_t1.stk", "file_t2.stk", ...]
 *      "3"=>"foo.czi"
 *      ...
 */
class FileServer
{
    /**
     * Root from which the file system is scanned recursively
     * @var string
     */
    private $root;

    /**
     * Encapsulated array containing the directory tree
     * @var array|null
     */
    private $tree;

    /**
     * Dictionary mapping directories or image-time-series to the corresponding file lists
     * @var array
     */
    private $dict;

    /**
     * Number of directories in @link FileServer::$tree. It's value is null if no scan has been performed yet.
     * @var integer
     */
    private $ndirs;

    /**
     * Number of files in @link FileServer::$tree. It's value is null if no scan has been performed yet.
     * @var integer
     */
    private $nfiles;

    /**
     * Flag to know if time-series are collapsed or not (to save execution time)
     * @var bool
     */
    private $collapsed;

    /**
     * Unix time-stamp of the time the root was scanned.
     * @var null|int
     */
    private $scantime;



    /**
     * FileServer constructor.
     * @param $root string root directory
     */
    function __construct($root)
    {
        $this->root = $root;
        $this->tree = null;
        $this->dict = null;
        $this->ndirs = null;
        $this->nfiles = null;
        $this->collapsed = false;
        $this->scantime = null;
    }

    /**
     * Clear data
     */
    function reset()
    {
        $this->tree = null;
        $this->dict = null;
        $this->ndirs = null;
        $this->nfiles = null;
        $this->collapsed = false;
        $this->scantime = null;
    }

    /**
     * Scan the recursively the file tree bellow the given @link FileServer::$root
     *
     * @param string $root
     * @param bool $get_image_series
     * @param bool $collapse_time_series
     * @return array|mixed|null file dictionary. USE GETTERS FOR PRODUCTION USAGE
     * @todo things can be sped up a lot if not all image series were queried here, but only upon asking the files on a particular directory @link FileServer::getFiles.
     */
    public function scan($root = null, $get_image_series = false, $collapse_time_series = false)
    {
        if ($this->dict === null) {
            if ($root === null) {
                $root = $this->root;
            }

            list($this->tree, $this->dict, $this->ndirs, $this->nfiles) = $this->scan_recursive($root);
            $this->scantime = time();
        }

        if ($get_image_series) {
            foreach ($this->dict as $dir => $files) {
                if ($files != null) {
                    $new = ImageFiles::getImageFileSeries($dir, $files);
                    $this->dict[$dir] = $new;
                }
            }
        }

        if ($collapse_time_series) {
            $this->collapseImageTimeSeries();
        }

        return $this->dict;
    }

    /**
     * Get an encapsulated dictionary of all the directories and subdirectories.
     * (This is to easily build the directory tree from a json)
     *
     * @return array|null encapsulated directories
     */
    public function getDirectoryTree()
    {
        return $this->tree;
    }

    /**
     * Get the list of files of a directory
     *
     * @param string $dir directory to list files from
     * @param null $extension filter by file extention
     * @return array|mixed
     */
    public function getFileList($dir, $extension = null)
    {
        $files = $this->scan()[$dir];

        foreach  ($files as $index => $name) {
            if (is_array($name)) {
                $files[$index] = "(" . count($name) . " files) " . $index;
            }
        }

        if ($extension != null) {
            $files = FileServer::filter_file_extension($files, $extension);
        }

        return $files;
    }

    /**
     * Get a list of file paths belonging to a selection.
     *
     * Indices define a subset of files for a directory.
     * Directories with empty index arrays are selected entirely.
     *
     * @param array $selection dictionary of directories and indices
     *                         [dir1=>[], dir2=>[ind1, time-series-name, ind2, ...]]
     * @param string $file_extension allowed file extension (needed for directory selection)
     * @return array
     */
    public function getFilePaths(array $selection, $file_extension)
    {
        $paths = array();
        foreach ($selection as $dir => $indices) {
            if (isset($indices)) {
                foreach ($indices as $index) {
                    $paths[] = $dir . $this->dict[$dir][$index];
                }
            } else {
                $files = FileServer::filter_file_extension($this->dict[$dir], $file_extension);
                foreach ($files as $file) {
                    $paths[] = $dir . $file;
                }
            }
        }

        return $paths;
    }

    /**
     * Get the entire list of directories in the file tree
     *
     * @return array
     */
    public function getDirectories()
    {
        return array_keys($this->scan());
    }

    /**
     * get the number of files in the entire directory tree.
     *
     * @return int|null number of file | null if not scanned.
     */
    public function getNumberOfFiles()
    {
        return $this->nfiles;
    }

    /**
     * Get the number of directories (including all subdirectories) in the tree.
     *
     * @return int|null number of file | null if not scanned.
     */
    public function getNumberOfDirectories()
    {
        return $this->ndirs;
    }

    /**
     * Check if the input directory is the root directory
     *
     * @param string $dir input directory
     * @return bool
     */
    public function isRootDirectory($dir)
    {
        return ($dir == $this->root);
    }

    /**
     * Check if the root directory was modified since the scan.
     * @link FileServer::$root
     * @link FileServer::scan
     *
     * @return bool
     */
    public function isSynchronised()
    {
        $stat = stat($this->root);
        return $stat['mtime'] < $this->scantime;
    }

    /**
     * Explode time-series, i.o.w. show all file that are part of
     * a known time-series consisting of several image files.
     */
    public function explodeImageTimeSeries()
    {
        if (!$this->collapsed) {
            return;
        }

        foreach ($this->dict as $dir => $files) {
            $exploded = array();
            foreach ($files as $file) {
                if (is_array($file)) {
                    $exploded = array_merge($exploded, $file);
                } else {
                    $exploded[] = $file;
                }
            }

            $this->dict[$dir] = $exploded;
        }

        $this->collapsed = false;
    }

    /**
     * Collapse time-series; encapsulate a list of image files
     * containing all member files in the file lists.
     */
    public function collapseImageTimeSeries()
    {
        if ($this->collapsed) {
            return;
        }

        foreach ($this->dict as $dir => $files) {
            if ($files != null) {
                $this->dict[$dir] = ImageFiles::collapseTimeSeries($files);
            }
        }

        $this->collapsed = true;
    }

    /**
     * Recursively scan the content of an input directory
     *
     * @param string $dir input directory
     * @param string $dirname
     * @param array $ignore list subdirectories to ignore
     * @return array ($tree, $ndirs, $nfiles)
     */
    private static function scan_recursive($dir, $dirname = "/", array $ignore = [".", "..", "hrm_previews"])
    {
        $tree = [$dir => [$dirname => []] ];
        $dict = array();
        $files = array();
        $ndirs = 1;
        $nfiles = 0;

        foreach (scandir($dir) as $item) {
            if (in_array($item, $ignore)) {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                list($subtree, $subdict, $subndirs, $subnfiles) = FileServer::scan_recursive($path, $item, $ignore);
                $tree[$dir][$dirname] = array_merge($tree[$dir][$dirname], $subtree);
                $dict = array_merge($dict, $subdict);
                $ndirs += $subndirs;
                $nfiles += $subnfiles;
            } else {
                $files[] = $item;
            }
        }

        $nfiles += count($files);
        $dict[$dir] = $files;

        return array($tree, $dict, $ndirs, $nfiles);
    }

    /**
     * Filter files according a known file extension.
     *
     * @param array $files list of input files
     * @param string $extension file extension
     * @return array filtered files
     */
    private static function filter_file_extension($files, $extension) {
        if ($extension != null) {
            $reg = "/.*" . strtolower($extension) . "$/";
            $files = array_filter($files, function ($str) use($reg) {
                return (preg_match($reg, strtolower($str)) == 1);
            });
        }

        return $files;
    }

    /**
     * Commodity function for quick testing and debugging.
     *
     * @param $obj
     * @return mixed
     */
    static function json2html($obj)
    {
        return str_replace("\n", "<br/>",
                           str_replace(" ", "&nbsp;",
                                       str_replace("\\", "",
                                                    json_encode($obj, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT))));
    }

}
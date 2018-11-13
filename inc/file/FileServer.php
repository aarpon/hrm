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
     * Key to store the FileServer instance in the session. (This is to render references more transparent).
     * @var string
     */
    static $SESSION_KEY = "file-server";

    /**
     * Root from which the file system is scanned recursively
     * @var string
     */
    private $root;

    /**
     * List of directories to ignore during the file system scan.
     * @var array
     */
    private $ignored_dirs;

    /**
     * Imploding time-series results in an additional nested array containing the
     * image files of the time-series, having the image file name trunk ans key.
     * @var bool
     */
    private $imploded_time_series;

    /**
     * Get the number of images series contained in an image file from the file metadata.
     * @var bool
     */
    private $image_series;

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
    private $n_dirs;

    /**
     * Number of files in @link FileServer::$tree. It's value is null if no scan has been performed yet.
     * @var integer
     */
    private $n_files;

    /**
     * Unix time-stamp of the time the root was scanned.
     * @var null|int
     */
    private $scan_time;


    /**
     * FileServer constructor.
     * @param string $root directory
     * @param bool $get_image_series if true, the metadata of the image file is accessed to retrieve image series
     * @param bool $implode_time_series if true, an additional level in the @link FileServer::dict is created
     *                                  members of the time-series are encapsulated in an sub-array.
     *                                  This is can be changed after scanning with $link FileServer::implodeImageTimeSeries
     *                                  and @link FileServer::explodeImageTimeSeries
     * @param array $ignored_directories list of directories excluded from the @link FileServer::scan
     */
    function __construct($root,
                         $implode_time_series = true,
                         $get_image_series = false,
                         $ignored_directories = [".", "..", "hrm_previews"])
    {
        $this->root = $root;
        $this->ignored_dirs = $ignored_directories;
        $this->image_series = $get_image_series;
        $this->imploded_time_series = $implode_time_series;

        $this->tree = null;
        $this->dict = null;
        $this->n_dirs = null;
        $this->n_files = null;
        $this->scan_time = null;

        $this->scan();
    }

    /**
     * Scan the recursively the file tree bellow the given @link FileServer::$root.
     * This method is called once upon instantiation.
     * To re-scan a new @link FileServer has to be instantiated.
     *
     * @todo things can be sped up a lot if not all image series were queried here, but only upon asking the files on a particular directory @link FileServer::getFileList.
     */
    private function scan()
    {
        list($this->tree, $this->dict, $this->n_dirs, $this->n_files) =
            FileServer::scan_recursive($this->root, "/", $this->ignored_dirs);

        if ($this->image_series) {
            foreach ($this->dict as $dir => $files) {
                if ($files != null) {
                    $new = ImageFiles::getImageFileSeries($dir, $files);
                    $this->dict[$dir] = $new;
                }
            }
        }

        if ($this->imploded_time_series) {
            $this->implodeImageTimeSeries();
        }

        $this->scan_time = time();
    }

    /**
     * Get an encapsulated dictionary of all the directories and subdirectories.
     * (This is to easily build the directory tree from a json)
     *
     * @return array|null encapsulated directories
     */
    public function getDirectoryTree()
    {
        $this->synchronize();
        return $this->tree;
    }

    /**
     * Get the file dictionary that is mapping directories to file lists
     *
     * @return array|mixed|null file dictionary
     */
    public function getFileDictionary()
    {
        $this->synchronize();
        return $this->dict;
    }

    /**
     * Get the list of files of a directory or null if the directory does not exist
     *
     * @param string $dir directory to list files from
     * @param null $extension filter by file extension
     * @return array|mixed
     */
    public function getFileList($dir, $extension = null)
    {
        $this->synchronize();
        $files = array();

        $i = 0;
        foreach ($this->dict[$dir] as $index => $name) {
            $fileCount = NAN;
            $isMulti = ImageFiles::isMultiImage($name);
            $isTimeSeries = false;
            $entryName = $name;
            $fileName = $name;

            if (is_array($name)) {
                $isTimeSeries = true;
                $entryName = array_keys($name)[0];
                $seriesFiles = array_values($name)[0];
                $fileCount = count($seriesFiles);
                $fileName = $seriesFiles[0];
            }

            $mtime = filemtime($dir . '/' . $fileName);
            $files["{$i}"] = array('name' => $entryName,
                'mtime' => "{$mtime}",
                'multi' => "{$isMulti}",
                'time-series' => "{$isTimeSeries}",
                'count' => "{$fileCount}");

            $i++;
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
        $this->synchronize();
        return array_keys($this->dict);
    }

    /**
     * get the number of files in the entire directory tree.
     *
     * @return int|null number of file | null if not scanned.
     */
    public function getNumberOfFiles()
    {
        $this->synchronize();
        return $this->n_files;
    }

    /**
     * Get the number of directories (including all subdirectories) in the tree.
     *
     * @return int|null number of file | null if not scanned.
     */
    public function getNumberOfDirectories()
    {
        $this->synchronize();
        return $this->n_dirs;
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
     * Check if the root directory was modified since the scan, if so rescan to refresh the memory copy.
     *
     * @link FileServer::$root
     * @link FileServer::scan
     */
    public function synchronize()
    {
        $stat = stat($this->root);
        if (!$stat['mtime'] > $this->scan_time) {
            $this->scan();
        }
    }

    /**
     * Check if time-series are imploded
     *
     * @return bool
     */
    public function hasImplodedTimeSeries()
    {
        return $this->imploded_time_series;
    }

    /**
     * Check if image file (sub-) series were scanned.
     *
     * @return bool
     */
    public function hasImageSeries()
    {
        return $this->image_series;
    }

    /**
     * Explode time-series, i.o.w. show all file that are part of
     * a known time-series consisting of several image files.
     */
    public function explodeImageTimeSeries()
    {
        foreach ($this->dict as $dir => $content) {
            $exploded = array();
            $i = 0;
            foreach ($content as $index => $entry) {
                if (is_array($entry)) {
                    $seriesFiles = array_values($entry)[0];
                    foreach ($seriesFiles as $file) {
                        $exploded["{$i}"] = $file;
                        $i++;
                    }
                } else {
                    $exploded["{$i}"] = $entry;
                    $i++;
                }
            }

            $this->dict[$dir] = $exploded;
        }

        $this->imploded_time_series = false;
    }

    /**
     * Collapse time-series; encapsulate a list of image files
     * containing all member files in the file lists.
     */
    public function implodeImageTimeSeries()
    {
        foreach ($this->dict as $dir => $content) {
            if ($content != null) {
                $seriesList = ImageFiles::collapseTimeSeries($content);
                $i = -1;
                $collapsed = array();
                foreach ($seriesList as $series => $files) {
                    if (is_int($series)) {
                        $i = $series;
                        $collapsed[$series] = $files;
                    } else {
                        $i++;
                        $collapsed["{$i}"] = [$series => $files];
                    }
                }

                $this->dict[$dir] = $collapsed;
            }
        }

        $this->imploded_time_series = true;
    }

    /**
     * Recursively scan the content of an input directory
     *
     * @param string $dir input directory
     * @param string $dirname name used in the root directory
     * @param array $ignore list subdirectories to ignore
     * @return array ($tree, $ndirs, $nfiles)
     */
    private static function scan_recursive($dir, $dirname = "/", array $ignore)
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
            $reg = "/.*(" . strtolower($extension) . "$|" . strtolower($extension) . " \(.+\)$)/";
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
    static function array2html($obj)
    {
//        echo "<br/><br/>";
//        echo var_dump($obj);
        return str_replace("\n", "<br/>",
                           str_replace(" ", "&nbsp;",
                                       str_replace("\\", "",
                                                    json_encode($obj, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT))));
    }

}
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
 * Simple interface to the file system.
 * It scans with fast php functions and keeps the directory tree in memory as long as the root dir has not been modified.
 *
 * It provides functionality to group time-series of multiple image files and to retrieve multi-series information
 * from the metadata by means of Hucore.
 *
 * To optimize performance it also caches the list of multi-series ([file].ls.json next to the input image).
 *
 * @link FileServer::$tree maps directories and optionally time-series to the corresponding file lists.
 *
 *      dir 1 => [file 1, file 2, ... ]
 *      dir 2 => [file 1, time-series-basename => [file 1, file 2 ...], file 2, ...],
 *                        multi-series-file-name => [series 1, series 2, ...],
 *                 ... ]
 *
 * In this nested array the first keys are all directories (of different depth). The values are arrays with
 * the contained files. If there is a time-series the index is replaced with the time-series base name:
 *      "0"=>"file1.tif"
 *      "1"=>"file2.tif"
 *      "ts-basename.stk" => ["file_t1.stk", "file_t2.stk", ...]
 *      "3"=>"foo.czi"
 *      ...
 */
class FileServer extends UserFiles
{
    /**
     * Key to store the FileServer instance in the session.
     * (This is to render references more transparent and easier to find).
     * @var string
     */
    const SESSION_KEY = "file-server";

    /**
     * Extension for the file holding the multi-series files's list
     * @var string
     */
    const CACHE_FILE_EXTENSION = ".ls.json";

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
    private $is_imploded_time_series;

    /**
     * Get the number of images series contained in an image file from the file metadata.
     * @var bool
     */
    private $is_showing_multi_series;

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
     *
     * @param $username
     * @param bool $implode_time_series Default=true. If true, an additional level in the @link FileServer::dict is
     *                                  created members of the time-series are encapsulated in an sub-array.
     *                                  This is can be changed after scanning with
     *                                  @see FileServer::implodeImageTimeSeries
     *                                  and @see FileServer::explodeImageTimeSeries
     * @param bool $show_image_series Default=false. If true, all the image multi-series are retrieved upon
     *                                instantiation. Each file with a multi-series file extension will have the series
     *                                retrieved by Hucore accessing the meta-data of the image.
     *                                Beware of the Performance issue!!!
     *                                In general it is better to go with the default and use
     *                                @see FileServer::getImageMultiSeries() to retrive them induvidually upon demand.
     * @param array $ignored_directories list of directories excluded from the @link FileServer::scan
     */
    function __construct($username,
                         $implode_time_series = true,
                         $show_image_series = false,
                         $ignored_directories = [".", ".."])
    {
        parent::__construct($username);

        $this->ignored_dirs = $ignored_directories;
        $this->is_showing_multi_series = null;
        $this->is_imploded_time_series = null;

        $this->tree = null;
        $this->dict = null;
        $this->n_dirs = null;
        $this->n_files = null;
        $this->scan_time = null;

        $this->scan($show_image_series, $implode_time_series);
    }

    /**
     * Get an encapsulated dictionary of all the directories and subdirectories.
     * (This is to easily build the directory tree from a json)
     *
     * For a node deeper in the tree, the entire path has to fit.
     *
     * @param string $node
     * @param array $dir_filter
     * @return array|null encapsulated directories
     */
    public function getDirectoryTree($node = '/', $dir_filter = [".", "hrm_previews"])
    {
        $this->synchronize();
        $tree_ = self::filter_dir_tree($this->tree, $dir_filter);

        if ($node === '/') {
            return $tree_;
        }

        $nodes = preg_split('#/#', rtrim($node, '/'));
        if ($nodes === false) {
            $nodes = array($node);
        } else {
            $nodes = array_merge(['/'], $nodes);
        }

        return [$node => self::get_subtree($nodes, $tree_)];
    }

    /**
     * Get the file dictionary that is mapping directories to file lists
     *
     * The filters for directories and files check if the respective name start with any of the strings
     * in the filter list
     *
     * @param array $dir_filters
     * @param array $file_filters
     * @return array|mixed|null file dictionary
     */
    public function getFileDictionary($dir_filters = ['.', 'hrm_previews'], $file_filters = ['.'])
    {
        $this->synchronize();
        return self::filter_file_dict($this->dict, $dir_filters, $file_filters);
    }

    /**
     * Get the file dictionary with the relative paths
     *
     * The filters for directories and files check if the respective name start with any of the strings
     * in the filter list
     *
     * @param array $dir_filters
     * @param array $file_filters
     * @return array
     */
    public function getRelativeFileDirectory($dir_filters = ['.', 'hrm_previews'], $file_filters = ['.'])
    {
        $rel_dict = array();
        foreach ($this->getFileDictionary($dir_filters, $file_filters) as $dir => $content) {
            $rel_dir = $this->getRelativePath($dir);
            $rel_dict[$rel_dir] = $content;
        }

        return $rel_dict;
    }

    /**
     * Get the list of files of a directory or null if the directory does not exist
     *
     * @param string $dir directory to list files from
     * @param string $extfilt filter by file extension
     * @param array $file_filters
     * @return array|mixed
     */
    public function getFileList($dir, $extfilt = '', $file_filters = ['.'])
    {
        $this->synchronize();
        $files = array();

        $i = 0;
        foreach (self::filter_file_list($this->dict[$dir], $file_filters) as $index => $name) {
            $fileCount = NAN;
            $entryName = $name;
            $fileName = $name;
            $isMulti = false;

            if (is_array($name)) {
                $entryName = array_keys($name)[0];
                $seriesFiles = array_values($name)[0];
                $fileCount = count($seriesFiles);
                $isMulti = ImageFiles::isMultiImage($entryName);
                $fileName = $isMulti ? $entryName: $seriesFiles[0];
            }

            $extension = ImageFiles::getExtension($fileName);
            $isTimeSeries = !$isMulti && ($fileCount > 1);
            $mtime = filemtime($dir . '/' . $fileName);

            $files["{$i}"] = array('name' => $entryName,
                'mtime' => "{$mtime}",
                'multi-series' => "{$isMulti}",
                'time-series' => "{$isTimeSeries}",
                'count' => "{$fileCount}",
                'extension' => $extension);

            $i++;
        }

        if (!empty($extfilt)) {
            $reg = "/.*(" . strtolower($extfilt) . "$|" . strtolower($extfilt) . " \(.+\)$)/";
            $files = array_filter($files, function ($str) use($reg) {
                return (preg_match($reg, strtolower($str["name"])) == 1);
            });
        }
        return $files;
    }

    /**
     * @todo: here it is not yet clear how the selection will be passed from the new file manager. Once this is clear, this method needs probably to be adapted.
     *
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
     * Get the list of images beloning to a image time series
     * the path is /user/path/sub-dir/time-series-name
     *
     * @param $filepath
     * @return mixed|null
     */
    public function getImageTimeSeries($filepath)
    {
        $wasExploded = !$this->is_imploded_time_series;
        $this->implodeImageTimeSeries();

        $dirname = dirname($filepath);
        $filename = basename($filepath);
        $dircontent = $this->dict[$dirname];

        $filelist = null;
        foreach ($dircontent as $index => $item) {
            if (is_array($item)) {
                $tsname = array_keys($item)[0];
                if ($tsname == $filename) {
                    $filelist = array_values($item)[0];
                }
            }
        }

        if ($wasExploded) {
            $this->explodeImageTimeSeries();
        }

        return $filelist;
    }

    /**
     * Get the series contained by a multi-series image.
     * (from a file cache or by using Hucore)
     *
     * @param $filepath
     * @return array
     */
    public function getImageMultiSeries($filepath)
    {
        $dirname = dirname($filepath);
        $filename = basename($filepath);

        $key = self::get_file_index($this->dict[$dirname], $filename);
        if (is_array($this->dict[$dirname][$key])) {
//            echo 'memory<br>';
            return $this->dict[$dirname][$key];
        } else {
            $cachepath = $dirname . "/." . $filename . self::CACHE_FILE_EXTENSION;

            $read_from_image = true;
            if (file_exists($cachepath)) {
                $img_stat = stat($filepath);
                $cache_stat = stat($cachepath);
                if ($cache_stat['mtime'] > $img_stat['mtime']) {
                    $read_from_image = false;
                }
            }

            if ($read_from_image) {
                $series = ImageFiles::getImageFileSeries($dirname, array($filename));
                $this->saveFileSeriesCache($cachepath, $series);
//                echo 'image<br>';
            } else {
                $series = $this->loadFileSeriesCache($cachepath);
//                echo 'cache<br>';
            }

            $series = [$filename => $series];

            // Put it in memory
            $this->dict[$dirname][$key] = $series;

            return $series;
        }
    }

    /**
     * Check if time-series are imploded in the directory/file dictionary
     *
     * @return bool
     */
    public function isShowingImplodedTimeSeries()
    {
        return $this->is_imploded_time_series;
    }

    /**
     * Check if image file (sub-) series were scanned.
     *
     * @return bool
     */
    public function isShowingImageMultiSeries()
    {
        return $this->is_showing_multi_series;
    }

    /**
     * Explode time-series, i.o.w. show all file that are part of
     * a known time-series consisting of several image files.
     */
    public function explodeImageTimeSeries()
    {
        if (($this->is_imploded_time_series != null) && (!$this->is_imploded_time_series)) {
            return;
        }

        foreach ($this->dict as $dir => $content) {
            $exploded = array();
            $i = 0;
            foreach ($content as $index => $entry) {
                if (is_array($entry)) {
                    $seriesFiles = array_values($entry)[0];
                    if (empty($seriesFiles) || !is_array($seriesFiles)) {
                        $exploded["{$i}"] = $entry;
                        $i++;
                    } else {
                        foreach ($seriesFiles as $file) {
                            $exploded["{$i}"] = $file;
                            $i++;
                        }
                    }
                } else {
                    $exploded["{$i}"] = $entry;
                    $i++;
                }
            }

            $this->dict[$dir] = $exploded;
        }

        $this->is_imploded_time_series = false;
    }

    /**
     * Collapse time-series; encapsulate a list of image files
     * containing all member files in the file lists.
     */
    public function implodeImageTimeSeries()
    {
        if (($this->is_imploded_time_series != null) && ($this->is_imploded_time_series)) {
            return;
        }

        foreach ($this->dict as $dir => $content) {
            if (($content !== null) && !empty($content) && is_array($content)) {
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

        $this->is_imploded_time_series = true;
    }

    /**
     * Delete a list of files with all their associated data.
     * @todo: finish implementing and test thoroughly
     *
     * @param $file_list
     * @param bool $is_relative_path
     * @return array
     */
    public function deleteFiles($file_list, $is_relative_path = true)
    {
        $response = array();

        if ($is_relative_path === true) {
            $files = array();
            foreach ($file_list as $file) {
                array_push($files, $this->getAbsolutePath($file));
            }
        } else {
            $files = $file_list;
        }

        $previews = new ImagePreviews($this->getUserName());

        foreach ($files as $file) {
            file_put_contents('php://stderr', print_r($file, TRUE)."\n");

            $messages = array();
            $to_trash = array();
            list($dirname, $filename) = $this->splitPath($file);

            // Check if it is a time-series
            $key = self::get_file_index($this->dict[$dirname], $filename);
            if (is_array($this->dict[$dirname][$key]) && !ImageFiles::isMultiImage($filename)) {
                foreach ($this->dict[$dirname][$key] as $name) {
                    array_push($to_trash, $dirname . '/' . $name);
                }
            } else {
                array_push($to_trash, $file);
            }

            // Caches
            array_push($to_trash, $dirname . '/.' . $filename . self::CACHE_FILE_EXTENSION);

            // Previews
            $preview_files = $previews->getAvailableViews($dirname, $filename);
//            var_dump($preview_files);
            $preview_files_count = count($preview_files);
            $to_trash = array_merge($to_trash, $preview_files);

            // Results @todo

            // Delete the stuff
            $success_count = 0;
            $error_count = 0;
            foreach ($to_trash as $trash) {
                $status = true;//unlink($trash);@todo
                if ($status === true) {
                    array_push($messages, 'deleted');
                    $success_count++;
                } else {
                    array_push($messages, 'error');
                    $error_count++;
                }
            }

            array_push($response,
                [
                    'file name' => $filename,
                    'preview count' => "{$preview_files_count}",
                    'success count' => "{$success_count}",
                    'error count' => "{$error_count}",
                    'associated files' => array_combine($to_trash, $messages)
                ]);
        }

//        echo self::array2html($response);
//        file_put_contents('php://stderr', print_r($response, TRUE)."\n");
        return $response;
    }

    /**
     * Query all the multi-series and show them in the file dictionary
     */
    private function queryAllImageMultiSeries()
    {
        if ($this->is_showing_multi_series === true) {
            return;
        }

        foreach ($this->dict as $dir => $files) {
            if ($files != null) {
                $files_ = array();
                foreach ($files as $index => $filename) {
                    if (ImageFiles::isMultiImage($filename)) {
                        $series = $this->getImageMultiSeries($dir . '/' . $filename);
                        $files_[$index] = $series;
                    } else {
                        $files_[$index] = $filename;
                    }

                }
                $this->dict[$dir] = $files_;
            }
        }

        $this->is_showing_multi_series = true;
    }


    /**
     * Save a hidden files containing the list of image series next to a given image file
     *
     * @param $cachepath
     * @param $list
     */
    private function saveFileSeriesCache($cachepath, $list)
    {
        $str = json_encode($list, JSON_FORCE_OBJECT);
        $handle = fopen($cachepath, 'w');
        fwrite($handle, $str);
        fclose($handle);
    }

    /**
     * Load the file with the image-series of a given image file
     *
     * @param $cache_path
     * @return mixed
     */
    private function loadFileSeriesCache($cache_path)
    {
        $str = file_get_contents($cache_path);
        return json_decode($str, true);
    }

    /**
     * Check if the root directory was modified since the scan, if so rescan to refresh the memory copy.
     *
     * @link FileServer::$root
     * @link FileServer::scan
     */
    private function synchronize()
    {
        $stat = stat($this->getRootDirecotry());
        if ($stat['mtime'] > $this->scan_time) {
//            echo 'sync: '; var_dump($stat['mtime']); var_dump($this->scan_time) ;echo '<br>';
            $this->scan($this->is_showing_multi_series, $this->is_imploded_time_series);
        }
    }

    /**
     * Scan the recursively the file tree bellow the given @link FileServer::$root.
     * This method is called once upon instantiation.
     * To re-scan a new @link FileServer has to be instantiated.
     *
     * @param $show_multi_series
     * @param $implode_time_series
     */
    private function scan($show_multi_series, $implode_time_series)
    {
        list($this->tree, $this->dict, $this->n_dirs, $this->n_files) =
            FileServer::scan_recursive($this->getRootDirecotry(), "/", $this->ignored_dirs);

        if ($show_multi_series) {
            $this->queryAllImageMultiSeries();
        }

        if ($implode_time_series) {
            $this->implodeImageTimeSeries();
        }

        $this->scan_time = time();
    }

    /**
     * Recursively scan the content of an input directory
     *
     * @param string $dir input directory
     * @param string $dirname name used in the root directory
     * @param array $ignore list subdirectories to ignore
     * @param $no_hidden
     * @return array ($tree, $ndirs, $nfiles)
     */
    private static function scan_recursive($dir, $dirname = "/", array $ignore)
    {
        $tree = [$dirname => []];
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
                $tree[$dirname] = array_merge($tree[$dirname], $subtree);
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
     * Filter the directory tree with an array of filters
     *
     * @param $tree array Input directory tree
     * @param $filters array Array of filters. If a directory name starts with any of the filter entries it is excluded
     * @return array Filtered Tree
     */
    private static function filter_dir_tree($tree, $filters)
    {
        $tree_ = array();
        foreach ($tree as $dirname => $subtree) {
            $keep = true;
            foreach ($filters as $filter) {
                if (strpos($dirname, $filter) === 0) {
                    $keep = false;
                    break;
                }
            }

            if ($keep === true) {
                if (empty($subtree)) {
                    $tree_[$dirname] = $subtree;
                } else {
                    $tree_[$dirname] = self::filter_dir_tree($subtree, $filters);
                }
            }
        }

        return $tree_;
    }

    /**
     * Filter the file dictionary.
     * Any dictionary and file starting with any of the respective sequences in the array will be filtered out
     *
     * @param $dict array File dictionary
     * @param $dir_filters array Directory filters
     * @param $file_filters array File filters
     * @return array
     */
    private static function filter_file_dict($dict, $dir_filters, $file_filters)
    {
        $dict_ = array();
        foreach ($dict as $path => $content) {
            $dirname = basename($path);
            $keep_dir = true;
            if ((!$dir_filters !== null) && !empty($dir_filters)) {
                foreach ($dir_filters as $pattern) {
                    if (strpos($dirname, $pattern) === 0) {
                        $keep_dir = false;
                        break;
                    }
                }
            }

            if ($keep_dir === true) {
                $dict_[$path] = self::filter_file_list($content, $file_filters);
            }
        }

        return $dict_;
    }

    /**
     * Filter a list of files
     *
     * @param $list array File list
     * @param $file_filters array File filters.
     *                            File is filtered if it starts with any of the sequences in the filter list
     * @return array
     */
    private static function filter_file_list($list, $file_filters)
    {
        if ((!$file_filters !== null) && !empty($file_filters)) {
            $list_ = array();

            foreach ($list as $index => $filename) {
                $keep_file = true;
                if (!empty($filename) && !is_array($filename)) {
                    foreach ($file_filters as $file_filter) {
                        if (strpos($filename, $file_filter) === 0) {
                            $keep_file = false;
                            break;
                        }
                    }
                }

                if ($keep_file === true) {
                    $list_[$index] = $filename;
                }
            }
            return $list_;
        } else {
            return $list;
        }
    }

    /**
     * Get the subtree from a given node (first match is returned)
     *
     * @param array $nodes
     * @param $tree
     * @return null
     */
    private static function get_subtree(array $nodes, $tree)
    {
        $node = array_shift($nodes);

        foreach ($tree as $dirname => $subtree) {
            if ($dirname === $node) {
                if (empty($nodes) === true) {
                    return $subtree;
                } else {
                    return self::get_subtree($nodes, $subtree);
                }
            }
        }

        return null;
    }

    /**
     * Get the index/key of an entry in a file list or return false
     *
     * @param array $list
     * @param string $filename
     * @return bool|int|string
     */
    private static function get_file_index(array $list, $filename)
    {
        foreach ($list as $index => $name) {
            if (is_array($name)) {
                $name = array_keys($name)[0];
            }

            if ($name === $filename) {
                return $index;
            }
        }

        return false;
    }

    /**
     * Commodity function for quick testing and debugging.
     *
     * @param $obj
     * @return mixed
     */
    static function array2html($obj)
    {
        return str_replace("\n", "<br/>",
                           str_replace(" ", "&nbsp;",
                                       str_replace("\\", "",
                                                    json_encode($obj, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT))));
    }
}
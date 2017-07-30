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
 * Simple settings-agnostic interface to the file system.
 * It scans with fast php implementation and allows to cache
 * the file system scan
 *
 */
class FileServer
{

    private $root;

    private $tree;

    private $ndirs;

    private $nfiles;


    function __construct($root)
    {
        $this->root = $root;
        $this->tree = null;
        $this->ndirs = null;
        $this->nfiles = null;
    }

    function reset()
    {
        $this->tree = null;
        $this->ndirs = null;
        $this->nfiles = null;
    }

    public function getFileTree($dir = null)
    {
        if ($this->tree === null) {
            if ($dir === null) {
                $dir = $this->root;
            }

            list($this->tree, $this->ndirs, $this->nfiles) = $this->scan_recursive($dir);
        }

        return $this->tree;
    }

    public function getFiles($dir = null, $extension = null)
    {
        $files = array();
        if ($dir === null) {
            foreach ($this->tree as $dir => $files) {
                $files[] = $files;
            }
        } else {
            $files = $this->getFileTree()[$dir];
        }

        if ($extension != null) {
            $reg = "/.*" . strtolower($extension) . "$/";
            $files = array_filter($files, function ($str) use($reg) {
                return (preg_match($reg, strtolower($str)) == 1);
            });
        }

        return $files;
    }

    public function getSelectedFiles($selection)
    {
        $files = array();
        foreach ($selection as $dir => $indices) {
            if ($indices == null) {
                $files = array_merge($files, $this->tree[$dir]);
            } else {
                foreach ($indices as $index) {
                    $files[] = $this->tree[$index];
                }
            }
        }

        return $files;
    }

    public function getDirectories()
    {
        return array_keys($this->getFileTree());
    }

    public function isRootDirectory($dir)
    {
        return ($dir == $this->root);
    }

    public function getNumberOfFiles()
    {
        return $this->nfiles;
    }

    public function getNumberOfDirectories()
    {
        return $this->ndirs;
    }

    private static function scan_recursive($dir, $ignore = [".", "..", "hrm_previews"])
    {
        $tree = array();
        $files = array();
        $ndirs = 1;
        $nfiles = 0;

        foreach (scandir($dir) as $item) {
            if (in_array($item, $ignore)) {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                list($subtree, $subndirs, $subnfiles) = FileServer::scan_recursive($path, $ignore);
                $tree = array_merge($tree, $subtree);
                $ndirs += $subndirs;
                $nfiles += $subnfiles;
            } else {
                $files[] = $item;
            }
        }

        $nfiles += count($files);
        $tree[$dir] = $files;

        return array($tree, $ndirs, $nfiles);
    }

    static function json2html($str)
    {
        return str_replace("\n", "<br/>",
                           str_replace(" ", "&nbsp;",
                                       str_replace("\\", "",
                                                    json_encode($str, JSON_PRETTY_PRINT))));
    }

}
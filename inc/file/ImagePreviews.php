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

use hrm\HuygensTools;
use hrm\Log;
use ReflectionClass;

require_once dirname(__FILE__) . '/../bootstrap.php';

/**
 * Class to generate image previews, provide information about the status and properties of the previews and manage
 * the paths to access them.
 * It also can determine the paths of the different result previews (not the txt-files) for a given image file.
 */
class ImagePreviews extends UserFiles
{
    /** Sub-directory for the HRM previews. @todo: maybe hide the folder (if this does not cause trouble with the url) */
    const CACHE_DIR = 'hrm_previews';

    /** x-y-plane preview name, used in hucore and as suffix for the output file */
    const VIEW_XY = '_xy';

    /** x-y-plane preview name, used in hucore and as suffix for the output file */
    const VIEW_XZ = '_xz';

    /** x-z-plane preview name... */
    const VIEW_YZ = '_yz';

    /** SFP view */
    const VIEW_SFP = '.sfp';

    /** Comparision slider strip */
    const VIEW_STRIP = '.strip_';

    /** stack view @todo ambiguous ('stack.compare.strip contains' 'stack' of the avi movie file name) */
    const VIEW_STACK = '.stack';

    /** Preview output format */
    const OUTPUT_FORMAT = '.jpg';

    /** Image to be returned when no preview is available */
    const DEFAULT_OUTPUT = "images/no_preview.jpg";

    /** 3D flag */
    const DIMENSION_3D = 3;

    /** 2D flag */
    const DIMENSION_2D = 2;


    /**
     * Serves a certain file from the dest directory. Intended to serve
     * jpg thumbnails in combination with imgThumbnail.
     *
     * @param string $filepath Image path relative to @see UserFiles::$root_dir
     * @param bool $view Preview perspective (@const VIEW_XY, @const VIEW_XZ or @const VIEW_YZ)
     * @return string Return the absolute path to the thumbnail file
     */
    public function getThumbnailPath($filepath, $view = true)
    {
        // Set default value (workaround)
        if ($view === true) {
            $view = self::VIEW_XY;
        }

        list($filedir, $filename) = $this->splitPath($filepath);
        $filedir_ = $this->getAbsolutePath($filedir);

        if (self::getDimensionality($filedir_, $filename) == 0) {
            self::generateThumbnails($filedir_, $filename);
        }

        $path = self::getHucoreOutputPath($filedir_, $filename, $view);
        $path = stripslashes($path);
        if (!file_exists($path)) {
            $path = self::DEFAULT_OUTPUT;
        }

        return $path;
    }

    /**
     * Return an array with information about the image and the generated previews.
     *
     * @param string $filepath Image path relative to @see UserFiles::$root_dir
     * @return array
     */
    public function getInfo($filepath)
    {
        list($filedir, $filename) = $this->splitPath($filepath);

        $filedir_ = $this->getAbsolutePath($filedir);
        $thumb_path = stripslashes($this->getRelativePath($this->getThumbnailPath($filepath)));

        return [
            'image file' => $filename,
            'image directory' => $filedir,
            'image dimensions' => $this->getDimensionality($filedir_, $filename),
            'image previews' => $this->getAvailableViews($filedir_, $filename),
            'thumbnail path' => $thumb_path,
        ];
    }

    public function getResultPath($filepath, $size = '', $view = '', $original = '', $type = 'jpg')
    {
        list($filedir, $filename) = $this->splitPath($filepath);
        $filedir_ = $this->getAbsolutePath($filedir) . '/' . self::CACHE_DIR;
        $fileid = $this->getResultId($filename);

        // build the pattern
        $pattern = '/.*' . preg_quote($fileid);

        if (!($original === '')) {
            $pattern .= '\.';
            if ($original) {
                $pattern .= 'original';
            } else {

                $pattern .= '(?!original)';
            }
        }

        if (!empty($size)) {
            $pattern .= '.*' . $size;
        }

        $view_ = preg_quote($this->validateView($view));
        if (empty($view)) {
            Log::error("No file contains " . $view . 'view parameter in ' . $filedir_);
            return self::DEFAULT_OUTPUT;
        } else {
            $pattern .= '.*' . $view_;
        }

        $pattern .=  '.*' . preg_quote($type) . '$/';

        // get the corresponding file
        $matches = array();
        foreach (scandir($filedir_) as $path) {
            if (preg_match($pattern, $path)) {
                array_push($matches, $path);
            }
        }

        $count = count($matches);
        if ($count == 1) {
            return $filedir_ . '/' . $matches[0];
        }

        Log::error($count . "matches -> no unique result preview match with"
            . $pattern . " in " . $filedir_ . 'check ajax query.');
        return self::DEFAULT_OUTPUT;
    }

    /**
     * Extract the result id from the file name.
     *
     * @param $filename
     * @return null | string
     */
    private function getResultId($filename)
    {
        preg_match('/([0-9a-z]{10,16}_hrm[.a-z0-9]{2,4})/', $filename, $matches);
        if (count($matches) == 0) {
            return $matches[0];
        } else {
            return null;
        }
    }

    /**
     * Calls hucore to open an image and generate a jpeg preview.
     *
     * @param string $dir Absolute path to image
     * @param string $filename Image file name.
     * @param int|string $sizes Either an integer, or "preview". Default = "preview".
     * @return string HTML page (complete).
     */
    private function generateThumbnails($dir, $filename, $sizes = "preview")
    {
        $dst_dir = $dir . '/' . self::CACHE_DIR;
        $src_path = $dir . '/' . $filename;

        // Make sure that the folder exists and that the correct permissions are set.
        // Hucore actually creates the folder, but here we want to make sure that the permissions are set in a way to
        // allow the web interface to delete previews created by the queue manager, no matter which user is running it.
        // TODO: this looks dangerous.
        if (!file_exists($dst_dir)) {
            @mkdir($dst_dir, 0777);
        }
        @chmod($dst_dir, 0777);

        $extra = "";
        $series = "auto";
        $opt = "-filename \"$filename\" -src \"$dir\" -dest \"$dst_dir\" " .
               "-scheme auto -sizes \{$sizes\} -series $series $extra";
        $answer = HuygensTools::huCoreTools("generateImagePreview", $opt);
        $lines = count($answer);

        // Log the output of hucore
        for ($i = 0; $i < $lines; $i++) {
            $key = $answer[$i];

            switch ($key) {
                case "ERROR":
                    $i++;
                    Log::error("Error generating previews for " . $src_path . ": " . $answer[$i]);
                    break;
                case "REPORT":
                    $i++;
                    Log::info("Report generating previews for " . $src_path . ": " . $answer[$i]);
                    break;
            }
        }

        return $this->getDimensionality($dir, $filename);
    }

    /**
     * Get the path to the Hucore output files.
     *
     * @param string $dir Absolute path to image
     * @param string $filename Image file name
     * @param bool $view @const VIEW_XY (default) or @const VIEW_XZ
     * @param string $size Preview size: integer or 'preview'
     * @return string Absolute path to generated preview
     */
    private function getHucoreOutputPath($dir, $filename, $view, $size = 'preview')
    {
        // Huygens does not support ":" in names of saved files.
        $filename_ = str_replace(":", "_", $filename);

        $preview_path = $dir . "/" . self::CACHE_DIR . "/" . $filename_ . ".". $size . $view;
        if ($view == self::VIEW_STACK) {
            $preview_path .= ".avi"; //@todo: might be better to have VIEW_ to be tuples with name->file-format pairs
        } else {
            $preview_path .= self::OUTPUT_FORMAT;
        }

        return stripslashes($preview_path);
    }

    /**
     * Checks whether an image preview is available.
     *
     * @param string $dir Absolute path of Image parent directory
     * @param string $filename Image file name.
     * @return int A numeric code: 0 -> not available, 2 -> 2D, 3 -> 3D.
     */
    private function getDimensionality($dir, $filename)
    {
        // Try the most dimensions (3D) first
        $path = $this->getHucoreOutputPath($dir, $filename, self::VIEW_XZ);
        if (file_exists($path)) {
            return self::DIMENSION_3D;
        }

        // Try try fewer (2D)
        $path = $this->getHucoreOutputPath($dir, $filename, self::VIEW_XY);
        if (file_exists($path)) {
            return 2;
        }

        return 0;
    }

    /**
     * Get all the available previews of a given file
     *
     * @param $dir
     * @param $filename
     * @return array
     */
    public function getAvailableViews($dir, $filename)
    {
        $views = array();
        foreach ($this->getViewConstants() as $index => $view) {
            $path = $this->getHucoreOutputPath($dir, $filename, $view);
            if (file_exists($path)) {
                array_push($views, $view);
            }
        }

        return $views;
    }

    /**
     * Validate thew view parameter (probably retrieved from the url) against the possible views
     * defined in this class
     *
     * @param $view
     * @return mixed|string
     */
    private function validateView($view)
    {
        foreach ($this->getViewConstants() as $constant) {
            if (preg_match('/.*' . $view . '.*/', $constant)) {
                return $constant;
            }
        }

        return '';
    }

    /**
     * Get all the view constants defined in this class.
     *
     * @return array
     */
    private function getViewConstants()
    {
        $constants = array();
        try {
            $clazz = new ReflectionClass(__CLASS__);
            foreach ($clazz->getConstants() as $name => $value) {
                if (strpos($name, 'VIEW') !== false) {
                    array_push($constants, $value);
                }
            }
        } catch (\ReflectionException $e) {
            Log::error($e);
        }

        return $constants;
    }
}
<?php
/**
 * Class ImageFileFormat
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\file;

use hrm\HuygensTools;
use hrm\DatabaseConnection;

require_once dirname(__FILE__) . '/../bootstrap.php';



/**
 * Class ImageFileFormat
 * @package hrm\file
 *
 * Utility class to manipulate different image file formats
 */
class ImageFiles
{
    /**
     * Certain image files contain several image series within one file.
     * An series corresponds to a multidimensional acquisition. Different
     * series are different acquisitions belonging to one experiment.
     *
     * @param string $dir path to the image directory
     * @param array $files list of image files
     * @return array
     * @todo this seems to be the part that slows down file-listings a lot.
     */
    public static function getImageFileSeries($dir, array $files)
    {
        $multiext = ImageFiles::getMultiImageExtensions();

        $i = 0;
        $imgList = "";
        foreach ($files as $key => $path) {
            $ext = ImageFiles::getExtension($path);
            if (in_array($ext, $multiext)) {
                $imgList .= " -img_$i \"$path\"";
                $i++;
                unset($files[$key]);
            }
        }

        if ($imgList == "") {
            return $files;
        }

        $opt = "-count $i $imgList -dir \"" . $dir . "\"";
        $answer = HuygensTools::huCoreTools("reportSubImages", $opt);

        if (!$answer) {
            return array();
        }

        $lines = count($answer);

        $tree = array();
        $new_files = array();
        $cur = NULL;

        for ($i = 0; $i < $lines; $i++) {
            $key = $answer[$i];

            switch ($key) {
                case "BEGIN IMG":
                    $i++;
                    $cur = $answer[$i];
                    break;
                case "ERROR":
                    $i++;
                    echo($answer[$i]);
                case "END IMG":
                    $cur = NULL;
                    break;
                case "PATH":
                    if ($cur) {
                        $i++;
                        $tree[$cur]['path'] = $answer[$i];
                    }
                    break;
                case "COUNT":
                    if ($cur) {
                        $i++;
                        $tree[$cur]['count'] = $answer[$i];
                    }
                    break;
                case "TYPE":
                    if ($cur) {
                        $i++;
                        $tree[$cur]['type'] = $answer[$i];
                    }
                    break;
                case "SUBIMG":
                    if ($cur) {
                        $i++;
                        $tree[$cur]['subimg'][] = $answer[$i];
                        $new_files[] = $cur . " (" . $answer[$i] . ")";
                    }
                    break;
            }
        }

        return array_merge($files, $new_files);
    }

    /**
     * Return all valid image extensions saved in the database.
     *
     * @return array of image extensions.
     */
    public static function getImageExtensions()
    {
        $db = new DatabaseConnection();

        return $db->allFileExtensions();
    }

    public static function getMultiImageExtensions()
    {
        $db = new DatabaseConnection();

        return $db->allMultiFileExtensions();
    }

    /**
     * Get file extension in a robust way.
     *
     * Double extensions (as in .ome.tif) are correctly returned. There
     * is no support for longer, composite extensions because they do not
     * occur in practice.
     *
     * @param string $filename Filename to be processed.
     * @return string Complete extension.
     */
    public static function getExtension($filename)
    {
        $info = pathinfo($filename);
        $info_ext = pathinfo($info["filename"], PATHINFO_EXTENSION);
        if ($info_ext == "") {
            return $info["extension"];
        } else {
            if (strlen($info_ext) > 4) {
                // Avoid pathological cases with dots somewhere in the file name.
                return $info["extension"];
            }
            $allExtensions = ImageFiles::getImageExtensions();
            $composedExt = $info_ext . "." . $info["extension"];
            if (in_array($composedExt, $allExtensions)) {
                return $composedExt;
            } else {
                return $info["extension"];
            }
        }
    }

    /**
     * Check whether the file is of supported format.
     * @param string $filename File name to be checked.
     * @param bool $includeExtras If true consider also "ids" and "ids.gx" as supported formats.
     * @return bool True if the file name is supported, false otherwise.
     * @todo put extra extensions in the database (do they need to be there or can we keep them in the settings or even the class file).
     * @todo Also when filtering for image files during the image selection (page) this check might be made implicitly.
     */
    public static function isValidImage($filename, $includeExtras = false)
    {
        $extension = strtolower(ImageFiles::getExtension($filename));

        // Retrieve the valid file extensions from the database
        $validImageExtensions = ImageFiles::getImageExtensions();
        $status = in_array($extension, $validImageExtensions);

        // If the extension is in the standard formats, we can return
        if ($status) {
            return true;
        }

        // If the extension is not in the standard formats, we might have to check the extras.
        if ($includeExtras) {
            $status = in_array($extension, array("ids", 'idx.gz'));
        }

        // Return
        return $status;
    }


    /**
     * Collapse all files with known file name pattern into a time series:
     * @see ImageFiles::collapseStkTimeSeries()
     * @see ImageFiles::collapseLeicaTimeSeries()
     *
     * @param array $files
     * @return array dictionary with the collapsed time series.
     */
    public static function collapseTimeSeries(array $files)
    {
        list($rest, $ts1) = ImageFiles::collapseLeicaTimeSeries($files);
        list($rest, $ts2) = ImageFiles::collapseStkTimeSeries($rest);
        return array_merge($rest, $ts1, $ts2 );
    }

    /**
     * Create a dictionary of time-series base names and arrays of image files.
     *
     * @param array $files
     * @return array [array of non-collapsible files, time-series dictionary]
     */
    private static function collapseLeicaTimeSeries(array $files)
    {
        $dict = array();
        $candidates = preg_grep("/[^_]+_(T|t|Z|z|CH|ch)[0-9]+\w+\.ti(f{1,2})$/i", $files);
        foreach ($candidates as $key => $value) {
            $basename = ImageFiles::getLeicaTimeSeriesBaseName($value);
            $dict[$basename][] = $value;
            unset($files[$key]);
        }

        return array($files, $dict);
    }

    /**
     * Get the base name of a Leica time-series
     *
     * @param string $name file name
     * @return mixed string or null
     */
    private static function getLeicaTimeSeriesBaseName($name)
    {
        return preg_replace("/([^_]+|\/)(_)(T|t|Z|z|CH|ch)([0-9]+)(\w+)(\.)(\w+)/", "$1$6$7", $name);
    }

    /**
     * Create a dictionary of time-series base names and arrays of image files.
     *
     * @param array $files
     * @return array [array of non-collapsible files, time-series dictionary]
     */
    private static function collapseStkTimeSeries(array $files)
    {
        $dict = array();
        $candidates = preg_grep("/[^_]+_(T|t)[0-9]+\.stk$/i", $files);
        foreach ($candidates as $key => $file) {
            $basename = ImageFiles::getStkTimeSeriesBaseName($file);
            $dict[$basename][] = $file;
            unset($files[$key]);
        }

        return array($files, $dict);
    }

    /**
     * Get the base name of stk-time-series
     *
     * @param string $name file name
     * @return mixed string or null
     */
    private static function getStkTimeSeriesBaseName($name)
    {
        return preg_replace("/([^_]+|\/)(_)(T|t)([0-9]+)(\.)(\w+)/", "$1$5$6", $name);
    }
}
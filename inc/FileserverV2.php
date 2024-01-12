<?php
/**
 * FileserverV2
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm;

/**
 * Takes care of all file handling to and from the image area (version 2).
 *
 * @package hrm
 */
class FileserverV2
{
    /**
     * Get the decompression command (with %DEST% placeholder for the target)
     * @return array Array with the command for each supported decompressor (currently only zip).
     */
    private static function getDecompressors()
    {
        // Get the archive file extensions from the configuration files.
        global $decompressBin;
        return $decompressBin;
    }

    /**
     * Move uploaded file to its final destination.
     *
     * If the file is an archive, it will also be decompressed.
     *
     * @param string $file Full path of the file to be moved.
     * @param string $destDir Full path to the destination directory.
     * @param string $errorMessage Reference to a string to store error messages.
     * @return bool True if moving was successful, false otherwise.
     */
    public static function moveUploadedFile($file, $destDir, &$errorMessage)
    {
        // Does the file exist?
        if (!is_file($file)) {
            $errorMessage = "The file $file does not exist!";
            return false;
        }

        // Drop path info and sanitize file name
        $destBaseName = FileserverV2::sanitizeFileName(basename($file));

        // Full path of destination file
        $destFile = $destDir . "/" . $destBaseName;

        // Get the file extension
        $extension = FileserverV2::getFileNameExtension($destFile);

        // Get the file name body (base name without extension)
        $destBodyName = FileserverV2::getFileBaseName($destBaseName);

        // Is the file an archive?
        if (FileserverV2::isArchiveFile($file)) {
            // Get the output folder where to store the archive
            $destDecDir = FileserverV2::getValidFileOrDirName($destDir, $destBodyName);

            // Did we get a valid destination folder name?
            if ($destDecDir == "") {
                // We ran out of suffixes. We return false.
                $errorMessage = "Too many folders with the same base name. Aborting...";
                return false;
            }

            // We expand the archive
            if (! FileserverV2::decompressArchive($file, $destDecDir)) {
                $errorMessage = "Failed decompressing archive file $file.";
                return false;
            } else {
                // We can return success here.
                return true;
            }
        }

        // The file was not an archive, check that it is a valid image
        // Consider also extras (ids, idx.gz)
        if (! FileserverV2::isValidImage($file, true)) {
            $errorMessage = "The file $file is not a valid image.";
            return false;
        }

        // Now move the file over (but make sure not to overwrite anything)
        $destDecFile = FileserverV2::getValidFileOrDirName($destDir, $destBodyName, $extension);

        // Did we get a valid destination file name?
        if ($destDecFile == "") {
            // We ran out of suffixes. We return false.
            $errorMessage = "Too many files with the same base name. Aborting...";
            return false;
        }

        // Move the file
        $status = rename($file, $destDecFile);

        if (! $status) {
            $errorMessage = "Failed moving file $destBaseName to its final destination.";
        }

        return $status;
    }

    /**
     * Extract files from supported archive.
     * @param string $file Archive file with full path.
     * @param string $destDir Destination folder where the archive will be extracted.
     * @return bool True if the decompression was successful, false otherwise.
     */
    public static function decompressArchive($file, $destDir)
    {
        // Get the archive file extensions from the configuration files.
        $decompressBin = self::getDecompressors();

        // Create the output directory
        if (!is_dir($destDir)) {
            mkdir($destDir);
        }

        // Get the archive type (Extension)
        $extension = strtolower(FileserverV2::getFileNameExtension($file));

        // Build the system command to execute
        $command = str_replace("%DEST%", "\"" . $destDir . "\"", $decompressBin[$extension]) . " \"$file\"";

        // Run the command and collect the output
        $output = array();
        $result = -1;
        exec($command, $output, $result);

        Log::info("Decompressing with command: $command, got: " . print_r($output, true));

        // It turns out that unzip restores the original permissions of the files
        // in the zip package, as they were in the client before zipping. Using
        // umask to set proper HRM permissisions does not help here. Thus, we have
        // to at least make sure that the HRM group can read the data so that the
        // QM can process the file.

        // First, start with the directories.
        $chmodCmd = "find " . $destDir . " -type d -exec chmod 0750 {} +";
        exec($chmodCmd, $chmodOutput, $chmodResult);
        Log::info("Chmod subdirectories with command: $chmodCmd, got: " . print_r($chmodOutput, true));

        // Then, process the files.
        $chmodCmd = "find " . $destDir . " -type f -exec chmod 0660 {} +";
        exec($chmodCmd, $chmodOutput, $chmodResult);
        Log::info("Chmod files with command: $chmodCmd, got: " . print_r($chmodOutput, true));

        // Return the status
        return ($result == 0);
    }

    /**
     * Check whether the file name has one of the recognized archive extensions.
     *
     * @param string $fileName File Full file name of the file to check.
     * @return bool True if the file is an a supported archive, false otherwise.
     */
    public static function isArchiveFile($fileName)
    {
        // Get the archive file extensions from the configuration files.
        $decompressBin = self::getDecompressors();

        // Get the file extension
        $extension = FileserverV2::getFileNameExtension($fileName);

        // Is the extension one of the supported archive extensions
        return array_key_exists($extension, $decompressBin);
    }

    /**
     * Return all valid extensions.
     *
     * Valid extensions and image extensions, extra extensions, archive extensions.
     * @param bool $withLeadingDot Set to true to return the extensions with a leading 'dot'.
     * @return array of extensions.
     */
    public static function getAllValidExtensions($withLeadingDot = false)
    {
        // Image extensions
        $validImageExtensions = FileserverV2::getImageExtensions();

        // Extras extensions
        $extrasExtensions = FileserverV2::getImageExtrasExtensions();

        // Archive extensions
        $archiveExtensions = FileserverV2::getArchiveExtensions();

        // Merge them and return
        $allExtensions = array_merge($validImageExtensions, $extrasExtensions, $archiveExtensions);

        // Should we append the leading dot?
        if ($withLeadingDot) {
            array_walk($allExtensions, function (&$value) {
                $value = "." . $value;
            });
        }

        return $allExtensions;
    }

    /**
     * Return all valid image extensions.
     * @return array of image extensions.
     */
    public static function getImageExtensions()
    {
        // Instantiate a new database connection
        $db = DatabaseConnection::get();

        // Return the image extensions
        return $db->allFileExtensions();
    }

    /**
     * Return all archive extensions.
     * @return array of archive extensions.
     */
    public static function getArchiveExtensions()
    {
        // Get the archive file extensions from the configuration files.
        $decompressBin = self::getDecompressors();

        // Archive extensions
        return array_keys($decompressBin);
    }

    /**
     * Return image extras extensions.
     * @return array of image extras extensions.
     *
     * TODO: Add them to the database.
     */
    public static function getImageExtrasExtensions()
    {
        // Return the image extras extensions
        return array("ids", 'ids.gz');
    }

    /**
     * Check whether the file is of supported format.
     * @param string $filename File name to be checked.
     * @param bool $alsoExtras If true consider also "ids" and "ids.gx" as supported formats.
     * @return bool True if the file name is supported, false otherwise.
     */
    public static function isValidImage($filename, $alsoExtras = false)
    {
        // Extract the file extension
        $extension = strtolower(FileserverV2::getFileNameExtension($filename));

        // Retrieve the valid file extensions from the database
        $validImageExtensions = FileserverV2::getImageExtensions();
        $status = in_array($extension, $validImageExtensions);

        // If the extension is in the standard formats, we can return
        if ($status) {
            return true;
        }

        // If the extension is not in the standard formats, we might have to check the extras.
        if ($alsoExtras) {
            $extras = FileserverV2::getImageExtrasExtensions();
            $status = in_array($extension, $extras);
        }

        // Return
        return $status;
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
     *
     */
    public static function getFileNameExtension($filename)
    {
        // Process the path information
        $info = pathinfo($filename);
        $allExtensions = FileserverV2::getAllValidExtensions();
        if (in_array(strtolower($info["extension"]), $allExtensions)) {
            return $info["extension"];
        }

        # Process possibly composed extension
        $info_ext = pathinfo($info["filename"], PATHINFO_EXTENSION);
        if ($info_ext == "") {
            return $info["extension"];
        } else {
            $composedExt = strtolower($info_ext . "." . $info["extension"]);
            if (in_array($composedExt, $allExtensions)) {
                return $info_ext . "." . $info["extension"];
            } else {
                return $info["extension"];
            }
        }
    }

    /**
     * Get file base name.
     *
     * @param string $filename Filename to be processed.
     * @return string Base name.
     *
     */
    public static function getFileBaseName($filename)
    {
        // Drop path info
        $filename = basename($filename);

        // Extract extension
        $extension = FileserverV2::getFileNameExtension($filename);

        $lenExt = strlen($extension);
        if ($lenExt == 0) {
            return $filename;
        }

        // Extract base name
        $basename = substr($filename, 0, (strlen($filename) - $lenExt - 1));
        if (strlen($basename) == 0) {
            return $filename;
        }

        return $basename;
    }

    /**
     * Recursively delete a folder and all its content.
     * @param string $dir Directory to be deleted.
     */
    public static function removeDirAndContent($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        FileserverV2::removeDirAndContent($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }

    /**
     * Get a file or directory name in the destination folder that does not point to an already existing object.
     *
     * The function returns the full path to a file or folder that is contained in the given destination directory
     * $destDir. If an object with that name already exists, a numeric suffix is added and the test is repeated.
     * If also the modified name exists, the suffix is incremented and tested again. The first name that does not
     * point to an already existing file or folder is returned.
     *
     * The input argument $fileOrDirBodyName is the base body name, i.e. without path and (in case of a file) extension.
     * If you want to get the full path to a file, please specify the third input argument $extension. Omit for folders.
     *
     * Please mind that there is cap of 1000 attempts.
     *
     * @param string $destDir Parent directory for the file or folder to be tested.
     * @param string $fileOrDirBodyName Base body name for the file (i.e. without extension) or folder.
     * @param string $extension (optional) Extension for a file; omit for a folder.
     * @return string full path to the file or folder or "" if all attempts failed.
     */
    private static function getValidFileOrDirName($destDir, $fileOrDirBodyName, $extension = "")
    {
        // Make the function robust against $extension with or without a leading dot.
        if ($extension != "") {
            if (substr($extension, 0, 1) !== '.') {
                $extension = "." . $extension;
            }
        }

        // In case a file or folder with that name already exists, we append a numeric suffix.
        // We stop after $xMaxSuffix trials.
        $zSuffix = 0;
        $zMaxSuffix = 1000;

        // Tentative (full) file or folder name
        $testExpand = $destDir . "/" . $fileOrDirBodyName . $extension;

        // If the file/folder already exists, we try with incremental numeric suffixes
        while (file_exists($testExpand)) {
            // Increment the suffix
            $zSuffix++;

            // File name to test for existence
            $testExpand = $destDir . "/" . $fileOrDirBodyName . "_$zSuffix" . $extension;

            // Have we tried too many suffixes?
            if ($zSuffix > $zMaxSuffix) {
                // We ran out of suffixes. We return an empty string.
                return "";
            }
        }

        // If we need to add a suffix, we do it now
        if ($zSuffix > 0) {
            return $destDir . "/" . $fileOrDirBodyName . "_$zSuffix" . $extension;
        }

        // Complete destination folder
        return $destDir . "/" . $fileOrDirBodyName . $extension;
    }

    /**
     * Create the download and upload (chunk and files) folder if they do not exist.
     *
     * The folder paths are taken from inc/hrm_config.inc.php (advanced parameters).
     */
    public static function createUpDownloadFolderIfMissing()
    {
        global $allowHttpTransfer, $allowHttpUpload;
        global $httpDownloadTempFilesDir, $httpUploadTempChunksDir, $httpUploadTempFilesDir;

        // Initialize result
        $result = true;

        // Random file name to test access
        $fname = uniqid();

        // Download folder

        // Are downloads active?
        if (isset($allowHttpTransfer) && $allowHttpTransfer == true) {
            // Does the download directory exist?
            if (!is_dir($httpDownloadTempFilesDir)) {
                Log::info("Trying to create " . $httpDownloadTempFilesDir);
                $result &= mkdir($httpDownloadTempFilesDir, 0775);
                if (! $result) {
                    Log::error("ERROR creating " . $httpDownloadTempFilesDir);
                    return false;
                }

            }

            // $perms = substr(sprintf('%o', fileperms($httpDownloadTempFilesDir)), -4);
            // Log::info("Permissions: " . $perms);
            if (! chmod($httpDownloadTempFilesDir, 0775)) {
                Log::warning("Adjusting permissions failed on " . $httpDownloadTempFilesDir);
            }

            // Check that the download directory is writable
            $fid = @fopen("$httpDownloadTempFilesDir/$fname", "w");
            if (false === $fid) {
                return false;
            } else {
                fclose($fid);
                unlink("$httpDownloadTempFilesDir/$fname");
                $result &= true;
            }
        }

        // Upload chunk and files folders

        // Are uploads active?
        if (isset($allowHttpUpload) && $allowHttpUpload == true) {
            // Chunk upload directory

            // Does the chunk upload directory exist?
            if (!is_dir($httpUploadTempChunksDir)) {
                Log::info("Trying to create " . $httpUploadTempChunksDir);
                $result &= mkdir($httpUploadTempChunksDir, 0775);
                if (! $result) {
                    Log::error("Error creating " . $httpUploadTempChunksDir);
                    return false;
                }
            }

            if (! chmod($httpUploadTempChunksDir, 0775)) {
                Log::warning("Adjusting permissions failed on " . $httpUploadTempChunksDir);
            }

            // Check that the chunk upload directory is writable
            $fid = @fopen("$httpUploadTempChunksDir/$fname", "w");
            if (false === $fid) {
                return false;
            } else {
                fclose($fid);
                unlink("$httpUploadTempChunksDir/$fname");
                $result &= true;
            }

            // Files upload directory

            // Does the file upload directory exist?
            if (!is_dir($httpUploadTempFilesDir)) {
                Log::info("Trying to create " . $httpUploadTempFilesDir);
                $result &= mkdir($httpUploadTempFilesDir, 0775);
                if (! $result) {
                    Log::error("Error creating " . $httpUploadTempFilesDir);
                    return false;
                }

            }

            if (! chmod($httpUploadTempFilesDir, 0775)) {
                Log::warning("Adjusting permissions failed on " . $httpUploadTempFilesDir);
            }

            // Check that the file upload directory is writable
            $fid = @fopen("$httpUploadTempFilesDir/$fname", "w");
            if (false === $fid) {
                return false;
            } else {
                fclose($fid);
                unlink("$httpUploadTempFilesDir/$fname");
                $result &= true;
            }

        }

        // Return global result
        return $result;
    }

    /**
     * Remove any non-ASCII characters and convert known non-ASCII characters
     * to their ASCII equivalents, if possible. Also, replaces blank spaces
     * with "_".
     *
     * @param string $string
     * @return string $string
     * @author Jay Williams <myd3.com>
     * @license MIT License
     * @link http://gist.github.com/119517
     *
     * Modified by Aaron Ponti and Kevin Namink for the HRM project.
     */
    public static function sanitizeFileName($string)
    {
        // Replace Single Curly Quotes
        $search[]  = chr(226).chr(128).chr(152);
        $replace[] = "'";
        $search[]  = chr(226).chr(128).chr(153);
        $replace[] = "'";

        // Replace En Dash
        $search[]  = chr(226).chr(128).chr(147);
        $replace[] = '--';

        // Replace Em Dash
        $search[]  = chr(226).chr(128).chr(148);
        $replace[] = '---';

        // Replace Ellipsis with three consecutive dots
        $search[]  = chr(226).chr(128).chr(166);
        $replace[] = '...';

        // Replace blank spaces with underscores
        $search[]  = " ";
        $replace[] = "_";
        
        // Apply Replacements
        $string = str_replace($search, $replace, $string);

        // Replace non-ASCII characters and any characters that are not
        // one of the following: "!%^&=',.-_" "0-9" "A-Z" "a-z" with "_".
        $string = preg_replace("/[^\!\%\^\&\=\'\,\.\-\_0-9A-Za-z]/","_",
                               $string);

        return $string;
    }
}

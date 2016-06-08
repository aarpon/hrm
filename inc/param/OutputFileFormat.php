<?php
/**
 * OutputFileFormat
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\DatabaseConnection;
use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter to represent the output file format.
 *
 * @package hrm
 */
class OutputFileFormat extends ChoiceParameter
{

    /**
     * OutputFileFormat constructor.
     */
    public function __construct()
    {
        parent::__construct("OutputFileFormat");
    }

    /**
     * Checks whether the Parameter is a Task Parameter.
     * @return bool Always true.
     */
    public function isTaskParameter()
    {
        return True;
    }

    /**
     * Returns the Parameter translated value.
     *
     * The translated form of the Parameter value is then one used in
     * the Tcl script. The translation of the output file format is read from
     * the database.
     *
     * @return string Translated value.
     */
    public function translatedValue()
    {
        $db = new DatabaseConnection();
        $result = $db->translationFor($this->name, $this->value);
        return $result;
    }

    /**
     * Returns the file extension associated with a given output format
     * translated value.
     * @return string The file extension
     * @todo This information is _partially_ in the database.
     */
    public function extension()
    {
        $result = $this->translatedValue();
        switch ($result) {
            case "tiff":
            case "tiffrgb":
            case "tiff16":
                return "tif";
            case "imaris":
                return "ims";
            case "ome":
                return "ome";
            case "ics":
            case "ics2":
                return "ics";
            case "hdf5":
                return "h5";
            case "r3d":
                return "r3d";
            default:
                return "";
        }
    }
}

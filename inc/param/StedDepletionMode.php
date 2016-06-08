<?php
/**
 * StedDepletionMode
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\DatabaseConnection;
use hrm\param\base\AnyTypeArrayParameter;

/**
 * An AnyTypeArrayParameter to represent the STED depletion mode.
 *
 * @package hrm
 */
class StedDepletionMode extends AnyTypeArrayParameter
{

    /**
     * StedDepletionMode constructor.
     */
    public function __construct()
    {
        parent::__construct("StedDepletionMode");
    }

    /**
     * Confirms that this is NOT a Microscope Parameter.
     *
     * We make a distinction between STED parameters and microscope parameters.
     *
     * @return bool Always false.
     */
    public function isForMicroscope()
    {
        return False;
    }

    /**
     * Confirms that this is a Sted Parameter.
     *
     * We make a distinction between STED parameters and microscope parameters.
     * @return bool Always true.
     */
    public function isForSted()
    {
        return True;
    }

    /**
     * Returns the Parameter translated value.
     *
     * The translated form of the Parameter value is then one used in
     * the Tcl script. The translation of the sted depletion mode is read from
     * the database.
     * @return mixed translated value.
     */
    public function translatedValue()
    {
        $db = new DatabaseConnection();
        $result = $db->translationFor($this->name, $this->value);
        return $result;
    }

    /**
     * Checks whether the Parameter is valid.
     * @return bool True if the Parameter is valid, false otherwise.
     */
    public function check()
    {
        for ($i = 0; $i < $this->numberOfChannels(); $i++) {
            if ($this->value[$i] == NULL) {
                $this->message = "Please select a depletion mode for channel $i!";
                return False;
            }
        }
        return True;
    }
}

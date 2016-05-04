<?php
/**
 * SpimDir
 *
 * @package hrm
 * @subpackage param
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\DatabaseConnection;
use hrm\param\base\AnyTypeArrayParameter;

/**
 * Class SpimDir
 *
 * An AnyTypeArrayParameter to represent the SPIM direction .
 *
 * @package hrm\param
 */
class SpimDir extends AnyTypeArrayParameter
{


    /**
     * SpimDir constructor.
     */
    public function __construct()
    {
        parent::__construct("SpimDir");
    }

    /**
     * Confirms that this is NOT a Microscope Parameter.
     *
     * We make a distinction between SPIM parameters and microscope parameters.
     *
     * @return bool Always false.
     */
    public function isForMicroscope()
    {
        return False;
    }

    /**
     * Confirms that this is a SPIM Parameter.
     *
     * We make a distinction between SPIM parameters and microscope parameters.
     * @return bool Always true.
     */
    public function isForSpim()
    {
        return True;
    }

    /**
     * Returns the Parameter translated value.
     *
     * The translated form of the Parameter value is then one used in
     * the Tcl script.
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
     * Checks whether the Parameter is valid.
     * @return bool True if the Parameter is valid, false otherwise.
     */
    public function check()
    {
        for ($i = 0; $i < $this->numberOfChannels(); $i++) {
            if ($this->value[$i] == NULL) {
                $this->message = "Please select an illumination direction for channel $i!";
                return False;
            }
        }
        return True;
    }

    /**
     * Returns the string representation of the Parameter.
     * @param int $numberOfChannels Number of channels (ignored).
     * @return string String representation of the Parameter.
     */
    public function displayString($numberOfChannels = 0)
    {
        $result = $this->formattedName();

        /* Do not count empty elements. Do count channel '0'. */
        $channels = array_filter($this->value, 'strlen');
        $value = implode(", ", $channels);
        $result = $result . $value . "\n";

        return $result;
    }
}

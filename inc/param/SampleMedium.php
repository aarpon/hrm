<?php
/**
 * SampleMedium
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
 * A ChoiceParameter to represent the sample medium.
 *
 * @package hrm
 */
class SampleMedium extends ChoiceParameter
{

    /**
     * SampleMedium constructor.
     */
    public function __construct()
    {
        parent::__construct("SampleMedium");
    }

    /**
     * Confirms that this is a Microscope Parameter.
     * @return bool Always true.
     */
    public function isForMicroscope()
    {
        return True;
    }

    /**
     * Returns the Parameter translated value
     *
     * The translated form of the Parameter value is then one used in
     * the Tcl script. The translation of the sample medium is read from
     * the database.
     *
     * @return mixed Translated value.
     */
    public function translatedValue()
    {
        if (in_array($this->value, $this->possibleValues)) {
            $db = DatabaseConnection::get();
            $result = $db->translationFor($this->name, $this->value);
            return $result;
        } else {
            return $this->value;
        }
    }

    /**
     * Checks whether the Parameter is valid.
     * @return bool True if the Parameter is valid, false otherwise.
     */
    public function check()
    {
        $this->message = '';
        $result = in_array($this->value, $this->possibleValues());
        if ($result == False) {
            // No preset selected: the value must then be numeric
            if (!is_numeric($this->value)) {
                $this->message = "The refractive index of the sample medium " .
                    "must be a number!";
            } else {
                $result = True;
            }
        }
        return $result;
    }

}

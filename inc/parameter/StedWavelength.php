<?php
/**
 * StedWavelength
 *
 * @package hrm
 * @subpackage param
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\NumericalArrayParameter;

/**
 * Class StedWavelength
 *
 * A NumericalParameter to represent the STED depletion wavelength.
 *
 * @package hrm\param
 */
class StedWavelength extends NumericalArrayParameter
{

    /**
     * StedWavelength constructor.
     */
    public function __construct()
    {
        parent::__construct("StedWavelength");
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
     * Checks whether the Parameter is valid.
     * @return bool True if the Parameter is valid, false otherwise.
     */
    public function check()
    {
        $this->message = '';
        $result = True;

        $values = array_slice($this->value, 0, $this->numberOfChannels);

        // First check that all values are set.
        // '0' is a valid entry. Thus, search in 'strict' mode.
        if (array_search("", $values, true) !== FALSE) {
            if ($this->mustProvide()) {
                $this->message = 'STED wavelength: ' .
                    'some of the values are missing!';
            } else {
                $this->message = 'You can omit typing values for this ' .
                    'parameter. If you decide to provide them, though, ' .
                    'you must provide them all.';
            }
            return false;
        }
        // Now check the values themselves
        for ($i = 0; $i < $this->numberOfChannels; $i++) {
            $result = $result && parent::checkValue($this->value[$i]);
        }
        if ($result == false) {
            $this->message = "STED Wavelength: " . $this->message;
        }

        return $result;
    }
}

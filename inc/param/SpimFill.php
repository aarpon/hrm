<?php
/**
 * SpimFill
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
 * A NumericalArrayParameter to represent the SPIM Fill Factor.
 *
 * @package hrm
 */
class SpimFill extends NumericalArrayParameter
{


    /**
     * SpimFill constructor.
     */
    public function __construct()
    {
        parent::__construct("SpimFill");
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
                $this->message = 'SPIM Fill Factor: ' .
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
            $this->message = "SPIM Fill Factor: " . $this->message;
        }

        return $result;
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


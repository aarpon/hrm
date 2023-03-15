<?php
/**
 * Acuity
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\NumericalArrayParameter;

/**
 * An NumericalArrayParameter to represent the acuity value per channel.
 *
 * @package hrm
 */
class Acuity extends NumericalArrayParameter
{

    /**
     * Acuity constructor.
     */
    public function __construct()
    {
        parent::__construct("Acuity");
    }

    /**
     * Checks whether the Parameter is a Task Parameter
     * @return bool Always true.
     */
    public function isTaskParameter()
    {
        return True;
    }

    /**
     * Checks whether the Parameter is valid
     * @return bool True if the Parameter is valid, false otherwise.
     */
    public function check()
    {
    
        $result = parent::check();
        if ($result == false) {
            $this->message = "Acuity: " . $this->message;
        }
        return $result;
    }

    /**
     * Returns the string representation of the Acuity parameter.
     * @param int $numberOfChannels Number of channels.
     * @return string String representation of the Acuity Parameter.
     */
    public function displayString($numberOfChannels = 0)
    {

        $value = array_slice($this->value, 0, $numberOfChannels);
        $value = implode(", ", $value);
        $result = $this->formattedName() . $value . "\n";

        return $result;
    }
}

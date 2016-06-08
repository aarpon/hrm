<?php
/**
 * ColocChannel
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\AnyTypeArrayParameter;

/**
 * An AnyTypeArrayParameter to represent the colocalization threshold.
 *
 * @package hrm
 */
class ColocThreshold extends AnyTypeArrayParameter
{


    /**
     * ColocThreshold constructor.
     */
    public function __construct()
    {
        parent::__construct("ColocThreshold");
    }

    /**
     * Checks whether the ColocThreshold Parameter is valid
     * @return bool True if the ColocThreshold Parameter is valid, false otherwise.
     */
    public function check()
    {
        $this->message = '';
        $value = $this->internalValue();
        $result = True;
        if ($value[0] == "auto")
            return True;
        for ($i = 0; $i < $this->numberOfChannels; $i++) {
            $result = $result && $this->checkValue($value[$i]);
        }
        if ($result == False) {
            $this->message = 'Colocalization threshold: ' .
                $this->message;
        }
        return $result;
    }

    /**
     * Returns the string representation of the ColocThreshold Parameter.
     * @param int $numberOfChannels Number of channels (ignored).
     * @return string String representation of the ColocThreshold Parameter.
     */
    public function displayString($numberOfChannels = 0)
    {

        $result = $this->formattedName();

        /* Do not count empty elements. */
        $channels = array_filter($this->value, 'strlen');
        $value = implode(", ", $channels);
        $result = $result . $value . "\n";

        return $result;
    }
}


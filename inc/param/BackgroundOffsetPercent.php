<?php
/**
 * BackgroundOffsetPercent
 *
 * @package hrm
 * @subpackage param
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\AnyTypeArrayParameter;

/**
 * An AnyTypeArrayParameter to represent the background offset in percent.
 *
 * @package hrm
 */
class BackgroundOffsetPercent extends AnyTypeArrayParameter
{

    /**
     * BackgroundOffsetPercent constructor.
     */
    public function __construct()
    {
        parent::__construct("BackgroundOffsetPercent");
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
        $this->message = '';
        $value = $this->internalValue();
        $result = True;
        if ($value[0] == "auto" || $value[0] == "object")
            return True;
        for ($i = 0; $i < $this->numberOfChannels; $i++) {
            $result = $result && $this->checkValue($value[$i]);
        }
        if ($result == False) {
            $this->message = 'Background offset: ' . $this->message;
        }
        return $result;
    }

    /**
     * Returns the string representation of the BackgroundOffsetPercent parameter.
     * @param int $numberOfChannels Number of channels.
     * @return string String representation of the BackgroundOffsetPercent Parameter.
     */
    public function displayString($numberOfChannels = 0)
    {

        if ($this->value[0] == 'auto') {
            $name = ' background estimation';
            $value = 'auto';
        } elseif ($this->value[0] == 'object') {
            $name = ' background estimation';
            $value = 'in/near object';
        } else {
            if ($numberOfChannels == 1) {
                $name = 'background absolute value';
                $value = $this->value[0];
            } else {
                $name = ' background absolute values';
                $value = array_slice($this->value, 0, $numberOfChannels);
                $value = implode($value, ", ");
            }
        }
        $result = $this->formattedName($name) . $value . "\n";
        return $result;
    }
}

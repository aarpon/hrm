<?php
/**
 * ColocCoefficient
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
 * Class ColocCoefficient
 *
 * A ChoiceParameter to represent the colocalization coefficients choice.
 *
 * @package hrm\param
 */
class ColocCoefficient extends AnyTypeArrayParameter
{


    /**
     * ColocCoefficient constructor.
     */
    public function __construct()
    {
        parent::__construct("ColocCoefficient");
    }

    /**
     * Sets the value of the Parameter.
     * @param mixed $value Value for the Parameter.
     */
    public function setValue($value)
    {

        /* The parent function links the number of channels and the
         allowed number of values for a parameter. This is clearly not
         enough for the 'ColocCoefficient' class. */

        $n = count($value);
        $valueCnt = count($this->possibleValues);

        for ($i = 0; $i < $valueCnt; $i++) {
            if ($i < $n) {
                $this->value[$i] = $value[$i];
            } else {
                $this->value[$i] = null;
            }
        }
    }

    /**
     * Dummy function to override the parent 'setNumberOfChannels'.
     * @todo This can be dealt with better!
     */
    public function setNumberOfChannels()
    {
        /* The parent function links the number of channels and the
         allowed number of values for a parameter. This is clearly
         not enough for the 'ColocCoefficient' class. */
        return;
    }

    /**
     * Returns the string representation of the Parameter.
     * @param int $numberOfChannels Number of channels (ignored).
     * @return string String representation of the Parameter.
     */
    public function displayString($numberOfChannels = 0)
    {

        $result = $this->formattedName();

        /* Do not count empty elements. */
        $values = array_filter($this->value, 'strlen');
        $value = implode(", ", $values);
        $result = $result . $value . "\n";

        return $result;
    }

}

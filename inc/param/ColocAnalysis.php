<?php
/**
 * ColocAnalysis
 *
 * @package hrm
 * @subpackage param
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter to represent the colocalization analysis choice.
 *
 * @package hrm
 */
class ColocAnalysis extends ChoiceParameter
{

    /**
     * ColocAnalysis constructor.
     */
    public function __construct()
    {
        parent::__construct("ColocAnalysis");
    }

    /**
     * Returns the string representation of the Parameter.
     * @param  int $numberOfChannels This is ignored.
     * @return string String representation of the Parameter.
     */
    public function displayString($numberOfChannels = 0)
    {
        if ($this->value() == 0) {
            $value = "no";
        } else {
            $value = "yes";
        }
        $result = $this->formattedName();
        $result = $result . $value . "\n";
        return $result;
    }
}

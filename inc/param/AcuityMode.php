<?php
/**
 * Acuity Mode
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter to indicate whether acuity is enabled.
 *
 * @todo Whyt not using a BooleanParameter?
 *
 * @package hrm
 */
class AcuityMode extends ChoiceParameter
{

    /**
     * AcuityMode constructor.
     */
    public function __construct()
    {
        parent::__construct("AcuityMode");
    }

    /**
     * Returns the string representation of the Parameter.
     * @param int $numberOfChannels Number of channels (ignored).
     * @return string String representation of the Parameter.
     */
    public function displayString($numberOfChannels = 0)
    {
        $result = $this->formattedName();
        $result = $result . $this->value() . "\n";
        return $result;
    }
}

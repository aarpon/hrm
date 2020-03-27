<?php
/**
 * Autocrop
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter to indicate whether autocrop is enabled.
 *
 * @todo Whyt not using a BooleanParameter?
 *
 * @package hrm
 */
class Autocrop extends ChoiceParameter
{

    /**
     * Autocrop constructor.
     */
    public function __construct()
    {
        parent::__construct("Autocrop");
    }

    /**
     * Returns the string representation of the Parameter.
     * @param int $numberOfChannels Number of channels (ignored).
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

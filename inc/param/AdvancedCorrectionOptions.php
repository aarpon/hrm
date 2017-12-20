<?php
/**
 * AdvancedCorrectionOptions
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter to indicate the options of aberration correction.
 *
 * @package hrm
 */
class AdvancedCorrectionOptions extends ChoiceParameter
{

    /**
     * AdvancedCorrectionOptions constructor.
     */
    public function __construct()
    {
        parent::__construct("AdvancedCorrectionOptions");
    }

    /**
     * Confirms that this is a Correction Parameter
     * @return bool Always true.
     */
    public function isForCorrection()
    {
        return True;
    }

    /**
     * Returns the string representation of the Parameter
     * @param int $numberOfChannels Number of channels (ignored).
     * @return string String representation of the Parameter.
     */
    public function displayString($numberOfChannels = 0)
    {
        $value = "undefined";
        switch ($this->value()) {
            case 'few-slabs':
                $value = "few slabs";
                break;
            case 'slice':
                $value = "slice by slice";
                break;
            case 'few':
                $value = "few bricks";
                break;
        }
        $name = $this->formattedName();
        $result = $name . $value . "\n";
        return $result;
    }

}

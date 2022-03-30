<?php
/**
 * StitchAlignmentMode
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter for the alignment mode.
 *
 * @package hrm
 */
class StitchAlignmentMode extends ChoiceParameter
{

    /**
     * StitchAlignmentMode constructor.
     */
    public function __construct()
    {
        parent::__construct("StitchAlignmentMode");
    }

    
    /**
     * Returns the string representation of the Parameter.
     * @param int $numberOfChannels Number of channels (ignored).
     * @return string String representation of the Parameter.
     */
    public function displayString($numberOfChannels = 0)
    {
        switch($this->value()) {
            case 'xyz':
                $value = "xyz";
                break;
            case 'none':
                $value = "no optimization";
            case 'xy_zcenter':
                $value = "xy at z center";
                break;
            default:
                Log::error("Unknown option '" . $this->value() . "'.");
        }

        $result = $this->formattedName();
        $result = $result . $value . "\n";
        return $result;
    }
}

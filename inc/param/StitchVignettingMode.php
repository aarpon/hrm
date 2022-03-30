<?php
/**
 * StitchVignettingMode
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter for the vignetting mode.
 *
 * @package hrm
 */
class StitchVignettingMode extends ChoiceParameter
{

    /**
     * StitchVignettingMode constructor.
     */
    public function __construct()
    {
        parent::__construct("StitchVignettingMode");
    }

    
    /**
     * Returns the string representation of the Parameter.
     * @param int $numberOfChannels Number of channels (ignored).
     * @return string String representation of the Parameter.
     */
    public function displayString($numberOfChannels = 0)
    {
        switch($this->value()) {
            case 'manual':
                $value = "measured";
                break;
            case 'off':
                $value = "off";
                break;
            case 'auto':
                $value = "auto";
                break;
            default:
                Log::error("Unknown option '" . $this->value() . "'.");
        }

        $result = $this->formattedName();
        $result = $result . $value . "\n";
        return $result;
    }
}

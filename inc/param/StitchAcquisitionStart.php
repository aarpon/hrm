<?php
/**
 * StitchAcquisitionStart
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter to indicate where the acquisition starts.
 *
 * @package hrm
 */
class StitchAcquisitionStart extends ChoiceParameter
{

    /**
     * StitchAcquisitionStart constructor.
     */
    public function __construct()
    {
        parent::__construct("StitchAcquisitionStart");
    }

    
    /**
     * Returns the string representation of the Parameter.
     * @param int $numberOfChannels Number of channels (ignored).
     * @return string String representation of the Parameter.
     */
    public function displayString($numberOfChannels = 0)
    {
        switch($this->value()) {
            case 'tl':
                $value = "top left";
                break;
            case 'tr':
                $value = "top right";
                break;
            case 'bl':
                $value = "bottom left";
                break;
            case 'br':
                $value = "bottom right";
                break;
            default:
                Log::error("Unknown option '" . $this->value() . "'.");
        }

        $result = $this->formattedName();
        $result = $result . $value . "\n";
        return $result;
    }
}

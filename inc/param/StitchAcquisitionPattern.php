<?php
/**
 * StitchAcquisitionPattern
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter to indicate the acquisition pattern.
 *
 * @package hrm
 */
class StitchAcquisitionPattern extends ChoiceParameter
{

    /**
     * StitchAcquisitionPattern constructor.
     */
    public function __construct()
    {
        parent::__construct("StitchAcquisitionPattern");
    }

    
    /**
     * Returns the string representation of the Parameter.
     * @param int $numberOfChannels Number of channels (ignored).
     * @return string String representation of the Parameter.
     */
    public function displayString($numberOfChannels = 0)
    {
        switch($this->value()) {
            case 'rs':
                $value = "row snake";
                break;
            case 'rl':
                $value = "row line";
                break;
            case 'cs':
                $value = "column snake";
                break;
            case 'cl':
                $value = "column line";
                break;
            case 'sc':
                $value = "spiral clockwise";
                break;
            case 'sa':
                $value = "spiral counterclockwise";
                break;
            default:
                Log::error("Unknown option '" . $this->value() . "'.");
        }

        $result = $this->formattedName();
        $result = $result . $value . "\n";
        return $result;
    }
}

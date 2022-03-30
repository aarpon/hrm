<?php
/**
 * StitchOffsetsInit
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter to indicate on which condition to base the initial offsets.
 *
 * @package hrm
 */
class StitchOffsetsInit extends ChoiceParameter
{

    /**
     * StitchOffsetsInit constructor.
     */
    public function __construct()
    {
        parent::__construct("StitchOffsetsInit");
    }


    /**
     * Returns the string representation of the Parameter.
     * @param int $numberOfChannels Number of channels (ignored).
     * @return string String representation of the Parameter.
     */
    public function displayString($numberOfChannels = 0)
    {
        switch($this->value()) {
            case 'pattern_overlap':
                $value = "pattern and overlap settings";
                break;
            case 'list_offsets':
                $value = "list of saved offsets";
                break;
            case 'metadata':
                $value = "meta data from image format";
                break;
            default:
                Log::error("Unknown option '" . $this->value() . "'.");
        }

        $result = $this->formattedName();
        $result = $result . $value . "\n";
        return $result;
    }
}

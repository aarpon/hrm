<?php
/**
 * StitchVignettingFlatfield
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\AnyTypeArrayParameter;

/**
 * An AnyTypeArrayParameter that handles the file name of the flat field.
 *
 * @package hrm
 */
class StitchVignettingFlatfield extends AnyTypeArrayParameter
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct('StitchVignettingFlatfield');
    }

    /**
     * Checks whether the Flatfield parameter is valid
     * @return bool Always true. Whatever the selection, it should be accepted.
     */
    public function check()
    {
        return True;
    }
    
    /**
     * Returns the string representation of the Flatfield file name.
     * @param int $numberOfChannels Number of channels (redundant).
     * @return string String representation of the Flatfield file name .
     */
    public function displayString($numberOfChannels = 0)
    {
        $result = $this->formattedName("stitch vignetting flatfield");
     
        if ($this->notSet()) {
            $result = $result . "*not set*" . "\n";
        } else {
            $result = $result . $this->value[0] . "\n";
        }

        return $result;
    }
}

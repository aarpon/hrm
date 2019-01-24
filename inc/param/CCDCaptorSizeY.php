<?php
/**
 * CCDCaptorSizeY
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\param;

use hrm\param\base\NumericalParameter;

/**
 * A NumericalParameter to represent the x-size of the CCD pixel.
 *
 * @package hrm
 */
class CCDCaptorSizeY extends NumericalParameter
{

    /**
     * CCDCaptorSizeY constructor.
     */
    public function __construct()
    {
        parent::__construct("CCDCaptorSizeY");
    }

    /**
     * Confirms that this is a Capture Parameter.
     * @return bool Always true.
     */
    public function isForCapture()
    {
        return True;
    }

    /**
     * Returns the string representation of the CCDCaptorSizeY Parameter.
     * @param int $numberOfChannels Number of channels (ignored).
     * @return string String representation of the CCDCaptorSizeY Parameter.
     */
    public function displayString($numberOfChannels = 0)
    {
        $result = $this->formattedName('pixel size Y');
        if ($this->notSet()) {
            $result = $result . "*not set*" . "\n";
        } else {
            $result = $result . $this->value . "\n";
        }
        return $result;
    }
}

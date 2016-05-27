<?php
/**
 * CCDCaptorSize
 *
 * @package hrm
 * @subpackage param
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\param;

use hrm\param\base\NumericalParameter;

/**
 * Class CCDCaptorSize
 *
 * A NumericalParameter to represent the x-size of the CCD pixel.
 *
 * @package hrm\param
 */
class CCDCaptorSize extends NumericalParameter
{

    /**
     * CCDCaptorSize constructor.
     *
     * This is use to calculate the pixel size (i.e. CCDCaptorSizeX) from the
     * camera and magnification of the microscope)
     */
    public function __construct()
    {
        parent::__construct("CCDCaptorSize");
    }

    /**
     * Confirms that this is a Calculation Parameter.
     * @return bool Always true.
     */
    public function isForPixelSizeCalculation()
    {
        return True;
    }

    /**
     * Checks whether the Parameter is valid.
     * @return bool True if the Parameter is valid, false otherwise.
     */
    public function check()
    {
        $result = parent::check();
        if ($result == false) {
            $this->message = "CCD element size: " . $this->message;
        }
        return $result;
    }

    /**
     * This Parameter should not display anything.
     * @param int $numberOfChannels Number of channels (ignored).
     * @return string Empty string.
     */
    public function displayString($numberOfChannels = 0)
    {
        $result = '';
        return $result;
    }
}

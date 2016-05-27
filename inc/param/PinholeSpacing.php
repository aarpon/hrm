<?php
/**
 * PinholeSpacing
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
 * Class PinholeSpacing
 *
 * A NumericalParameter to represent the pinhole spacing per Nipkow spinning disks.
 *
 * @package hrm\param
 */
class PinholeSpacing extends NumericalParameter
{

    /**
     * PinholeSpacing constructor.
     */
    public function __construct()
    {
        parent::__construct("PinholeSpacing");
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
     * Checks whether the PinholeSpacing Parameter is valid
     * @return bool True if the PinholeSpacing Parameter is valid, false otherwise.
     */
    public function check()
    {
        $result = parent::check();
        if ($result == false) {
            $this->message = "Pinhole Spacing: " . $this->message;
        }
        return $result;
    }
}

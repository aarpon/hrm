<?php
/**
 * PinholeSize
 *
 * @package hrm
 * @subpackage param
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\NumericalArrayParameter;

/**
 * A NumericalArrayParameter to represent the pinhole size per channel.
 *
 * @package hrm
 */
class PinholeSize extends NumericalArrayParameter
{

    /**
     * PinholeSize constructor.
     */
    public function __construct()
    {
        parent::__construct("PinholeSize");
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
     * Checks whether the PinholeSize Parameter is valid.
     * @return bool True if the PinholeSize Parameter is valid, false otherwise.
     */
    public function check()
    {
        $result = parent::check();
        if ($result == false) {
            $this->message = "Pinhole size: " . $this->message;
        }
        return $result;
    }
}

<?php
/**
 * TimeInterval
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\NumericalParameter;

/**
 * A NumericalParameter to represent the time interval in time series.
 *
 * @package hrm
 */
class TimeInterval extends NumericalParameter
{

    /**
     * TimeInterval constructor.
     */
    public function __construct()
    {
        parent::__construct("TimeInterval");
    }

    /**
     * Checks whether the TimeInterval Parameter is valid
     * @return bool true if the TimeInterval Parameter is valid, false otherwise.
     */
    public function check()
    {
        // The TimeInterval parameter can have a special value of 0
        // if datasets are not time series.
        if (floatval($this->value) == 0.0) {
            return True;
        }
        // Now run the standard test
        $result = parent::check();
        if ($result == false) {
            $this->message = "Time interval: " . $this->message;
        }
        return $result;
    }

    /**
     * Confirms that this is a Capture Parameter.
     * @return bool Always true.
     */
    public function isForCapture()
    {
        return True;
    }
}

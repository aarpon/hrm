<?php
/**
 * ZStepSize
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\NumericalParameter;

/**
 * A NumericalParameter to represent the z step (distance between planes).
 *
 * @package hrm
 */
class ZStepSize extends NumericalParameter
{

    /**
     * ZStepSize constructor.
     */
    public function __construct()
    {
        parent::__construct("ZStepSize");
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
     * Checks whether the ZStepSize Parameter is valid.
     * @return bool True if the ZStepSize Parameter is valid, false otherwise.
     */
    public function check()
    {
        // The ZStepSize parameter can have a special value of 0
        // for 2D datasets.
        if (floatval($this->value) == 0.0) {
            return True;
        }
        // Now run the standard test
        $result = parent::check();
        if ($result == false) {
            $this->message = "Z step: " . $this->message;
        }
        return $result;
    }
}

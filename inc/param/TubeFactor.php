<?php
/**
 * TubeFactor
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
 * A NumericalParameter to represent the tube factor.
 *
 * @package hrm
 */
class TubeFactor extends NumericalParameter
{

    /**
     * TubeFactor constructor.
     */
    public function __construct()
    {
        parent::__construct("TubeFactor");
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
            $this->message = "Tube Factor: " . $this->message;
        }
        return $result;
    }

}

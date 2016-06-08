<?php
/**
 * NumericalAperture
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\NumericalParameter;

require_once dirname(__FILE__) . '/../bootstrap.inc.php';

/**
 * A NumericalParameter to represent the numerical aperture of the objective.
 *
 * @package hrm
 */
class NumericalAperture extends NumericalParameter
{
    /**
     * NumericalAperture constructor.
     */
    public function __construct()
    {
        parent::__construct("NumericalAperture");
    }

    /**
     * Confirms that this is a Microscope Parameter.
     * @return bool Always true.
     */
    public function isForMicroscope()
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
            $this->message = "Numerical Aperture: " . $this->message;
        }
        return $result;
    }

}

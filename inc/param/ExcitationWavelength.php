<?php
/**
 * ExcitationWavelength
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\NumericalArrayParameter;

require_once dirname(__FILE__) . '/../bootstrap.inc.php';

/**
 * A NumericalArrayParameter to represent the excitation wavelength.
 *
 * The ExcitationWavelength class can store an array of numbers as value.
 *
 * @package hrm
 */
class ExcitationWavelength extends NumericalArrayParameter
{

    /**
     * ExcitationWavelength constructor.
     */
    public function __construct()
    {
        parent::__construct("ExcitationWavelength");
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
            $this->message = "Excitation Wavelength: " . $this->message;
        }
        return $result;
    }
}

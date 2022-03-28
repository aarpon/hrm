<?php
/**
 * StitchAcquisitionOverlap
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\NumericalParameter;

/**
 * A NumericalParameter for the acquisition overlap (in tile %).
 *
 * @package hrm
 */
class StitchAcquisitionOverlap extends NumericalParameter
{

    /**
     * StitchAcquisitionOverlap constructor.
     */
    public function __construct()
    {
        parent::__construct("StitchAcquisitionOverlap");
    }

    /**
     * Checks whether the Parameter is valid.
     * @return bool True if the Parameter is valid, false otherwise.
     */
    public function check()
    {
        $result = parent::check();
        if ($result == false) {
            $this->message = "Acquisition Overlap: " . $this->message;
        }
        return $result;
    }
}

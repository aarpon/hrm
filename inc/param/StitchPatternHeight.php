<?php
/**
 * StitchPatternHeight
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\NumericalParameter;

/**
 * A NumericalParameter for the pattern height (in number of tiles).
 *
 * @package hrm
 */
class StitchPatternHeight extends NumericalParameter
{

    /**
     * StitchPatternHeight constructor.
     */
    public function __construct()
    {
        parent::__construct("StitchPatternHeight");
    }

    /**
     * Checks whether the Parameter is valid.
     * @return bool True if the Parameter is valid, false otherwise.
     */
    public function check()
    {
        $result = parent::check();
        if ($result == false) {
            $this->message = "Pattern Height: " . $this->message;
        }
        return $result;
    }
}

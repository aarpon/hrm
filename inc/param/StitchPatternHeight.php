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
}

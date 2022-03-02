<?php
/**
 * StitchPatternWidth
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\NumericalParameter;

/**
 * A NumericalParameter for the pattern width (in number of tiles).
 *
 * @package hrm
 */
class StitchPatternWidth extends NumericalParameter
{

    /**
     * StitchPatternWidth constructor.
     */
    public function __construct()
    {
        parent::__construct("StitchPatternWidth");
    }
}

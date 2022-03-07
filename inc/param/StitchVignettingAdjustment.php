<?php
/**
 * StitchVignettingAdjustment
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\NumericalParameter;

/**
 * A ChoiceParameter for the vignetting adjustment.
 *
 * @package hrm
 */
class StitchVignettingAdjustment extends NumericalParameter
{

    /**
     * StitchVignettingAdjustment constructor.
     */
    public function __construct()
    {
        parent::__construct("StitchVignettingAdjustment");
    }
}

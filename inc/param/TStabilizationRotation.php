<?php
/**
 * TStabilizationRotation
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter to indicate whether to correct for rotations while
 * stabilizing a time series.
 *
 * @todo Why not a BooleanParameter?
 *
 * @package hrm
 */
class TStabilizationRotation extends ChoiceParameter
{

    /**
     * TStabilizationRotation constructor.
     */
    public function __construct()
    {
        parent::__construct("TStabilizationRotation");
    }
}

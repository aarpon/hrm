<?php
/**
 * TStabilizationCropping
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter to indicate which method to use for cropping the
 * stabilized time series.
 *
 * @todo Why not a BooleanParameter?
 *
 * @package hrm
 */
class TStabilizationCropping extends ChoiceParameter
{

    /**
     * TStabilizationCropping constructor.
     */
    public function __construct()
    {
        parent::__construct("TStabilizationCropping");
    }
}

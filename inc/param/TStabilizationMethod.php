<?php
/**
 * TStabilizationMethod
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter to indicate which method to use in the T Stabilization.
 *
 * @todo Why not a BooleanParameter?
 *
 * @package hrm
 */
class TStabilizationMethod extends ChoiceParameter
{

    /**
     * TStabilizationMethod constructor.
     */
    public function __construct()
    {
        parent::__construct("TStabilizationMethod");
    }
}

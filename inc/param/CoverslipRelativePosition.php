<?php
/**
 * CoverslipRelativePosition
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter to represent the relative position of plane 0 with respect
 * to the coverslip.
 *
 * @package hrm
 */
class CoverslipRelativePosition extends ChoiceParameter
{

    /**
     * CoverslipRelativePosition constructor.
     */
    public function __construct()
    {
        parent::__construct("CoverslipRelativePosition");
    }

    /**
     * Confirms that this is a Correction Parameter.
     * @return bool Always true.
     */
    public function isForCorrection()
    {
        return True;
    }

}

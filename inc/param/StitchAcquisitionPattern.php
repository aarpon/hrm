<?php
/**
 * StitchAcquisitionPattern
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter to indicate the acquisition pattern.
 *
 * @package hrm
 */
class StitchAcquisitionPattern extends ChoiceParameter
{

    /**
     * StitchAcquisitionPattern constructor.
     */
    public function __construct()
    {
        parent::__construct("StitchAcquisitionPattern");
    }
}

<?php
/**
 * StitchAcquisitionStart
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter to indicate where the acquisition starts.
 *
 * @package hrm
 */
class StitchAcquisitionStart extends ChoiceParameter
{

    /**
     * StitchAcquisitionStart constructor.
     */
    public function __construct()
    {
        parent::__construct("StitchAcquisitionStart");
    }
}

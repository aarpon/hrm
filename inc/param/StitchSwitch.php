<?php
/**
 * StitchSwitch
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter for enabling/disabling stitching.
 *
 * @package hrm
 */
class StitchSwitch extends ChoiceParameter
{

    /**
     * StitchSwitch constructor.
     */
    public function __construct()
    {
        parent::__construct("StitchSwitch");
    }
}

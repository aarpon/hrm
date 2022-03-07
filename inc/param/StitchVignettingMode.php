<?php
/**
 * StitchVignettingMode
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter for the vignetting mode.
 *
 * @package hrm
 */
class StitchVignettingMode extends ChoiceParameter
{

    /**
     * StitchVignettingMode constructor.
     */
    public function __construct()
    {
        parent::__construct("StitchVignettingMode");
    }
}

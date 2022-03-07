<?php
/**
 * StitchPrefilterMode
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter for the outlier prefiltering mode.
 *
 * @package hrm
 */
class StitchPrefilterMode extends ChoiceParameter
{

    /**
     * StitchPrefilterMode constructor.
     */
    public function __construct()
    {
        parent::__construct("StitchPrefilterMode");
    }
}

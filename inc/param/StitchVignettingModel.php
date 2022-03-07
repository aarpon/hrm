<?php
/**
 * StitchVignettingModel
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter for the vignetting model.
 *
 * @package hrm
 */
class StitchVignettingModel extends ChoiceParameter
{

    /**
     * StitchVignettingModel constructor.
     */
    public function __construct()
    {
        parent::__construct("StitchVignettingModel");
    }
}

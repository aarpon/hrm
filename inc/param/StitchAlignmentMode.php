<?php
/**
 * StitchAlignmentMode
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter for the alignment mode.
 *
 * @package hrm
 */
class StitchAlignmentMode extends ChoiceParameter
{

    /**
     * StitchAlignmentMode constructor.
     */
    public function __construct()
    {
        parent::__construct("StitchAlignmentMode");
    }
}

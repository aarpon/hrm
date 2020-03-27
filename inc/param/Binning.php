<?php
/**
 * NumberOfChannels
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter to represent the binning.
 *
 * @package hrm
 */
class Binning extends ChoiceParameter
{

    /**
     * Binning constructor.
     */
    public function __construct()
    {
        parent::__construct("Binning");
    }

    /**
     * Confirms that this is a Calculation Parameter.
     * return bool Always true.
     */
    public function isForPixelSizeCalculation()
    {
        return True;
    }

}

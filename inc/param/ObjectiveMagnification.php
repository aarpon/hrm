<?php
/**
 * ObjectiveMagnification
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

require_once dirname(__FILE__) . '/../bootstrap.php';

/**
 * A ChoiceParameter to represent the objective magnification.
 *
 * @package hrm
 */
class ObjectiveMagnification extends ChoiceParameter
{

    /**
     * ObjectiveMagnification constructor.
     */
    public function __construct()
    {
        parent::__construct("ObjectiveMagnification");
    }

    /**
     * Confirms that this is a Calculation Parameter.
     * @return bool Always true.
     */
    public function isForPixelSizeCalculation()
    {
        return True;
    }

}

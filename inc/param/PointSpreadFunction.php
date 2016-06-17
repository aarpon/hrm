<?php
/**
 * PointSpreadFunction
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
 * A ChoiceParameter to handle the type of PointSpreadFunction to be used,
 * theoretical or measured.
 *
 * @package hrm
 */
class PointSpreadFunction extends ChoiceParameter
{

    /**
     * PointSpreadFunction constructor.
     */
    public function __construct()
    {
        parent::__construct("PointSpreadFunction");
    }

    /**
     * Confirms that this is an Image Parameter.
     *
     * @return bool Always true.
     */
    public function isForImage()
    {
        return True;
    }

}

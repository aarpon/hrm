<?php
/**
 * QualityChangeStoppingCriterion
 *
 * @package hrm
 * @subpackage param
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\NumericalParameter;

/**
 * Class QualityChangeStoppingCriterion
 *
 * A NumericalParameter to represent the quality change stopping criterion.
 *
 * @package hrm\param
 */
class QualityChangeStoppingCriterion extends NumericalParameter
{

    /**
     * QualityChangeStoppingCriterion constructor.
     */
    public function __construct()
    {
        parent::__construct("QualityChangeStoppingCriterion");
    }

    /**
     * Checks whether the Parameter is a Task Parameter.
     * @return bool Always true.
     */
    public function isTaskParameter()
    {
        return True;
    }

    /**
     * Checks whether the Parameter is valid.
     * @return bool True if the Parameter is valid, false otherwise.
     */
    public function check()
    {
        $result = parent::check();
        if ($result == false) {
            $this->message = "Quality change: " . $this->message;
        }
        return $result;
    }

}

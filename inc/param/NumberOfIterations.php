<?php
/**
 * NumberOfIterations
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
 * A NumericalParameter to represent the number of iterations.
 *
 * @package hrm
 */
class NumberOfIterations extends NumericalParameter
{

    /**
     * NumberOfIterations constructor.
     */
    public function __construct()
    {
        parent::__construct("NumberOfIterations");
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
     * Checks whether the Parameter is valid
     * @return bool True if the Parameter is valid, false otherwise.
     */
    public function check()
    {
        $result = parent::check();
        if ($result == false) {
            $this->message = "Number of iterations: " . $this->message;
        }
        return $result;
    }

}

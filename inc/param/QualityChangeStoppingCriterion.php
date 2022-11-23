<?php
/**
 * QualityChangeStoppingCriterion
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\DatabaseConnection;
use hrm\param\base\NumericalArrayParameter;

/**
 * A NumericalArrayParameter to represent the quality change stopping
 * criterion per channel.
 *
 * @package hrm@param
 */
class QualityChangeStoppingCriterion extends NumericalArrayParameter
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
   
    /**
     * Sets the value of the NumericalArrayParameter.
     *
     * The value must be an array with 'maxChanCnt' values (those who refer to
     * non-existing channels should be null).
     *
     * @todo Notice that right now we do not force the input argument to
     * be an array, since this requires a large refactoring that will be
     * done in a later stage.
     *
     * @param array $values Array of values for the NumericalArrayParameter.
     */
    public function setValue($values)
    {
        $db = DatabaseConnection::get();
        $maxChanCnt = $db->getMaxChanCnt();
        $lastValue = null;

        if (is_array($values)) {
            foreach ($values as $i => $value) {
                if ($i < $maxChanCnt) {
                    if ($value != null) {
                        $this->value[$i] = $value;
                        $lastValue = $value;
                    } else {
                        $this->value[$i] = $lastValue;
                    }
                } else {
                    $this->value[$i] = null;
                }
            }
            
        } else {
            $this->value = array($values);
        }
    }
}

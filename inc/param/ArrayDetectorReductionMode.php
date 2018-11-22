<?php
/**
 * Array Detector Reduction Mode
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter to indicate which reduction mode to use.
 *
 * @package hrm
 */
class ArrayDetectorReductionMode extends ChoiceParameter
{

    /**
     * ArrayDetectorReductionMode constructor.
     */
    public function __construct()
    {
        parent::__construct("ArrayDetectorReductionMode");
    }

    /**
     * Checks whether the Parameter is a Task Parameter.
     * @return bool Always true/
     */
    public function isTaskParameter()
    {
        return True;
    }

    /**
     * Overrides the abstract function to set a specific order in 
     * the resulting array.
     */
    public function possibleValues()
    {
        $this->message = '';

        $possibleValuesSorted = array("auto", "all", "no", "core all", "core no", 
                                      "supersample in Y", "supersample in XY");
        $possibleValuesUnsorted = parent::possibleValues();

        /* Check for consistency. */
        /* There might be a better way to cross-check both arrays in PHP. */
        foreach ($possibleValuesSorted as $value) {
            if (!in_array($value, $possibleValuesUnsorted)) {
                $this->message = "Non existing value in Reduction Mode table.";
                
                unset($possibleValuesSorted);
                break;
            }                            
        }
        foreach ($possibleValuesUnsorted as $value) {
            if (!in_array($value, $possibleValuesSorted)) {
                $this->message = "Non existing value in Reduction Mode array.";

                unset($possibleValuesSorted);
                break;
            }                            
        }

        return $possibleValuesSorted;
    }
}

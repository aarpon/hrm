<?php
/**
 * DeconvolutionAlgorithm
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter to represent the deconvolution algorithm.
 *
 * @package hrm
 */
class DeconvolutionAlgorithm extends ChoiceParameter
{

    /**
     * DeconvolutionAlgorithm constructor.
     */
    public function __construct()
    {
        parent::__construct("DeconvolutionAlgorithm");
    }

    /**
     * Checks whether the Parameter is a Task Parameter.
     * @return bool Always true/
     */
    public function isTaskParameter()
    {
        return True;
    }

}

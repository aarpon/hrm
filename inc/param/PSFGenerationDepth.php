<?php
/**
 * ZStepSize
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
 * A NumericalParameter to represent the depth of the PSF generation.
 *
 * @package hrm
 */
class PSFGenerationDepth extends NumericalParameter
{

    /**
     * PSFGenerationDepth constructor.
     */
    public function __construct()
    {
        parent::__construct("PSFGenerationDepth");
    }

    /**
     * Checks whether the PSFGenerationDepth Parameter is valid.
     * #return bool True if the PSFGenerationDepth Parameter is valid, false
     * otherwise.
     */
    public function check()
    {
        $result = parent::check();
        if ($result == false) {
            $this->message = "PSF generation depth: " . $this->message;
        }
        return $result;
    }
}

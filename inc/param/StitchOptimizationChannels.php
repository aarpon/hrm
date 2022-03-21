<?php
/**
 * StitchOptimizationChannels
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\AnyTypeArrayParameter;

/**
 * An ArrayParameter for the optimization channels.
 *
 * @package hrm
 */
class StitchOptimizationChannels extends AnyTypeArrayParameter
{

    /**
     * StitchOptimizationMode constructor.
     */
    public function __construct()
    {
        parent::__construct("StitchOptimizationChannels");
    }
}

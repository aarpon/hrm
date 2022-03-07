<?php
/**
 * StitchVignettingChannels
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\AnyTypeArrayParameter;

/**
 * An ArrayParameter for the vignetting channels.
 *
 * @package hrm
 */
class StitchVignettingChannels extends AnyTypeArrayParameter
{

    /**
     * StitchVignettingMode constructor.
     */
    public function __construct()
    {
        parent::__construct("StitchVignettingChannels");
    }
}

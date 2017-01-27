<?php
/**
 * MultiChannelOutput
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\BooleanParameter;

/**
 * A BooleanParameter to indicate whether the output is multi-channel.
 *
 * @package hrm
 */
class MultiChannelOutput extends BooleanParameter
{

    /**
     * MultiChannelOutput constructor.
     */
    public function __construct()
    {
        parent::__construct("MultiChannelOutput");
    }

    /**
     * Checks whether the Parameter is a Task Parameter.
     * return bool Always true.
     */
    public function isTaskParameter()
    {
        return True;
    }

    /**
     * This Parameter should not display anything.
     * @param int $numberOfChannels Number of channels (ignored).
     * @return string Empty string.
     */
    public function displayString($numberOfChannels = 0)
    {
        return "";
    }

}

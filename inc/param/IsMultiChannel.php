<?php
/**
 * IsMultiChannel
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\BooleanParameter;

/**
 * A BooleanParameter that distinguishes between single- and multi-channel
 * images.
 *
 * @todo Is this still in use?
 * 
 * @package hrm
 */
class IsMultiChannel extends BooleanParameter
{

    /**
     * IsMultiChannel constructor.
     */
    public function __construct()
    {
        parent::__construct("IsMultiChannel");
    }

    /**
     * Returns the string representation of the isMultiChannel Parameter
     * @param int $numberOfChannels Number of channels (ignored).
     * @return string String representation of the IsMultiChannel Parameter.
     */
    public function displayString($numberOfChannels = 0)
    {
        if ($this->value() == 'True') {
            $result = " multichannel image\n";
        } else {
            $result = " single channel image\n";
        }
        return $result;
    }
}

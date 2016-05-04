<?php
/**
 * IsMultiChannel
 *
 * @package hrm
 * @subpackage param
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\BooleanParameter;

require_once dirname(__FILE__) . '/../bootstrap.inc.php';

/**
 * Class IsMultiChannel
 *
 * A BooleanParameter that distinguishes between single- and multi-channel
 * images.
 *
 * @package hrm\param
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
     * @return string String representation of the IsMultiChannel Parameter.
     */
    public function displayString()
    {
        if ($this->value() == 'True') {
            $result = " multichannel image\n";
        } else {
            $result = " single channel image\n";
        }
        return $result;
    }
}

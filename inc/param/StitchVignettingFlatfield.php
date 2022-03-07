<?php
/**
 * StitchVignettingFlatfield
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\AnyTypeArrayParameter;

/**
 * An AnyTypeArrayParameter that handles the file name of the flat field.
 *
 * @package hrm
 */
class StitchVignettingFlatfield extends AnyTypeArrayParameter
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct('StitchVignettingFlatfield');
    }
}

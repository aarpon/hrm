<?php
/**
 * StitchVignettingDarkframe
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\AnyTypeArrayParameter;

/**
 * An AnyTypeArrayParameter that handles the file name of the dark frame.
 *
 * @package hrm
 */
class StitchVignettingDarkframe extends AnyTypeArrayParameter
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct('StitchVignettingDarkframe');
    }
}

<?php
/**
 * AberrationCorrectionNecessary
 *
 * @package hrm
 * @subpackage param
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\BooleanParameter;

/**
 * A BooleanParameter to indicate whether aberration correction is necessary.
 *
 * @package hrm
 */
class AberrationCorrectionNecessary extends BooleanParameter {

    /**
     * AberrationCorrectionNecessary constructor.
     */
    public function __construct() {
        parent::__construct("AberrationCorrectionNecessary");
    }

}

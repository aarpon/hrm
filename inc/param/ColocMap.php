<?php
/**
 * ColocAnalysis
 *
 * @package hrm
 * @subpackage param
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * Class ColocMap
 *
 * A ChoiceParameter to represent the colocalization map choice.
 *
 * @package hrm\param
 */
class ColocMap extends ChoiceParameter {

    /**
     * ColocMap constructor.
     */
    public function __construct() {
        parent::__construct("ColocMap");
    }

}

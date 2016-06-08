<?php
/**
 * ColocAnalysis
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter to represent the colocalization map choice.
 *
 * @package hrm
 */
class ColocMap extends ChoiceParameter {

    /**
     * ColocMap constructor.
     */
    public function __construct() {
        parent::__construct("ColocMap");
    }

}

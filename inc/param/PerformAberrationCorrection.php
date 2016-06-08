<?php
/**
 * PerformAberrationCorrection
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter to indicate whether aberration correction should be
 * performed.
 *
 * @todo Why not a BooleanParameter?
 *
 * @package hrm
 */
class PerformAberrationCorrection extends ChoiceParameter
{

    /**
     * PerformAberrationCorrection constructor.
     */
    public function __construct()
    {
        parent::__construct("PerformAberrationCorrection");
    }

    /**
     * Confirms that this is a Correction Parameter
     * @return bool Always true.
     */
    public function isForCorrection()
    {
        return True;
    }

    /**
     * Returns the string representation of the Parameter
     * @param int $numberOfChannels Number of channels (ignored).
     * @return string String representation of the Parameter.
     */
    public function displayString($numberOfChannels = 0)
    {
        if ($this->value() == 0) {
            $value = "no";
        } else {
            $value = "yes";
        }
        $result = $this->formattedName();
        $result = $result . $value . "\n";
        return $result;
    }

}

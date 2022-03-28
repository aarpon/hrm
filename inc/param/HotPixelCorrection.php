<?php
/**
 * HotPixelCorrection
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\AnyTypeArrayParameter;

require_once dirname(__FILE__) . '/../bootstrap.php';

/**
 * An AnyTypeArrayParameter that handles the file names of the Hot Pixel
 template files per channel.
 *
 * @package hrm
 */
class HotPixelCorrection extends AnyTypeArrayParameter
{

    /**
     * HotPixelCorrection constructor.
     */
    public function __construct()
    {
        parent::__construct('HotPixelCorrection');
    }

    /**
     * Checks whether the HotPixelCorrection parameter is valid
     * @return bool Always true. Whatever the selection, it should be accepted.
     */
    public function check()
    {
        return True;
    }

    /**
     * Returns the string representation of the HotPixelCorrection parameter.
     * @param int $numberOfChannels Number of channels (redundant).
     * @return string String representation of the HotPixelCorrection Parameter.
     */
    public function displayString($numberOfChannels = 0)
    {
        $result = $this->formattedName("hot pixel correction - mask file");
     
        if ($this->notSet()) {
            $result = $result . "*not set*" . "\n";
        } else {
            $result = $result . $this->value[0] . "\n";
        }

        return $result;
    }

}

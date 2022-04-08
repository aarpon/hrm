<?php
/**
 * PSF
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\param\base\AnyTypeArrayParameter;

/**
 * An AnyTypeArrayParameter that handles the file names of the PSF files per
 * channel.
 *
 * @package hrm
 */
class PSF extends AnyTypeArrayParameter
{

    /**
     * PSF constructor.
     */
    public function __construct()
    {
        parent::__construct('PSF');
    }

    /**
     * Checks whether the PSF parameter is valid
     * @return bool True if the PSF parameter is valid, false otherwise.
     */
    public function check()
    {
        for ($i = 0; $i < $this->numberOfChannels(); $i++) {
            if ($this->value[$i] == NULL) {
                $this->message = "Please select a PSF file for channel $i!";
                return False;
            }
        }
        return True;
    }

    /**
     * Returns the string representation of the PSF parameter.
     * @param int $numberOfChannels Number of channels.
     * @return string String representation of the PSF Parameter.
     */
    public function displayString($numberOfChannels = 0)
    {
        if ($numberOfChannels == 1) {
            $result = $this->formattedName("PSF file name");
        } else {
            $result = $this->formattedName("PSF file names");
        }
        if ($this->notSet()) {
            $result = $result . "*not set*" . "\n";
        } else {
            if ($numberOfChannels == 1) {
                $result = $result . $this->value[0] . "\n";
            } else {
                $values = implode(", ", array_slice($this->value, 0, $numberOfChannels));
                $result = $result . $values . "\n";
            }
        }
        return $result;
    }

}

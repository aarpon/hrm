<?php
/**
 * ImageFileFormat
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\DatabaseConnection;
use hrm\param\base\SingleOrMultiChannelParameter;

/**
 * A SingleOrMultiChannelParameter to represent the image file format.
 *
 * @package hrm
 */
class ImageFileFormat extends SingleOrMultiChannelParameter
{

    /**
     * ImageFileFormat constructor.
     */
    public function __construct()
    {
        parent::__construct("ImageFileFormat");
    }

    /**
     * Confirms that this is an Image Parameter.
     * @return bool Always true.
     */
    public function isForImage()
    {
        return True;
    }

    /**
     * Returns all image file extensions for current or given format.
     * @param string $value Set to null to get the extensions for current format.
     * @return array Array of file extensions.
     */
    public function fileExtensions($value = NULL)
    {
        if ($value == NULL) {
            $value = $this->value();
        }

        $db = DatabaseConnection::get();
        $result = $db->fileExtensions($value);
        return $result;
    }

    /**
     * Returns the string representation of the ImageFileFormat Parameter.
     * @param int $numberOfChannels Number of channels (default 0).
     * @return string String representation of the ImageFileFormat Parameter.
     */
    public function displayString($numberOfChannels = 0)
    {
        $value = $this->translatedValueFor($this->value());
        $result = $this->formattedName();
        if ($this->notSet()) {
            $result = $result . "*not set*" . "\n";
        } else {
            $result = $result . $value . "\n";
        }
        return $result;
    }

}

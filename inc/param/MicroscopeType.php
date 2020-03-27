<?php
/**
 * MicroscopeType
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\param;

use hrm\DatabaseConnection;
use hrm\param\base\ChoiceParameter;

/**
 * A ChoiceParameter to represent the microscope type.
 *
 * @package hrm
 */
class MicroscopeType extends ChoiceParameter
{

    /**
     * MicroscopeType constructor.
     */
    public function __construct()
    {
        parent::__construct("MicroscopeType");
    }

    /**
     * Confirms that this is a Microscope Parameter.
     * @return bool Always true.
     */
    public function isForMicroscope()
    {
        return True;
    }

    /**
     * Returns the Parameter translated value.
     *
     * The translated form of the Parameter value is then one used in
     * the Tcl script. The translation of the microscope type is read from
     * the database.
     *
     * @return string Translated value.
     */
    public function translatedValue()
    {
        $db = DatabaseConnection::get();
        $result = $db->translationFor($this->name, $this->value);
        return $result;
    }

    /**
     * Returns the value expected by HRM based on the key from Huygens.
     *
     * The translated form of the Parameter value is then one used in
     * the Tcl script. The translated value of the microscope type is read from
     * the database.
     *
     * @param string $hucoreval Hucore value to be translated.
     * @return string Translated value.
     */
    public function translateHucore($hucoreval)
    {
        $db = DatabaseConnection::get();
        $result = $db->hucoreTranslation($this->name, $hucoreval);
        return $result;
    }

    /**
     *
     * Returns true if the given microscope type has a license.
     * @param string $value Microscope type to check for a valid license; $value
     * must be one one of the possible values.
     * @return bool True if the microscope type is licensed, false otherwise.
     */
    static public function hasLicense($value)
    {
        $db = DatabaseConnection::get();
        switch ($value) {
            case 'widefield':
                return $db->hasLicense("widefield");
            case 'multipoint confocal (spinning disk)':
                return $db->hasLicense("nipkow-disk");
            case 'single point confocal':
                return $db->hasLicense("confocal");
            case 'two photon':
                return $db->hasLicense("multi-photon");
            case 'STED':
                return $db->hasLicense("sted");
            case 'STED 3D':
                return $db->hasLicense("sted3D");
            case 'SPIM':
                return $db->hasLicense("spim");
            case 'rescan':
                return $db->hasLicense("rescan");
            case 'array detector confocal':
                return $db->hasLicense("detector-array");
            default:
                return false;
        }
    }
}

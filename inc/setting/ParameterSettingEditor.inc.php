<?php
/**
 * ParameterSettingEditor
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm;

use hrm\user\User;

require_once dirname(__FILE__) . '/../bootstrap.inc.php';


/**
 * Class ParameterSettingEditor
 *
 * Implements an Editor for ParameterSetting.
 *
 * @package hrm
 */
class ParameterSettingEditor extends SettingEditor
{

    /**
     * SettingEditor constructor.
     * @param User $user Current User.
     */
    public function __construct(User $user)
    {
        parent::__construct($user);
    }

    /**
     * Returns the name of the database table in which the ParameterSetting
     * are stored.
     * @return string The table name.
     */
    function table()
    {
        return "parameter_setting";
    }

    /**
     * Creates and returns a new ParameterSetting.
     * @return ParameterSetting A new ParameterSetting.
     */
    public function newSettingObject()
    {
        return (new ParameterSetting());
    }

    /**
     * Creates a new ParameterSetting based on parsing the given file through HuCore.
     * @param ParameterSetting $setting The ParameterSetting object to fill.
     * @param string $dirName Full path to the containing folder.
     * @param string $fileName File name without path.
     * @return bool True if the new template creation was successful, false
     * otherwise.
     *
     */
    public function image2hrmTemplate(ParameterSetting $setting, $dirName, $fileName)
    {
        $result = False;

        if ($setting == NULL) {
            return $result;
        }

        /* If it doesn't work, just do the same as create new. */
        $opts = "-path \"" . $dirName . "\" -filename \"$fileName\"";

        $data = askHuCore('getMetaDataFromImage', $opts);

        $setting->parseParamsFromHuCore($data);
        $this->message = $setting->message();

        return $result;
    }

}

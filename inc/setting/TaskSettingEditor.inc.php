<?php
/**
 * TaskSettingEditor
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
 * Class TaskSettingEditor
 *
 * Implements an Editor for TaskSetting.
 *
 * @package hrm
 */
class TaskSettingEditor extends SettingEditor
{

    /**
     * TaskSettingEditor constructor.
     * @param User $user Current User.
     */
    public function __construct(User $user)
    {
        parent::__construct($user);
    }

    /**
     * Returns the name of the database table in which the TaskSetting
     * are stored.
     * @return string The table name.
     */
    function table()
    {
        return "task_setting";
    }

    /**
     * Creates and returns a new TaskSetting.
     * @return ParameterSetting A new TaskSetting.
     */
    function newSettingObject()
    {
        return (new TaskSetting());
    }

    /**
     * Populates a setting based on parsing the raw file string of a Huygens
     * template.
     * @param TaskSetting $setting The TaskSetting object to fill.
     * @param string $huTemplate The raw contents of the template file.
     * @return bool True if the new template creation was successful, false
     * otherwise.
     */
    public function huTemplate2hrmTemplate($setting, $huTemplate)
    {

        $result = False;

        if ($setting == NULL) {
            return $result;
        }

        $opts = "-huTemplate \"" . $huTemplate . "\"";

        $data = askHuCore('getDeconDataFromHuTemplate', $opts);

        $setting->parseParamsFromHuCore($data);
        $this->message = $setting->message();

        return $result;
    }

}

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

}

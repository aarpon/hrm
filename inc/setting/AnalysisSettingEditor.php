<?php
/**
 * AnalysisSettingEditor
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\setting;

use hrm\setting\base\SettingEditor;
use hrm\user\UserV2;

/**
 * Implements an Editor for AnalysisSetting.
 *
 * @package hrm
 */
class AnalysisSettingEditor extends SettingEditor
{

    /**
     * AnalysisSettingEditor constructor.
     * @param UserV2 $user Current User.
     */
    public function __construct(UserV2 $user)
    {
        parent::__construct($user);
    }

    /**
     * Returns the name of the database table in which the AnalysisSetting
     * are stored.
     * @return string The table name.
     */
    function table()
    {
        return "analysis_setting";
    }

    /**
     * Creates and returns a new AnalysisSetting.
     * @return ParameterSetting A new AnalysisSetting.
     */
    function newSettingObject()
    {
        return (new AnalysisSetting());
    }

}

<?php
/**
 * AnalysisSettingEditor
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
 * Class AnalysisSettingEditor
 *
 * Implements an Editor for AnalysisSetting.
 *
 * @package hrm
 */
class AnalysisSettingEditor extends SettingEditor
{

    /**
     * AnalysisSettingEditor constructor.
     * @param User $user Current User.
     */
    public function __construct(User $user)
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

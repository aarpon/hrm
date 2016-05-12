<?php
/**
 * JobAnalysisSetting
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm;

require_once dirname(__FILE__) . '/../bootstrap.inc.php';


/**
 * Class JobAnalysisSetting
 *
 * A JobAnalysisSetting is an AnalysisSetting that is used when a Job is
 * executed by the queue manager. It uses different database tables and knows
 * how to put its parameter settings onto a script.
 *
 * @package hrm
 */
class JobAnalysisSetting extends AnalysisSetting {

    /**
     * Returns the name of the database table in which the list of Setting names
     * are stored.
     *
     * Besides the name, the table contains the Setting's name, owner and the
     * standard (default) flag.
     *
     * @return string The table name.
     */
    public static function table() {
        return "job_analysis_setting";
    }

    /**
     * Returns the name of the database table in which all the Parameters
     * for the Settings stored in the table specified in table().
     * @return string The parameter table name.
     * @see table()
     */
    public static function parameterTable() {
        return "job_analysis_parameter";
    }

}

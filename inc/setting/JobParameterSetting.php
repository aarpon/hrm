<?php
/**
 * JobParameterSetting
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\setting;

/**
 * A JobParameterSetting is a ParameterSetting that is used when a Job is
 * executed by the queue manager. It uses different database tables and knows
 * how to put its parameter settings onto a script.
 * 
 * @package hrm
 */
class JobParameterSetting extends ParameterSetting
{

    /**
     * Returns the name of the database table in which the list of Setting names
     * are stored.
     *
     * Besides the name, the table contains the Setting's name, owner and the
     * standard (default) flag.
     *
     * @return string The table name.
     */
    public static function table()
    {
        return "job_parameter_setting";
    }

    /**
     * Returns the name of the database table in which all the Parameters
     * for the Settings stored in the table specified in table().
     * @return string The parameter table name.
     * @see table()
     */
    public static function parameterTable()
    {
        return "job_parameter";
    }

}

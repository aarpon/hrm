<?php
/**
 * AnalysisSetting
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\setting;

use hrm\DatabaseConnection;
use hrm\param\base\Parameter;
use hrm\param\ColocAnalysis;
use hrm\setting\base\Setting;

require_once dirname(__FILE__) . '/../bootstrap.inc.php';

/**
 * An AnalysisSetting is a complete set of analysis parameters.
 *
 * @package hrm
 */
class AnalysisSetting extends Setting
{

    /**
     * AnalysisSetting constructor.
     */
    public function __construct()
    {

        // Call parent constructor.
        parent::__construct();

        // @todo Retrieve from database.
        $parameterClasses = array(
            'ColocAnalysis',
            'ColocChannel',
            'ColocCoefficient',
            'ColocThreshold',
            'ColocMap',
        );

        // Instantiate Parameter objects.
        foreach ($parameterClasses as $class) {
            $className = 'hrm\\param\\' . $class;
            $param = new $className;
            /** @var Parameter $param */
            $name = $param->name();
            $this->parameter[$name] = $param;
            $this->numberOfChannels = NULL;
        }
    }

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
        return "analysis_setting";
    }

    /**
     * Returns the name of the database table in which the list of shared
     * Setting names are stored.
     *
     * @return string The shared table name.
     */
    public static function sharedTable()
    {
        return "shared_analysis_setting";
    }

    /**
     * Returns the name of the database table in which all the Parameters
     * for the Settings stored in the table specified in table().
     * @return string The parameter table name.
     * @see table()
     */
    public static function parameterTable()
    {
        return "analysis_parameter";
    }

    /**
     * Returns the name of the database table to use for sharing settings.
     * @return string The shared parameter table name.
     * @see sharedTable()
     */
    public static function sharedParameterTable()
    {
        return "shared_analysis_parameter";
    }

    /**
     * Checks that the posted analysis Parameters are all defined and valid.
     * @param array $postedParameters The array of posted parameters.
     * @return bool True if all Parameters are defined and valid, false
     * otherwise.
     */
    public function checkPostedAnalysisParameters(array $postedParameters)
    {

        if (count($postedParameters) == 0) {
            $this->message = '';
            return False;
        }

        $db = new DatabaseConnection;
        $maxChanCnt = $db->getMaxChanCnt();

        $this->message = '';
        $noErrorsFound = True;

        $parameter = $this->parameter("ColocAnalysis");
        $parameter->setValue($postedParameters["ColocAnalysis"]);
        $this->set($parameter);

        if ($parameter->value() == False) {
            return $noErrorsFound;
        }

        // At least two channels must be selected.
        if (!isset($postedParameters["ColocChannel"])
            || $postedParameters["ColocChannel"] == ""
        ) {
            $this->message = "Please indicate the channels (at least two) for ";
            $this->message .= "colocalization analysis.";
            return False;
        } else {
            $parameter = $this->parameter("ColocChannel");
            $parameter->setValue($postedParameters["ColocChannel"]);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                return False;
            }
        }

        // At least one coefficient must be selected.
        if (!isset($postedParameters["ColocCoefficient"])
            || $postedParameters["ColocCoefficient"] == ""
        ) {
            $this->message = "Please indicate the coefficients for ";
            $this->message .= "colocalization analysis.";
            return False;
        } else {
            $parameter = $this->parameter("ColocCoefficient");
            $parameter->setValue($postedParameters["ColocCoefficient"]);
            $this->set($parameter);
        }

        // Colocalization threshold mode
        if (!isset($postedParameters["ColocThresholdMode"]) ||
            $postedParameters["ColocThresholdMode"] == ''
        ) {
            $this->message = 'Please choose a colocalization threshold mode!';
            $noErrorsFound = False;
        } else {
            $value = array_fill(0, $maxChanCnt, null);
            switch ($postedParameters["ColocThresholdMode"]) {
                case 'auto':

                    $value[0] = 'auto';
                    break;

                case 'manual' :

                    for ($i = 0; $i < $maxChanCnt; $i++) {
                        $value[$i] = null;
                        $name = "ColocThreshold$i";
                        if (isset($postedParameters[$name])) {
                            $value[$i] = $postedParameters[$name];
                        }
                    }
                    break;

                default :
                    $this->message = 'Unknown colocalization threshold mode!';
                    $noErrorsFound = False;
            }

            $parameter = $this->parameter("ColocThreshold");
            $parameter->setValue($value);
            $this->set($parameter);
            if (!$parameter->check()) {
                $this->message = $parameter->message();
                $noErrorsFound = False;
            }
        }

        $parameter = $this->parameter("ColocMap");
        $parameter->setValue($postedParameters["ColocMap"]);
        $this->set($parameter);

        return $noErrorsFound;
    }

    /**
     * Returns the number of channels of the Setting
     * @return int The number of channels for the Setting.
     */
    public function numberOfChannels()
    {
        return $this->numberOfChannels;
    }

    /**
     * Displays the setting as a text containing Parameter names and their values.
     * @param int $numberOfChannels Number of channels (ignored).
     * @param string|null $micrType Microscope type (ignored).
     * @return string Parameter names and their values as a string.
     */
    public function displayString($numberOfChannels = 0, $micrType = NULL)
    {
        $result = '';

        $colocAnalysis = $this->parameter("ColocAnalysis")->value();
        foreach ($this->parameter as $parameter) {

            /** @var Parameter $parameter */
            if ($parameter->name() != "ColocAnalysis" &&
                $colocAnalysis == False) {
                continue;
            }

            /** @var ColocAnalysis $parameter */
            $result = $result .
                $parameter->displayString($this->numberOfChannels());
        }
        return $result;
    }


    /**
     * Get the list of templates shared with the given user.
     * @param string $username Name of the user to query for.
     * @return array List of shared templates with the user.
     */
    public static function getTemplatesSharedWith($username)
    {
        $db = new DatabaseConnection();
        $result = $db->getTemplatesSharedWith($username, self::sharedTable());
        return $result;
    }

    /**
     * Get the list of templates shared by the given user.
     * @param string $username Name of the user to query for.
     * @return array List of shared templates by the user.
     */
    public static function getTemplatesSharedBy($username)
    {
        $db = new DatabaseConnection();
        $result = $db->getTemplatesSharedBy($username, self::sharedTable());
        return $result;
    }

}

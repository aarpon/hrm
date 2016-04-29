<?php
/**
 * Stats
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm;

require_once dirname(__FILE__) . '/bootstrap.inc.php';


require_once("User.inc.php");
require_once("Database.inc.php");
require_once("Util.inc.php");

/**
 * Class Type
 * @package hrm
 *
 * Type of display to be generated from the statistics
 *
 * This class is used by the Stats class.
 */
class Type
{

    /**
     * Name of the report.
     * @var string
     */
    public $m_Name;

    /**
     * Name of the column to target in the statistics table.
     * @var string
     */
    public $m_Variable;

    /**
     * Descriptive name for the report.
     * @var string
     */
    public $m_DescriptiveName;

    /**
     * Type of report: one of 'text', 'piechart', 'linechart', 'dumptable'.
     * @var string
     */
    public $m_Type;

    /**
     * Makes a particular report accessible only to the admin.
     * @var boolean
     */
    public $m_AdminOnly;

    /**
     * Type constructor: : sets the relevant information for the report.
     * @param string $name Name of the report.
     * @param string $variable Name of the column to target in the statistics
     *    table.
     * @param string $descriptiveName Descriptive name for the report.
     * @param string $type Type of report: one of 'text', 'piechart', 'linechart',
     *    'dumptable'.
     * @param boolean $adminOnly Specifies whether the report is accessible only
     *    to the admin user.s
     */
    public function __construct($name, $variable, $descriptiveName, $type,
                                $adminOnly)
    {
        $this->m_Name = $name;
        $this->m_Variable = $variable;
        $this->m_DescriptiveName = $descriptiveName;
        $this->m_Type = $type;
        $this->m_AdminOnly = $adminOnly;
    }

    /**
     * Returns true is the requested statistics generates a graph,
     * or false if it returns a text/table.
     * @return boolean True for graphs, false for text/table.
     */
    public function isGraph()
    {
        if (($this->m_Type == "piechart") || ($this->m_Type == "linechart")) {
            return true;
        }
        return false;
    }

    /**
     * Get the JS/PHP script to display the statistics.
     * @param \hrm\DatabaseConnection $db DatabaseConnection object.
     * @param boolean $admin True if the user is the admin.
     * @param string $fromDate Start date for filtering in the form "YYYY-mm-dd"
     *    (or generally "YYYY-mm-dd hh:mm:ss").
     * @param string $toDate End date for filtering in the form "YYYY-mm-dd"
     *    (or generally "YYYY-mm-dd hh:mm:ss").
     * @param string $group Group name.
     * @param string $username User name.
     * @return string JS/PHP script to display the statistics.
     */
    public function getStatisticsScript(DatabaseConnection $db, $admin,
                                        $fromDate, $toDate, $group, $username)
    {
        // Date filters
        $dateFilter = $this->getDateFilter($fromDate, $toDate);

        // Group filters (admin only)
        $groupFilter = $this->getGroupFilter($admin, $group);

        // Non-admin users can only access their stats
        $userNameFilter = $this->getUsernameFilter($admin, $username);

        switch ($this->m_Type) {
            case "text":
                $script = $this->getTable($db, $this->m_Name, $group,
                    $dateFilter, $groupFilter, $userNameFilter);
                break;
            case "piechart":
                $script = $this->getPieChart($db, $this->m_Variable, $group,
                    $dateFilter, $groupFilter, $userNameFilter);
                break;
            case "linechart":
                // @todo: Check whether this is correct!
                $script = "";
                break;
            default:
                $script = "Error: bad statistics type!";
        }
        return $script;
    }

    /* -------------------------- PRIVATE METHODS --------------------------- */


    /**
     * Get the JS script to create a pie chart of the requested column
     * (variable) from the statistics table.
     * @param \hrm\DatabaseConnection $db DatabaseConnection object.
     * @param string $variable Name of the column to target in the statistics
     *    table.
     * @param string $group Group name.
     * @param string $dateFilter Filter on date, @see getDateFilter().
     * @param string $groupFilter Filter on group, @see getGroupFilter().
     * @param string $userNameFilter Filter on username, @see getUsernameFilter().
     * @return string The JS script to generate the pie chart.
     */
    private function getPieChart(DatabaseConnection $db, $variable, $group,
                                 $dateFilter, $groupFilter, $userNameFilter)
    {

        if (strstr($this->m_Name, 'Coloc')) {
            $colocFilter = " AND ColocAnalysis = '1' ";
        } else {
            $colocFilter = " ";
        }

        // Get data
        // -------------------------------------------------------------------------
        $row = $db->execute("SELECT COUNT( id ) FROM statistics WHERE " .
            $dateFilter . $groupFilter . $userNameFilter .
            $colocFilter . ";")->FetchRow();
        $numJobs = $row[0];
        if ($numJobs == 0) {
            $data = "[]";
            $title = "Nothing to display!";
            $subtitle = "";
        } else {
            $entities = $db->execute("SELECT DISTINCT( " . $variable . ") " .
                "FROM statistics WHERE " . $dateFilter .
                $groupFilter . $userNameFilter .
                $colocFilter . ";");

            $row = $db->execute("SELECT COUNT( DISTINCT( " . $variable . " ) ) " .
                "FROM statistics WHERE " . $dateFilter .
                $groupFilter . $userNameFilter . $colocFilter .
                ";")->FetchRow();

            $numEntities = $row[0];
            $data = "[";

            for ($i = 0; $i < $numEntities; $i++) {

                // Get current username
                $row = $entities->FetchRow();
                $variableName = $row[0];
                $row = $db->execute("SELECT COUNT(id) FROM statistics WHERE " .
                    $variable . " = '" . $variableName . "' AND " .
                    $dateFilter . $groupFilter . $userNameFilter .
                    $colocFilter . ";")->FetchRow();

                $numUserJobs = $row[0];
                $percent = 100 * $numUserJobs / $numJobs;
                $percent = number_format($percent, 2);
                if ($i < ($numEntities - 1)) {
                    $data .= "['" . $variableName . "', " . $percent . " ], ";
                } else {
                    $data .= "['" . $variableName . "', " . $percent . " ] ]";
                }
            }

            // Title
            $title = $this->m_DescriptiveName;

            // Assemble also subtitle
            if ($group != "All groups") {
                $groupStr = " Group: " . $group . ".";
            } else {
                $groupStr = "";
            }
            $subtitle = "Total: " . $numJobs . " entries." . $groupStr;

        }

        // Create script
        // -------------------------------------------------------------------------
        $script = "$(document).ready(function() {
        var chart = new Highcharts.Chart({
        chart: { renderTo: 'statschart', margin: [50, 200, 60, 170] },
        title: { text: '" . $title . "'  },
        subtitle: { text: '" . $subtitle . "' },
        plotArea: { shadow: null, borderWidth: null, backgroundColor: null },
        tooltip: { formatter: function() { return '<b>'+ this.point.name +'</b>: '+ this.y +' %'; } },
        plotOptions: { pie: { dataLabels: { enabled: true,
                                            formatter: function() { if (this.y > 5) return this.point.name; },
                                            color: 'black',
                                            style: { font: '13px Trebuchet MS, Verdana, sans-serif' } } } },
	legend: { layout: 'vertical', style: { left: 'auto', bottom: 'auto', right: '50px', top: '100px' } },
        series: [{ type: 'pie', name: '" . $title . "', data: " . $data . " } ] });
		});
    ";
        return $script;
    }

    /**
     * Get the HTML script to create a table for the total run time per
     * user or total run time per group statistics
     * @param \hrm\DatabaseConnection $db DatabaseConnection object.
     * @param string $statsType Name of report to generate, one of
     *    'TotalRunTimePerUser' or 'TotalRunTimePerGroup'.
     * @param string $group Group name.
     * @param string $dateFilter Filter on date, @see  getDateFilter().
     * @param string $groupFilter Filter on group, @see  getGroupFilter().
     * @param string $userNameFilter Filter on username, @see  getUsernameFilter().
     * @return strign The html code for the total run time per user/group table.
     * @todo    remove $statsType
     */
    private function getTable(DatabaseConnection $db, $statsType, $group,
                              $dateFilter, $groupFilter, $userNameFilter)
    {

        switch ($statsType) {
            case "TotalRunTimePerUser":
                return ($this->getTotalRunTimePerUserTable(
                    $db, $statsType, $group, $dateFilter, $groupFilter,
                    $userNameFilter));

            case "TotalRunTimePerGroup":
                return ($this->getTotalRunTimePerGroupTable(
                    $db, $statsType, $group, $dateFilter, $groupFilter,
                    $userNameFilter));

            default:
                return "";
        }
    }

    /**
     * Get the HTML script to create a table for the total run time per user
     * statistics.
     * @param \hrm\DatabaseConnection $db DatabaseConnection object.
     * @param string $statsType This is not used, and is assumed to be 'TotalRunTimePerUser'.
     * @param string $group Group name.
     * @param string $dateFilter Filter on date, @see  getDateFilter().
     * @param string $groupFilter Filter on group, @see  getGroupFilter().
     * @param string $userNameFilter Filter on username, @see  getUsernameFilter().
     * @return string The html code for the total run time per user table.
     * @todo Remove $statsType
     */
    private function getTotalRunTimePerUserTable(DatabaseConnection $db,
                                                 $statsType, $group, $dateFilter,
                                                 $groupFilter, $userNameFilter)
    {

        $script = "<table>";

        // Get data
        // -------------------------------------------------------------------------
        $row = $db->execute("SELECT COUNT( id ) FROM statistics WHERE " .
            $dateFilter . $groupFilter . $userNameFilter . ";")->FetchRow();
        $numJobs = $row[0];
        if ($numJobs == 0) {

            $script = "<h3>Nothing to display!</h3>";

        } else {

            $script .= "<tr>
        <th>Group</th>
        <th>User</th>
        <th>Number of jobs</th>
        <th>Total runtime (s)</th>
        <th>Time per job (s)</th>
        </tr>";

            // Get all groups
            $queryGroup = "SELECT DISTINCT( research_group ) FROM statistics WHERE " .
                $dateFilter . $groupFilter . $userNameFilter . ";";
            $resGroup = $db->execute($queryGroup);

            while ($rowGroup = $resGroup->FetchRow()) {

                // Gel all user names for current group
                $queryUser = "SELECT DISTINCT( owner ) FROM statistics WHERE " .
                    $dateFilter . " AND research_group = '" . $rowGroup[0] .
                    "' " . $userNameFilter . ";";
                $resUser = $db->execute($queryUser);

                $userNum = 0;
                while ($rowUser = $resUser->FetchRow()) {

                    // Query all jobs for current user
                    $queryJobsUser = "SELECT start, stop FROM statistics WHERE owner = '" .
                        $rowUser[0] . "' AND " . $dateFilter . $groupFilter .
                        $userNameFilter . " ; ";
                    $resJobsUser = $db->execute($queryJobsUser);

                    $nJobs = 0;
                    $time = 0;
                    while ($rowJobsUser = $resJobsUser->FetchRow()) {
                        $time += strtotime($rowJobsUser["stop"]) -
                            strtotime($rowJobsUser["start"]);
                        $nJobs++;
                    }

                    $userNum++;
                    if ($userNum == 1) {
                        $groupEntry = $rowGroup[0];
                    } else {
                        $groupEntry = "&nbsp;";
                    }
                    $script .= "<tr>
              <td>" . $groupEntry . "</td>
              <td>" . $rowUser[0] . "</td>
              <td>" . $nJobs . "</td>
              <td>" . $time . "</td>
              <td>" . number_format($time / $nJobs, 2) . "</td></tr>";

                }

            }

        }
        $script .= "</table>";

        return $script;
    }

    /**
     * Get the HTML script to create a table for the total run time per group
     * statistics.
     * @param \hrm\DatabaseConnection $db DatabaseConnection object.
     * @param string $statsType This is not used, and is assumed to be
     * 'TotalRunTimePerUser'.
     * @param string $group Group name.
     * @param string $dateFilter Filter on date, @see  getDateFilter().
     * @param string $groupFilter Filter on group, @see  getGroupFilter().
     * @param string $userNameFilter Filter on username, @see  getUsernameFilter().
     * @return string The html code for the total run time per group table.
     * @todo Remove $statsType
     */
    private function getTotalRunTimePerGroupTable(DatabaseConnection $db,
                                                  $statsType, $group,
                                                  $dateFilter, $groupFilter,
                                                  $userNameFilter)
    {

        $script = "<table>";

        // Get data
        // -------------------------------------------------------------------------
        $row = $db->execute("SELECT COUNT( id ) FROM statistics WHERE " .
            $dateFilter . $groupFilter . $userNameFilter . ";")->FetchRow();
        $numJobs = $row[0];
        if ($numJobs == 0) {

            $script = "<h3>Nothing to display!</h3>";

        } else {

            $script .= "<tr>
        <th>Group</th>
        <th>Number of jobs</th>
        <th>Total runtime (s)</th>
        <th>Time per job (s)</th>
        </tr>";

            // Get all groups
            $queryGroup = "SELECT DISTINCT( research_group ) FROM statistics WHERE " .
                $dateFilter . $groupFilter . $userNameFilter . ";";
            $resGroup = $db->execute($queryGroup);

            while ($rowGroup = $resGroup->FetchRow()) {

                // Gel all user names for current group
                $queryTime = "SELECT start, stop FROM statistics WHERE " .
                    $dateFilter . " AND research_group = '" . $rowGroup[0] .
                    "' " . $userNameFilter . ";";
                $resTime = $db->execute($queryTime);

                $nJobs = 0;
                $time = 0;
                while ($rowTime = $resTime->FetchRow()) {

                    $time += strtotime($rowTime["stop"]) -
                        strtotime($rowTime["start"]);
                    $nJobs++;

                }

                $script .= "<tr>
          <td>" . $rowGroup[0] . "</td>
          <td>" . $nJobs . "</td>
          <td>" . $time . "</td>
          <td>" . number_format($time / $nJobs, 2) . "</td></tr>";

            }

        }
        $script .= "</table>";

        return $script;
    }

    /**
     * Get the SQL sub-query to filter by date
     * @param string $fromDate Date to filter from.
     * @param string $toDate Date to filter to.
     * @return string SQL sub-query for filtering by date.
     */
    private function getDateFilter($fromDate, $toDate)
    {
        $dateFilter = "start >= '" . $fromDate . "' AND stop <= '" . $toDate . "'";
        return $dateFilter;
    }

    /**
     * Get the SQL sub-query to filter by group
     * @param boolean $admin True if the user is the admin.
     * @param string $group Group name.
     * @return string SQL sub-query for filtering by group.
     */
    private function getGroupFilter($admin, $group)
    {
        if ($admin) {
            if ($group == "All groups") {
                $groupFilter = "";
            } else {
                $groupFilter = " AND research_group = '" . $group . "'";
            }
        } else {
            $groupFilter = "";
        }
        return $groupFilter;
    }

    /**
     * Get the SQL sub-query to filter by username
     * @param boolean $admin True if the user is the admin.
     * @param string $username Username.
     * @return string SQL sub-query for filtering by username.
     */
    private function getUsernameFilter($admin, $username)
    {
        if ($admin) {
            $userNameFilter = "";
        } else {
            $userNameFilter = " AND owner = '" . $username . "'";
        }
        return $userNameFilter;
    }

}   // End of Type class

/*
	============================================================================
*/

/**
 * Class Stats
 * @package hrm
 *
 * Commodity class to generate statistics of HRM usage.
 */
class Stats
{

    /**
     * Name of the user for whom statistics are returned; if the user is
     * the admin user, global statistics are returned.
     * @var string
     */
    private $m_Username;

    /**
     * DatabaseConnection object.
     * @var DatabaseConnection
     */
    private $m_DB;

    /**
     * Array of Type objects containing all currently supported statistics.
     * @var array(Type)
     */
    private $m_Stats_Array;

    /**
     * Date to filter from.
     * @var string
     */
    private $m_Filter_FromDate;

    /**
     * Date to filter to.
     * @var string
     */
    private $m_Filter_ToDate;

    /**
     * Group to whom the user belongs.
     * @var string
     */
    private $m_Filter_Group;

    /**
     * The currently selected statistics.
     * @var string.
     */
    private $m_Selected_Statistics;


    /**
     * Constructs the Stats object.
     * @param string $username Name of the user for whom statistics are returned;
     * if the user is the admin user, global statistics are returned.
     */
    public function __construct($username)
    {
        $this->m_Username = $username;
        $this->m_DB = new DatabaseConnection();
        $this->m_Filter_FromDate = $this->getFromDate();
        $this->m_Filter_ToDate = $this->getToDate();
        $this->m_Filter_Group = "All groups";
        // Now create the statistics array
        $this->fillStatsArray();
        // Set default (accessible) statistics
        $this->setDefaultStats();
    }

    /**
     * Returns true is the requested statistics generates a graph,
     * and false if it returns a text/table
     * @return boolean True for graphs, false for text/table.
     */
    public function isGraph()
    {
        return ($this->m_Stats_Array[$this->m_Selected_Statistics]->isGraph());
    }

    /**
     * Returns an array with the descriptive names of all supported
     * statistics (e.g. to be used in a < select > element).
     * @return array Array of descriptive names of supported statistics.
     */
    public function getAllDescriptiveNames()
    {
        $names = array();
        for ($i = 0; $i < count($this->m_Stats_Array); $i++) {
            if ((!$this->isAdmin()) && $this->m_Stats_Array[$i]->m_AdminOnly) {
                continue;
            }
            $names[] = $this->m_Stats_Array[$i]->m_DescriptiveName;
        }
        return $names;
    }

    /**
     * Returns the descriptive name of the selected statistics.
     * @return string The descriptive name of the selected statistics.
     */
    public function getSelectedStatistics()
    {
        return ($this->m_Stats_Array[$this->m_Selected_Statistics]->m_DescriptiveName);
    }

    /**
     * Sets the selected statistics.
     *
     * If the input statistics name does not exist, the first statistics is
     * selected (i.e. the one with index 0).
     * @param string $descriptiveName Descriptive name of the selected statistics.
     */
    public function setSelectedStatistics($descriptiveName)
    {
        for ($i = 0; $i < count($this->m_Stats_Array); $i++) {
            if ($this->m_Stats_Array[$i]->m_DescriptiveName == $descriptiveName) {
                $this->m_Selected_Statistics = $i;
                return;
            }
        }

        // If no match with the descriptive name, set selected statistics to 0.
        $this->m_Selected_Statistics = 0;
    }

    /**
     * Get the JS/PHP script to display the statistics.
     *
     * This function call should be combined with a isGraph() call, to decide
     * whether the generated (JS) script should be passed on to the HighCharts
     * library or not.
     * @return string JS/PHP script to display the statistics.
     */
    public function getStatistics()
    {

        switch ($this->m_Stats_Array[$this->m_Selected_Statistics]->m_Type) {

            case "dumptable":

                // Download the whole statistics table
                return ($this->dumpStatsTableToFile());

            case "piechart":
            case "text":

                // Create JS script (HighCharts) or HTML Table
                return ($this->m_Stats_Array[$this->m_Selected_Statistics]->getStatisticsScript(
                    $this->m_DB, $this->isAdmin(), $this->m_Filter_FromDate,
                    $this->m_Filter_ToDate, $this->m_Filter_Group, $this->m_Username));

            default:
                return "Error: bad value from Statistics type. Please report!\n";
        }

    }

    /**
     * Returns the date of the first implementation of statistics in HRM
     * @return string 2010-02-01.
     */
    public function getFromDate()
    {
        return "2010-02-01";
    }

    /**
     * Gets the end of current month
     * @return  string Date in the format (YYYY-MM-DD).
     */
    public function getToDate()
    {
        $year = date("Y");
        $month = date("m") + 1;

        if ($month > 12) {
            $month -= 12;
            $year++;
        }

        // End of month
        return date("Y-m-d", strtotime($year . "-" . $month . "-01 - 1 day"));
    }

    /**
     * Gets an array of unique group names from the statistics table.
     * @return array Array of all unique group names.
     */
    public function getGroupNames()
    {

        $groupNames = array("All groups");
        $row = $this->m_DB->execute("SELECT COUNT( DISTINCT( research_group ) ) FROM statistics;")->FetchRow();
        $numGroups = $row[0];
        if ($numGroups == 0) {
            return $groupNames;
        }
        // List the group names
        $res = $this->m_DB->execute("SELECT DISTINCT( research_group ) FROM statistics;");
        $counter = 1;
        for ($i = 0; $i < $numGroups; $i++) {
            $row = $res->FetchRow();
            if ($row[0] != "") {
                $groupNames[$counter++] = $row[0];
            }
        }
        return $groupNames;
    }

    /**
     * Sets the 'from date' for the date filter.
     * @param string $fromDate 'From' date for the date filter.
     */
    public function setFromDateFilter($fromDate)
    {
        $this->m_Filter_FromDate = $fromDate;
    }

    /**
     * Sets the 'to date' for the date filter.
     * @param string $toDate 'To' date for the date filter.
     */
    public function setToDateFilter($toDate)
    {
        $this->m_Filter_ToDate = $toDate;
    }

    /**
     * Sets the group name for the group filter.
     * @param string $group Group name for the group filter.
     */
    public function setGroupFilter($group)
    {
        $this->m_Filter_Group = $group;
    }

    /* -------------------------- PRIVATE METHODS --------------------------- */

    /**
     * Sets the index of the first acceptable statistics.
     *
     * For the admin it is the very first, since there are no limitations for
     * the admin; for the user it is the first allowed one.
     */
    private function setDefaultStats()
    {
        if ($this->isAdmin()) {
            $this->m_Selected_Statistics = 0;
            return;
        }

        for ($i = 0; $i < count($this->m_Stats_Array); $i++) {
            if ((!$this->isAdmin()) && (!($this->m_Stats_Array[$i]->m_AdminOnly))) {
                $this->m_Selected_Statistics = $i;
                return;
            }
        }
    }

    /**
     * Creates the array of all supported statistics types.
     */
    private function fillStatsArray()
    {
        // Make sure to clear
        $this->m_Stats_Array = array();

        // Some alias...
        $admin = true;
        $user = false;

        $this->m_Stats_Array[] = new Type("JobsPerUser",
            "owner",
            "Number of jobs per user (%)",
            "piechart",
            $admin);
        $this->m_Stats_Array[] = new Type("JobsPerGroup",
            "research_group",
            "Number of jobs per group (%)",
            "piechart",
            $admin);
        $this->m_Stats_Array[] = new Type("TotalColocRunsPerUser",
            "owner",
            "Number of colocalization runs per user (%)",
            "piechart",
            $admin);
        $this->m_Stats_Array[] = new Type("TotalColocRunsPerGroup",
            "research_group",
            "Number of colocalization runs per group (%)",
            "piechart",
            $admin);
        $this->m_Stats_Array[] = new Type("ImageFileFormat",
            "ImageFileFormat",
            "Input file format (%)",
            "piechart",
            $user);
        $this->m_Stats_Array[] = new Type("OutputFileFormat",
            "OutputFileFormat",
            "Output file format (%)",
            "piechart",
            $user);
        $this->m_Stats_Array[] = new Type("PointSpreadFunction",
            "PointSpreadFunction",
            "Type of Point-Spread Function used (%)",
            "piechart",
            $user);
        $this->m_Stats_Array[] = new Type("MicroscopeType",
            "MicroscopeType",
            "Microscope type (%)",
            "piechart",
            $user);
        $this->m_Stats_Array[] = new Type("TotalRunTimePerUser",
            "time",
            "Total run time per user",
            "text",
            $user);
        $this->m_Stats_Array[] = new Type("TotalRunTimePerGroup",
            "time",
            "Total run time per group",
            "text",
            $admin);
        $this->m_Stats_Array[] = new Type("DumpTable",
            "",
            "Export all statistics to file",
            "dumptable",
            $admin);
    }

    /**
     * Compares the passed username to the admin user name and returns true
     * if the user is the admin.
     * @return boolean True if the user is the admin user, false otherwise.
     */
    private function isAdmin()
    {
        return ($this->m_Username == User::getAdminName());
    }

    /**
     * Dumps the statistics table to file and serves it for download.
     * @todo Check: it could be that more than just the table is dumped!
     */
    private function dumpStatsTableToFile()
    {
        // Make sure that the script doesn't timeout.
        set_time_limit(0);

        // Is there something to dump?
        $row = $this->m_DB->execute("SELECT COUNT( id ) FROM statistics;")->FetchRow();
        $numJobs = $row[0];
        if ($numJobs == 0) {
            return "<h3>Nothing to export!</h3>";
        }

        // Get the data from the statistics table
        $res = $this->m_DB->execute("SELECT * FROM statistics;");
        if ($res) {

            // Open a temporary file
            $fileName = "stats_dump_" . date("Y-m-d_H-i-s") . ".csv";
            $fullFileName = "/tmp/" . $fileName;
            $fileHandle = fopen($fullFileName, 'w+');
            if ($fileHandle == 0) {
                return "<h3>Error: could not open file.</h3>";
            }

            // Export header
            $header = "Job id, Owner, Group, Start time, End time, Input format, " .
                "Output format, PSF type, Microscope, Coloc run\n";

            fwrite($fileHandle, $header);

            // Now export the data
            while ($row = $res->FetchRow()) {

                $currentRow =
                    $row["id"] . ", " .
                    $row["owner"] . ", " .
                    $row["research_group"] . ", " .
                    $row["start"] . ", " .
                    $row["stop"] . ", " .
                    $row["imagefileformat"] . ", " .
                    $row["outputfileformat"] . ", " .
                    $row["pointspreadfunction"] . ", " .
                    $row["microscopetype"] . ", " .
                    $row["colocanalysis"] . "\n";

                fwrite($fileHandle, $currentRow);

            }

            // Close the file
            fclose($fileHandle);
        }

        // Now serve the file
        $size = filesize($fullFileName);
        $type = "Content-Type: text/plain";
        $dlname = $fileName;

        if ($size) {
            header("Accept-Ranges: bytes");
            header("Connection: close");
            header("Content-Disposition-type: attachment");
            header("Content-Disposition: attachment; filename=\"$dlname\"");
            header("Content-Length: $size");
            header("Content-Type: $type; name=\"$dlname\"");
            ob_clean();
            flush();
            readfile_chunked($fullFileName);
            unlink($fullFileName);
            return "";
        } else {
            return ("<h3>Error serving the file " . $fileName . ".</h3>");
        }
        return ("<h3>Nothing to download!</h3>");;
    }

}  // End of Stats class

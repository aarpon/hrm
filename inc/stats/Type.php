<?php
/**
 * Type
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\stats;
use hrm\DatabaseConnection;

/**
 * Type of display to be generated from the statistics.
 *
 * This class is used by the Stats class.
 *
 * @package hrm
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
     * @param DatabaseConnection $db DatabaseConnection object.
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
     * @param DatabaseConnection $db DatabaseConnection object.
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
     * @return string The html code for the total run time per user/group table.
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

}
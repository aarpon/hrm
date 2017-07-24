<?php
/**
 * Stats
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\stats;

use hrm\DatabaseConnection;
use hrm\user\mngm\UserManager;
use hrm\user\UserV2;
use hrm\Util;

require_once dirname(__FILE__) . '/../bootstrap.php';

/**
 * Commodity class to generate statistics of HRM usage.
 *
 * @package hrm
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
        $user = new UserV2();
        $user->setName($this->m_Username);
        return $user->isAdmin();
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
                Util::readfile_chunked($fullFileName);
                unlink($fullFileName);
                return "";
            } else {
                return ("<h3>Error serving the file " . $fileName . ".</h3>");
            }
        }
        return "<h3>No statistics to report.</h3>";
    }

}

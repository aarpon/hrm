<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once( "User.inc.php" );
require_once( "Database.inc.php" );
require_once( "Util.inc.php" );

/*!
 \class	Type
 \brief	Type of display to be generated from the statistics

 This class is used by the Stats class.
 */
class Type {

  /*!
   \var	$m_Name
   \brief	Name of the report
   */
  public $m_Name;

  /*!
   \var	$m_Variable
   \brief	Name of the column to target in the statistics table
   */
  public $m_Variable;

  /*!
   \var	$m_DescriptiveName
   \brief	Descriptive name for the report
   */
  public $m_DescriptiveName;

  /*!
   \var	$m_Type
   \brief	Type of report: one of 'text', 'piechart', 'linechart', 'dumptable'
   */
  public $m_Type;

  /*!
   \var	$m_AdminOnly
   \brief	Makes a particular report accessible only to the admin
   */
  public $m_AdminOnly;

  /*!
  \brief	Constructor: sets the relevant information for the report
  \param	$name				Name of the report
  \param	$variable			Name of the column to target in the statistics table
  \param	$descriptiveName	Descriptive name for the report
  \param	$type				Type of report: one of 'text', 'piechart', 'linechart', 'dumptable'
  \param	$adminOnly			Specifies whether the report is accessible only to the admin user
  */
  public function __construct( $name, $variable, $descriptiveName, $type, $adminOnly ) {
    $this->m_Name            = $name;
    $this->m_Variable        = $variable;
    $this->m_DescriptiveName = $descriptiveName;
    $this->m_Type            = $type;
    $this->m_AdminOnly       = $adminOnly;
  }

  /*!
  \brief	returns true is the requested statistics generates a graph,
			and false if it returns a text/table
  \return   true for graphs, false for text/table
  */
  public function isGraph( ) {
    if ( ( $this->m_Type == "piechart" ) || ( $this->m_Type == "linechart" ) ) {
      return true;
    }
    return false;
  }

  /*!
	\brief	Get the JS/PHP script to display the statistics.
	\param	$db			DatabaseConnection object
	\param	$admin		True if the user is the admin
	\param	$fromDate	Start date for filtering in the form "YYYY-mm-dd" (or generally "YYYY-mm-dd hh:mm:ss")
  	\param	$toDate    	End date for filtering in the form "YYYY-mm-dd" (or generally "YYYY-mm-dd hh:mm:ss")
  	\param  $group		Group name
  	\param	$username	User name
  	\return      string JS/PHP script to display the statistics.
  	*/
  public function getStatisticsScript( DatabaseConnection $db, $admin, $fromDate, $toDate, $group, $username ) {

    // Date filters
    $dateFilter = $this->getDateFilter( $fromDate, $toDate );

    // Group filters (admin only)
    $groupFilter = $this->getGroupFilter( $admin, $group );

    // Non-admin users can only access their stats
    $userNameFilter = $this->getUsernameFilter( $admin, $username );

    switch ( $this->m_Type ) {
      case "text":
        $script = $this->getTable( $db, $this->m_Name, $group, $dateFilter, $groupFilter, $userNameFilter );
        break;
      case "piechart":
        $script = $this->getPieChart( $db, $this->m_Variable, $group, $dateFilter, $groupFilter, $userNameFilter );
        break;
      case "linechart":
        break;
      default:
        $script = "Error: bad statistics type!";
    }
    return $script;
  }

  /* ===========================================================================
   *
   * PRIVATE METHODS
   *
   ========================================================================== */


  /*!
	\brief	Get the JS script to create a pie chart of the requested column
			(variable) from the statistics table.
	\param  $db				DatabaseConnection object
	\param	$variable		Name of the column to target in the statistics table
  	\param  $group			Group name
	\param	$dateFilter		Filter on date, \see getDateFilter()
	\param	$groupFilter	Filter on group, \see getGroupFilter()
  	\param	$userNameFilter	Filter on username, \see getUsernameFilter()
	\return the JS script to generate the pie chart.
	*/
  private function getPieChart( DatabaseConnection $db, $variable, $group, $dateFilter, $groupFilter, $userNameFilter ) {

    // Get data
    // -------------------------------------------------------------------------
    $row      = $db->execute( "SELECT COUNT( id ) FROM statistics WHERE " . $dateFilter . $groupFilter . $userNameFilter . ";" )->FetchRow( );
    $numJobs  = $row[ 0 ];
    if ( $numJobs == 0 ) {

      $data     = "[]";
      $title    = "Nothing to display!";
      $subTitle = "";

    } else {

      $entities    = $db->execute( "SELECT DISTINCT( " . $variable . ") FROM statistics WHERE " . $dateFilter . $groupFilter . $userNameFilter . ";" );
      $row      = $db->execute( "SELECT COUNT( DISTINCT( " . $variable . " ) ) FROM statistics WHERE " . $dateFilter . $groupFilter . $userNameFilter . ";" )->FetchRow( );
      $numEntities = $row[ 0 ];
      $data = "[";

      for ( $i = 0; $i < $numEntities; $i++ ) {
        // Get current username
        $row = $entities->FetchRow( );
        $variableName = $row[ 0 ];
        $row = $db->execute( "SELECT COUNT(id) FROM statistics WHERE " . $variable . " = '" . $variableName . "' AND " . $dateFilter . $groupFilter . $userNameFilter . ";" )->FetchRow( );
        $numUserJobs = $row[ 0 ];
        $percent = 100 * $numUserJobs / $numJobs;
        $percent = number_format($percent, 2);
        if ( $i < ( $numEntities - 1 ) ) {
          $data .= "['" . $variableName . "', " . $percent . " ], ";
        } else {
          $data .= "['" . $variableName . "', " . $percent . " ] ]";
        }
      }

      // Title
      $title = $this->m_DescriptiveName;

      // Assemble also subtitle
      if ( $group != "All groups" ) {
        $groupStr = " Group: " . $group . "." ;
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

  /*!
	\brief	Get the HTML script to create a table for the total run time per
            user or total run time per group statistics
	\param  $db				DatabaseConnection object
	\param	$statsType		Name of report to generate, one of 'TotalRunTimePerUser' or 'TotalRunTimePerGroup'
  	\param  $group			Group name
	\param	$dateFilter		Filter on date, \see getDateFilter()
	\param	$groupFilter	Filter on group, \see getGroupFilter()
  	\param	$userNameFilter	Filter on username, \see getUsernameFilter()
	\return the html code for the total run time per user/group table
	\todo	remove $statsType
	*/
  private function getTable( DatabaseConnection $db, $statsType, $group, $dateFilter, $groupFilter, $userNameFilter ) {

    switch ( $statsType ) {
      case "TotalRunTimePerUser":
        return ( $this->getTotalRunTimePerUserTable(
        $db, $statsType, $group, $dateFilter, $groupFilter, $userNameFilter ) );

      case "TotalRunTimePerGroup":
        return ( $this->getTotalRunTimePerGroupTable(
        $db, $statsType, $group, $dateFilter, $groupFilter, $userNameFilter ) );

      default:
        return "";
    }
  }

  /*!
	\brief	Get the HTML script to create a table for the total run time per user statistics
	\param  $db				DatabaseConnection object
	\param	$statsType		This is not used, and is assumed to be 'TotalRunTimePerUser'
  	\param  $group			Group name
	\param	$dateFilter		Filter on date, \see getDateFilter()
	\param	$groupFilter	Filter on group, \see getGroupFilter()
  	\param	$userNameFilter	Filter on username, \see getUsernameFilter()
	\return the html code for the total run time per user table
	\todo	remove $statsType
	*/
    private function getTotalRunTimePerUserTable( DatabaseConnection $db, $statsType, $group, $dateFilter, $groupFilter, $userNameFilter ) {

    $script="<table>";

    // Get data
    // -------------------------------------------------------------------------
    $row      = $db->execute( "SELECT COUNT( id ) FROM statistics WHERE " . $dateFilter . $groupFilter . $userNameFilter . ";" )->FetchRow( );
    $numJobs  = $row[ 0 ];
    if ( $numJobs == 0 ) {

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
      $queryGroup = "SELECT DISTINCT( research_group ) FROM statistics WHERE " . $dateFilter . $groupFilter . $userNameFilter . ";";
      $resGroup = $db->execute( $queryGroup );

      while ( $rowGroup = $resGroup->FetchRow( ) ) {

        // Gel all user names for current group
        $queryUser = "SELECT DISTINCT( owner ) FROM statistics WHERE " . $dateFilter . " AND research_group = '" . $rowGroup[ 0 ] . "' " . $userNameFilter . ";";
        $resUser = $db->execute( $queryUser );

        $userNum = 0;
        while ( $rowUser = $resUser->FetchRow( ) ) {

          // Query all jobs for current user
          $queryJobsUser = "SELECT start, stop FROM statistics WHERE owner = '" . $rowUser[ 0 ] . "' AND " . $dateFilter . $groupFilter . $userNameFilter ." ; ";
          $resJobsUser = $db->execute( $queryJobsUser );

          $nJobs = 0; $time = 0;
          while ( $rowJobsUser = $resJobsUser->FetchRow( ) ) {
            $time += strtotime( $rowJobsUser["stop"] ) - strtotime( $rowJobsUser["start"] );
            $nJobs++;
          }

          $userNum++;
          if ( $userNum == 1 ) {
            $groupEntry = $rowGroup[ 0 ];
          } else {
            $groupEntry = $nbsp;
          }
          $script .= "<tr>
              <td>" . $groupEntry . "</td>
              <td>" . $rowUser[ 0 ] . "</td>
              <td>" . $nJobs . "</td>
              <td>" . $time . "</td>
              <td>" . number_format( $time/$nJobs, 2 ) . "</td></tr>";

        }

      }

    }
    $script .= "</table>";

    return $script;
  }

	/*!
	\brief	Get the HTML script to create a table for the total run time per group statistics
	\param  $db				DatabaseConnection object
	\param	$statsType		This is not used, and is assumed to be 'TotalRunTimePerUser'
  	\param  $group			Group name
	\param	$dateFilter		Filter on date, \see getDateFilter()
	\param	$groupFilter	Filter on group, \see getGroupFilter()
  	\param	$userNameFilter	Filter on username, \see getUsernameFilter()
	\return the html code for the total run time per group table
	\todo	remove $statsType
	*/
  private function getTotalRunTimePerGroupTable( DatabaseConnection $db, $statsType, $group, $dateFilter, $groupFilter, $userNameFilter ) {

    $script="<table>";

    // Get data
    // -------------------------------------------------------------------------
    $row      = $db->execute( "SELECT COUNT( id ) FROM statistics WHERE " . $dateFilter . $groupFilter . $userNameFilter . ";" )->FetchRow( );
    $numJobs  = $row[ 0 ];
    if ( $numJobs == 0 ) {

      $script = "<h3>Nothing to display!</h3>";

    } else {

      $script .= "<tr>
        <th>Group</th>
        <th>Number of jobs</th>
        <th>Total runtime (s)</th>
        <th>Time per job (s)</th>
        </tr>";

      // Get all groups
      $queryGroup = "SELECT DISTINCT( research_group ) FROM statistics WHERE " . $dateFilter . $groupFilter . $userNameFilter . ";";
      $resGroup = $db->execute( $queryGroup );

      while ( $rowGroup = $resGroup->FetchRow( ) ) {

        // Gel all user names for current group
        $queryTime = "SELECT start, stop FROM statistics WHERE " . $dateFilter . " AND research_group = '" . $rowGroup[ 0 ] . "' " . $userNameFilter . ";";
        $resTime = $db->execute( $queryTime );

        $nJobs = 0; $time = 0;
        while ( $rowTime = $resTime->FetchRow( ) ) {

          $time += strtotime( $rowTime["stop"] ) - strtotime( $rowTime["start"] );
          $nJobs++;

        }

        $script .= "<tr>
          <td>" . $rowGroup[ 0 ] . "</td>
          <td>" . $nJobs . "</td>
          <td>" . $time . "</td>
          <td>" . number_format( $time/$nJobs, 2 ) . "</td></tr>";

      }

    }
    $script .= "</table>";

    return $script;
  }

  /*!
	\brief  Get the SQL sub-query to filter by date
  	\param  $fromDate	Ddate to filter from
  	\param  $toDate		Date to filter to
  	\return	SQL sub-query for filtering by date
  */
  private function getDateFilter( $fromDate, $toDate ) {
    $dateFilter = "start >= '" . $fromDate ."' AND stop <= '" . $toDate . "'";
    return $dateFilter;
  }

  /*!
	\brief  Get the SQL sub-query to filter by group
  	\param  $admin	True if the user is the admin
  	\param  $group	Group name
  	\return	SQL sub-query for filtering by group
  */
  private function getGroupFilter( $admin, $group ) {
    if ( $admin ) {
      if ( $group == "All groups" ) {
        $groupFilter = "";
      } else {
        $groupFilter = " AND research_group = '" . $group . "'";
      }
    } else {
      $groupFilter = "";
    }
    return $groupFilter;
  }

  /*!
	\brief  Get the SQL sub-query to filter by username
  	\param  $admin		True if the user is the admin
  	\param  $username	Username
  	\return	SQL sub-query for filtering by username
  */
  private function getUsernameFilter( $admin, $username ) {
    if ( $admin ) {
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

/*!
 \class    Stats
 \brief    Commodity class to generate statistics of HRM usage
*/
class Stats {

  /* ===========================================================================
   *
   * MEMBER VARIABLES
   *
   ========================================================================== */

  /*!
   \var		$m_Username
   \brief	Name of the user for whom statistics are returned; if the user is
   			the admin user, global statistics are returned
   */
  private $m_Username;

  /*!
   \var		$m_DB
   \brief	DatabaseConnection object
   */
  private $m_DB;

  /*!
   \var		$m_Stats_Array
   \brief	Array of Type objects containing all currently supported statistics
   */
  private $m_Stats_Array;

  /*!
   \var		$m_Filter_FromDate
   \brief	Date to filter from
   */
  private $m_Filter_FromDate;

    /*!
   \var		$m_Filter_ToDate
   \brief	Date to filter to
   */
  private $m_Filter_ToDate;

    /*!
   \var		$m_Filter_Group
   \brief	Group to whom the user belongs
   */
  private $m_Filter_Group;

    /*!
   \var		$m_Selected_Statistics
   \brief	The currently selected statistics
   */
  private $m_Selected_Statistics;


  /* ===========================================================================
   *
   * PUBLIC METHODS
   *
   ========================================================================== */

  /*!
	\brief	Constructs the Stats object.
	\param  $username Name of the user for whom statistics are returned;
						if the user is the admin user, global statistics
						are returned.
  */
  public function __construct( $username ) {
    $this->m_Username = $username;
    $this->m_DB = new DatabaseConnection();
    $this->m_Filter_FromDate = $this->getFromDate( );
    $this->m_Filter_ToDate = $this->getToDate( );
    $this->m_Filter_Group = "All groups";
    // Now create the statistics array
    $this->fillStatsArray( );
    // Set default (accessible) statistics
    $this->setDefaultStats();
  }

  /*!
	\brief	returns true is the requested statistics generates a graph,
			and false if it returns a text/table
	\return true for graphs, false for text/table
	*/
  public function isGraph( ) {
    return ( $this->m_Stats_Array[ $this->m_Selected_Statistics ]->isGraph( ) );
  }

  /*!
	\brief	returns an array with the descriptive names of all supported
			statistics (e.g. to be used in a < select > element)
	\return array of descriptive names of supported statistics
	*/
  public function getAllDescriptiveNames( ) {
    $names = array( );
    for ( $i = 0; $i < count( $this->m_Stats_Array ); $i++ ) {
      if ( ( !$this->isAdmin( ) ) && $this->m_Stats_Array[ $i ]->m_AdminOnly ) {
        continue;
      }
      $names[ ] = $this->m_Stats_Array[ $i ]->m_DescriptiveName;
    }
    return $names;
  }

	/*!
	\brief	returns the descriptive name of the selected statistics
	\return	the descriptive name of the selected statistics
	*/
  public function getSelectedStatistics( ) {
    return ( $this->m_Stats_Array[ $this->m_Selected_Statistics ]->m_DescriptiveName );
  }

	/*!
	\brief	sets the selected statistics
	\param	$descriptiveName	Descriptive name of the selected statistics
	\return the index in $m_Stats_Array of the selected statistics, or
			0 if the statistics does not exist
	*/
    public function setSelectedStatistics( $descriptiveName ) {
    for ( $i = 0; $i < count( $this->m_Stats_Array ); $i++ ) {
      if ( $this->m_Stats_Array[ $i ]->m_DescriptiveName == $descriptiveName ) {
        $this->m_Selected_Statistics = $i;
        return;
      }
    }
    // If no match with the descriptive name, set selected statistics to 0.
    $this->m_Selected_Statistics = 0;
  }

	/*!
	\brief	Get the JS/PHP script to display the statistics. This function
			call should be combined with a isGraph() call, to decide
			whether the generated (JS) script should be passed on to the
			HighCharts library or not.
	\return	string JS/PHP script to display the statistics.
	*/
    public function getStatistics( ) {

    switch ( $this->m_Stats_Array[$this->m_Selected_Statistics]->m_Type ) {

      case "dumptable":

        // Download the whole statistics table
        return ( $this->dumpStatsTableToFile() );

      case "piechart":
      case "text":

        // Create JS script (HighCharts) or HTML Table
        return ( $this->m_Stats_Array[$this->m_Selected_Statistics]->getStatisticsScript(
        $this->m_DB, $this->isAdmin(), $this->m_Filter_FromDate,
        $this->m_Filter_ToDate, $this->m_Filter_Group, $this->m_Username ) );

      default:
        return "Error: bad value from Statistics type. Please report!\n";
    }

  }

	/*!
	\brief	Returns the date of the first implementation of statistics in HRM
	\return	date (2010-02-01)
	*/
  public function getFromDate( ) {

    return "2010-02-01";

  }

	/*!
	\brief	Gets the end of current month
	\return	date (YYYY-MM-DD)
	*/
  public function getToDate( ) {

    $year  = date( "Y" );
    $month = date( "m" ) + 1;

    if ( $month > 12 ) {
      $month -= 12;
      $year++;
    }

    // End of month
    return date( "Y-m-d", strtotime( $year . "-" . $month . "-01 - 1 day" ) );

  }

	/*!
	\brief	Gets an array of unique group names from the statistics table
	\return	array of all unique group names
	*/
  public function getGroupNames( ) {

    $groupNames = array( "All groups" );
    $row        = $this->m_DB->execute( "SELECT COUNT( DISTINCT( research_group ) ) FROM statistics;" )->FetchRow( );
    $numGroups  = $row[ 0 ];
    if ( $numGroups == 0 ) {
      return $groupNames;
    }
    // List the group names
    $res = $this->m_DB->execute( "SELECT DISTINCT( research_group ) FROM statistics;" );
    $counter = 1;
    for ( $i = 0; $i < $numGroups; $i++ ) {
      $row = $res->FetchRow( );
      $groupNames[ $counter++ ] = $row[ 0 ];
    }
    return $groupNames;
  }

  /*!
 	\brief	Sets the 'from date' for the date filter
 	\param	$fromDate	'From' date for the date filter
 	*/
  public function setFromDateFilter( $fromDate ) {
    $this->m_Filter_FromDate = $fromDate;
  }

  /*!
 	\brief	Sets the 'to date' for the date filter
 	\param	$toDate	'To' date for the date filter
 	*/
    public function setToDateFilter( $toDate ) {
    $this->m_Filter_ToDate = $toDate;
  }

  /*!
 	\brief	Sets the group name for the group filter
 	\param	$group	Group name for the group filter
 	*/
  public function setGroupFilter( $group ) {
    $this->m_Filter_Group = $group;
  }

  /* ===========================================================================
   *
   * PRIVATE METHODS
   *
   ========================================================================== */

  /*!
 	\brief	Sets the index of the first acceptable statistics for the user
 			(the very first for the admin since there are no limitations for the admin)
 */
  private function setDefaultStats(  ) {
    if ( $this->isAdmin( ) ) {
      $this->m_Selected_Statistics = 0;
      return;
    }

    for ( $i = 0; $i < count( $this->m_Stats_Array ); $i++ ) {
      if ( ( !$this->isAdmin() ) && ( !( $this->m_Stats_Array[ $i ]->m_AdminOnly ) ) ) {
        $this->m_Selected_Statistics = $i;
        return;
      }
    }
  }

  /*!
 	\brief	Creates the array of all supported statistics types.
 */
  private function fillStatsArray(  ) {
    // Make sure to clear
    $this->m_Stats_Array = array();

    // Some alias...
    $admin = true;
    $user  = false;

    $this->m_Stats_Array[] = new Type(
      "JobsPerUser",  "owner", "Number of jobs per user (%)", "piechart", $admin );
    $this->m_Stats_Array[] = new Type(
      "JobsPerGroup", "research_group", "Number of jobs per group (%)", "piechart", $admin );
    $this->m_Stats_Array[] = new Type(
      "ImageFileFormat", "ImageFileFormat", "Input file format (%)", "piechart", $user );
    $this->m_Stats_Array[] = new Type(
      "OutputFileFormat", "OutputFileFormat", "Output file format (%)", "piechart", $user );
    $this->m_Stats_Array[] = new Type(
      "PointSpreadFunction", "PointSpreadFunction", "Type of Point-Spread Function used (%)", "piechart", $user );
    $this->m_Stats_Array[] = new Type(
      "ImageGeometry", "ImageGeometry", "Image geometry (%)", "piechart", $user );
    $this->m_Stats_Array[] = new Type(
      "MicroscopeType", "MicroscopeType", "Microscope type (%)", "piechart", $user );
    $this->m_Stats_Array[] = new Type(
      "TotalRunTimePerUser", "time", "Total run time per user", "text", $user );
    $this->m_Stats_Array[] = new Type(
      "TotalRunTimePerGroup", "time", "Total run time per group", "text", $admin );
    $this->m_Stats_Array[] = new Type(
      "DumpTable", "", "Export all statistics to file", "dumptable", $admin );
  }

  /*!
	\brief	Compares the passed username to the admin user name and returns true
			 if the user is the admin.
	\return	True if the user is the admin user, false otherwise.
  */
  private function isAdmin(  ) {
    $user = new User();
    return ( $this->m_Username == $user->getAdminName() );
  }

  /*!
	\brief	 Dumps the statistics table to file and "downloads" it
	\todo	Check: it could be that more than just the table is dumped!
  */
  private function dumpStatsTableToFile( ) {
    // Make sure that the script doesn't timeout.
    set_time_limit(0);

    // Is there something to dump?
    $row  = $this->m_DB->execute( "SELECT COUNT( id ) FROM statistics;" )->FetchRow( );
    $numJobs  = $row[ 0 ];
    if ( $numJobs == 0 ) {
      return "<h3>Nothing to export!</h3>";
    }

    // Get the data from the statistics table
    $res = $this->m_DB->execute( "SELECT * FROM statistics;" );
    if ( $res ) {

      // Open a temporary file
      $fileName = "stats_dump_" . date( "Y-m-d_H-i-s" ) . ".txt";
      $fullFileName = "/tmp/" .$fileName;
      $fileHandle = fopen( $fullFileName, 'w+' );
      if ( $fileHandle == 0 ) {
        return "<h3>Error: could not open file.</h3>";
      }

      // Now export the data
      while ( $row = $res->FetchRow( ) ) {

        $currentRow =
        $row[ "id" ] . "\t" .
        $row[ "owner" ] . "\t" .
        $row[ "research_group" ] . "\t" .
        $row[ "start" ] . "\t" .
        $row[ "stop" ] . "\t" .
        $row[ "ImageFileFormat" ] . "\t" .
        $row[ "OutputFileFormat" ] . "\t" .
        $row[ "PointSpreadFunction" ] . "\t" .
        $row[ "ImageGeometry" ] . "\t" .
        $row[ "MicroscopeType" ] . "\n";

        fwrite( $fileHandle, $currentRow );

      }

      // Close the file
      fclose( $fileHandle );
    }

    // Now serve the file
    $size = filesize( $fullFileName );
    $type = "Content-Type: text/plain";
    $dlname = $fileName;

    if ($size) {
      header ("Accept-Ranges: bytes");
      header ("Connection: close");
      header ("Content-Disposition-type: attachment");
      header ("Content-Disposition: attachment; filename=\"$dlname\"");
      header ("Content-Length: $size");
      header ("Content-Type: $type; name=\"$dlname\"");
      ob_clean();
      flush();
      readfile_chunked($fullFileName);
      unlink($fullFileName);
      return "";
    } else {
      return ( "<h3>Error serving the file " . $fileName . ".</h3>" );
    }
    return ( "<h3>Nothing to download!</h3>" );;
  }

}  // End of Stats class

?>

<?php
// Module Mail.inc

// This file is part of huygens remote manager.

// Copyright: Montpellier RIO Imaging (CNRS) 

// contributors : 
// 	     Pierre Travo	(concept)	     
// 	     Volker Baecker	(concept, implementation)

// email:
// 	pierre.travo@crbm.cnrs.fr
// 	volker.baecker@crbm.cnrs.fr

// Web:     www.mri.cnrs.fr

// huygens remote manager is a software that has been developed at 
// Montpellier Rio Imaging (mri) in 2004 by Pierre Travo and Volker 
// Baecker. It allows running image restoration jobs that are processed 
// by 'Huygens professional' from SVI. Users can create and manage parameter 
// settings, apply them to multiple images and start image processing 
// jobs from a web interface. A queue manager component is responsible for 
// the creation and the distribution of the jobs and for informing the user 
// when jobs finished.

// This software is governed by the CeCILL license under French law and
// abiding by the rules of distribution of free software. You can use, 
// modify and/ or redistribute the software under the terms of the CeCILL
// license as circulated by CEA, CNRS and INRIA at the following URL
// "http://www.cecill.info". 

// As a counterpart to the access to the source code and  rights to copy,
// modify and redistribute granted by the license, users are provided only
// with a limited warranty and the software's author, the holder of the
// economic rights, and the successive licensors  have only limited
// liability. 

// In this respect, the user's attention is drawn to the risks associated
// with loading, using, modifying and/or developing or reproducing the
// software by the user in light of its specific status of free software,
// that may mean that it is complicated to manipulate, and that also
// therefore means that it is reserved for developers and experienced
// professionals having in-depth IT knowledge. Users are therefore encouraged
// to load and test the software's suitability as regards their requirements
// in conditions enabling the security of their systems and/or data to be
// ensured and, more generally, to use and operate it in the same conditions
// as regards security. 

// The fact that you are presently reading this means that you have had
// knowledge of the CeCILL license and that you accept its terms.

require_once "inc/User.inc";
require_once "inc/Database.inc";

Class Type {
  public $m_Name;
  public $m_Variable;
  public $m_DescriptiveName;
  public $m_Type;        // One of 'text', 'piechart', 'linechart'
  public $m_AdminOnly;
  
  //!---------------------------------------------------------------------------
  // @function    __construct( $name, $variable, $descriptiveName, $type, $adminOnly )
  //!---------------------------------------------------------------------------
  function __construct( $name, $variable, $descriptiveName, $type, $adminOnly ) {
    $this->m_Name            = $name;
    $this->m_Variable        = $variable;
    $this->m_DescriptiveName = $descriptiveName;
    $this->m_Type            = $type;
    $this->m_AdminOnly       = $adminOnly;
  }
  
  //!---------------------------------------------------------------------------
  // @function    string Type::isGraph( $variable )
  // @desc        returns true is the requested statistics generates a graph,
  //              and false if it returns a text/table
  // @param       string $variable name of the column from the statistics page
  // @return      bool True for graphs, False for text/table
  //!---------------------------------------------------------------------------
  public function isGraph( ) {
    if ( ( $this->m_Type == "piechart" ) || ( $this->m_Type == "linechart" ) ) {
      return true;
    }
    return false;
  }
  
  //!---------------------------------------------------------------------------
  // @function    string Type::getStatisticsScript( $dateFilter, $groupFilter, $userNameFilter )
  // @desc        Get the JS/PHP script to display the statistics.
  // $param       $dateFilter SQL query to filter by date
  // $param       $groupFilter  SQL query to filter by group
  // $param       $userNameFilter  SQL query to filter by user name
  // @return      string JS/PHP script to display the statistics. 
  //!---------------------------------------------------------------------------
  public function getStatisticsScript( $db, $admin, $fromDate, $toDate, $group, $userName ) {
    
        // Date filters
    $dateFilter = "start >= '" . $fromDate ."' AND stop <= '" . $toDate . "'";

    // Group filters (admin only)
    if ( $admin ) {
      if ( $group == "All groups" ) {
        $groupFilter = "";
      } else {
        $groupFilter = " AND research_group = '" . $group . "'";
      }
    } else {
      $groupFilter = "";
    }
    
    // Non-admin users can only access their stats
    if ( $admin ) {
      $userNameFilter = "";
    } else {
      $userNameFilter = " AND owner = '" . $userName . "'";
    }

    switch ( $this->m_Type ) {
      case "text":
        $script = "Not implemented yet!";
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

  //!---------------------------------------------------------------------------
  // @function    string Stats::getPieChart( $variable )
  // @desc        Get the JS script to create a pie chart of the requested
  //              column from the statistics table.
  // @param       string $variable name of the column from the statistics page
  //              for which a pie chart is requested.
  // @return      string JS script to generate the pie chart.
  //!---------------------------------------------------------------------------
  private function getPieChart( $db, $variable, $group, $dateFilter, $groupFilter, $userNameFilter ) {

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
				chart: {
					renderTo: 'statschart',
					margin: [50, 200, 60, 170]
				},
				title: {
					text: '" . $title . "'
				},
                subtitle: {
                  text: '" . $subtitle . "'
                },
				plotArea: {
					shadow: null,
					borderWidth: null,
					backgroundColor: null
				},
				tooltip: {
					formatter: function() {
						return '<b>'+ this.point.name +'</b>: '+ this.y +' %';
					}
				},
				plotOptions: {
					pie: {
						dataLabels: {
							enabled: true,
							formatter: function() {
								if (this.y > 5) return this.point.name;
							},
							color: 'black',
							style: {
								font: '13px Trebuchet MS, Verdana, sans-serif',
							},
						}
					}
				},
				legend: {
					layout: 'vertical',
					style: {
						left: 'auto',
						bottom: 'auto',
						right: '50px',
						top: '100px'
					}
				}, series: [{
					type: 'pie',
					name: '" . $title . "', data: " . $data . "}] });
		});
    ";
    return $script;
  }

}


// =============================================================================
// =============================================================================
// =============================================================================


Class Stats {
  
  /* ===========================================================================
   *
   * MEMBER VARIABLES
   *
   ========================================================================== */

  private $m_Username;
  private $m_DB;
  private $m_Stats_Array;
  private $m_Filter_FromDate;
  private $m_Filter_ToDate;
  private $m_Filter_Group;
  private $m_Selected_Statistics;

  /* ===========================================================================
   *
   * PUBLIC METHODS
   *
   ========================================================================== */
   
  //!---------------------------------------------------------
  // @function    Stats::__construct( $username ): constructor.
  // @desc        Constructs the Stats object.
  // @param       string $username name of the user for which statistics are
  //              returned; if the user is the admin user, global statistics
  //              are returned.
  //!---------------------------------------------------------
  public function __construct( $username ) {
    $this->m_Username = $username;
    $this->m_DB = new DatabaseConnection();
    $fromDates = $this->getFromDates( );
    $this->m_Filter_FromDate = $fromDates[ 0 ];
    $toDates = $this->getToDates( );
    $this->m_Filter_ToDate = $toDates[ count( $toDates ) - 1 ];
    $this->m_Filter_Group = "All groups";
    // Now create the statistics array
    $this->fillStatsArray( );
    // Default statistics is the first added
    $this->m_Selected_Statistics = 0;
  }
  
  //!---------------------------------------------------------------------------
  // @function    string Stats::isGraph( $variable )
  // @desc        returns true is the requested statistics generates a graph,
  //              and false if it returns a text/table
  // @param       string $variable name of the column from the statistics page
  // @return      bool True for graphs, False for text/table
  //!---------------------------------------------------------------------------
  public function isGraph( ) {
    return ( $this->m_Stats_Array[ $this->m_Selected_Statistics ]->isGraph( ) );
  }

  //!---------------------------------------------------------------------------
  // @function    string Stats::getAllDescriptiveNames( )
  // @desc        returns an array with the descriptive names of all supported
  //              statistics (e.g. to be used in a <select> element)
  // @return      array of strings Descriptive names of supported statistics
  //!---------------------------------------------------------------------------
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

  //!---------------------------------------------------------------------------
  // @function    void Stats::setSelectedStatistics( $descriptiveName )
  // @desc        sets the selected statistics
  // $param       $descriptiveName Descriptive name of the selected statistics
  //!---------------------------------------------------------------------------
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
  
  //!---------------------------------------------------------------------------
  // @function    string Stats::getStatisticsScript( )
  // @desc        Get the JS/PHP script to display the statistics. This function
  //              call should be combined with a isGraph() call, to decide
  //              whether the generated (JS) script should be passed on to the
  //              HighCharts library or not.
  // @return      string JS/PHP script to display the statistics.
  //!---------------------------------------------------------------------------
  public function getStatisticsScript( ) {
    
    // Create script
    return $this->m_Stats_Array[$this->m_Selected_Statistics]->getStatisticsScript(
      $this->m_DB, $this->isAdmin(), $this->m_Filter_FromDate,
      $this->m_Filter_ToDate, $this->m_Filter_Group, $this->m_Username );
  }
   
  //!---------------------------------------------------------------------------
  // @function    array Stats::getFromDates( )
  // @desc        Gets all beginning of the months from February 2010 until now
  // @return      array All beginning of monthts
  //!---------------------------------------------------------------------------
  public function getFromDates( ) {
      
      $fromDates = array( );
      
      // Today
      $today = date( "Y-m-d" );
      
      // Start date
      $fromDates[ 0 ] = "2010-02-01";
      
      $nextMonth = date( "Y-m-d", strtotime( $fromDates[ 0 ] . " + 1 month" ) );
      
      $counter = 1;
      while ( strtotime( $nextMonth ) <= strtotime( $today ) ) {
        $fromDates[ $counter ] = $nextMonth;
        $nextMonth = date( "Y-m-d", strtotime( $fromDates[ $counter++ ] . " + 1 month" ) );
      }
      
      return $fromDates;
  }

  //!---------------------------------------------------------------------------
  // @function    array Stats::getToDates( )
  // @desc        Gets all end of the months from February 2010 until now
  // @return      array All ends of monthts
  //!---------------------------------------------------------------------------
  public function getToDates( ) {
      
      $toDates = array( );
      
      // Today
      $today =  date( "Y-m-d", strtotime( " + 1 month" ) );


      // Start date
      $toDates[ 0 ] = "2010-02-28";

      // Make sure to get the end of the month
      $year  = date( "Y", strtotime( $toDates[ 0 ] ) );
      $month = date( "m", strtotime( $toDates[ 0 ] ) );
      $nextMonth = date( "Y-m-d", strtotime( $year . "-" . ( $month + 2 ) . "-01 - 1 day" ) );
      
      $counter = 1;
      while ( strtotime( $nextMonth ) <= strtotime( $today ) ) {
        $toDates[ $counter ] = $nextMonth;
        $year  = date( "Y", strtotime( $toDates[ $counter] ) );
        $month = date( "m", strtotime( $toDates[ $counter ] ) ) + 2;
        if ( $month > 12 ) {
          $month -= 12;
          $year++;
        }
        
        $nextMonth = date( "Y-m-d", strtotime( $year . "-" . $month . "-01 - 1 day" ) );
        $counter++;
      }
      
      return $toDates;
  }

  //!---------------------------------------------------------------------------
  // @function    array Stats::getGroupNames( )
  // @desc        Gets an array of unique group names from the statistics table
  // @return      array of all unique group names
  //!---------------------------------------------------------------------------
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

  //!---------------------------------------------------------------------------
  // @function    void Stats::setFromDateFilter( )
  // @desc        Set the from date filter
  //!---------------------------------------------------------------------------
  public function setFromDateFilter( $fromDate ) {
      $this->m_Filter_FromDate = $fromDate;
  }

  //!---------------------------------------------------------------------------
  // @function    void Stats::setToDateFilter( )
  // @desc        Set the to date filter
  //!---------------------------------------------------------------------------
  public function setToDateFilter( $toDate ) {
      $this->m_Filter_ToDate = $toDate;
  }

  //!---------------------------------------------------------------------------
  // @function    void Stats::setGroupFilter( )
  // @desc        Set the group filter
  //!---------------------------------------------------------------------------
  public function setGroupFilter( $group ) {
      $this->m_Filter_Group = $group;
  }
  
  /* ===========================================================================
   *
   * PRIVATE METHODS
   *
   ========================================================================== */

  //!---------------------------------------------------------------------------
  // @function    bool Stats::fill( )
  // @desc        Creates the array of statistics types.
  //!---------------------------------------------------------------------------
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
  }
  
  //!---------------------------------------------------------------------------
  // @function    bool Stats::isAdmin( )
  // @desc        Compares the passed username to the admin user name and
  //              returns true if the user is the admin.
  // @return      bool True if the user is the admin user.
  //!---------------------------------------------------------------------------
  private function isAdmin(  ) {
    $user = new User();
    return ( $this->m_Username == $user->getAdminName() );
  }
  
}

?>

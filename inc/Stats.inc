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

Class Stats {
  
  /* ===========================================================================
   *
   * MEMBER VARIABLES
   *
   ========================================================================== */

  private $m_Username;
  private $m_DB;
  private $m_Filter_FromDate;
  private $m_Filter_ToDate;
  private $m_Filter_Group;

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
  }
  
  //!---------------------------------------------------------------------------
  // @function    string Stats::getPieChart( $variable )
  // @desc        Get the JS script to create a pie chart of the requested
  //              column from the statistics table.
  // @param       string $variable name of the column from the statistics page
  //              for which a pie chart is requested.
  // @return      string JS script to generate the pie chart.
  //!---------------------------------------------------------------------------
  public function getPieChart( $variable ) {
    // Create script
    return ( $this->generateDataForPieChart( $variable ) );
   }

  //!---------------------------------------------------------------------------
  // @function    array Stats::getPieChartVariables( )
  // @desc        Get an array of possible variables (columns) from the
  //              statistics table that can be plotted as a pie chart.
  // @return      array Possible variables for a pie chart.
  //!---------------------------------------------------------------------------
  public function getPieChartVariables( ) {
    if ( $this->isAdmin() ) {
      return ( array( "owner", "research_group", "ImageFileFormat",
                       "OutputFileFormat", "PointSpreadFunction",
                       "ImageGeometry", "MicroscopeType" ) );
    } else {
      return ( array( "ImageFileFormat",
                       "OutputFileFormat", "PointSpreadFunction",
                       "ImageGeometry", "MicroscopeType" ) );
    }
  }

  //!---------------------------------------------------------------------------
  // @function    array Stats::getPieChartVariables( )
  // @desc        Get an array of possible variables (columns) from the
  //              statistics table that can be plotted as a pie chart.
  // @return      array Possible variables for a pie chart.
  //!---------------------------------------------------------------------------
  public function getPieChartStatistics( ) {
    if ( $this->isAdmin() ) {
      return ( array( "owner"               => "Number of jobs per user (%)",
                    "research_group"      => "Number of jobs per group (%)",
                    "ImageFileFormat"     => "Input file format (%)",
                    "OutputFileFormat"    => "Output file format (%)",
                    "PointSpreadFunction" => "Type of Point-Spread Function used (%)",
                    "ImageGeometry"       => "Image geometry (%)",
                    "MicroscopeType"      => "Microscope type (%)" ) );
    } else {
      return ( array( "ImageFileFormat"     => "Input file format (%)",
                    "OutputFileFormat"    => "Output file format (%)",
                    "PointSpreadFunction" => "Type of Point-Spread Function used (%)",
                    "ImageGeometry"       => "Image geometry (%)",
                    "MicroscopeType"      => "Microscope type (%)" ) );
    }
  }

  //!---------------------------------------------------------------------------
  // @function    array Stats::getStartTimes( )
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
  // @function    array Stats::getEndTimes( )
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
  // @function    bool Stats::isAdmin( )
  // @desc        Compares the passed username to the admin user name and
  //              returns true if the user is the admin.
  // @return      bool True if the user is the admin user.
  //!---------------------------------------------------------------------------
  private function isAdmin(  ) {
    $user = new User();
    return ( $this->m_Username == $user->getAdminName() );
  }

  //!---------------------------------------------------------------------------
  // @function    string Stats::generateDataForPieChart( $variable )
  // @desc        Generates the data (string) from the statistics table to
  //              feed into pieChart( ), the function that creates the complete
  //              JS script. 
  // $param       string $variable Column name from the statistics table for
  //              which a pie chart is requested; $variable is one of:
  //                               owner
  //                               research_group
  //                               ImageFileFormat
  //                               OutputFileFormat
  //                               PointSpreadFunction
  //                               ImageGeometry
  //                               MicroscopeType
  // @return      string JS script used to generate the requested pie chart.
  //!---------------------------------------------------------------------------
  private function generateDataForPieChart( $variable ) {
    $titles = $this->getPieChartStatistics( );
    $title = $titles[ $variable ];

    // Date filters
    $dateFilter = "start >= '" . $this->m_Filter_FromDate ."' AND stop <= '" .
      $this->m_Filter_ToDate . "'";

    // Group filters (admin only)
    if ( $this->isAdmin() ) {
      if ( $this->m_Filter_Group == "All groups" ) {
        $groupFilter = "";
      } else {
        $groupFilter = " AND research_group = '" . $this->m_Filter_Group . "'";
      }
    } else {
      $groupFilter = "";
    }
    
    // Non-admin users can only access their stats
    if ( $this->isAdmin() ) {
      $userNameFilter = "";
    } else {
      $userNameFilter = " AND owner = '" . $this->m_Username . "'";
    }
    
    // Create data
    $row      = $this->m_DB->execute( "SELECT COUNT( id ) FROM statistics WHERE " . $dateFilter . $groupFilter . $userNameFilter . ";" )->FetchRow( );
    $numJobs  = $row[ 0 ];
    if ( $numJobs == 0 ) {
      return ( $this->pieChart( "[]", "Nothing to display!", "" ) );
    }
    $entities    = $this->m_DB->execute( "SELECT DISTINCT( " . $variable . ") FROM statistics WHERE " . $dateFilter . $groupFilter . $userNameFilter . ";" );
    $row      = $this->m_DB->execute( "SELECT COUNT( DISTINCT( " . $variable . " ) ) FROM statistics WHERE " . $dateFilter . $groupFilter . $userNameFilter . ";" )->FetchRow( );
    $numEntities = $row[ 0 ];
    $data = "[";
    
    for ( $i = 0; $i < $numEntities; $i++ ) {
      // Get current username
      $row = $entities->FetchRow( );
      $variableName = $row[ 0 ];
	  $row = $this->m_DB->execute( "SELECT COUNT(id) FROM statistics WHERE " . $variable . " = '" . $variableName . "' AND " . $dateFilter . $groupFilter . $userNameFilter . ";" )->FetchRow( );
	  $numUserJobs = $row[ 0 ];
      $percent = 100 * $numUserJobs / $numJobs;
      $percent = number_format($percent, 2);
      if ( $i < ( $numEntities - 1 ) ) {
        $data .= "['" . $variableName . "', " . $percent . " ], ";
      } else {
        $data .= "['" . $variableName . "', " . $percent . " ] ]";
      }
    }
    
    // Assemble also subtitle
    if ( $this->m_Filter_Group != "All groups" ) {
      $groupStr = " Group: " . $this->m_Filter_Group . "." ;
    } else {
      $groupStr = "";
    }
    $subtitle = "Total: " . $numJobs . " entries." . $groupStr;
    
    // Create script
    return ( $this->pieChart( $data, $title, $subtitle ) );
  }

  //!---------------------------------------------------------------------------
  // @function    string Stats::pieChart( $data, $title, $subtitle )
  // @desc        Generates the complete JS script for the requested pie chart
  //              from guven data string $data and title $title. 
  // $param       string $data Data string generated by generateDataForPieChart( ).
  // $param       string $title Title of the pie chart.
  // $param       string $subtitle Subtitle of the pie chart.
  // @return      string Complete JS script used to generate the requested pie chart.
  //!---------------------------------------------------------------------------
  private function pieChart( $data, $title, $subtitle ) {
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

  //!---------------------------------------------------------------------------
  // @function    array Stats::getLastMonths( $n )
  // @desc        Generates an array with the dates of the beginning and the 
  //              end of all of the last $n months. Useful to query a database
  //              for entries that lie within a time range.
  // $param       integer $n Number of months for which to return the start and
  //              end dates.
  // @return      array of arrays of all the dates of the last $n months
  //!---------------------------------------------------------------------------
  private function getLastMonths( $n ) {
    // Get the dates of the first day of the month for the last 12 months
    $lastMonths = array( );
    $counter = 0;
    for ( $i = 11; $i >= 0; $i-- ) {
      $fromDate = date( "Y-m-d",
            strtotime( date( "Y-m" ) . "-01 -" . $i . " months" ) );
      $toDate = date( "Y-m-d",
            strtotime( date( "Y-m" ) . "-01 -" . ( $i - 1 ). " months -1 day" ) );
      $lastMonths[ $counter++ ] = array( "from" => $fromDate, "to" => $toDate );
    }
  }
  
}

?>

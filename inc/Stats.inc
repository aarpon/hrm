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

  var $m_Username;
  var $m_DB;



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
  }
  
  //!---------------------------------------------------------------------------
  // @function    string Stats::getPieChart( $entity )
  // @desc        Get the JS script to create a pie chart of the requested
  //              column from the statistics table.
  // @param       string $entity name of the column from the statistics page
  //              for which a pie chart is requested.
  // @return      string JS script to generate the pie chart.
  //!---------------------------------------------------------------------------
  public function getPieChart( $entity ) {
    // Create script
    return ( $this->generateDataForPieChart( $entity ) );
   }



  /* ===========================================================================
   *
   * PRIVATE METHODS
   *
   ========================================================================== */
  
  //!---------------------------------------------------------------------------
  // @function    bool Stats::isAdminMode( )
  // @desc        Compares the passed username to the admin user name and
  //              returns true if the user is the admin.
  // @return      bool True if the user is the admin user.
  //!---------------------------------------------------------------------------
  private function isAdminMode(  ) {
    $user = new User();
    return ( $this->m_Username == $user->getAdminName() );
  }

  //!---------------------------------------------------------------------------
  // @function    string Stats::generateDataForPieChart( $entity )
  // @desc        Generates the data (string) from the statistics table to
  //              feed into pieChart( ), the function that creates the complete
  //              JS script. 
  // $param       string Column name from the statistics table for which a pie
  //              chart is requested; $entity is one of:
  //                               owner
  //                               research_group
  //                               ImageFileFormat
  //                               OutputFileFormat
  //                               PointSpreadFunction
  //                               ImageGeometry
  //                               MicroscopeType
  // @return      string JS script used to generate the requested pie chart.
  //!---------------------------------------------------------------------------
  private function generateDataForPieChart( $entity ) {
    switch ( $entity ) {
      case 'owner':
        $title = "Number of jobs per user (%)";
        break;
      case 'research_group':
        $title = "Number of jobs per group (%)";
        break;
      case 'ImageFileFormat':
        $title = "Input file format (%)";
        break;
      case 'OutputFileFormat':
        $title = "Output file format (%)";
        break;
      case 'PointSpreadFunction':
        $title = "Type of Point-Spread Function used (%)";
        break;
      case 'ImageGeometry':
        $title = "Image geometry (%)";
        break;
      case 'MicroscopeType':
        $title = "Microscope type (%)";
        break;
      default:
        error_log( "Unknown column for the statistics table!");
        exit( 1 );
    }
  
    // Create data
    $row      = $this->m_DB->execute( "SELECT COUNT( id ) FROM statistics;" )->FetchRow( );
    $numJobs  = $row[ 0 ];
    $entities    = $this->m_DB->execute( "SELECT DISTINCT( " . $entity . ") FROM statistics;" );
    $row      = $this->m_DB->execute( "SELECT COUNT( DISTINCT( " . $entity . " ) ) FROM statistics;" )->FetchRow( );
    $numEntities = $row[ 0 ];
    $data = "[";
    
    for ( $i = 0; $i < $numEntities; $i++ ) {
      // Get current username
      $row = $entities->FetchRow( );
      $entityName = $row[ 0 ];
      $query = "SELECT COUNT(id) FROM statistics WHERE " . $entity . " = '" . $entityName . "';";
	  $row = $this->m_DB->execute( $query )->FetchRow( );
	  $numUserJobs = $row[ 0 ];
      $percent = 100 * $numUserJobs / $numJobs;
      $percent = number_format($percent, 2);
      if ( $i < ( $numEntities - 1 ) ) {
        $data .= "['" . $entityName . "', " . $percent . " ], ";
      } else {
        $data .= "['" . $entityName . "', " . $percent . " ] ]";
      }
    }
    
    // Create script
    return ( $this->pieChart( $data, $title ) );
  }

  //!---------------------------------------------------------------------------
  // @function    string Stats::pieChart( $data, $title )
  // @desc        Generates the complete JS script for the requested pie chart
  //              from guven data string $data and title $title. 
  // $param       string $data Data string generated by generateDataForPieChart( ).
  // $param       string $title Title of the pie chart.
  // @return      string Complete JS script used to generate the requested pie chart.
  //!---------------------------------------------------------------------------
  private function pieChart( $data, $title ) {
    $script = "$(document).ready(function() {
			var chart = new Highcharts.Chart({
				chart: {
					renderTo: 'container',
					margin: [50, 200, 60, 170]
				},
				title: {
					text: '" . $title . "'
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
							color: 'white',
							style: {
								font: '13px Trebuchet MS, Verdana, sans-serif'
							}
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

?>

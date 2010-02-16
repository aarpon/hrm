<?php

// php page: select_images.php

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

require_once("./inc/User.inc");
require_once("./inc/Fileserver.inc");
require_once("./inc/Stats.inc");

session_start();

if (isset($_GET['home'])) {
  header("Location: " . "home.php"); exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

// Create a Stats object
$stats = new Stats( $_SESSION['user']->name() );

// Get a list of possible variable to be plotted
//$possibleVariables = $stats->getPieChartVariables( );

// Get a list of statistics names for the <select> element
$possibleStats = $stats->getAllDescriptiveNames( );

// Filters
$fromDates  = $stats->getFromDates();
$toDates    = $stats->getToDates();
$groupNames = $stats->getGroupNames();

// Was some statistics chosen?
if (isset($_POST["Statistics"] ) ) {
  $stats->setSelectedStatistics( $_POST["Statistics"] );
}

// Was some fromDate chosen?
if (isset($_POST["FromDate"] ) ) {
  $chosenFromDate = $_POST["FromDate"];
} else {
  $chosenFromDate = $fromDates[ 0 ];
}

// Was some toDate chosen?
if (isset($_POST["ToDate"] ) ) {
  $chosenToDate = $_POST["ToDate"];
} else {
  $chosenToDate = $toDates[ count( $toDates ) - 1 ];
}

// Was some Group chosen?
if (isset($_POST["Group"] ) ) {
  $chosenGroupName = $_POST["Group"];
} else {
  $chosenGroupName = $groupNames[ 0 ];
}

// Set the filters
$stats->setFromDateFilter( $chosenFromDate );
$stats->setToDateFilter( $chosenToDate );
$stats->setGroupFilter( $chosenGroupName );

// If the statistics is a graph, we display the generated javascript via the
// '$generatedScript' header inclusion; otherwhise, we get the (PHP) script into
// the differently-named variable $tableScript.
if ( $stats->isGraph( ) == true ) {
  $generatedScript = $stats->getStatistics( );
  $tableScript     = "";
} else {
  $generatedScript = "";
  $tableScript     = $stats->getStatistics( );
}

include("header.inc.php");

?>
    <div id="nav">
        <ul>
            <li><?php echo $_SESSION['user']->name(); ?></li>
            <li><a href="<?php echo getThisPageName();?>?home=home"><img src="images/home.png" alt="home" />&nbsp;Home</a></li>
            <li><a href="javascript:openWindow('')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
    </div>

    <!-- Here we put a select element for the user to choose which stats he wants to display -->
    <div id="stats">
      
      <form method="post" action="" id="displayStats">

        <fieldset>
          
          <legend>
            <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpStatistics')"><img src="images/help.png" alt="?" /></a>
            Statistics
          </legend>  
            
          <select name="Statistics" id="Statistics" size="1">
        
          <?php
        
          foreach ($possibleStats as $currentStats) {

            if ( $currentStats == $stats->getSelectedStatistics() ) {
              $selected = "selected=\"selected\"";
            } else {
              $selected = "";
            }
            
          ?>
            <option <?php echo $selected ?>><?php echo $currentStats ?></option>
        
          <?php
          }
          ?>
        
          </select>
    
          <div class="nowrap_noborder">
            
          <!-- Filter: from date -->
          <select name="FromDate" id="FromDate" size="1" style="width:49%">
        
          <?php
          foreach ($fromDates as $fromDate) {

            if ( $fromDate == $chosenFromDate ) {
              $selected = "selected=\"selected\"";
            } else {
              $selected = "";
            }
            
          ?>
            <option <?php echo $selected ?>><?php echo $fromDate ?></option>
        
          <?php
          }
          ?>
        
          </select>

          <!-- Filter: to date -->
          <select name="ToDate" id="ToDate" size="1" style="width:49%">
        
          <?php
          foreach ($toDates as $toDate ) {

            if ( $toDate == $chosenToDate ) {
              $selected = "selected=\"selected\"";
            } else {
              $selected = "";
            }
            
          ?>
            <option <?php echo $selected ?>><?php echo $toDate ?></option>
        
          <?php
          }
          ?>
        
          </select>
            
          </div>
    
          <!-- Filter: Group This is visible only for the admin user-->
          <?php
          if ( $_SESSION['user']->isAdmin() ) {
          ?>
          
          <select name="Group" id="Group" size="1">
        
          <?php
          foreach ($groupNames as $groupName ) {

            if ( $groupName == $chosenGroupName ) {
              $selected = "selected=\"selected\"";
            } else {
              $selected = "";
            }
            
          ?>
            <option <?php echo $selected ?>><?php echo $groupName ?></option>
        
          <?php
          }
          ?>
        
          </select>
          <?php
          }
          ?>
          
          <input type="submit" name="Submit" value="Go!" />

          </fieldset>
          
      </form>
        
    </div>
    
    <?php
      if ( $stats->isGraph( ) == true ) {
    ?>
      <!--  This is where the graph will be displayed -->
      <div id="statschart"></div>
    <?php
      } else {
    ?>          
      <div id="statstable"><?php echo $tableScript; ?></div>
    <?php
      }
    ?>

<?php

include("footer.inc.php");

?>

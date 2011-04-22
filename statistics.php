<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

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

// HighChart JavaScript library inclusions
$script = array(
      "highcharts/jquery.min.js",
      "highcharts/excanvas.compiled.js",
      "highcharts/highcharts.js" );

include("header.inc.php");

?>
    <div id="nav">
        <ul>
            <li><img src="images/user.png" alt="user" />&nbsp;<?php echo $_SESSION['user']->name(); ?></li>
            <li><a href="<?php echo getThisPageName();?>?home=home"><img src="images/home.png" alt="home" />&nbsp;Home</a></li>
            <li><a href="javascript:openWindow('http://www.svi.nl/HuygensRemoteManagerHelpStatistics')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
    </div>

    <!-- Here we put a select element for the user to choose which stats he wants to display -->
    <div id="stats">
      
      <form method="post" action="" id="displayStats">

        <fieldset>
          
          <legend>
            <a href="javascript:openWindow('http://www.svi.nl/HuygensRemoteManagerHelpStatistics')"><img src="images/help.png" alt="?" /></a>
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

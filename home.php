<?php

// php page: login.php

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
require_once("./inc/Database.inc"); 
require_once("./inc/CreditOwner.inc");
require_once("./inc/hrm_config.inc");
require_once("./inc/Fileserver.inc");
require_once("./inc/versions.inc");

global $email_admin;
global $enableUserAdmin;
global $use_accounting_system;
global $authenticateAgainst;

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

session_start();

if (isset($_GET['exited'])) {
  $_SESSION['user']->logout();
  session_unset();
  session_destroy();
  header("Location: " . "login.php"); exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

// Refresh the page every 10 seconds
$meta = "<meta http-equiv=\"refresh\" content=\"10\" />";
include("header.inc.php");

?>

    <div id="nav">
        <ul>
			<li><?php echo $_SESSION['user']->name(); ?></li>
			<li><a href="<?php echo getThisPageName();?>?exited=exited"><img src="images/exit.png" alt="exit" />&nbsp;Logout</a></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpHome')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li> 
        </ul>
    </div>
    
    <div id="homepage">

        <?php

        if ($_SESSION['user']->name() == $_SESSION['user']->getAdminName() ) {
        ?>
		
		  <table>
		  
		  <tbody>
			
			<tr >

			  <?php
			    if ($enableUserAdmin) {
			  ?>
				<td class="icon">
				  <a href="./user_management.php">
				  <img alt="Users" src="./images/users.png" />
				  </a>
				</td>
				
				<td class="text"><div class="cell">
                   <a href="./user_management.php">Manage users</a><br />
                    <p />View, add, edit and delete users.
                  </div>
			    </td>

			  <?php
				} else {
			  ?>
				<td class="icon">
				  <img alt="Users" src="./images/users_disabled.png" />
				</td>
				<td class="text"><div class="cell">
                  <p>User management through the HRM is disabled.
                  </p></div>
			    </td>

			  <?php
				}
			  ?>
			  
			  <td class="icon">
				<a href="./job_queue.php">
				<img alt="Queue" src="./images/queue.png" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                          <a href="./job_queue.php">Queue status</a><br />
				<p />See and manage all jobs.
                          </div>
			  </td>
		    
			</tr>
			
			<tr>
			  
			  <td class="icon">
				<a href="./file_management.php?folder=src">
				<img alt="FileManager" src="./images/filemanager.png?folder=src" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                <a href="./file_management.php?folder=src">File manager</a><br />
			  <p />Upload your raw data.
                </div>
			  </td>
			  
			  <td class="icon">
				<a href="./statistics.php">
				<img alt="Statistics" src="./images/stats.png" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                  <a href="./statistics.php">Global statistics</a><br />
				<p />Summary of usage statistics for all users.
                  </div>
			  </td>
			  
		    </tr>

			<tr>
			  
			  <td class="icon">
				<a href="./select_parameter_settings.php">
				<img alt="Parameter templates" src="./images/parameters.png" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                <a href="./select_parameter_settings.php">Image templates</a><br />
			  <p />Create templates for the image parameters.
                </div>
			  </td>
			  
			  <td class="icon">
				<a href="./select_task_settings.php">
				<img alt="Task parameters" src="./images/tasks.png" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                <a href="./select_task_settings.php">Restoration templates</a><br />
				<p />Create templates for the restoration parameters.
                </div>
			  </td>
			  
		    </tr>

			<tr>
			
			  <td class="icon">
				<a href="./account.php">
				<img alt="Account" src="./images/account.png" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                <a href="./account.php">Your account</a><br />
				<p />View and change your personal data.
                </div>
			  </td>

			  <td class="icon">
				<a href="./update.php">
				<img alt="Update" src="./images/updatedb.png" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                <a href="./update.php">Database update</a><br />
				<p />Update the database to the latest version.
                </div>
			  </td>
			  
		    </tr>
			
		  </tbody>
		  
		</table>
        
        <?php
		  } else {
        ?>
		<table>
		  
		  <tbody>
			
			<tr >
			  
			  <td class="icon">
				<a href="./select_parameter_settings.php">
				<img alt="Jobs" src="./images/start.png" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                <a href="./select_parameter_settings.php">Start a job</a><br />
				<p />Create and start deconvolution jobs.
                </div>
			  </td>
			  
			  <td class="icon">
				<a href="./job_queue.php">
				<img alt="Queue" src="./images/queue.png" />
				</a>
			  </td>
			  
			  <?php
				$db = new DatabaseConnection();
				$query = "SELECT COUNT(id) FROM job_queue WHERE username = '" . $_SESSION['user']->name( ) . "';";
				$row = $db->execute( $query )->FetchRow( );
				$jobsInQueue = $row[ 0 ];
				
				if ( $jobsInQueue == 0 ) {
				  $str = '<strong>no jobs</strong>';
				} elseif ( $jobsInQueue == 1 ) {
				  $str = '<strong>1 job</strong>';
				} else {
				  $str = '<strong>' .$jobsInQueue . ' jobs</strong>';
				}
			  ?>
			  <td class="text"><div class="cell">
                <a href="./job_queue.php">Queue status</a><br />
				<p />See all jobs.<br />
                You have <?php echo $str; ?> in the queue.
                </div>
			  </td>
		    
			</tr>
			
			<tr>
			  
			  <td class="icon">
				<a href="./file_management.php">
				<img alt="FileManager" src="./images/filemanager.png" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                <a href="./file_management.php">File manager</a><br />
			  <p />Upload, download and view your raw and deconvolved data.
                </div>
			  </td>
			  
			  <td class="icon">
				<a href="./statistics.php">
				<img alt="Statistics" src="./images/stats.png" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                <a href="./statistics.php">Your statistics</a><br />
				<p />Summary of your usage statistics.
                </div>
			  </td>
			  
		    </tr>
			
			<?php
			if ( $authenticateAgainst == "MYSQL" ) {
			?>
			<tr>
			
			  <td class="icon">
				<a href="./account.php">
				<img alt="Account" src="./images/account.png" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                <a href="./account.php">Your account</a><br />
				<p />View and change your personal data.
                </div>
			  </td>
			  
			  <td class="icon"></td>
			  
			  <td class="text">&nbsp;</td>
			  
		    </tr>
			<?php
			}
			?>
			
		  </tbody>
		  
		</table>

        <?php
        }
        ?>        
   
    </div> <!-- home -->

<?php

include("footer.inc.php");

?>

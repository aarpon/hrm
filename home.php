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
require_once("./inc/Database.inc"); // for account management (email & last_access fields)
require_once("./inc/CreditOwner.inc");
require_once("./inc/hrm_config.inc");
require_once("./inc/Fileserver.inc");
require_once("./inc/versions.inc");

global $email_admin;
global $enableUserAdmin;
global $use_accounting_system;

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

include("header.inc.php");

?>

    <div id="nav">
        <ul>
			<li><a href="<?php echo getThisPageName();?>?exited=exited"><img src="images/exit.png" alt="exit" />&nbsp;Logout</a></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpHome')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li> 
        </ul>
    </div>
    
    <div id="homepage">

        <?php

        if ($_SESSION['user']->name() == "admin") {
            if ($enableUserAdmin) {
        ?>
		
				<table>
		  
		  <tbody>
			
			<tr >
			  
			  <td class="icon">
				<a href="./user_management.php">
				<img alt="Users" src="./images/users.png" />
				</a>
			  </td>
			  
			  <td class="text"><a href="./user_management.php">Manage users</a><br />
				<p />Here you can view, add, edit and delete users.
			  </td>
			  
			  <td class="icon">
				<a href="./job_queue.php">
				<img alt="Queue" src="./images/queue.png" />
				</a>
			  </td>
			  
			  <td class="text"><a href="./job_queue.php">Queue status</a><br />
				<p />Here you can see and manage all running jobs.
			  </td>
		    
			</tr>
			
			<tr>
			  
			  <td class="icon">
				<a href="./file_management.php">
				<img alt="FileManager" src="./images/filemanager.png" />
				</a>
			  </td>
			  
			  <td class="text"><a href="./file_management.php">File manager</a><br />
			  <p />Here you can upload, download and view your raw and deconvolved data.
			  </td>
			  
			  <td class="icon">
				<a href="#">
				<img alt="Statistics" src="./images/stats.png" />
				</a>
			  </td>
			  
			  <td class="text"><a href="#">Global statistics</a><br />
				<p />Here you will find a summary of usage statistics for all users.
			  </td>
			  
		    </tr>

			<tr>
			  
			  <td class="icon">
				<a href="./select_parameter_settings.php">
				<img alt="Parameter templates" src="./images/parameters.png" />
				</a>
			  </td>
			  
			  <td class="text"><a href="./select_parameter_settings.php">Parameter templates</a><br />
			  <p />Here you can create templates for the optical parameters.
			  </td>
			  
			  <td class="icon">
				<a href="./select_tasks_settings.php">
				<img alt="Task parameters" src="./images/tasks.png" />
				</a>
			  </td>
			  
			  <td class="text"><a href="./select_tasks_settings.php">Task templates</a><br />
				<p />Here you can create templates for the deconvolution parameters.
			  </td>
			  
		    </tr>

			<tr>
			
			  <td class="icon">
				<a href="./update.php">
				<img alt="Update" src="./images/updatedb.png" />
				</a>
			  </td>
			  
			  <td class="text"><a href="./update.php">Database update</a><br />
				<p />Here you can update the database to the latest version.
			  </td>
			  
			  <td class="icon"></td>
			  
			  <td class="text">&nbsp;</td>
			  
		    </tr>
			
		  </tbody>
		  
		</table>
        
        <?php
            }
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
			  
			  <td class="text"><a href="./select_parameter_settings.php">Start a job</a><br />
				<p />Here you can create and start deconvolution jobs.
			  </td>
			  
			  <td class="icon">
				<a href="./job_queue.php">
				<img alt="Queue" src="./images/queue.png" />
				</a>
			  </td>
			  
			  <td class="text"><a href="./job_queue.php">Queue status</a><br />
				<p />Here you will see all running jobs.<br />You have 0 jobs in the queue.
			  </td>
		    
			</tr>
			
			<tr>
			  
			  <td class="icon">
				<a href="./file_management.php">
				<img alt="FileManager" src="./images/filemanager.png" />
				</a>
			  </td>
			  
			  <td class="text"><a href="./file_management.php">File manager</a><br />
			  <p />Here you can upload, download and view your raw and deconvolved data.
			  </td>
			  
			  <td class="icon">
				<a href="#">
				<img alt="Statistics" src="./images/stats.png" />
				</a>
			  </td>
			  
			  <td class="text"><a href="#">Your statistics</a><br />
				<p />Here you will find a summary of your usage statistics.
			  </td>
			  
		    </tr>
			
			<tr>
			
			  <td class="icon">
				<a href="./account.php">
				<img alt="Account" src="./images/account.png" />
				</a>
			  </td>
			  
			  <td class="text"><a href="./account.php">Your account</a><br />
				<p />Here you can view and change your personal data.
			  </td>
			  
			  <td class="icon"></td>
			  
			  <td class="text">&nbsp;</td>
			  
		    </tr>
			
		  </tbody>
		  
		</table>

        <?php
        }
        ?>        
   
    </div> <!-- home -->

<?php

include("footer.inc.php");

?>

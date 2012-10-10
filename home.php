<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/hrm_config.inc.php");
require_once("./inc/Fileserver.inc.php");
require_once("./inc/System.inc.php");

global $email_admin;
global $enableUserAdmin;
global $authenticateAgainst;

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

$message = "";

$script = array( "ajax_utils.js" );

include("header.inc.php");

?>

    <div id="nav">
        <ul>
			<li>
                <img src="images/user.png" alt="user" />
                &nbsp;<?php echo $_SESSION['user']->name(); ?>
            </li>
	        <li>
                <a href="javascript:openWindow(
                   'http://huygens-rm.org/home/?q=node/7')">
                    <img src="images/manual.png" alt="manual" />
                    &nbsp;User manual
                </a>
            </li>
			<li>
                <a href="<?php echo getThisPageName();?>?exited=exited">
                    <img src="images/exit.png" alt="exit" />
                    &nbsp;Logout
                </a>
            </li>
            <li>
                <a href="javascript:openWindow(
                   'http://www.svi.nl/HuygensRemoteManagerHelpHome')">
                    <img src="images/help.png" alt="help" />
                    &nbsp;Help
                </a>
            </li>
        </ul>
    </div>
    
    <div id="homepage">

        <?php
            $textHome = "Home";
            if ( !isset( $_SESSION['BEEN_HOME'] ) ) {
                $textHome = "Welcome!";
                $_SESSION['BEEN_HOME'] = 1;
            }
        ?>
        <h3><img src="images/home.png" alt="Home" />&nbsp;
            <?php echo $textHome; ?></h3>
        
        <?php

        if ($_SESSION['user']->isAdmin()) {
        ?>
		
		  <table>
		  
		  <tbody>
			
			<tr >

			  <?php
			    if ( $authenticateAgainst == "MYSQL" ) {
			  ?>
				<td class="icon">
				  <a href="./user_management.php">
				  <img alt="Users" src="./images/users.png" />
				  </a>
				</td>
				
				<td class="text"><div class="cell">
                   <a href="./user_management.php">Manage users</a>
                   <br />
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
				<a href="./account.php">
				<img alt="Account" src="./images/account.png" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                <a href="./account.php">Account</a>
                <br />
				<p />View and change your personal data.
                </div>
			  </td>			  

			</tr>
                               <tr class="separator"><td></td><td></td><td></td><td></td></tr>
                            
			<tr>
                                               <td class="icon">
				<a href="./job_queue.php">
				<img alt="Queue" src="./images/queue.png" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                <a href="./job_queue.php">Queue status</a>
                <br />
				<p />See and manage all jobs.
                          </div>
			  </td>
			  
			  
			  <td class="icon">
				<a href="./statistics.php">
				<img alt="Statistics" src="./images/stats.png" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                  <a href="./statistics.php">Global statistics</a>
                  <br />
				<p />Summary of usage statistics for all users.
                  </div>
			  </td>
			  
		    </tr>

                               <tr class="separator"><td></td><td></td><td></td><td></td></tr>

			<tr>
			  
			  <td class="icon">
				<a href="./select_parameter_settings.php">
				<img alt="Parameter templates" src="./images/parameters.png" />
				</a>
			  </td>
                               
			  
			  <td class="text"><div class="cell">
                <a href="./select_parameter_settings.php">Image templates</a>
                <br />
			  <p />Create templates for the image parameters.
                </div>
			  </td>
			  
			  <td class="icon">
				<a href="./select_task_settings.php">
				<img alt="Task parameters" src="./images/tasks.png" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                <a href="./select_task_settings.php">Restoration templates</a>
                <br />
				<p />Create templates for the restoration parameters.
                </div>
			  </td>
			  
		    </tr>

			<tr>
                          <td class="icon">
                                <a href="./select_analysis_settings.php">
				<img alt="Analysis" src="./images/analysis.png" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                <a href="./select_analysis_settings.php">Analysis templates</a>
                <br />
				<p />Create templates for the analysis parameters.
                </div>
			  </td>
        <td class="icon">
				<a href="./file_management.php?folder=src">
				<img alt="FileManager" src="./images/filemanager.png" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                <a href="./file_management.php?folder=src">Raw images</a>
                <br />
			  <p />Upload your raw images.
                </div>
			  </td>

			
		    </tr>
			<tr class="separator"><td></td><td></td><td></td><td></td></tr>
			<tr>

        <td class="icon">
				<a href="./update.php">
				<img alt="Update" src="./images/updatedb.png" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                <a href="./update.php">Database update</a>
                <br />
				<p />Update the database to the latest version.
                </div>
			  </td>

			
			  <td class="icon">
				<a href="./system.php">
				<img alt="System summary" src="./images/system.png" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                <a href="./system.php">System summary</a>
                <br />
				<p />Inspect your system.
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
				<a href="./select_images.php">
				<img alt="Jobs" src="./images/start.png" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                <a href="./select_images.php">Start a job</a>
                <br />
				<p />Create and start restoration and analysis jobs.
                </div>
			  </td>
			  
			  <td class="icon">
				<a href="./job_queue.php">
				<img alt="Queue" src="./images/queue.png" />
				</a>
			  </td>
			  
			  <?php
                if ( isset( $_SESSION['jobcreated'] ) &&
                     isset( $_SESSION['numberjobadded'] ) &&
                     $_SESSION['numberjobadded'] > 0 ) {
                        if ( $_SESSION['numberjobadded'] == 1 ) {
                            $str = "1 job";
                        } else {
                            $str = $_SESSION['numberjobadded'] . " jobs";
                        }
                        unset( $_SESSION['numberjobadded'] );
                    ?>
                    <td class="text"><div class="cell">
                        <a href="./job_queue.php">Queue status</a>
                        <br />
                        <div id="jobsInQueue">
                            <p class="added_jobs"/>
                            <a href="./job_queue.php">Congratulations!<br />
                            You added <strong><?php echo $str; ?></strong> to
                            the queue!</a>
                        </div>
                        </div>
                    </td>
                    <?php

                } else {
    				$jobsInQueue = $_SESSION['user']->numberOfJobsInQueue();
        			if ( $jobsInQueue == 0 ) {
            		  $str = '<strong>no jobs</strong>';
                	} elseif ( $jobsInQueue == 1 ) {
                      $str = '<strong>1 job</strong>';
                    } else {
    				  $str = '<strong>' .$jobsInQueue . ' jobs</strong>';
        			}
                    ?>
                    <td class="text"><div class="cell">
                        <a href="./job_queue.php">Queue status</a>
                        <br />
                        <div id="jobsInQueue">
                            <p />See all jobs.<br />
                            You have <?php 
                                echo "<strong><span id=\"jobsInQueue\">
                                    $str</span> </strong>"; ?>
                            in the queue.
                        </div>
                        </div>
                    </td>
                    <?php
                }
			  ?>
		    
			</tr>
			
			<tr>
			  
			  <td class="icon">
				<a href="./file_management.php?folder=src">
				<img alt="Raw images" src="./images/filemanager.png" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                <a href="./file_management.php?folder=src">Raw images</a>
                <br />
			  <p />Upload raw images to deconvolve.
                </div>
			  </td>
			  
			  <td class="icon">
				<a href="./file_management.php?folder=dest">
				<img alt="Results" src="./images/results.png" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                <a href="./file_management.php?folder=dest">Results</a>
                <br />
				<p />Inspect and download your deconvolved data and results.
                </div>
			  </td>
			  
      </tr>

    	<tr>

			  <td class="icon">
				<a href="./statistics.php">
				<img alt="Statistics" src="./images/stats.png" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                <a href="./statistics.php">Statistics</a>
                <br />
				<p />Summary of your usage statistics.
                </div>
			  </td>
        
			<?php
			if ( $authenticateAgainst == "MYSQL" ) {
			?>
			  <td class="icon">
				<a href="./account.php">
				<img alt="Account" src="./images/account.png" />
				</a>
			  </td>
			  
			  <td class="text"><div class="cell">
                <a href="./account.php">Account</a>
                <br />
				<p />View and change your personal data.
                </div>
			  </td>
			  
			<?php
			} else {
			?>
        <td class="icon">&nbsp;</td>
        <td class="text">&nbsp;</td>
			<?php
			}
			?>
        
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

<!-- Ajax function to update the number of jobs in the queue every 10 s -->
<script type="text/javascript">
    $(document).ready(function() {
        setInterval(function() { 
          ajaxGetNumberOfUserJobsInQueue(
            'jobsInQueue',
            '<p />See all jobs.<br />You have <strong>',
            '</strong> in the queue.'); },
            10000);
    });
</script>

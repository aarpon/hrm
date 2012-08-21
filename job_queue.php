<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/JobDescription.inc.php");
require_once("./inc/JobQueue.inc.php");

session_start();

$queue = new JobQueue();

if (isset($_GET['home'])) {
  header("Location: " . "home.php"); exit();
}
if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

if (isset($_SERVER['HTTP_REFERER']) &&
    !strstr($_SERVER['HTTP_REFERER'], 'job_queue')) {
        $_SESSION['referer'] = $_SERVER['HTTP_REFERER'];
}

if (isset($_POST['delete'])) {
  if (isset($_POST['jobs_to_kill'])) {
    $queue->markJobsAsRemoved($_POST['jobs_to_kill'],
        $_SESSION['user']->name());
  }
}
else if (isset($_POST['update']) && $_POST['update']=='update') {
  // nothing to do
}

$script = array( "queue.js", "ajax_utils.js" );

include("header.inc.php");

?>

    <div id="nav">
        <ul>
            <li>
                <img src="images/user.png" alt="user" />
                &nbsp;<?php echo $_SESSION['user']->name(); ?>
            </li>
            <?php $referer = $_SESSION['referer']; ?>
            <li>
                <a href="<?php echo $referer;?>">
                    <img src="images/back_small.png" alt="back" />&nbsp;Back</a>
            </li>
            <li>
                <a href="<?php echo getThisPageName();?>?home=home">
                    <img src="images/home.png" alt="home" />&nbsp;Home</a>
            </li>
            <li>
                <a href="javascript:openWindow('
                   http://www.svi.nl/HuygensRemoteManagerHelpQueue')">
                    <img src="images/help.png" alt="help" />&nbsp;Help
                </a>
            </li>
        </ul>
    </div>
   
   <div id="joblist">
   <h3><img src="images/queue_small.png" alt="Queue" />&nbsp;Queue status</h3>
    
    <form method="post" action="" id="jobqueue">
    <p>
        <input name="update" type="submit" value="" class="icon update"
            id="controls_refresh" /><?php echo date("l d. F Y, H:i:s"); ?>
   </p>

   <!-- Display total number and number of jobs owned by current user.
   This will be filled in by Ajax calls. -->
   <p id="summary"><img src="./images/note.png" alt=\"Summary\" />&nbsp;
     <span id="totalJobNumber">&nbsp</span>
     <span id="userJobNumber">&nbsp;</p>

   <!-- Display full queue table. -->
    <div id="queue">&nbsp; </div> <!-- queue -->

        </form>
  
    </div> <!-- joblist -->

<?php

/*
 * Tooltips. 
 * 
 * Define $tooltips array with object id as key and tooltip string as value.
 */
$tooltips = array(
    "controls_refresh" => "Refresh the queue.",
    "controls_delete" => "Delete selected job(s) from the queue. If a job is running, it will be killed!"
);

include("footer.inc.php");

?>

<!-- Activate Ajax functions to render the dynamic views -->
<script type="text/javascript">
  
    // Fill in the information about jobs and draw the job queue table as
    // soon as the page is ready
    getTotalNumberOfJobsInQueue('totalJobNumber');
    getNumberOfUserJobsInQueue('userJobNumber', 'You own ', '.');
    getJobQueuetable('queue');
    
    // Then, update the total number of jobs every 10 s
    $(document).ready(function() {
        setInterval(function() { 
          getTotalNumberOfJobsInQueue('totalJobNumber'); 
        }, 10000 );
    });
    
    // Update the number of jobs per user every 10 s
    $(document).ready(function() {
        setInterval(function() { 
          getNumberOfUserJobsInQueue('userJobNumber', 'You own ', '.'); 
        }, 10000 );
    });

    // Update the job queue table every 10 s
    $(document).ready(function() {
        setInterval(function() { 
          getJobQueuetable('queue'); 
        }, 10000 );
    });

</script>

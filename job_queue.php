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

    <!--
      Tooltips
    -->
    <span class="toolTip" id="ttRefresh">Refresh the queue.</span>
    <?php
      $rows = $queue->getContents();
      if (count($rows) != 0) {
    ?>
    <span class="toolTip" id="ttDelete">Delete selected job(s) from the queue.
      If a job is running, it will be killed!</span>
    <?php
      }
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
   <h3><img alt="SelectImages" src="./images/queue_title.png" width="40"/>&nbsp;&nbsp;Queue status</h3>
    
    <form method="post" action="" id="jobqueue">
      
   <!-- Display total number and number of jobs owned by current user.
   This will be filled in by Ajax calls. -->
   <div id="summary">
      <input name="update" type="submit" value="" class="icon update"
        onmouseover="TagToTip('ttRefresh' )"
        onmouseout="UnTip()" style="vertical-align: middle;" />
      &nbsp;
     <span id="totalJobNumber"  style="vertical-align: middle;">&nbsp;</span>
     <span id="userJobNumber"  style="vertical-align: middle;">&nbsp;</span>
   </div>

   <!-- Display full queue table. -->
    <div id="queue">&nbsp; </div> <!-- queue -->

        </form>
  
    </div> <!-- joblist -->

<?php

include("footer.inc.php");

?>

<!-- Activate Ajax functions to render the dynamic views -->
<script type="text/javascript">
    $(document).ready(function() {

        // Fill in the information about jobs and draw the job queue table as
        // soon as the page is ready
        updateAll();

        // Set up timer for the repeated update
        var interval = window.setInterval(function() { updateAll(); }, 5000);

        // Function that queries the server via Ajax calls
        function updateAll() {
            ajaxGetTotalNumberOfJobsInQueue('totalJobNumber');
            ajaxGetNumberOfUserJobsInQueue('userJobNumber', 'You own ', '.');
            ajaxGetJobQueueTable('queue');
        }
    });
</script>

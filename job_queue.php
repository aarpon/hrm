<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\job\JobQueue;
use hrm\Nav;
use hrm\Util;

require_once dirname(__FILE__) . '/inc/bootstrap.php';

session_start();

$queue = new JobQueue();

if (isset($_GET['home'])) {
    header("Location: " . "home.php");
    exit();
}
if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit();
}

if (isset($_SERVER['HTTP_REFERER']) &&
    !strstr($_SERVER['HTTP_REFERER'], 'job_queue')
) {
    $_SESSION['referer'] = $_SERVER['HTTP_REFERER'];
}

if (isset($_POST['delete'])) {
    if (isset($_POST['jobs_to_kill'])) {
        $queue->removeJobs($_POST['jobs_to_kill'],
            $_SESSION['user'], $_SESSION['user']->isAdmin());
    }
} else if (isset($_POST['update']) && $_POST['update'] == 'update') {
    // nothing to do
}

$script = array("queue.js", "ajax_utils.js", "json-rpc-client.js");

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
    <div id="navleft">
        <ul>
            <?php
            echo(Nav::linkWikiPage('HuygensRemoteManagerHelpQueue'));
            ?>
        </ul>
    </div>
    <div id="navright">
        <ul>
            <?php
            echo(Nav::textUser($_SESSION['user']->name()));
            echo(Nav::linkBack($_SESSION['referer']));
            echo(Nav::linkHome(Util::getThisPageName()));
            ?>
        </ul>
    </div>
    <div class="clear"></div>
</div>

<div id="joblist">
    <h3><img alt="SelectImages" src="./images/queue_title.png" width="40"/>&nbsp;&nbsp;Queue
        status</h3>

    <form method="post" action="" id="jobqueue">

        <!-- Display total number and number of jobs owned by current user.
        This will be filled in by Ajax calls. -->
        <div id="summary">
            <input name="update" type="submit" value="" class="icon update"
                   onmouseover="TagToTip('ttRefresh' )"
                   onmouseout="UnTip()" style="vertical-align: middle;"/>
            &nbsp;
            <span id="jobNumber" style="vertical-align: middle;">&nbsp;</span>
            <br/>
            <span id="lastUpdateTime"
                  style="vertical-align: middle;">&nbsp;</span>
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
    $(document).ready(function () {

        // Fill in the information about jobs and draw the job queue table as
        // soon as the page is ready
        updateAll();

        // Set up timer for the repeated update
        window.setInterval(function () {
            updateAll();
        }, 1000);

        // Function that queries the server via Ajax calls
        function updateAll() {
            JSONRPCRequest({
                method: 'jsonGetUserAndTotalNumberOfJobsInQueue',
                params: []
            }, function (response) {
                var jobNumberDiv = $("#jobNumber");
                if (!response) {
                    jobNumberDiv.html("<b>Error: could not query job status!</b>");
                    return;
                }
                if (response.success === "false") {
                    jobNumberDiv.html("<b>" + response.message + "</b>");
                    return;
                }
                // Make sure to work with integers
                var numAllJobsInQueue = parseInt(response.numAllJobsInQueue);
                var numUserJobsInQueue = parseInt(response.numUserJobsInQueue);

                // Update the page
                var message = "";
                outer:
                    switch (numAllJobsInQueue) {

                        case 0:
                            message = "There are <b>no jobs</b> in the queue.";
                            break;

                        case 1:
                            switch (numUserJobsInQueue) {

                                case 0:
                                    message = "There is <b>1 job</b> owned by " +
                                        "another user in the queue.";
                                    break outer;

                                case 1:
                                    message = "<b>Yours is the only job</b> " +
                                        "in the queue.";
                                    break outer;

                                default:
                                    message = "<b>Error: inconsistent job count!</b>";
                                    break outer;
                            }
                            break;

                        default:
                            switch (numUserJobsInQueue) {

                                case 0:
                                    message = "There are <b>" + numAllJobsInQueue +
                                        " jobs</b> in the queue, <b>none</b> " +
                                        "of which is yours.";
                                    break outer;

                                case 1:
                                    message = "There are <b>" + numAllJobsInQueue +
                                        " jobs</b> in the queue, <b>1</b> " +
                                        "of which is yours.";
                                    break outer;

                                default:
                                    var insert = "";
                                    if (numAllJobsInQueue > 100) {
                                        insert = " (showing first 100) ";
                                    }
                                    if (numAllJobsInQueue === numUserJobsInQueue) {
                                        message = "There are <b>" + numAllJobsInQueue +
                                            " jobs</b> in the queue" + insert +
                                            ", <b>all</b> yours.";

                                    } else if (numAllJobsInQueue > numUserJobsInQueue) {
                                        message = "There are <b>" + numAllJobsInQueue +
                                            " jobs</b> in the queue " + insert +
                                            ", <b>" + numUserJobsInQueue + "</b>" +
                                            " of which are yours.";
                                    } else {
                                        message = "<b>Error: inconsistent job count!</b>";
                                    }
                                    break outer;
                            }

                            break;
                    }
                jobNumberDiv.html(message);

                // Update the time as well
                $("#lastUpdateTime").text("Last update: " +
                    response.lastUpdateTime);
            });

            // Redraw the table
            ajaxGetJobQueueTable('queue');
        }
    });
</script>





















/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */

if ($_SESSION['task_setting']->checkPostedTaskParameters($_POST)) {
    if ($_SESSION['user']->isAdmin()
    || $_SESSION['task_setting']->isEligibleForCAC()
    || $_SESSION['task_setting']->isEligibleForTStabilization($_SESSION['setting'])) {
        header("Location: " . "post_processing.php");
        exit();
    } else {
        header("Location: " . "select_hpc.php");
        exit();
    }
} else {
    $message = $_SESSION['task_setting']->message();
}

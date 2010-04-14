<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("inc/User.inc");
require_once("inc/CreditOwner.inc");
require_once("inc/hrm_config.inc");
session_start();
$user = $_SESSION['user'];
$name = $user->name(); 	
$creditOwner = new CreditOwner($name);
$message = '&nbsp;<br>&nbsp;<br>';
$_SESSION['credit'] = "";	
if (isset($_POST['SelectedCredit'])) {
 $_SESSION['credit'] = $_POST['SelectedCredit'];
 $groups = $creditOwner->myGroupsForCredit(new CreditOwner($_SESSION['credit']));
 if (count($groups)>1) {
	header("Location: " . "select_group.php"); exit();
 } else {
 	$_SESSION['group'] = $groups[0]->id();
	header("Location: " . "select_parameter_settings.php"); exit();	
 }
}
?>

<?php
include("header.inc.php"); 
?>

<div id="content">
<h3>Select credit</h3>
<?php
	$positiveCreditsNames = $creditOwner->positiveCreditsNames();
		
	if ( count( $positiveCreditsNames ) < 2 ) {
		?>
		<p>No multiple credits found.</p>
		<?php
	}
	else
	{	
		?>
		<p>You have access to multiple credits. Please select the one you want to use for this session.</p>
		<div>
		<form action="select_credit.php" method="post">
		<p>
		<select name="SelectedCredit" size="1" style="width: 60%; vertical-align:middle; text-align:middle;">
		<?php
		foreach ($positiveCreditsNames as $positiveCredit) {
			if ($positiveCredit == $_SESSION['credit']) {
			   $selected="selected";
			} else {
			   $selected='';
			}
			print '<option style="text-align:right;" ' . $selected . '>' . $positiveCredit . "</option>\n";
		}
		?>
		</select>
		<button style="width: 25%;" type="submit" name="OK">OK</button>
		</p>
		</form>
		</div>
		<hr />
		<table>
			<tr><td><b>available credits</b></td><td><b>remaining hours</b></td><td><b>remaining hour for hrm</b></td></tr>
			<?php
			foreach ($positiveCreditsNames as $creditName) {
				$credit = new CreditOwner($creditName);
				$credit->load();
				print "<tr>";
				print "<td>$creditName</td><td>" . 
					$credit->remainingHoursString() . 
					"</td><td>" . 
					$credit->remainingHoursForHrmString() . 
					"</td></tr>\n";   
			}
			?>
		</table>
		<?php
	}
?>
<br />
</div>
<?php
include("footer.inc.php");
?>

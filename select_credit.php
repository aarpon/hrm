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

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3c.org/TR/1999/REC-html401-19991224/loose.dtd">
<HTML xmlns="http://www.w3.org/1999/xhtml">
<HEAD>
<TITLE>Huygens Remote Manager - Select Credit</TITLE>
<link rel="stylesheet" type="text/css" href="huygens_style.css">
<style type="text/css">
<!--
  ... page specific style ...
-->
</style>
<META http-equiv=Content-Type content="text/html; charset=windows-1252">
<META content="MSHTML 6.00.2800.1400" name=GENERATOR></HEAD>
<BODY>

<?php
include("header.inc.php"); 
?>

<UL id=nav>
  <LI><A href="http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpSelectCredit" target="_blank">help</A> 
  <LI><A href="about.php">about</A>
  <LI><A href="last_changes.php">changes</A>
</UL>
<DIV id=content>
<H2>Huygens Remote Manager</H2>
<H3>Select Credit</H3>
<P>You have access to multiple credits. Please select the one you want to use for 
this session. Only the treatment of the images by our image processing servers will 
be taken into account.
</P>
<div  align="center">
<form action="select_credit.php" method='POST' name="credit">
<select name="SelectedCredit" size="1" style="width: 60%; vertical-align:middle; text-align:middle;">
<?php
	$positiveCreditsNames = $creditOwner->positiveCreditsNames();
	foreach ($positiveCreditsNames as $positiveCredit) {
		if ($positiveCredit == $_SESSION['credit']) {
		   $selected="selected";
		} else {
		   $selected='';
		}
		print '<option style="text-align:right;" align="right" ' . $selected . '>' . $positiveCredit . "</option>\n";
	} 
?>
</select>
<button style="width: 25%;" type="submit" name="OK">OK</button>
</form>
<table>
<tr><td><b>available credits</b></td><td><b>remaining hours</b></td><td><b>remaining hour for hrm</b></td> 
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
</div>
</DIV>
<DIV id=stuff>
<br>
<?php 
	  echo $message; 
?>
<H3>Internal Links</H3>
<UL>
  <LI><a href="http://www.mri.cnrs.fr/">
  		 MONTPELLIER RIO IMAGING </a></LI>   
  <LI><a href="http://www.mri.cnrs.fr/welcome.php">Microscope Reservation System</a></LI></UL>
<H3>External Links</H3>
<UL>
  <LI><a href="http://www.svi.nl/">Scientific Volume Imaging B.V.</a> 
 </UL>
</DIV>

<DIV id=footer align="center"><small>created 2004 by <a href="mailto:volker.baecker@crbm.cnrs.fr">
Volker Baecker</a></small></DIV>
<hr>
<?php
include("footer.inc.php");
?>
</DIV>
</BODY></HTML>

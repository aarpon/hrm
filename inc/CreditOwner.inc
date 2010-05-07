<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("AccountingDatabase.inc");

Class CreditOwner {
  public $id;
  public $credit;

  function CreditOwner($id) {
    $this->id = $id;
    $this->credit = null;
  } 

  function id() {
    return $this->id;
  } 
  
  function credit() {
    return $this->credit;
  } 

  function setCredit($amount) {
    $this->credit = $amount;
  }

  function credits() { 
    $db = new AccountingDatabaseConnection();
    return $db->getCreditsFor($this);
  }

  function positiveCredits() {
    $credits = $this->credits();
    $positiveCredits = array();
    foreach ($credits as $credit) {
      if ($credit->credit()>0) {
	$positiveCredits[] = $credit; 
      } 
    }
    return $positiveCredits;
  } 

  function positiveCreditsNames() {
    $names = array();
    foreach ($this->positiveCredits() as $credit) {
      $names[] = $credit->id();
    } 
    return $names;
  }

  function load() {
    $db = new AccountingDatabaseConnection();
    $id = $this->id();
    $credit = $db->loadCreditOwner($id);
    $this->credit = $credit;
    return $this;
  }

  function myGroupsForCredit($credit) {
    $myGroupsForCredit = array();
    $myGroups = $this->myGroups();
    foreach ($myGroups as $myGroup) { 
      if ($myGroup->isSubgroupOf($credit)) {
	$myGroupsForCredit[] = $myGroup; 
      } 
    }
    return $myGroupsForCredit;
  }

  function myGroups() {
    $db = new AccountingDatabaseConnection();
    return $db->groupsOf($this);
  } 

  function isSubgroupOf($credit) {
    $credits = $this->credits();
    $found = false;
    foreach ($credits as $aCredit) {
      if ($aCredit->id() == $credit->id()) {
	$found = true;
	break;
      } 
    } 
    return $found;
  } 

  function remainingHoursString() {
    return $this->remainingHoursStringFor($this->credit());
  } 

  function remainingHoursStringFor($credit) {
    $days = floor($credit / 24.0);
    $rest = $credit - (24 * $days);
    $hours = floor($rest);
    $rest = $rest - $hours;
    $minutes = floor($rest * 60);
    $result = $days . "d-" . $hours . "h-" . $minutes . "m";
    return $result;
  } 

  function remainingHoursForHrmString() {
    $db = new AccountingDatabaseConnection();
    $tariff = $db->tariffForResource("hrm");
    $credit = $this->credit() * $tariff;
    return $this->remainingHoursStringFor($credit);
  }
}
?>
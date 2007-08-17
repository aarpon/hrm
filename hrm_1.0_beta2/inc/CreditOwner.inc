<?php
// Module Parameter.inc

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

require_once("AccountingDatabase.inc");

Class CreditOwner {
  var $id;
  var $credit;

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
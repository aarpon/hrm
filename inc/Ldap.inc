<?php

//!--------------------------------------------------------------------
// @class    Ldap
// @desc     Manage ldap connections
//!--------------------------------------------------------------------

include "ldap_config.inc";

Class Ldap {
  var $connectionString; // @var connectionString   the ldap connection string
  
  var $connection; 
  var $writeConnection;
  var $userDirectory;

  function Ldap() {
    global $ldap_host;
    global $ldap_port;
    global $ldap_root;
    global $use_ldaps;

    $connectionMethod = 'ldap';
    if ($use_ldaps) {
	$connectionMethod = 'ldaps';	
    } 
    $this->connectionString = $connectionMethod . '://' . $ldap_host . ':' . $ldap_port + "/" + $ldap_root;
  }
  
  function connectForReading() {
    global $ldap_host;

    $result = false;
    $this->closeConnection();
    $this->connection = ldap_connect($ldap_host);
    if ($this->connection) {
      $result = true;
    }
    return $result;
  }

  function connectForWriting() {
    global $ldap_manager;
    global $ldap_root;
    global $ldap_password;

    $result = false;
    if ($this->connectForReading()) {
      ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, 3);
      $dn = "cn=$ldap_manager" . "," . $ldap_root;
      $this->writeConnection = ldap_bind($this->connection, $dn, $ldap_password);
      if ($this->writeConnection) {
	$result = true;
      }
    }
    return $result;
  }

  function authenticate($uid, $userPassword) {
    global $ldap_root;
    if (!$this->connectForReading()) {
      return false;
    }
    ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, 3);
    $dn = "uid=$uid, ou=Users" . "," . $ldap_root;
    ob_start();
    $writeConnection = ldap_bind($this->connection, $dn, $userPassword);
    ob_end_clean();
    if (!$writeConnection) {
      return false;
    }
    $this->closeConnection();
    return true;
  }

  function closeConnection() {
    if ($this->connection != null) {
      ldap_unbind($this->connection);
      $this->connection = null;
    }
  }

  function isConnected() {
    $result = false;
    if ($this->connection==null) {
      return false;
    }
    // todo test if reading possible
  }
  
  function userAttributeNames() {
    return array('givenname', 'sn', 'mail', 'status', 'accounttype', 'userpassword');
  }

  function loadUser($loginname) {
    global $ldap_root;
    $result = true;
    if (!$this->connectForWriting()) {
      return false;
    }
    $filter = '(uid=' . $loginname . ')';
    $attributeNames = $this->userAttributeNames();
    $searchResults = ldap_search($this->connection, $ldap_root, $filter, $attributeNames);
    $resultSet = ldap_get_entries($this->connection, $searchResults);
    if ($resultSet['count']<1) {
      return false;
    }
    $result = array();
    for ($i=0; $i<count($attributeNames); $i++) {
      $key = $attributeNames[$i];
      $value = $resultSet[0][$key][0];
      $result[$key]=$value; 
    }
    $this->closeConnection();
    return $result;
  }

  function updateUser($loginname, $data) {      
    global $ldap_root;
    if (!$user = $this->loadUser($loginname)) {
      return false; 
    }
    if (!$this->connectForWriting()) { 
      return false;
    } 
    $attributeNames = $this->userAttributeNames();
    for ($i=0; $i<count($attributeNames); $i++) {
      if (isset($data[$attributeNames[$i]])) { 
	$user[$attributeNames[$i]] = $data[$attributeNames[$i]];
      } 
    }  
    $result = ldap_mod_replace($this->connection, "uid=$loginname, ou=Users, $ldap_root", $user);
    $this->closeConnection();
    return $result;
  }

  function encodePassword($password) {
    global $ldap_encryption_method;
    $result = base64_encode(pack("H*",md5($password)));
    $result = $ldap_encryption_method . $result;
    return $result;
  }

  function addUser($loginname, $data) {
    global $ldap_root;
    if (!$uidNumber = $this->nextUidNumber()) { 
      return false;
    }
    if (!$gidNumber = $this->getGidNumber($data)) { 
      return false;
    }    
    if ($this->loadUser($loginname)) { 
      return false;
    }
    if (!$this->connectForWriting()) { 
      return false;
    } 
    $user = array();
    $user['uid'] = $loginname;
    $user['cn'] = $loginname;
    $user['givenname'] = $data['givenname'];
    $user['sn'] = $data['sn']; 
    $user['mail'] = $data['mail'];
    $status = $data['status'];
    if ($status=='actif') {
      $status='active';
    }
    if ($status=='inactif') {
      $status='inactive';
    }
    $user['status'] = $status;
    $accountType = $data['accounttype'];
    if ($accountType=='visiteur') {
      $accountType = 'guest';
    }
    if ($accountType=='utilisateur') {
      $accountType = 'user';
    }
    if ($accountType=='administrateur') {
      $accountType = 'administrator';
    }
    $user['accounttype'] = $accountType;
    $user['uidnumber'] = $uidNumber;
    $user['gidnumber'] = $gidNumber;
    $user['homedirectory'] = '/home/' . $loginname;
    $user['loginshell'] = '/bin/bash';
    $user['userpassword'] = $data['userpassword'];
    $user["objectClass"][0]="top";
    $user["objectClass"][1]="inetOrgPerson";
    $user["objectClass"][2]="posixAccount";
    $user["objectClass"][3]="mri-user";
    $result=ldap_add($this->connection, "uid=$loginname,ou=Users,$ldap_root", $user);
    $this->closeConnection();
    return $result;
  }

  function nextUidNumber() {
    global $ldap_root;
    $result = "";
    $key='uidnumber';
    if (!$this->connectForWriting()) {
      return false;
    }
    $filter = '(sn=NextFreeUnixId)';
    $attributeNames = array($key);
    $searchResults = ldap_search($this->connection, $ldap_root, $filter, $attributeNames);
    $resultSet = ldap_get_entries($this->connection, $searchResults);
    if ($resultSet['count']<1) {
      return false;
    }
    $value = $resultSet[0][$key][0];
    $value++;
    $newData = array();
    $newData[$key] = $value;
    $result = ldap_mod_replace($this->connection, "cn=NextFreeUnixId, $ldap_root", $newData);
    $this->closeConnection();    
    return $value;
  }

  function getGidNumber($accountType) {
    global $ldap_root;
    $key = 'gidnumber';
    $group = "Domain Guests";
    if ($accountType=="user") { 
      $group = "Domain Users";
    }
    if ($accountType=="administrator") { 
      $group = "Domain Admins";
    }
    if (!$this->connectForWriting()) {
      return false;
    }
    $filter = "(cn=$group)";
    $attributeNames = array($key);
    $searchResults = ldap_search($this->connection, $ldap_root, $filter, $attributeNames);
    $resultSet = ldap_get_entries($this->connection, $searchResults);
    if ($resultSet['count']<1) {
      return false;
    }
    $value = $resultSet[0][$key][0];
    return $value;
  }

  function deleteUser($loginname) {
    global $ldap_root;
    if (!$this->connectForWriting()) {
      return false;
    } 
    $result = ldap_delete($this->connection, "uid=$loginname,ou=Users,$ldap_root");
    return $result;
  }

  function allUsersWith($subfilter) {
    global $ldap_root;
    $result = true;
    if (!$this->connectForWriting()) {
      return false;
    }
    $filter = "(&(objectclass=mri-user)(uid=*)$subfilter)";
    $attributeNames = $this->userAttributeNames();
    $attributeNames[] = "uid";
    $searchResults = ldap_search($this->connection, $ldap_root, $filter, $attributeNames);
    $resultSet = ldap_get_entries($this->connection, $searchResults);
    if ($resultSet['count']<1) {
      return false;
    }
    $result = array();
    for ($nr=0; $nr<count($resultSet)-1; $nr++) {
      for ($i=0; $i<count($attributeNames); $i++) {
	$key = $attributeNames[$i];
	$value = $resultSet[$nr][$key][0];
	$result[$nr][$key]=$value; 
      }
    }
    $this->closeConnection();
    return $result;  
  } 

  function orderBy($users, $orderedBy) {
    foreach($users as $key => $row) {
      $sort[$key] =  $row[$orderedBy];
    }
    array_multisort($sort, SORT_ASC, $users);
    return $users;
  }

  function allUsersOrderedBy($orderedBy) {
    global $ldap_root;
    $result = true;
    if (!$this->connectForWriting()) {
      return false;
    }
    $filter = '';
    $result = $this->allUsersWith($filter);
    $this->closeConnection();
    $result = $this->orderBy($result, $orderedBy);
    return $result;  
  }

  function allUsersWithAccountTypeUserOrderedBy($orderedBy) {
    global $ldap_root;
    $result = true;
    if (!$this->connectForWriting()) {
      return false;
    }
    $filter = '(accounttype=user)';
    $result = $this->allUsersWith($filter);
    $this->closeConnection();
    $result = $this->orderBy($result, $orderedBy);
    return $result;  
  }

  function lastError() {
    return ldap_error($this->connection);
  }
}

?>
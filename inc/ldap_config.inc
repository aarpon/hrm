<?php

// the machine on which the ldap server is running
$ldap_host = "pdc1.fmi.ch";

// the port for the ldap connection
$ldap_port="389";

// the ldap root
$ldap_root="OU=Users,OU=FMI,DC=fmi,DC=ch";

// the ldap manager
$ldap_manager="Visitor@FMI";

// the ldap security athentication method
$ldap_security_authentication="simple";

// the ldap manager password
$ldap_password = "";

// the ldap encryption method for user passwords
$ldap_encryption_method="{MD5}";

// use ldaps or ldap
$use_ldaps = false;

?>

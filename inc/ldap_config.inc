<?php

// the machine on which the ldap server is running
$ldap_host = "172.28.200.1";

// the port for the ldap connection
$ldap_port="389";

// the ldap root
$ldap_root="dc=fmi,dc=ch";

// the ldap manager
$ldap_manager="adsearch";

// the ldap security athentication method
$ldap_security_authentication="simple";

// the ldap manager password
$ldap_password = "hcraesda";

// the ldap encryption method for user passwords
$ldap_encryption_method="{MD5}";

// use ldaps or ldap
$use_ldaps = false;
?>

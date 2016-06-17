<?php

$query_u = 'foo';                  // <<-- fill in the username for querying here!

if ($query_u == '') {
    echo "ERROR: edit the script and fill in a query-username!\n";
    exit();
}

$dir = dirname(__FILE__);

$adLDAP_path = $dir . "/../inc/extern/adLDAP4/src/adLDAP.php";
$ad_config = $dir . "/../config/active_directory_config.inc";

print "directory: $dir\n";
print "path to adLDAP: $adLDAP_path\n";
print "path to AD config: $ad_config\n";

require_once($adLDAP_path);
require_once($ad_config);


print "AD username: $AD_USERNAME\n";


$options = array(
    'account_suffix'      => $ACCOUNT_SUFFIX,
    'ad_port'             => $AD_PORT,
    'base_dn'             => $BASE_DN,
    'domain_controllers'  => $DOMAIN_CONTROLLERS,
    'admin_username'      => $AD_USERNAME,
    'admin_password'      => $AD_PASSWORD,
    'real_primarygroup'   => $REAL_PRIMARY_GROUP,
    'use_ssl'             => $USE_SSL,
    'use_tls'             => $USE_TLS,
    'recursive_groups'    => $RECURSIVE_GROUPS);

try {
    $m_AdLDAP = new adLDAP($options);
    // echo "Created adLDAP object!\n";
} catch (adLDAPException $e) {
    // Make sure to clean stack traces
    $pos = stripos($e, 'AD said:');
    if ($pos !== false) {
        $e = substr($e, 0, $pos);
    }
    echo "$e\n";
    exit();
}

$auth_u = $AD_USERNAME;
$auth_p = $AD_PASSWORD;

$b = $m_AdLDAP->user()->authenticate($auth_u, $auth_p);

if ($b === false) {
    print "User '$auth_u': authentication FAILED!\n";
    $m_AdLDAP->close();
} else {
    print "User '$auth_u': successfully authenticated!\n";
}

$info = $m_AdLDAP->user()->infoCollection($query_u, array("mail"));
$userGroups = $m_AdLDAP->user()->groups($query_u);

print_r($info);
print_r($userGroups);

$m_AdLDAP->close();
?>

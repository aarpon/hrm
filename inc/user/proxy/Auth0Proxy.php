<?php
/**
 * IntegratedAuthenticatorV2
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm\user\proxy;

use hrm\Log;
use Auth0\SDK\Auth0;

/**
 * Manages authentication against the internal HRM  user database.
 *
 * @package hrm
 */
class Auth0Proxy extends AbstractProxy {

    /**
     * Auth0 object
     * @var Auth0
     */
    protected $auth0;

    /**
     * Auth0Proxy constructor.
     * No parameters are passed to the constructor.
     */
    public function __construct()
    {
        // Include the configuration file
        $conf = dirname(__FILE__) . "/../../../config/auth0_config.inc";
        if (! is_file($conf)) {
            $msg = "The Auth0 configuration file 'auth0_config.inc' is missing!";
            Log::error($msg);
            throw new \Exception($msg);
        }
        /** @noinspection PhpIncludeInspection */
        include($conf);

        global $AUTH0_DOMAIN, $AUTH0_CLIENT_ID,
               $AUTH0_CLIENT_SECRET, $AUTH0_REDIRECT_URI;

        $this->auth0 = new Auth0(array(
            'domain'        => $AUTH0_DOMAIN,
            'client_id'     => $AUTH0_CLIENT_ID,
            'client_secret' => $AUTH0_CLIENT_SECRET,
            'redirect_uri'  => $AUTH0_REDIRECT_URI
        ));
    }

    /**
     * Return a friendly name for the proxy to be displayed in the ui.
     * @return string 'auth0'.
     */
    public function friendlyName()
    {
        return 'Auth0';
    }

    /**
     * Authenticates the User with given username and password against the
     * configured Auth0 application.
     * @param string $username Username for authentication.
     * @param string $password Plain password for authentication.
     * @return bool True if authentication succeeded, false otherwise.
     * @throws \Exception Implement this function!
    */
    public function authenticate($username, $password)
    {
        throw new \Exception("Implement!");
    }

    /**
     * Returns the group or groups the user with given username belongs to.
     * @param string $username Username for which to query the group(s).
     * @return null|string Group or Array of groups or NULL if not found.
     * @throws \Exception Implement this function!
     */
    public function getEmailAddress($username)
    {
        throw new \Exception("Implement!");
    }

    /**
     * Returns the group the user with given username belongs to.
     * @param string $username Username for which to query the group.
     * @return string Group or "" if not found.
     * @throws \Exception Implement this function!
    */
    public function getGroup($username)
    {
        throw new \Exception("Implement!");
    }
}

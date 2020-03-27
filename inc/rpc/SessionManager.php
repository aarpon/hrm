<?php

namespace hrm\rpc;

/**
 * A SessionManager class to be bundled with JSONRPCServer.
 *
 * This class abstracts operations to the PHP session and should be used instead of
 * PHP's session managing functions and instead of accessing the $_SESSION superglobals
 * directly.
 *
 * @author Aaron Ponti
 *
 * @package hrm
 */
class SessionManager
{
    /**
     * Last (error) message.
     * @var string
     */
    private string $lastMessage = "";

    /**
     * Constructor.
     *
     * Initializes both the SessionManager object and the PHP session.
     *
     */
    public function __construct()
    {
        // Start the session
        session_start();

    }

    /**
     * Start a new session.
     *
     * The existing session is destroyed and a new one is started instead.
     */
    public function restart()
    {
        // Destroy current session
        session_unset();
        session_destroy();

        // Start a new one
        session_start();
    }

    /**
     * Destroy the session.
     */
    public function destroy()
    {
        session_unset();
        session_destroy();
    }

    /**
     * Set a key-value pair in the Session.
     *
     * @param string $key Key
     * @param object $val Value
     */
    public function set(string $key, object $val)
    {
        $_SESSION[$key] = $val;
    }

    /**
     * Get the value for requested key or null if the key does not exist.
     *
     * @param string $key Key
     * @return object|null Value of the requested key or null if the key does not exist.
     */
    public function get(string $key): ?object
    {
        return (isset($_SESSION[$key])) ? $_SESSION[$key] : null;
    }

    /**
     * Delete a key-value pair.
     *
     * @param string $key Key to be deleted.
     * @return bool True if the key-value pair could be deleted, false otherwise.
     */
    public function delete(string $key): bool
    {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
            return true;
        }
        return false;
    }

    /**
     * Return the active status of the session.
     *
     * The session is active if:
     *
     * 1. the session id passed by the client matches the session id stored
     *    in the session object;
     * 2. a user id is stored in the session as well (i.e. a user successfully
     *    authenticated).
     *
     * @param string $clientSessionId Session ID as passed by the client.
     * @return bool True if the session is active, false otherwise.
     */
    public function isActive(string $clientSessionId): bool
    {
        // Check if the ID exists in the session
        if ($clientSessionId != session_id())
        {
            $this->lastMessage = "Invalid client session ID.";
            return false;
        }

        $userID = $this->get("UserID");
        if ($userID == null)
        {
            $this->lastMessage = "No user in session.";
            return false;
        }

        $this->lastMessage = "";
        return true;
    }

    /**
     * Get current session ID.
     *
     * Return the session ID or "" if no session exists.
     *
     * @return string Session ID.
     */
    public function getSessionID(): string
    {
        return session_id();
    }

    /**
     * Return the last message.
     *
     * @return string The last message.
     */
    public function getLastMessage(): string
    {
        return $this->lastMessage;
    }
}

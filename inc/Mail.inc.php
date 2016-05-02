<?php
/**
 * Mail
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */

namespace hrm;

require_once dirname(__FILE__) . '/bootstrap.inc.php';

/**
 * Mail
 *
 * Commodity class for sending e-mails.
 */
class Mail
{
    /**
     * Sender name
     * @var string
     */
    private $sender;

    /**
     * Receiver's e-mail address
     * @var string
     */
    private $receiver;

    /**
     * E-mail subject
     * @var string
     */
    private $subject;

    /**
     * E-mail message body.
     * @var string
     */
    private $message;

    /**
     * Mail constructor.
     * @param string $sender Sender's e-mai address.
     */
    public function __construct($sender)
    {
        $this->sender = $sender;
        $this->receiver = "";
        $this->subject = "";
        $this->message = "";
    }

    /**
     * Sets the e-mail address of the receiver.
     * @param string $receiver Receiver's e-mail address
     */
    public function setReceiver($receiver)
    {
        $this->receiver = $receiver;
    }

    /**
     * Sets the subject of the e-mail.
     * @param string $subject Subject of the e-mail.
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
    }

    /**
     * Sets the message of the e-mail.
     * @param string $message Message of the e-mail.
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

    /**
     * Sends the e-mail.
     * @return bool True if the e-mail was sent successfully, false otherwise.
     */
    public function send()
    {

        // Check for completeness
        if ($this->sender == "") {
            Log::error("Mail could not be sent because no sender was specified!");
            return false;
        }
        if ($this->receiver == "") {
            Log::error("Mail could not be sent because no receiver was specified!");
            return false;
        }
        if ($this->subject == "") {
            Log::error("Mail could not be sent because no subject was specified!");
            return false;
        }
        if ($this->message == "") {
            Log::error("Mail could not be sent because no message was specified!");
            return false;
        }

        // Now send
        $header = 'From: ' . $this->sender . "\r\n";
        $header .= 'Reply-To: ' . $this->sender . "\r\n";
        $header .= 'Return-Path: ' . $this->sender . "\r\n";
        $params = '-f' . $this->sender;

        // Make sure that the script does not try forever to send an email
        // if something is wrong with the configuration.
        set_time_limit(10);
        if (mail($this->receiver, $this->subject, $this->message, $header, $params)) {
            Log::info("Mail '" . $this->subject . "' sent to " . $this->receiver);
            return true;
        } else {
            Log::error("Could not send mail '" . $this->subject . "' to " . $this->receiver);
            return false;
        }

    }
}

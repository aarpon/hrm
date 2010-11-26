<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

/*!
  \class	Mail
  \brief	Commodity class to send e-mails
  
  The content of the email is also logged to disk.
*/

class Mail {
  private $sender;
  private $receiver;
  private $subject;
  private $message;
  
  /*!
    \brief  Constructor
    \param  $sender Sender's e-mail address
  */
  public function __construct( $sender ) {
    $this->sender = $sender;
    $this->receiver = "";
    $this->subject  = "";
    $this->message  = "";
  } 
  
  /*!
    \brief  Sets the e-mail address of the receiver
    \param  $receiver Receiver's e-mail address
  */
  public function setReceiver( $receiver ) {
    $this->receiver = $receiver;
  } 

  /*!
    \brief  Sets the subject of the e-mail
    \param  $subject  Subject of the e-mail
  */
  public function setSubject( $subject ) {
    $this->subject = $subject;
  } 

  /*!
    \brief  Sets the message of the e-mail
    \param  $message  Message of the e-mail
  */
  public function setMessage( $message ) {
    $this->message = $message;
  } 

  /*!
    \brief  Sends the e-mail
    \return true if the e-mail was sent successfully, false otherwise
  */
  public function send() {

    // Check for completeness
    if ( $this->sender == "" ) {
      report( "Mail could not be sent because no sender was specified!", 1 );
      return false;
    }
    if ( $this->receiver == "" ) {
      report( "Mail could not be sent because no receiver was specified!", 1 );
      return false;
    }
    if ( $this->subject == "" ) {
      report( "Mail could not be sent because no subject was specified!", 1 );
      return false;
    }
    if ( $this->message == "" ) {
      report( "Mail could not be sent because no message was specified!", 1 );
      return false;
    }

    // Now send
    $header  = 'From: ' . $this->sender . "\r\n";
    $header .= 'Reply-To: ' . $this->sender . "\r\n";
    $header .= 'Return-Path: ' . $this->sender . "\r\n";
      
    if ( mail( $this->receiver, $this->subject, $this->message, $header ) ) {
      report( "Mail '" . $this->subject. "' sent to " . $this->receiver, 2 );
      return true;
    } else {
      report( "Could not send mail '" . $this->subject. "' to " . $this->receiver, 1 );
      return false;
    }
    
  } 
} 

?>

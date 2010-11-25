<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

class Mail {
  private $sender;
  private $receiver;
  private $subject;
  private $message;
  
  public function __construct( $sender ) {
    $this->sender = $sender;
  } 
  
  public function setReceiver( $receiver ) {
    $this->receiver = $receiver;
  } 

  public function setSubject( $subject ) {
    $this->subject = $subject;
  } 

  public function setMessage( $message ) {
    $this->message = $message;
  } 

  public function send() {
      
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

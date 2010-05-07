<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

Class Mail {
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
    mail( $this->receiver, 
          $this->subject, 
          $this->message, 
          'From: ' . $this->sender . "\r\nReturn-Path: ". $this->sender,
          "-r ". $this->sender ); 
    report( "Mail '" . $this->subject. "' sent to " . $this->receiver, 2 );
  } 
} 

?>

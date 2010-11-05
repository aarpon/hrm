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
      
    $header   = 'From: ' . $this->sender . "\r\n" .
                        'Reply-To: ' . $this->sender . "\r\n" .
                        'Return-Path: ' . $this->sender . "\r\n" .
                        'X-Mailer: PHP/' . phpversion();
      
    mail( $this->receiver, 
          $this->subject, 
          $this->message, 
          $header ); 
    report( "Mail '" . $this->subject. "' sent to " . $this->receiver, 2 );
  } 
} 

?>

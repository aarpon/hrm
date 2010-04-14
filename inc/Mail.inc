<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

Class Mail {
  var $sender;
  var $receiver;
  var $subject;
  var $message;
  
  function Mail($sender) {
    $this->sender = $sender;
  } 
  
  function setReceiver($receiver) {
    $this->receiver = $receiver;
  } 

  function setSubject($subject) {
    $this->subject = $subject;
  } 

  function setMessage($message) {
    $this->message = $message;
  } 

  function send() {
    mail($this->receiver, $this->subject, $this->message, 'From: ' . $this->sender . "\r\nReturn-Path: ".$this->sender, "-r ".$this->sender); 
    report("Mail '". $this->subject. "' sent to ".$this->receiver, 2);
  } 
} 

?>

<?php
// Module SettingEditor.inc

// This file is part of huygens remote manager.

// Copyright: Montpellier RIO Imaging (CNRS) 

// contributors : 
// 	     Pierre Travo	(concept)	     
// 	     Volker Baecker	(concept, implementation)

// email:
// 	pierre.travo@crbm.cnrs.fr
// 	volker.baecker@crbm.cnrs.fr

// Web:     www.mri.cnrs.fr
// huygens remote manager is a software that has been developed at 
// Montpellier Rio Imaging (mri) in 2004 by Pierre Travo and Volker 
// Baecker. It allows running image restoration jobs that are processed 
// by 'Huygens professional' from SVI. Users can create and manage parameter 
// settings, apply them to multiple images and start image processing 
// jobs from a web interface. A queue manager component is responsible for 
// the creation and the distribution of the jobs and for informing the user 
// when jobs finished.

// This software is governed by the CeCILL license under French law and
// abiding by the rules of distribution of free software. You can use, 
// modify and/ or redistribute the software under the terms of the CeCILL
// license as circulated by CEA, CNRS and INRIA at the following URL
// "http://www.cecill.info". 

// As a counterpart to the access to the source code and  rights to copy,
// modify and redistribute granted by the license, users are provided only
// with a limited warranty and the software's author, the holder of the
// economic rights, and the successive licensors  have only limited
// liability. 

// In this respect, the user's attention is drawn to the risks associated
// with loading, using, modifying and/or developing or reproducing the
// software by the user in light of its specific status of free software,
// that may mean that it is complicated to manipulate, and that also
// therefore means that it is reserved for developers and experienced
// professionals having in-depth IT knowledge. Users are therefore encouraged
// to load and test the software's suitability as regards their requirements
// in conditions enabling the security of their systems and/or data to be
// ensured and, more generally, to use and operate it in the same conditions
// as regards security. 

// The fact that you are presently reading this means that you have had
// knowledge of the CeCILL license and that you accept its terms.

require_once("Setting.inc");
require_once("Database.inc");
require_once("User.inc");

//!---------------------------------------------------------
// @class    SettingEditor
// @desc     Editor for parameter settings
//!---------------------------------------------------------
Class SettingEditor {
  var $user;            // @var user          User    The current user    
  var $message;         // @var message       String  A message reporting errors that occured in the last operation
  var $selected;        // @var selected      String  The name of the setting that is currently selected

  //!---------------------------------------------------------
  // @function    SettingEditor::SettingEditor
  // @desc        Konstruktor. Creates a new SettingEditor and
  //              selects the default setting if a default 
  //              setting exists.
  // @param       user    User    The current user
  // @return      void
  //!---------------------------------------------------------
  function SettingEditor($user) { 
    $this->user = $user;
    $this->message = '';
    foreach ($this->settings() as $setting) {
      if ($setting->isDefault()) { 
	$this->selected = $setting->name();
      }
    } 
  }

  //!---------------------------------------------------------
  // @function    SettingEditor::selected
  // @desc        Answers the name of the currently selected
  //              setting or NULL. 
  // @return      String
  //!---------------------------------------------------------
  function selected() {
    return $this->selected;
  } 

  //!---------------------------------------------------------
  // @function    SettingEditor::setSelected
  // @desc        Set the name of the selected setting
  // @param       name    String    the name of the setting
  //                                the user selected.
  // @return      void
  //!---------------------------------------------------------
  function setSelected($name) {
    $this->selected = $name;
  } 

  //!---------------------------------------------------------
  // @function    SettingEditor::user
  // @desc        Answer the current user
  // @return      User
  //!---------------------------------------------------------
  function user() { 
    return $this->user;
  } 

  //!---------------------------------------------------------
  // @function    SettingEditor::createNewSetting
  // @desc        Check if the name already exists. If not
  //              create a new setting named name. Select
  //              the new setting
  // @param       name    String    the name of the new setting
  // @return      Setting
  //!---------------------------------------------------------
  function createNewSetting($name) {
    if (!$this->checkNewSettingName($name)) { 
      return NULL;
    }
    $setting = $this->newSettingObject(); 
    $setting->setName($name);
    $setting->setOwner($this->user);
    $this->setSelected($name);
    return $setting;
  }

  //!---------------------------------------------------------
  // @function    SettingEditor::newSettingObject
  // @desc        Answer a new setting object of the type 
  //              that the receiver is editing  
  // @return      Setting
  //!---------------------------------------------------------
  function newSettingObject() {
    return (new ParameterSetting());
  } 

  //!---------------------------------------------------------
  // @function    SettingEditor::copySelectedSetting
  // @desc        create a new setting newName in the database 
  //              and copy the parameter values of the existing 
  //              setting oldName to it.
  // @param       oldName    String  the name of an existing setting
  // @param       newname    String  the name of the new setting
  // @return      bool
  //!---------------------------------------------------------
  function copySelectedSetting($newName) {
    if (!$this->checkSelectedSetting()) {
      return False;
    };
    $settings = $this->settings();
    $oldSettingName = $this->selected();
    $oldSetting = $settings[$oldSettingName];
    $oldSetting = $oldSetting->load();
    $newSetting = $this->createNewSetting($newName);
    if ($newSetting==NULL) {
      return False;
    } 
    $newSetting->copyParameterFrom($oldSetting);
    $result = $newSetting->save();
    $this->message = $newSetting->message();
    return $result;
  }

  // add public setting support
  //!---------------------------------------------------------
  // @function    SettingEditor::copySelectedSetting
  // @desc        create a new setting in the database
  //              and copy the parameter values of the public
  //              setting to it.
  // @param       setting    Setting  the existing setting
  // @return      bool
  //!---------------------------------------------------------
  function copyPublicSetting($setting) {
    /*$settings = $this->settings();
    $oldSettingName = $this->selected();
    $oldSetting = $settings[$oldSettingName];
    $oldSetting = $oldSetting->load();*/
    
    $newSetting = $this->createNewSetting($setting->name);
    if ($newSetting == null) {
      return False;
    } 
    $newSetting->copyParameterFrom($setting);
    $result = $newSetting->save();
    $this->message = $newSetting->message();
    return $result;
  } 

  //!---------------------------------------------------------
  // @function    SettingEditor::loadSetting
  // @desc        Load the values of the setting name from the
  //              database and answer the setting. Select the
  //              loaded setting.
  // @param       name     the name of the setting to be loaded
  // @return      Setting
  //!---------------------------------------------------------
  function loadSelectedSetting() {
    if (!$this->checkSelectedSetting()) {
      return False;
    };
    $name = $this->selected();
    $settings = $this->settings();
    $setting = $settings[$name];
    $setting = $setting->load();
    $this->setSelected($name);
    return $setting;
  } 

  //!---------------------------------------------------------
  // @function    SettingEditor::makeDefault
  // @desc        make the setting name the default setting.
  //              The default setting will be preselected the
  //              next time the user enters the page
  // @param       name     the name of the setting 
  // @return      void
  //!---------------------------------------------------------
  function makeSelectedSettingDefault() { 
    if (!$this->checkSelectedSetting()) {
      return False;
    };
    $name = $this->selected();
    foreach ($this->settings() as $setting) {
      if ($setting->isDefault()) {
	$setting->resetDefault();
	$db = new DatabaseConnection();
	$db->updateDefault($setting);
      }
      if ($setting->name() == $name) {
	$setting->beDefault();
	$db = new DatabaseConnection();
	$db->updateDefault($setting);
      }
    }
  }

  //!---------------------------------------------------------
  // @function    SettingEditor::delete
  // @desc        delete the setting named name if it exists
  // @param       name     the name of the setting 
  // @return      bool
  //!---------------------------------------------------------
  function deleteSelectedSetting() {
    if (!$this->checkSelectedSetting()) {
      return False;
    };
    $name = $this->selected();
    $settings = $this->settings();
    if (!isset($settings[$name])) {
      return False;
    }
    $db = new DatabaseConnection();
    if (!$db->deleteSetting($settings[$name])) {
      $this->message = "delete setting - database error";
      return False;
    } 
    return True;
  } 

  //!---------------------------------------------------------
  // @function    SettingEditor::message
  // @desc        Answer the error message that has been
  //              set by the last operation
  // @return      string
  //!---------------------------------------------------------
  function message() { 
    return $this->message;
  } 

  //!---------------------------------------------------------
  // @function    SettingEditor::checkNewSettingName
  // @desc        Check that the name is not empty and that
  //              there doesn't exist a setting with the same
  //              name
  // @param       name    String    the name of the new setting
  // @return      bool
  //!---------------------------------------------------------
  function checkNewSettingName($name) { 
    $this->message = '';
    $names = array();
    foreach ($this->settings() as $setting) {
      $names[] = $setting->name(); 
    } 
    if (trim($name)=='') {
      $this->message = "Please enter a name for the setting and try again!";      
      return False;
    } 
    if (in_array($name, $names)) { 
      $this->message = "A setting with the name $name already exists. Please enter another name!";
      return False;
    } 
    return True;
  }

  //!---------------------------------------------------------
  // @function    SettingEditor::settings
  // @desc        Answer a collection of all settings of the 
  //              user.
  // @return      Array of Setting
  //!---------------------------------------------------------
  function settings() {
    $settings = array();
    $user = $this->user;
    $db = new DatabaseConnection();
    $results = $db->query($db->settingsQueryString($user->name(), $this->table()));    
    foreach ($results as $row) { 
      $setting = $this->newSettingObject();
      $setting->setName($row['name']);
      $setting->setOwner($user);  
      if($row['standard'] == 't') { 
	$setting->beDefault();
      }
      $settings[$row['name']] = $setting;
    } 
    return $settings;
  }
  // TODO refactor
  // add public setting support
  //!---------------------------------------------------------
  // @function    SettingEditor::setting
  // @desc        Answer the settings with the specified name.
  // @param       name    String    the name of the setting
  // @return      Setting
  //!---------------------------------------------------------
  function setting($name) {
    $user = $this->user;
    $db = new DatabaseConnection();
    $results = $db->query($db->settingsQueryString($user->name(), $this->table()));
    foreach ($results as $row) {
      if ($row['name'] == $name) {
	$setting = $this->newSettingObject();
	$setting->setName($row['name']);
	$setting->setOwner($user);
	$setting = $setting->load();
	return $setting;
      }
    }
    return null;
  }

  //!---------------------------------------------------------
  // @function    SettingEditor::defaultSettingName
  // @desc        Answer the name of the default setting if it
  //              exists and NULL otherwise.
  // @return      string
  //!---------------------------------------------------------  
  function defaultSettingName() {
    foreach ($this->settings() as $setting) {
      if ($setting->isDefault()) {
	return $setting->name();
      }
    }
    return NULL;
  }

  //!---------------------------------------------------------
  // @function    SettingEditor::table
  // @desc        Answer the name of the database table in
  //              which settings that the receiver edits are
  //              stored
  // @return      string
  //!---------------------------------------------------------  
  function table() { 
    return "parameter_setting";
  }

  //!---------------------------------------------------------
  // @function    SettingEditor::checkSelectedSetting
  // @desc        Answer true if a existing setting is selected.
  // @return      boolean
  //!---------------------------------------------------------  
  function checkSelectedSetting() {
    $this->message = '';
    $result = True;
    $nameOfSelectedSetting = $this->selected();
    if ($nameOfSelectedSetting=='') {
      $result = False;
    } 
    $settings = $this->settings();
    if (!isset($settings[$nameOfSelectedSetting])) {
      $result = False;
    }
    if (!$result) {
      $this->message = "Please select a setting in the list before pressing the button!";
    } 
    return $result;
  } 
}

//!---------------------------------------------------------
// @class    TaskSettingEditor
// @desc     Editor for task settings
//!---------------------------------------------------------
Class TaskSettingEditor extends SettingEditor {

  //!---------------------------------------------------------
  // @function    SettingEditor::table
  // @desc        Answer the name of the database table in
  //              which task settings are stored
  // @return      string
  //!---------------------------------------------------------  
  function table() { 
    return "task_setting";
  } 

  //!---------------------------------------------------------
  // @function    SettingEditor::newSettingObject
  // @desc        Answer a new setting object of the type 
  //              that the receiver is editing  
  // @return      TaskSetting
  //!---------------------------------------------------------
  function newSettingObject() {
    return (new TaskSetting());
  }
}
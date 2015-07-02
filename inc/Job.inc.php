<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once ("Setting.inc.php");
require_once ("JobDescription.inc.php");
require_once ("hrm_config.inc.php");
require_once ("Fileserver.inc.php");
require_once ("HuygensTemplate.inc.php");
require_once ("GC3PieController.inc.php");

/*!
  \class    Job
  \brief    Stores all information for a job.
 */
class Job {

    /*!
      \var      $controller
      \brief    Contains a GC3Pie controller to run a job
    */
    private $controller;

    /*!
      \var      $huTemplate
      \brief    Contains a Huygens Batch template
    */
    private $huTemplate;

    /*!
     \var      $jobDescription
     \brief    JobDescription object: microscopic & restoration data.
    */
    private $jobDescription;


    /* ---------------------------- Public methods --------------------------- */
    
    /*!
     \brief Constructor
     \param $jobDescription JobDescrition object
    */
    public function __construct($jobDescription) {
        $this->initialize($jobDescription);
    }

    /*!
      \brief  Splits the job into smaller parts when possible.
      \brief  If the job cannot be split further, it submits it to the queue.
    */
    public function process () {
        $jobDescription = $this->description();
        
        if ($jobDescription->isCompound()) {
            $this->createSubJobs();
        } else {
            $this->createJobControllers();
        }
    }

    /* ------------------------------------------------------------------------ */

    
    /*!
     \brief       Sets general class properties to initial values
     \param       $jobDescription A jobDescription object
    */
    private function initialize($jobDescription) {
        $this->huTemplate = '';
        $this->jobDescription = $jobDescription;
    }

    /*!
     \brief     Returns the JobDescription associated with the Job
     \return    JobDescription object
    */
    private function description() {
        return $this->jobDescription;
    }
    
    /*!
     \brief	Returns the Huygens template name (it contains the unique id)
     \return	the template name
    */
    private function huTemplateName() {
        $jobDescription = $this->description();
        return $jobDescription->getHuTemplateName();
    }
    
    /*!
     \brief	Returns the GC3Pie controller name containing the unique job id
     \return	the sript name
    */
    private function gc3ControllerName() {
        $jobDescription = $this->description();
        return $jobDescription->getGC3PieControllerName();
    }

    /*!
     \brief	Returns the Huygens template generated for the Job
     \return	huTemplate
    */
    private function getHuTemplate() {
        return $this->huTemplate;
    }
    
    /*!
     \brief	Creates a Huygens Template
    */
    private function createHuygensTemplate() {   
        $jobDescription = $this->description();
        $huTemplate = new HuygensTemplate($jobDescription);
        $this->huTemplate = $huTemplate->template;
    }

    /*!
     \brief     Creates a job controller for GC3Pie
    */
    private function createGC3PieController() {
        $jobDescription = $this->description();
        $jobDescription->setTaskType("decon");
        $gc3Pie = new GC3PieController($jobDescription);
        $this->controller = $gc3Pie;
    }

    /*!
      \brief   Submits the job to the queue by creating the file controllers.
    */
    private function createJobControllers() {
        report("Job is elementary", 1);
        
        $this->createHuygensTemplate();
        $this->writeHuTemplate();
        report("Created Huygens template", 1);
        
        $this->createGC3PieController();
        $this->controller->write2Spool();
        report("Created GC3Pie controller", 1);

        return $result;
    }

    /*!
      \brief   Splits  the job into smaller parts.
    */
    private function createSubJobs( ) {
        $jobDescription = $this->description();
        
        if ($jobDescription->createSubJobs()) {
            report("created sub jobs", 1);
        
            $queue = new JobQueue();
            if ($queue->removeJobs(
                $jobDescription->id(),
                $jobDescription->owner())) {
                
                report("removed compound job\n", 1);
            }
        }
    }
        
    /*!
     \brief	    Writes the template to the user's source folder
     \return	true if the template could be written, false otherwise
    */
    private function writeHuTemplate() {
        $jobDescription = $this->description();
        
        $user = $jobDescription->owner();
        $username = $user->name();
        $fileserver = new Fileserver($username);
        
        $templateName = $this->huTemplateName();
        $templatePath = $fileserver->sourceFolder();
        $templateFile = $templatePath . "/" . $templateName;
        $templateHandler = fopen($templateFile, "w");
        
        if ( !$templateHandler ) {
            report ("Error opening file $templateFile, verify permissions!", 0);
            report ("Waiting 15 seconds...", 1);
            sleep(15);
        } else {
            fwrite($templateHandler, $this->huTemplate);
            fclose($templateHandler);
            report("Wrote template $templateFile", 1);
        }
    }
}

?>

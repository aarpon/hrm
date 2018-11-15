<?php
/**
 * Job
 *
 * @package hrm
 *
 * This file is part of the Huygens Remote Manager
 * Copyright and license notice: see license.txt
 */
namespace hrm\job;

use hrm\shell\ExternalProcessFactory;
use hrm\shell\ExternalProcess;
use hrm\Fileserver;
use hrm\HuygensTemplate;
use hrm\Log;
use hrm\SnijderJobConfiguration;

require_once dirname(__FILE__) . '/../bootstrap.php';


/**
 * Stores all information for a job.
 */
class Job
{
    /**
     * Contains a Snijder configuration file to run a job.
     * @var string
     */
    private $controller;

    /**
     * Contains a Huygens Batch template.
     * @var string
     */
    private $huTemplate;

    /**
     * JobDescription object: microscopic, restoration and analysis data.
     * @var JobDescription
     */
    private $jobDescription;


    /* ---------------------------- Public methods --------------------------- */

    /**
     * Job constructor.
     * @param $jobDescription JobDescription object.
     */
    public function __construct(JobDescription $jobDescription)
    {
        $this->initialize($jobDescription);
    }

    /**
     * Splits the job into smaller parts when possible.
     * If the job cannot be split further, it submits it to the queue.
     */
    public function process () {
        $jobDescription = $this->description();        
        $this->createJobControllers();        
    }

    /* ------------------------------------------------------------------------ */

    /**
     * Sets general class properties to initial values.
     * @param JobDescription $jobDescription A jobDescription object.
     */
    private function initialize(JobDescription $jobDescription)
    {

        $this->huTemplate = '';
        $this->jobDescription = $jobDescription;
    }

    /**
     * Returns the JobDescription associated with the Job.
     * @return JobDescription The JobDescription object.
     */
    private function description()
    {
        return $this->jobDescription;
    }

    /**
     * Returns the Huygens template name (it contains the unique id)
     * @return string The template name.
     */
    private function huTemplateName()
    {
        $jobDescription = $this->description();
        return $jobDescription->getHuTemplateName();
    }

    /**
     * Returns the Snijder configuration file name containing the unique job id
     * @return string The script name
     */
    private function snijderControllerName() {
        $jobDescription = $this->description();
        return $jobDescription->getSnijderControllerName();
    }

    /**
     * Returns the Huygens template generated for the Job
     * huTemplate
     */
    private function getHuTemplate() {
        return $this->huTemplate;
    }    

    /**
     * Creates and sets a Huygens Template.
     * TODO: pass the correct GPU ID to the template generator.
     */
    private function createHuygensTemplate()
    {        
        $jobDescription = $this->description();

        $huTemplate = new HuygensTemplate($jobDescription);
        $this->huTemplate = $huTemplate->template;
    }

    /**
     * Creates a job configuration file for Snijder
     */
    private function createSnijderJobConfiguration() {
        $jobDescription = $this->description();
        $jobDescription->setTaskType("decon");
        $snijderJobConfiguration = new SnijderJobConfiguration($jobDescription);
        $this->controller = $snijderJobConfiguration;
    }

    /**
     * Submits the job to the queue by creating the file controllers.
     */
    private function createJobControllers() {
        Log::info("Job is elementary", 1);
        
        $this->createHuygensTemplate();
        $this->writeHuTemplate();
        Log::info("Created Huygens template", 1);
        
        $this->createSnijderJobConfiguration();
        $this->controller->write2Spool();
        Log::info("Created Snijder job configuration file", 1);
    }
    
    /**
     * Writes the template to the user's source folder
     * true if the template could be written, false otherwise.
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
            Log::info("Error opening file $templateFile, verify permissions!", 0);
            Log::info("Waiting 15 seconds...", 1);
            sleep(15);
        } else {
            fwrite($templateHandler, $this->huTemplate);
            fclose($templateHandler);
            Log::info("Wrote template $templateFile", 1);
        }
    }
}

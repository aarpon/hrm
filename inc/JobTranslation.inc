<?php
  // This file is part of the Huygens Remote Manager
  // Copyright and license notice: see license.txt

  /*!
      \class  JobTranslation
      \brief  Converts deconvolution parameters into Tcl lists.

      This class builds Tcl-compliant nested lists (templates) which summarize the
      tasks and properties of the deconvolution job. The resulting lists
      are interpreted by HuCore and executed accordingly to perform a 
      deconvolution.
    */
Class JobTranslation {

    /*!
      \var    $taskList
      \brief  A Tcl list with microscopic and restoration data
    */
    public $taskList;

    /*!
      \var    $envList
      \brief  A Tcl list with extra data: number of cores, timeout, etc. 
    */
    public $envList;

    /*!
      \var    $srcImage
      \brief  Path and name of the source image.
    */
    public $srcImage;

    /*!
      \var    $taskList
      \brief  Path and name of the deconvolved image.
    */
    public $destImage;

    /*!
      \var    $jobDescription
      \brief  A JobDescription object: microscopic and restoration data
    */
    private $jobDescription;

    /*!
      \var    $microSetting
      \brief  A ParametersSetting object: microscopic parameters.
    */
    private $microSetting;

    /*!
      \var    $deconSetting
      \brief  A TaskSetting object: restoration parameters.
    */
    private $deconSetting;

    /*!
      \var    $envArray
      \brief  Array with extra data: number of cores, timeout, etc.
    */
    private $envArray;

    /*!
      \var    $taskArray
      \brief  Array with microscopic and restoration data.
    */
    private $tasksArray;

    /* ---------------------------- Initialize --------------------------------- */

    /*!
     \brief       Constructor
     \param       $jobDescription JobDescription object
    */
    public function __construct($jobDescription) {
        $this->setJobParameters($jobDescription);
        $this->setJobEnvironmentList();
        $this->setJobTaskList();
    }


    /*!
     \brief       Sorts and sets job parameters
     \param       $jobDescription JobDescription object
    */
    private function setJobParameters($jobDescription) {
        $this->jobDescription = $jobDescription;
        $this->microSetting = $jobDescription->parameterSetting;
        $this->deconSetting = $jobDescription->taskSetting;
        $this->setEnvironmentArray();
        $this->setTasksArray();
        $this->setSrcImage();
        $this->setDestImage();
    }

    /*!
     \brief       Sets array with extra data: number of cores, timeout, etc.
    */
    private function setEnvironmentArray( ) {
        $this->envArray = array ( 'resultDir'=>'',
                                  'perJobThreadCnt'=>'auto',
                                  'concurrentJobCnt'=>'1',
                                  'OMP_DYNAMIC'=>'1',
                                  'timeOut'=>'10000',
                                  'exportFormat'=>'' );
    }

    /*!
     \brief       Sets array with microscopic and restoration data
     \todo        Add a field zdrift when it is implemented in the GUI.
    */
    private function setTasksArray( ) {
        $this->tasksArray = array ( 'open'=>'imgOpen',
                                    'setParameters'=>'setp',
                                    'adjustBaseline'=>'adjbl',
                                    'algorithms'=>'',
                                    'save'=>'imgSave');
    }

    /* ------------------------ Main lists handlers ---------------------------- */

    /*!
     \brief        Sets a Tcl list with extra data: number of cores, timeout, etc. 
    */
    private function setJobEnvironmentList( ) {
        $this->envList = $this->buildEnvironmentList();
    }
    
    /*!
     \brief        Sets a Tcl list with microscopic and restoration data. 
    */
    private function setJobTaskList( ) {
        $this->taskList = $this->buildTaskList();
    }

    /*!
     \brief        Builds a Tcl list with extra data: number of cores, etc. 
     \return       The Tcl-compliant nested list
    */
    private function buildEnvironmentList( ) {
        $environmentList = "";
        foreach ($this->envArray as $envOption => $envValue) {
            if ($envOption == "resultDir") {
                $envValue = $this->getDestDir();
            }
            if ($envOption == "exportFormat") {
                $envValue = "type ". $this->getOutputFileType().
                    " multidir 0 cmode scale";
                $envValue = $this->string2tcllist($envValue);
            }
            $environmentList .= "$envOption $envValue ";
        }
        
        return $environmentList;
    }

    /*!
     \brief        Builds a Tcl list with microscopic and restoration data. 
     \return       The Tcl-compliant nested list
    */
    private function buildTaskList( ) {
        $taskList = $this->getDeconTasks();
        $taskList .= $this->getTasksDetails();
        return $taskList;
    }

    /*!
     \brief        Gets the tasks to perform a deconvolution. 
     \return       The Tcl-compliant nested list with task names
    */
    private function getDeconTasks( ) {
        $taskList = "";
        foreach ($this->tasksArray as $key => $task) {
            if ($task == '') {
                $task = $this->parseTask($key);
            }
            $taskList .= "$task ";
        }
        $taskList = $this->string2tcllist($taskList);
        $taskList = " taskList $taskList";
        return $taskList;
    }


    /*!
     \brief        Gets specific details of each deconvolution task. 
     \return       The Tcl-compliant nested list with task details
    */
    private function getTasksDetails( ) {
        $taskList = "";
        foreach ($this->tasksArray as $key => $task) { 
            switch ( $key ) {
            case "open":
                $taskList .= $this->getTaskImgOpen();
                break;
            case "save":
                $taskList .= $this->getTaskImgSave();
                break;
            case "setParameters":
                $taskList .= $this->getTaskSetp();
                break;
            case "adjustBaseline":
                $taskList .= $this->getTaskAdjbl();
                break;
            case "algorithms":
                $taskList .= $this->getTaskAlgorithms();
                break;
            case "zdrift":
                $taskList .= $this->getTaskZDrift();
                break;
            default:
                $taskList = "";
                break;
            }
        }
        return $taskList;
    }

    /*!
     \brief        Gets the name of the chosen deconvolution method per channel
     \param        $key A deconvolution task
     \return       One deconvolution method per channel
    */
    private function parseTask($key) {
        if ($key == "algorithms") {
            $numberOfChannels = $this->getNumberOfChannels();
            $algorithms = "";
            for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
                $algorithms .= $this->getAlgorithm().":$chanCnt ";
            }
            $algorithms = trim($algorithms);
            return $algorithms;
        } else {
            error_log("Missing task handler.");
            return;
        }
    }

    /* ------------------------------- Tasks ----------------------------------- */
    
    /*!
     \brief        Gets options for the 'image open' task
     \return       Tcl list with the'Image open' task and its options
    */
    private function getTaskImgOpen( ) {
        $srcFileName = $this->jobDescription->sourceImageName();
        $imgOpen =  "path $srcFileName series auto index 0";
        $imgOpen = $this->string2tcllist($imgOpen);
        $imgOpen = " imgOpen $imgOpen";
        return $imgOpen;
    }

    /*!
     \brief        Gets options for the 'set parameters' task
     \return       Tcl list with the 'Set parameters' task and its options
    */
    private function getTaskSetp( ) {
        $options = "completeChanCnt ";
        $options .= $this->getNumberOfChannels();
        $options .= " iFacePrim ";
        $options .= $this->getiFacePrim();
        $options .= " prState,iFacePrim ";
        $options .= $this->getiFacePrimVerification();
        $options .= " iFaceSnd ";
        $options .= $this->getiFaceScnd();
        $options .= " prState,iFaceScnd ";
        $options .= $this->getiFaceScndVerification();
        $options .= " missingOptList ";
        $options .= $this->getMissingOptList();
        $options .= " pr ";
        $options .= $this->getPinholeRadius();
        $options .= " parState,pr ";
        $options .= $this->getPRVerifyList();
        $options .= " parState,imagingDir ";
        $options .= $this->getImagingDirVerification();
        $options .= " imagingDir ";
        $options .= $this->getImagingDirection();
        $options .= " parState,ps ";
        $options .= $this->getPinSpacingVerification();
        $options .= " ps ";
        $options .= $this->getPinholeSpacing();
        $options .= " s ";
        $options .= $this->getSamplingSizes();
        $options .= " parState,s ";
        $options .= $this->getSamplingVerification();
        $options .= " parState,objQuality ";
        $options .= $this->getObjQualityVerification();
        $options .= " objQuality ";
        $options .= $this->getObjQuality();
        $options .= " parState,pcnt ";
        $options .= $this->getExcitationPcntVerification();
        $options .= " pcnt ";
        $options .= $this->getExcitationPcnt();
        $options .= " ex ";
        $options .= $this->getExcitationLambda();
        $options .= " parState,ex ";
        $options .= $this->getExcitationLVerification();
        $options .= " parState,exBeamFill ";
        $options .= $this->getExBeamVerification();
        $options .= " exBeamFill ";
        $options .= $this->getExBeamFill();
        $options .= " parState,em ";
        $options .= $this->getEmissionLVerification();
        $options .= " em ";
        $options .= $this->getEmissionLambda();
        $options .= " parState,ri ";
        $options .= $this->getMediumRIndexVerification();
        $options .= " ri ";
        $options .= $this->getMediumRefractiveIndex();
        $options .= " micr ";
        $options .= $this->getMicroscopeType();
        $options .= " parState,micr ";
        $options .= $this->getMicroTypeVerification();
        $options .= " allVerified ";
        $options .= $this->getAllVerified();
        $options .= " parState,ril ";
        $options .= $this->getLensRIndexVerification();
        $options .= " ril ";
        $options .= $this->getLensRefractiveIndex();
        $options .= " parState,na ";
        $options .= $this->getNumericalAVerification();
        $options .= " na ";
        $options .= $this->getNumericalAperture();
        $options = $this->string2tcllist($options);
        $setp = " setp $options";
        return $setp;
    }

    /*!
     \brief        Gets options for the 'image save' task
     \return       Tcl list with the 'Image save' task and its options
    */
    private function getTaskImgSave( ) {
        $dstFileName = $this->jobDescription->destinationImageFullName();
        $imgSave = "rootName $dstFileName";
        $imgSave = $this->string2tcllist($imgSave);
        $imgSave = " imgSave $imgSave";
        return $imgSave;
    }

    /*!
     \brief        Gets options for the 'adjust baseline' task
     \return       Tcl list with the 'Adjust baseline' task and its options
    */
    private function getTaskAdjbl( ) {
        $adjbl = "enabled 0 ni 0";
        $adjbl = $this->string2tcllist($adjbl);
        $adjbl = " adjbl $adjbl";
        return $adjbl;
    }

    /*!
     \brief        Gets options for the 'algorithm' task. All channels.
     \return       Deconvolution 'algorithm' task string and its options.
    */
    private function getTaskAlgorithms( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $algorithms = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $algorithm = $this->getAlgorithm($chanCnt);
            $algOptions = $this->getTaskAlgorithm($chanCnt);
            $algorithms .= " ${algorithm}:$chanCnt $algOptions";
        }

        return $algorithms;
    }

    /*!
     \brief        Gets options for the 'algorithm' task. One channel.
     \param        $channel A channel
     \return       Tcl list with the deconvolution 'algorithm' task and its options
    */
    private function getTaskAlgorithm($channel) {
        $options = "q ";
        $options .= $this->getQualityFactor();
        $options .= " brMode ";
        $options .= $this->getBrMode();
        $options .= " it ";
        $options .= $this->getIterations();
        $options .= " bgMode ";
        $options .= $this->getBgMode();
        $options .= " bg ";
        $options .= $this->getBgValues();
        $options .= " sn ";
        $options .= $this->getSnrValues();
        $options .= " blMode ";
        $options .= $this->getBleachingMode();
        $options .= " pad ";
        $options .= $this->getPaddingMode();
        $options .= " mode ";
        $options .= $this->getIterationMode();
        $options .= " psfMode ";
        $options .= $this->getPsfMode();
        $options .= " psfPath ";
        $options .= $this->getPsfPath($channel);
        $options .= " timeOut ";
        $options .= $this->getTimeOut();
        $options = $this->string2tcllist($options);
        return $options;
    }

    /*!
     \brief        Gets options for the 'zdrift' task.
     \return       Tcl list with the 'zdrift' task and its options
     \todo         To be implemented in the GUI
    */
    private function getTaskZDrift( ) {
        $zdrift = "enabled 1 survey single chan 0 ".
            "filter median filterSize 3";
        $zdrift = $this->string2tcllist($zdrift);
        $zdrift = " zdrift $zdrift";
        return $zdrift;
    }

    /* -------------------------- Setp tasks ----------------------------------- */
    
    /*!
     \brief        Gets the position of the coverslip.
     \return       Position of the coverslip
     \todo         To be implemented in the GUI
    */
    private function getiFacePrim( ) {
        $iFacePrim = 0.0;
        return $iFacePrim;
    }

    /*!
     \brief        Gets whether the position of the coverslip is verified
     \return       True if the position of the coverslip is verified
     \todo         To be implemented in the GUI
    */
    private function getiFacePrimVerification( ) {
        $iFacePrim = $this->getiFacePrim();
        if ($this->isFieldVerified($iFacePrim) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    /*!
     \brief        Gets the position of the preparation glass.
     \return       Position of the preparation glass
     \todo         To be implemented in the GUI
    */
    private function getiFaceScnd( ) {
        $iFaceScnd = 1000000.0;
        return $iFaceScnd;
    }

    /*!
     \brief        Gets whether the position of the prep glas is verified
     \return       True if the position of the preparation glass is verified
     \todo         To be implemented in the GUI
    */
    private function getiFaceScndVerification( ) {
        $iFaceScnd = $this->getiFaceScnd();
        if ($this->isFieldVerified($iFaceScnd) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    /*!
     \brief        No purpose yet
     \todo         To be removed if the template works without it.
    */
    private function getMissingOptList( ) {
        $missingOptList = "";
        $missingOptList = $this->string2tcllist($missingOptList);
        return $missingOptList;
    }

    /*!
     \brief        Gets the pinhole radii. All channels.
     \return       Tcl list with the Pinhole radii.
    */
    private function getPinholeRadius( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $prList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $pinRadius = $this->getPinRadiusForChannel($chanCnt);
            $prList .= " $pinRadius ";
            $prList = trim($prList);
        }
        $prList = $this->string2tcllist($prList);
        return $prList;
    }

    /*!
     \brief        Gets the pinhole radius. One channel.
     \param        $channel A channel
     \return       The pinhole radius.
    */
    private function getPinRadiusForChannel($channel) {
        $microSetting = $this->microSetting;
        $pinholeSize = $microSetting->parameter("PinholeSize")->value();       
        $pinholeRadius = "$pinholeSize[$channel]";
        return $pinholeRadius;
    }

    /*!
     \brief        Verification of the pinhole radii. All channels.
     \return       Tcl-list: Trues/Falses if the pinhole radii are verified.
     \todo         To be implemented in the GUI
    */
    private function getPRVerifyList( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $prList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $verification = $this->getPRVerificationForChannel($chanCnt);
            $prList .= " $verification ";
            $prList = trim($prList);
        }
        $prList = $this->string2tcllist($prList);
        return $prList;
    }

    /*!
     \brief        Verification of the pinhole radius. One channel.
     \param        $channel A channel
     \return       True if the pinhole radius is verified.
     \todo         To be implemented in the GUI
    */
    private function getPRVerificationForChannel($channel) {
        $microSetting = $this->microSetting;
        $pinholeSize = $microSetting->parameter("PinholeSize")->value();       
        $pinholeRadius = " $pinholeSize[$channel]";

        if($this->isFieldVerified($pinholeRadius)) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    /*!
     \brief        Gets the microscope type of each channel. All channels.
     \return       The microscope type list
    */
    private function getMicroscopeType( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $microList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $microType = $this->getMicroTypeForChannel($chanCnt);
            $microList .= " $microType ";
            $microList = trim($microList);
        }
        $microList = $this->string2tcllist($microList);
        return $microList;
    }

    /*!
     \brief        Gets the microscope type of all channels.
     \param        $channel A channel
     \return       Tcl-list with the microscope types of all channels.
     \todo         To be implemented in the GUI
    */
    private function getMicroTypeForChannel($channel) {
        $microSetting = $this->microSetting;
        $microType = $microSetting->parameter('MicroscopeType')->translatedValue();
        return $microType;
    }

    /*!
     \brief        Verification of the microscope type. All channels.
     \return       Tcl-list: Trues/Falses if the microscope types are verified.
     \todo         To be implemented in the GUI
    */
    private function getMicroTypeVerification( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $verificationList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $verification = $this->getMTVerificationForChannel($chanCnt);
            $verificationList .= " $verification ";
            $verificationList = trim($verificationList);
        }
        $verificationList = $this->string2tcllist($verificationList);
        return $verificationList;
    }

    /*!
     \brief        Verification of the microscope type. One channel.
     \param        $channel A channel
     \return       True if the microscope type is verified.
     \todo         To be implemented in the GUI
    */
    private function getMTVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    /*!
     \brief        Verification of all the template parameters.
     \return       True if all fields are verified by the user.
     \todo         To be implemented, both here and in the GUI. 
    */
    private function getAllVerified( ) {
        return 1;
    }

    /*!
     \brief        Gets the refractive indexes of all channels.
     \return       Tcl-list with the refractive indexes.
    */
    private function getLensRefractiveIndex( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $lensRIList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $lensRI = $this->getLensRIForChannel($chanCnt);
            $lensRIList .= " $lensRI ";
            $lensRIList = trim($lensRIList);
        }
        $lensRIList = $this->string2tcllist($lensRIList);
        return $lensRIList; 
    }

    /*!
     \brief        Gets the refractive index of one channel.
     \param        $channel A channel
     \return       The refractive index.
    */
    private function getLensRIForChannel($channel) {
        $microSetting = $this->microSetting;
        $lensRI = $microSetting->parameter('ObjectiveType')->translatedValue();
        return $lensRI;
    }

    /*!
     \brief        Verification of the refractive index. All channels.
     \return       Tcl-list: Trues/Falses if the refractive indexes are verified.
     \todo         To be implemented in the GUI
    */
    private function getLensRIndexVerification( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $verificationList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $verification = $this->getLRIVerificationForChannel($chanCnt);
            $verificationList .= " $verification ";
            $verificationList = trim($verificationList);
        }
        $verificationList = $this->string2tcllist($verificationList);
        return $verificationList;
    }

    /*!
     \brief        Verification of the refractive index. One channel.
     \param        $channel A channel
     \return       True if the refractive index is verified.
     \todo         To be implemented in the GUI
    */
    private function getLRIVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;  
    }

    /*!
     \brief        Gets the numerical apertures. All channels.
     \return       Tcl-list with the numerical apertures.
    */
    private function getNumericalAperture( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $numAperList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $numAper = $this->getNumApertureForChannel($chanCnt);
            $numAperList .= " $numAper ";
            $numAperList = trim($numAperList);
        }
        $numAperList = $this->string2tcllist($numAperList);
        return $numAperList;
    }

    /*!
     \brief        Gets the numerical aperture. One channel.
     \param        $channel A channel
     \return       The numerical aperture.
    */
    private function getNumApertureForChannel($channel) {
        $microSetting = $this->microSetting;
        $numAper = $microSetting->parameter('NumericalAperture')->value();
        return $numAper;
    }

    /*!
     \brief        Verification of the numerical apertures. All channels.
     \return       Tcl-list: Trues/Falses if the numerical apertures are verified.
     \todo         To be implemented in the GUI
    */
    private function getNumericalAVerification( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $verificationList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $verification = $this->getNAVerificationForChannel($chanCnt);
            $verificationList .= " $verification ";
            $verificationList = trim($verificationList);
        }
        $verificationList = $this->string2tcllist($verificationList);
        return $verificationList;
    }

    /*!
     \brief        Verification of the numerical aperture. One channel.
     \param        $channel A channel
     \return       True if the numerical aperture is verified.
     \todo         To be implemented in the GUI
    */
    private function getNAVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    /*!
     \brief        Gets the pinhole spacing. All channels.
     \return       Tcl-list with pinhole spacing.
    */
    private function getPinholeSpacing( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $pinsList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $pinSpacing = $this->getPinSpacingForChannel($chanCnt);
            $pinsList .= " $pinSpacing ";
            $pinsList = trim($pinsList);
        }
        $pinsList = $this->string2tcllist($pinsList);
        return $pinsList; 
    }

    /*!
     \brief        Gets the pinhole spacing. One channel.
     \param        $channel A channel
     \return       The pinhole spacing.
    */
    private function getPinSpacingForChannel($channel) {
        $microSetting = $this->microSetting;
        $pinSpacing = $microSetting->parameter("PinholeSpacing")->value();
        if ($this->getMicroTypeForChannel($channel) == "nipkow") {
            $pinSpacing = "";
        }
        return $pinSpacing;
    }

    /*!
     \brief        Verification of the pinhole spacing. All channels.
     \return       Tcl-list: Trues/Falses if the pinhole spacing is verified.
     \todo         To be implemented in the GUI
    */
    private function getPinSpacingVerification( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $verificationList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $verification = $this->getPSVerificationForChannel($chanCnt);
            $verificationList .= " $verification ";
            $verificationList = trim($verificationList);
        }
        $verificationList = $this->string2tcllist($verificationList);
        return $verificationList;
    }


    /*!
     \brief        Verification of the pinhole spacing. One channel.
     \param        $channel A channel
     \return       True if the pinhole spacing is verified.
     \todo         To be implemented in the GUI
    */
    private function getPSVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    /*!
     \brief        Gets the sampling sizes. All channels.
     \return       Tcl-list with the sampling sizes.
    */
    private function getSamplingSizes( ) {
        $sampleSizeX = $this->microSetting->sampleSizeX();
        $sampleSizeY = $this->microSetting->sampleSizeY();
        $sampleSizeZ = $this->microSetting->sampleSizeZ();
        $sampleSizeT = $this->microSetting->sampleSizeT();
        $sampling = " $sampleSizeX $sampleSizeY $sampleSizeZ $sampleSizeT ";
        $sampling = $this->string2tcllist($sampling);
        return $sampling;
    }

    /*!
     \brief        Verification of the sampling sizes. All channels.
     \return       Tcl-list: Trues/Falses if the sampling sizes are verified.
     \todo         To be implemented in the GUI
    */
    private function getSamplingVerification( ) {
        if ($this->isFieldVerified(0) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    /*!
     \brief        Gets the objective qualities. All channels.
     \return       Tcl-list with the objective qualities.
    */
    private function getObjQuality( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $objQList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $objQuality = $this->getObjQualityForChannel($chanCnt);
            $objQList .= " $objQuality ";
            $objQList = trim($objQList);
        }
        $objQList = $this->string2tcllist($objQList);
        return $objQList; 
    }

    /*!
     \brief        Gets the Objective Quality. One channel.
     \param        $channel A channel
     \return       The objective quality.
     \todo         To be implemented in the GUI
    */
    private function getObjQualityForChannel($channel) {
        $objQuality = "perfect";
        return $objQuality;
    }

    /*!
     \brief        Verification of the objective qualities. All channels.
     \return       Tcl-list: Trues/Falses if the objective qualities are verified.
     \todo         To be implemented in the GUI
    */
    private function getObjQualityVerification( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $verificationList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $verification = $this->getOQVerificationForChannel($chanCnt);
            $verificationList .= " $verification ";
            $verificationList = trim($verificationList);
        }
        $verificationList = $this->string2tcllist($verificationList);
        return $verificationList;
    }

    /*!
     \brief        Verification of the Objective Quality. One channel.
     \param        $channel A channel
     \return       True if the Objective Quality is verified.
     \todo         To be implemented in the GUI
    */
    private function getOQVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    /*!
     \brief        Gets the excitation photon counts. All channels.
     \return       Tcl-list with the excitation photon  counts.
    */
    private function getExcitationPcnt( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $excPcntList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $excPcnt = $this->getExcitationPcntForChannel($chanCnt);
            $excPcntList .= " $excPcnt ";
            $excPcntList = trim($excPcnt);
        }
        $excPcntList = $this->string2tcllist($excPcntList);
        return $excPcntList; 
    }

    /*!
     \brief        Gets the excitation photon count. One channel.
     \param        $channel A channel
     \return       Tcl-list with the excitation photon count.
    */
    private function getExcitationPcntForChannel($channel) {
        if ($this->microSetting->isTwoPhoton()) {
            $pcnt = 2;
        } else {
            $pcnt = 1;
        }
        return $pcnt;
    }

    /*!
     \brief        Verification of the excitation photon count. All channels.
     \return       Tcl-list: Trues/Falses if the EP counts are verified.
     \todo         To be implemented in the GUI
    */
    private function getExcitationPcntVerification( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $verificationList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $verification = $this->getECntVerificationForChannel($chanCnt);
            $verificationList .= " $verification ";
            $verificationList = trim($verificationList);
        }
        $verificationList = $this->string2tcllist($verificationList);
        return $verificationList;
    }

    /*!
     \brief        Verification of the excitation photon count. One channel.
     \param        $channel A channel
     \return       True if the Excitation Photon count is verified.
     \todo         To be implemented in the GUI
    */
    private function getECntVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    /*!
     \brief        Gets the excitation wavelengths. All channels.
     \return       Tcl-list with the excitation wavelengths.
    */
    private function getExcitationLambda( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $exLambdaList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $exLambda = $this->getExLambdaForChannel($chanCnt);
            $exLambdaList .= " $exLambda ";
            $exLambdaList = trim($exLambdaList);
        }
        $exLambdaList = $this->string2tcllist($exLambdaList);
        return $exLambdaList; 
    }

    /*!
     \brief        Gets the excitation wavelength. One channel.
     \param        $channel A channel
     \return       Tcl-list with the excitation wavelength.
    */
    private function getExLambdaForChannel($channel) {
        $microSetting = $this->microSetting;
        $excitationLambdas = $microSetting->parameter("ExcitationWavelength");
        $excitationLambda = $excitationLambdas->value();
        return $excitationLambda[$channel];
    }

    /*!
     \brief        Verification of the excitation wavelength. All channels.
     \return       Tcl-list: Trues/Falses if the EWs are verified.
     \todo         To be implemented in the GUI
    */
    private function getExcitationLVerification( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $verificationList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $verification = $this->getELVerificationForChannel($chanCnt);
            $verificationList .= " $verification ";
            $verificationList = trim($verificationList);
        }
        $verificationList = $this->string2tcllist($verificationList);
        return $verificationList;
    }

    /*!
     \brief        Verification of the excitation wavelength . One channel.
     \param        $channel A channel
     \return       True if the Excitation wavelength is verified.
     \todo         To be implemented in the GUI
    */
    private function getELVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }
    
    /*!
     \brief        Gets the excitation beam overfill factor. All channels.
     \return       Tcl-list with the excitation beam overfill factors.
    */
    private function getExBeamFill( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $exBeamList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $exBeam = $this->getExBeamForChannel($chanCnt);
            $exBeamList .= " $exBeam ";
            $exBeamList = trim($exBeamList);
        }
        $exBeamList = $this->string2tcllist($exBeamList);
        return $exBeamList; 
    }

    /*!
     \brief        Gets the excitation beam overfill factor. One channel.
     \param        $channel A channel
     \return       Tcl-list with the excitation beam factor.
    */
    private function getExBeamForChannel($channel) {
        return 2.0;
    }

    /*!
     \brief        Gets the excitation beam factor verification. All channels.
     \return       Tcl-list: Trues/Falses if the EB factors are verified.
     \todo         To be implemented in the GUI
    */
    private function getExBeamVerification( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $verificationList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $verification = $this->getEBVerificationForChannel($chanCnt);
            $verificationList .= " $verification ";
            $verificationList = trim($verificationList);
        }
        $verificationList = $this->string2tcllist($verificationList);
        return $verificationList;
    }

    /*!
     \brief        Verification of the EB factor. One channel.
     \param        $channel A channel
     \return       True if the Excitation beam factor is verified.
     \todo         To be implemented in the GUI
    */
    private function getEBVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    /*!
     \brief        Gets the emission wavelengths. All channels.
     \return       Tcl-list with the emission wavelengths.
    */
    private function getEmissionLambda( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $emLambdaList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $emLambda = $this->getEmLambdaForChannel($chanCnt);
            $emLambdaList .= " $emLambda ";
            $emLambdaList = trim($emLambdaList);
        }
        $emLambdaList = $this->string2tcllist($emLambdaList);
        return $emLambdaList; 
    }

    /*!
     \brief        Gets the emission wavelength. One channel.
     \param        $channel A channel
     \return       The emission wavelength.
    */
    private function getEmLambdaForChannel($channel) {
        $microSetting = $this->microSetting;
        $emissionLambdas = $microSetting->parameter("EmissionWavelength");
        $emissionLambda = $emissionLambdas->value();
        return $emissionLambda[$channel];
    }
    
    /*!
     \brief        Gets the emission lambda verification. All channels.
     \return       Tcl-list: Trues/Falses if the Emission lambdas are verified.
     \todo         To be implemented in the GUI
    */
    private function getEmissionLVerification( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $verificationList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $verification = $this->getEMVerificationForChannel($chanCnt);
            $verificationList .= " $verification ";
            $verificationList = trim($verificationList);
        }
        $verificationList = $this->string2tcllist($verificationList);
        return $verificationList;
    }

    /*!
     \brief        Verification of the emission lambda. One channel.
     \param        $channel A channel
     \return       True if the emission lambda is verified.
     \todo         To be implemented in the GUI
    */
    private function getEMVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    /*!
     \brief        Gets the imaging direction. All channels.
     \return       Tcl list with the 'imaging direction'.
    */
    private function getImagingDirection( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $dirList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $imagingDir = $this->getImagingDirForChannel($chanCnt);
            $dirList .= " $imagingDir ";
            $dirList = trim($dirList);
        }
        $dirList = $this->string2tcllist($dirList);
        return $dirList;
    }

    /*!
     \brief        Gets the imaging direction. One channel.
     \param        $channel A channel
     \return       Whether the imaging is 'downward' or 'upward'
    */
    private function getImagingDirForChannel($channel) {
        $microSetting = $this->microSetting;
        $coverslip = $microSetting->parameter('CoverslipRelativePosition');
        $coverslipPos = $coverslip->value();
        if ($coverslipPos == 'farthest' ) {
            $imagingDir = "downward";
        } else {
            $imagingDir = "upward";
        }
        return $imagingDir;
    }

    /*!
     \brief        Gets the imaging direction verifications. All channels.
     \return       Tcl-list: Trues/Falses if the imaging directions are verified.
     \todo         To be implemented in the GUI
    */
    private function getImagingDirVerification( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $verificationList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $verification = $this->getIDVerificationForChannel($chanCnt);
            $verificationList .= " $verification ";
            $verificationList = trim($verificationList);
        }
        $verificationList = $this->string2tcllist($verificationList);
        return $verificationList;
    }
    
    /*!
     \brief        Gets the verification of the imaging direction. One channel.
     \param        $channel A channel
     \return       True if the imaging direction is verified.
     \todo         To be implemented in the GUI
    */
    private function getIDVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    /*!
     \brief        Gets the medium refractive index. All channels.
     \return       Tcl list with the 'medium refractive indexes'.
    */
    private function getMediumRefractiveIndex() {
        $numberOfChannels = $this->getNumberOfChannels();
        $MRIndexList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $MRIndex = $this->getMRIndexForChannel($chanCnt);
            $MRIndexList .= " $MRIndex ";
            $MRIndexList = trim($MRIndexList);
        }
        $MRIndexList = $this->string2tcllist($MRIndexList);
        return $MRIndexList; 
    }

    /*!
     \brief        Gets the medium refractive index. One channel.
     \param        $channel A channel
     \return       The medium refractive index
    */
    private function getMRIndexForChannel($channel) {
        $microSetting = $this->microSetting;
        $sampleMedium = $microSetting->parameter("SampleMedium");
        $refractiveIndx = $sampleMedium->translatedValue();
        return $refractiveIndx;
    }

    /*!
     \brief        Gets the medium refractive index verifications. All channels.
     \return       Tcl-list: Trues/Falses if the medium RIs are verified.
     \todo         To be implemented in the GUI
    */
    private function getMediumRIndexVerification( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $verificationList = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $verification = $this->getMRIVerificationForChannel($chanCnt);
            $verificationList .= " $verification ";
            $verificationList = trim($verificationList);
        }
        $verificationList = $this->string2tcllist($verificationList);
        return $verificationList;
    }

    /*!
     \brief        Gets the medium refractive index verification. One channel.
     \param        $channel A channel
     \return       True if the medium refractive index is verified.
     \todo         To be implemented in the GUI
    */
    private function getMRIVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    /* -------------------------- Algorithm tasks ------------------------------ */
    
    /*!
     \brief        Gets the brick mode.
     \return       Brick mode.
    */
    private function getBrMode( ) {
        $SAcorr = $this->getSAcorr();

        if ( $SAcorr[ 'AberrationCorrectionNecessary' ] == 1 ) {
            if ( $SAcorr[ 'PerformAberrationCorrection' ] == 0 ) {
                $brMode = 'one';
            } else {
                if ( $SAcorr[ 'AberrationCorrectionMode' ] == 'automatic' ) {
                    $brMode = 'auto';
                } else {
                    if ( $SAcorr[ 'AdvancedCorrectionOptions' ] == 'user' ) {
                        $brMode = 'one';
                    } elseif ( $SAcorr[ 'AdvancedCorrectionOptions' ] == 'slice' ) {
                        $brMode = 'sliceBySlice';
                    } elseif ( $SAcorr[ 'AdvancedCorrectionOptions' ] == 'few' ) {
                        $brMode = 'few';
                    } else {
                        error_log("Undefined brMode.");
                        $brMode = "";
                    }
                }
            }
        } else {
            $brMode = "";
            if ($this->getPsfMode() == "auto") {
                error_log("Undefined brMode.");
            }

            if ($this->getPsfMode() == "file") {
                $brMode = "auto";
            }
        }
        return $brMode;
    }

    /*!
     \brief        Gets the background mode.
     \return       Background mode.
    */
    private function getBgMode( ) {
        $bgParam = $this->deconSetting->parameter("BackgroundOffsetPercent");
        $bgValue = $bgParam->value();
        $internalValue = $bgParam->internalValue();
        if ($bgValue[0] == "auto" || $internalValue[0] == "auto") {
            $bgMode = "auto";
        } else if ($bgValue[0] == "object" || $internalValue[0] == "object") {
            $bgMode = "object";
        } else {
            $bgMode = "manual";
        }
        return $bgMode;
    }

    /*!
     \brief        Gets the background values. All channels.
     \return       Tcl-list with the background values.
    */
    private function getBgValues( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $bgValues = "";
        for ($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $bgValues .= $this->getBgValueForChannel($chanCnt);
        }
        $bgValues = $this->string2tcllist($bgValues);
        return $bgValues;
    }

    /*!
     \brief        Gets the background value. One channel.
     \param        $channel A channel
     \return       The background value.
    */
    private function getBgValueForChannel($channel) {
        $bgMode = $this->getBgMode();
        if ($bgMode == "auto") {
            $bgValue = " 0.0";
        } elseif ($bgMode == "object") {
            $bgValue = " 0.0";
        } elseif ($bgMode == "manual") {
            $deconSetting = $this->deconSetting;
            $bgRate = $deconSetting->parameter("BackgroundOffsetPercent")->value();
            $bgValue = " $bgRate[$channel]";
        } else {
            error_log("Unknown background mode for channel $channel.");
        }
        return $bgValue;
    }

    /*!
     \brief        Gets the SNR values. All channels.
     \return       Tcl-list with the SNR values.
    */
    private function getSnrValues( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $snrValues = "";
        for ($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $snrValues .= $this->getSnrValueForChannel($chanCnt);
        }
        $snrValues = $this->string2tcllist($snrValues);

        return $snrValues;
    }

    /*!
     \brief        Gets the SNR value. One channel.
     \param        $channel A channel
     \return       The SNR value.
    */
    private function getSnrValueForChannel($channel) {
        $deconSetting = $this->deconSetting;
        $snrRate = $deconSetting->parameter("SignalNoiseRatio")->value();       
        $snrValue = " $snrRate[$channel]";
         
        if ($this->getAlgorithm() == "qmle") {
            $indexValues = array  (1, 2, 3, 4, 5);
            $snrArray = array  ("low", "fair", "good", "inf", "auto");
            $snrValue = str_replace($indexValues, $snrArray, $snrValue);
        }

        return $snrValue;
    }

    /*!
     \brief        Gets the bleaching mode.
     \return       Bleaching mode
     \todo         To be implemented in the GUI
    */
    private function getBleachingMode( ) {
        $blMode = "auto";
        return $blMode;
    }

    /*!
     \brief        Gets the padding mode.
     \return       Padding mode
     \todo         To be implemented in the GUI
    */
    private function getPaddingMode( ) {
        $padding = "auto";
        return $padding;
    }

    /*!
     \brief        Gets the iteration mode.
     \return       Iteration mode
     \todo         To be implemented in the GUI
    */
    private function getIterationMode( ) {
        $iMode = "fast";
        return $iMode;
    }

    /*!
     \brief        Gets the PSF mode.
     \return       PSF mode
    */
    private function getPsfMode( ) {
        $microSetting = $this->microSetting;
        $psfMode = $microSetting->parameter("PointSpreadFunction")->value();
        if ($psfMode == "theoretical") {
            $psfMode = "auto";
        } else {
            $psfMode = "file";
        }
        return $psfMode;
    }

    /*!
     \brief        Gets the PSF path.
     \param        $channel A channel
     \return       Psf path
    */
    private function getPsfPath($channel) {
        $psfPath = "";
        if ($this->getPsfMode() == "file") {
            $microSetting = $this->microSetting;
            $psfFiles = $microSetting->parameter("PSF")->value();
            $psf = $this->getSrcDir() ."/". $psfFiles[$channel];
            $psfPath .= " $psf ";
            $psfPath = trim($psfPath);
        }
        if ($this->getPsfMode() == "auto") {
            $psfPath = $this->string2tcllist($psfPath);
        }
        return $psfPath;
    }

    /*!
     \brief        Gets the timeout value.
     \return       Timeout
     \todo         To be implemented in the GUI
    */
    private function getTimeOut( ) {
        $timeOut = 10000;
        return $timeOut;
    }

    /*!
     \brief        Gets the deconvolution quality factor.
     \return       The quality factor
    */
    private function getQualityFactor( ) {
        $deconSetting = $this->deconSetting;
        return $deconSetting->parameter('QualityChangeStoppingCriterion')->value();
    }

    /*!
     \brief        Gets the maximum number of iterations for the deconvolution.
     \return       The maximum number of iterations.
    */
    private function getIterations( ) {
        return $this->deconSetting->parameter('NumberOfIterations')->value();
    }

    /*!
     \brief        Gets the deconvolution algorithm.
     \return       Deconvolution algorithm.
    */
    private function getAlgorithm( ) {
        return $this->deconSetting->parameter('DeconvolutionAlgorithm')->value();
    }

    /*!
     \brief        Gets the spherical aberration correction.
     \return       Sperical aberration correction.
    */
    private function getSAcorr( ) {
        return $this->microSetting->getAberractionCorrectionParameters();
    }

    /* ------------------------------ Utilities -------------------------------- */

    /*!
     \brief        Gets the number of channels of the template.
     \return       Number of channels.
    */
    private function getNumberOfChannels( ) {
        return $this->microSetting->numberOfChannels();
    }

    /*!
     \brief        Checks whether a field is verified.
     \param        $field A field to check
     \return       True if the field is verified.
     \todo         Implement the body of this function.
    */
    private function isFieldVerified($field) {
        return 1;
    }

    /*!
     \brief        Wraps a string between curly braces to turn it into a Tcl list.
     \param        $string A string
     \return       A Tcl list.
    */
    private function string2tcllist($string) {
        $tcllist = trim($string);
        $tcllist = "{{$tcllist}}";
        return $tcllist;
    }

    /*!
     \brief        Sets the name of the source image.
    */
    private function setSrcImage( ) {
        $this->srcImage = $this->jobDescription->sourceImageName();
    }

    /*!
     \brief        Gets the name of the source image.
     \return       The name of the source image.
    */
    private function getSrcImage( ) {
        return $this->srcImage;
    }

    /*!
     \brief        Gets the directory of the source image.
     \return       A file path.
    */
    private function getSrcDir( ) {
        $srcFileName = $this->getSrcImage();
        return dirname($srcFileName);
    }

    /*!
     \brief        Sets the name of the destination image.
    */
    private function setDestImage ( ) {
        $this->destImage = $this->jobDescription->destinationImageFullName();
        $fileType = $this->getOutputFileType();
        $this->destImage = $this->destImage.".".$fileType;
    }

    /*!
     \brief        Gets the name of the destination image.
     \return       Name of the destination image, with path and extension.
    */
    private function getDestImage( ) {
        return $this->destImage;
    }

    /*!
     \brief        Gets the directory of the destination image.
     \return       A file path.
    */
    private function getDestDir( ) {
        $dstFileName = $this->getDestImage();
        return dirname($dstFileName);
    }

    /*!
     \brief        Gets the extension of the destination image.
     \return       A file extension.
    */
    private function getOutputFileType( ) {
        $outFileFormat = $this->deconSetting->parameter('OutputFileFormat');
        return $outFileFormat->translatedValue();
    }

    /* ------------------------------------------------------------------------- */
}

?>
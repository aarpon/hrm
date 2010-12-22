<?php
  // This file is part of the Huygens Remote Manager
  // Copyright and license notice: see license.txt

  // This class builds Tcl-compliant nested lists (templates) which summarize the
  // tasks and properties of the deconvolution job. The resulting lists
  // are interpreted by HuCore and executed accordingly to perform a 
  // deconvolution.

  // cmode: convert mode: clip, scale, query.
  // OMP_DYNAMIC: When running many jobs it is best to set it to 1.
  // perJobThreadCnt: It should be tunned from the configuration files.

Class JobTranslation {

    public $taskList;
    public $envList;
    public $srcImage;
    public $destImage;
    public $jobDescription;
    public $microSetting;
    public $deconSetting;
    public $envArray;
    public $tasksArray;

    // ---------------------------- Initialize ----------------------------------

    // Constructor
    function __construct($description) {
        $this->setJobParameters($description);
        $this->setJobEnvironmentList();
        $this->setJobTaskList();
    }

    // Initialize variables
    function setJobParameters($description) {
        $this->jobDescription = $description;
        $this->microSetting = $description->parameterSetting;
        $this->deconSetting = $description->taskSetting;
        $this->setEnvironmentArray();
        $this->setTasksArray();
        $this->setSrcImage();
        $this->setDestImage();
    }

    // Initialize environment options array
    function setEnvironmentArray( ) {
        $this->envArray = array ( 'resultDir'=>'',
                                  'perJobThreadCnt'=>'auto',
                                  'concurrentJobCnt'=>'1',
                                  'OMP_DYNAMIC'=>'1',
                                  'timeOut'=>'10000',
                                  'exportFormat'=>'' );
    }

    // Initialie deconvolution tasks array
    function setTasksArray( ) {
        $this->tasksArray = array ( 'open'=>'imgOpen',
                                    'setParameters'=>'setp',
                                    'adjustBaseline'=>'adjbl',
                                    'algorithms'=>'',
                                    'zdrift'=>'zdrift',
                                    'save'=>'imgSave');
    }

    // ------------------------ Main lists handlers -----------------------------

    function setJobEnvironmentList( ) {
        $this->envList = $this->buildEnvironmentList();
    }
    
    function setJobTaskList( ) {
        $this->taskList = $this->buildTaskList();
    }

    // Build environment list
    function buildEnvironmentList( ) {
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

    // Build task list and their options
    function buildTaskList( ) {
        $taskList = $this->getDeconTasks();
        $taskList .= $this->getTasksDetails();
        return $taskList;
    }

    // Build the list of details per deconvolution task.
    function getTasksDetails( ) {
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

    // Get the list of tasks to perform
    function getDeconTasks( ) {
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

    // Get a specific task
    function parseTask($key) {
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

    // ------------------------------- Tasks ------------------------------------
    
    // Deconvolution task: image open
    function getTaskImgOpen( ) {
        $srcFileName = $this->jobDescription->sourceImageName();
        $imgOpen =  "path $srcFileName series auto index 0";
        $imgOpen = $this->string2tcllist($imgOpen);
        $imgOpen = " imgOpen $imgOpen";
        return $imgOpen;
    }

    // Deconvolution task: set parameters
    function getTaskSetp( ) {
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

    // Deconvolution task: image save
    function getTaskImgSave( ) {
        $dstFileName = $this->jobDescription->destinationImageFullName();
        $imgSave = "rootName $dstFileName";
        $imgSave = $this->string2tcllist($imgSave);
        $imgSave = " imgSave $imgSave";
        return $imgSave;
    }

    // Deconvolution task: Adjust baseline
    function getTaskAdjbl( ) {
        $adjbl = "enabled 0 ni 0";
        $adjbl = $this->string2tcllist($adjbl);
        $adjbl = " adjbl $adjbl";
        return $adjbl;
    }

    // Deconvolution task: deconvolution algorithm of one channel
    function getTaskAlgorithm($channel) {
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

    // Deconvolution task: deconvolution algorithm of all channels
    function getTaskAlgorithms( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $algorithms = "";
        for($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $algorithm = $this->getAlgorithm($chanCnt);
            $algOptions = $this->getTaskAlgorithm($chanCnt);
            $algorithms .= " ${algorithm}:$chanCnt $algOptions";
        }

        return $algorithms;
    }

    // Deconvolution task: z drift
    function getTaskZDrift( ) {
        $zdrift = "enabled 1 survey single chan 0 ".
            "filter median filerSize 3";
        $zdrift = $this->string2tcllist($zdrift);
        $zdrift = " zdrift $zdrift";
        return $zdrift;
    }

    //-------------------------- Setp tasks -------------------------------------
    
    // Position of the coverslip: to be implemented in the GUI
    function getiFacePrim( ) {
        $iFacePrim = 0.0;
        return $iFacePrim;
    }

    // Verification of the coverslip position: to be implemented in the GUI
    function getiFacePrimVerification( ) {
        $iFacePrim = $this->getiFacePrim();
        if ($this->isFieldVerified($iFacePrim) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    // Position of the preparation glass: to be implemented in the GUI
    function getiFaceScnd( ) {
        $iFaceScnd = 1000000.0;
        return $iFaceScnd;
    }

    // Verification of the prep glass position: to be implemented in the GUI
    function getiFaceScndVerification( ) {
        $iFaceScnd = $this->getiFaceScnd();
        if ($this->isFieldVerified($iFaceScnd) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    // Not yet implemented
    function getMissingOptList( ) {
        $missingOptList = "";
        $missingOptList = $this->string2tcllist($missingOptList);
        return $missingOptList;
    }

    // List of pinhole radii for all channels
    function getPinholeRadius( ) {
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

    // Pinhole radius of one channel
    function getPinRadiusForChannel($channel) {
        $microSetting = $this->microSetting;
        $pinholeSize = $microSetting->parameter("PinholeSize")->value();       
        $pinholeRadius = "$pinholeSize[$channel]";
        return $pinholeRadius;
    }

    // Pinhole radius verification of one channel: to be implemented in the GUI
    function getPRVerificationForChannel($channel) {
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

    // Verification whether the pinhole radius has been verified by the user
    function getPRVerifyList( ) {
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

    // Imaging direction of one channel:
    function getImagingDirForChannel($channel) {
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

    // Imaging direction of all channels
    function getImagingDirection( ) {
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

    // Microscope Type of all channels
    function getMicroscopeType( ) {
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

    // Microscope of one channel: to be implemented in the GUI
    function getMicroTypeForChannel($channel) {
        $microSetting = $this->microSetting;
        $microType = $microSetting->parameter('MicroscopeType')->translatedValue();
        return $microType;
    }

    // Microscope type verification of one channel
    function getMTVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    // Microscope type verification of all channels
    function getMicroTypeVerification( ) {
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

    // All fields verified by the user: to be implemented in the GUI
    function getAllVerified( ) {
        return 1;
    }

    // Lens refractive index verification of one channel
    function getLRIVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;  
    }

    // Lens refractive index verification of all channels
    function getLensRIndexVerification( ) {
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

    // Lens refractive index of one channel
    function getLensRIForChannel($channel) {
        $microSetting = $this->microSetting;
        $lensRI = $microSetting->parameter('ObjectiveType')->translatedValue();
        return $lensRI;
    }

    // Lens refractive index of all channels
    function getLensRefractiveIndex( ) {
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

    // Numerical aperture verification for one channel
    // To be implemented in the GUI
    function getNAVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    // Numerical aperture verification for all channels
    function getNumericalAVerification( ) {
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

    // Numerical aperture of one channel 
    function getNumApertureForChannel($channel) {
        $microSetting = $this->microSetting;
        $numAper = $microSetting->parameter('NumericalAperture')->value();
        return $numAper;
    }

    // Numerical aperture of all channels
    function getNumericalAperture( ) {
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
    
    // Pinhole spacing of one channel
    function getPinSpacingForChannel($channel) {
        $microSetting = $this->microSetting;
        $pinSpacing = $microSetting->parameter("PinholeSpacing")->value();
        if ($this->getMicroTypeForChannel($channel) == "nipkow") {
            $pinSpacing = "";
        }
        return $pinSpacing;
    }

    // Pinhole spacing of all channels
    function getPinholeSpacing( ) {
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

    // Pinhole spacing verification of one channel: to be implemented in the GUI
    function getPSVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    // Pinhole spacing verification of all channels
    function getPinSpacingVerification( ) {
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

    // Sampling sizes
    function getSamplingSizes( ) {
        $sampleSizeX = $this->microSetting->sampleSizeX();
        $sampleSizeY = $this->microSetting->sampleSizeY();
        $sampleSizeZ = $this->microSetting->sampleSizeZ();
        $sampleSizeT = $this->microSetting->sampleSizeT();
        $sampling = " $sampleSizeX $sampleSizeY $sampleSizeZ $sampleSizeT ";
        $sampling = $this->string2tcllist($sampling);
        return $sampling;
    }

    // Sampling sizes verification: to be implemented in the GUI
    function getSamplingVerification( ) {
        if ($this->isFieldVerified(0) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    // Objective quality verification of one channel: to be implemented in the GUI
    function getOQVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    // Objective quality verification of all channels
    function getObjQualityVerification( ) {
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

    // Objective quality of one channel: to be implemented in the GUI
    function getObjQualityForChannel($channel) {
        $objQuality = "perfect";
        return $objQuality;
    }

    // Objective quality of all channels
    function getObjQuality( ) {
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

    // Excitation photon count verification of one channel
    function getECntVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    // Excitation photon count verification: to be implemented in the GUI
    function getExcitationPcntVerification( ) {
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

    // Excitation photon count of one channel
    function getExcitationPcntForChannel($channel) {
        if ($this->microSetting->isTwoPhoton()) {
            $pcnt = 2;
        } else {
            $pcnt = 1;
        }
        return $pcnt;
    }

    // Excitation photon count of all channels
    function getExcitationPcnt( ) {
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

    // Excitation lambda verification of one channel
    function getELVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }
    
    // Excitation lambda verification of all channels
    function getExcitationLVerification( ) {
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

    // Excitation wavelength of one channel
    function getExLambdaForChannel($channel) {
        $microSetting = $this->microSetting;
        $excitationLambdas = $microSetting->parameter("ExcitationWavelength");
        $excitationLambda = $excitationLambdas->value();
        return $excitationLambda[$channel];
    }
    
    // Excitation wavelength of all channels
    function getExcitationLambda( ) {
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

    // Excitation beam overfill factor verification of one channel
    function getEBVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    // Excitation beam overfill factor verification of all channels
    function getExBeamVerification( ) {
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

    // Excitation beam overfill factor of one channel
    // To be implemented in the GUI
    function getExBeamForChannel($channel) {
        return 2.0;
    }

    // Excitation beam overfill factor of all channels
    function getExBeamFill( ) {
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

    // Emission lambda verification of one channel
    function getEMVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    // Emission lambda verification of all channels
    function getEmissionLVerification( ) {
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

    // Emission wavelength of one channel
    function getEmLambdaForChannel($channel) {
        $microSetting = $this->microSetting;
        $emissionLambdas = $microSetting->parameter("EmissionWavelength");
        $emissionLambda = $emissionLambdas->value();
        return $emissionLambda[$channel];
    }
    
    // Emission wavelength of all channels
    function getEmissionLambda( ) {
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

    // Imaging direction verification of all channels
    function getImagingDirVerification( ) {
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
    
    // Imaging direction verification of one channel: to be implemented in the GUI
    function getIDVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    // Medium refractive index verification of one channel
    function getMRIVerificationForChannel($channel) {
        if ($this->isFieldVerified($channel) == 1) {
            $verification = "verified";
        } else {
            $verification = "estimated";
        }
        return $verification;
    }

    // Medium refractive index verification of all channels
    function getMediumRIndexVerification( ) {
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

    // Medium refractive index of one channel
    function getMRIndexForChannel($channel) {
        $microSetting = $this->microSetting;
        $sampleMedium = $microSetting->parameter("SampleMedium");
        $refractiveIndx = $sampleMedium->translatedValue();
        return $refractiveIndx;
    }

    // Medium refractive index of all channels
    function getMediumRefractiveIndex() {
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

    //-------------------------- Algorithm tasks --------------------------------

    // Brick mode
    function getBrMode( ) {
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

    // Background mode
    function getBgMode( ) {
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

    // Background value of one channel
    function getBgValueForChannel($channel) {
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

    // Background values of all channels
    function getBgValues( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $bgValues = "";
        for ($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $bgValues .= $this->getBgValueForChannel($chanCnt);
        }
        $bgValues = $this->string2tcllist($bgValues);
        return $bgValues;
    }

    // Snr value of one channel
    function getSnrValueForChannel($channel) {
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

    // Snr values of all channels
    function getSnrValues( ) {
        $numberOfChannels = $this->getNumberOfChannels();
        $snrValues = "";
        for ($chanCnt = 0; $chanCnt < $numberOfChannels; $chanCnt++) {
            $snrValues .= $this->getSnrValueForChannel($chanCnt);
        }
        $snrValues = $this->string2tcllist($snrValues);

        return $snrValues;
    }

    // Bleaching Mode: to be implemented in the GUI
    function getBleachingMode( ) {
        $blMode = "auto";
        return $blMode;
    }

    // Padding Mode: to be implemented in the GUI
    function getPaddingMode( ) {
        $padding = "auto";
        return $padding;
    }

    // Iteration Mode: to be implemented in the GUI
    function getIterationMode( ) {
        $iMode = "fast";
        return $iMode;
    }

    // Whether the psf is theoretical or measured
    function getPsfMode( ) {
        $microSetting = $this->microSetting;
        $psfMode = $microSetting->parameter("PointSpreadFunction")->value();
        if ($psfMode == "theoretical") {
            $psfMode = "auto";
        } else {
            $psfMode = "file";
        }
        return $psfMode;
    }

    // File path of a measured PSF
    function getPsfPathForChannel($channel) {
        $microSetting = $this->microSetting;
        $psfFiles = $microSetting->parameter("PSF")->value();
        $psf = $this->getSrcDir() ."/". $psfFiles[$channel];
        return $psf;
    }

    // File paths of the measured PSFs
    function getPsfPath($channel) {
        $psfPaths = "";
        if ($this->getPsfMode() == "file") {
            $PSFs = $this->microSetting->parameter('PSF')->value();
            $psf = $this->getPsfPathForChannel($channel);
            $psfPaths .= " $psf ";
            $psfPaths = trim($psfPaths);
        }
        if ($this->getPsfMode() == "auto") {
            $psfPaths = $this->string2tcllist($psfPaths);
        }
        return $psfPaths;
    }

    // TimeOut: to be implemented in the GUI
    function getTimeOut( ) {
        $timeOut = 10000;
        return $timeOut;
    }

    // Deconvolution quality factor
    function getQualityFactor( ) {
        $deconSetting = $this->deconSetting;
        return $deconSetting->parameter('QualityChangeStoppingCriterion')->value();
    }

    // Number of iterations
    function getIterations( ) {
        return $this->deconSetting->parameter('NumberOfIterations')->value();
    }

    // Deconvolution algorithm
    function getAlgorithm( ) {
        return $this->deconSetting->parameter('DeconvolutionAlgorithm')->value();
    }

    // Spherical aberration correction
    function getSAcorr( ) {
        return $this->microSetting->getAberractionCorrectionParameters();
    }

    //------------------------------ Utilities ----------------------------------

    //Number of Channels
    function getNumberOfChannels( ) {
        return $this->microSetting->numberOfChannels();
    }

    // Check if the user verified a field: to be implemented in the GUI
    function isFieldVerified($field) {
        return 1;
    }

    // Insert curly braces to turn string into tcl list
    function string2tcllist($string) {
        $tcllist = trim($string);
        $tcllist = "{{$tcllist}}";
        return $tcllist;
    }

    // Set name of the source image
    function setSrcImage( ) {
        $this->srcImage = $this->jobDescription->sourceImageName();
    }

    // Name of the source image, also path and file extension
    function getSrcImage( ) {
        return $this->srcImage;
    }

    // Image source directory. Destination for scripts
    function getSrcDir( ) {
        $srcFileName = $this->getSrcImage();
        return dirname($srcFileName);
    }

    // Set name of the destination image
    function setDestImage ( ) {
        $this->destImage = $this->jobDescription->destinationImageFullName();
        $fileType = $this->getOutputFileType();
        $this->destImage = $this->destImage.".".$fileType;
    }

    // Name of the destination image, also path and file extension
    function getDestImage( ) {
        return $this->destImage;
    }

    // Image destination directory
    function getDestDir( ) {
        $dstFileName = $this->getDestImage();
        return dirname($dstFileName);
    }

    // Output image file type
    function getOutputFileType( ) {
        $outFileFormat = $this->deconSetting->parameter('OutputFileFormat');
        return $outFileFormat->translatedValue();
    }

    //---------------------------------------------------------------------------
}

?>
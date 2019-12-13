// Quick help for the Capturing Parameter page
//
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Set the condition for the contextHelp div to 'default'
window.divCondition = "default";

// Initialize the help text
window.helpText = new Array();

window.helpText[ "method" ] =
  '<p>The Huygens software has different deconvolution algorithms as ' +
  'restoration methods. The <b>CMLE algorithm</b> is optimally suited for ' +
  'low-signal images; the <b>QMLE algorithm</b> is faster than CMLE, ' +
  'but it only works well with noise-free images (for example, good quality ' +
  'widefield images). Alternatively, <b>GMLE</b> can be used as a fast, ' +
  'good-quality algorithm for noisy images (for example STED or low signal confocal).</p>' +
  '<p>Different algorithms can be selected for different channels. ' +
  'This is mostly relevant when mixing channels from different microscope ' +
  'types in the same data set. For example, Confocal and Widefield channels ' + 
  'from the same field of view, or two confocal channels ' +
  'with different pinhole sizes. </p>' + 
  '<p>Choose <b>Skip</b> on selected channels to skip the deconvolution. ' +
  'This can also be useful for stabilization, chromatic aberration, ' +
  'correction or colocalization analysis of previously deconvolved data. ' +
  'In order to accomplish this, set all channels to <b>Skip</b>, then set ' +
  'the parameters of the stabilization, chromactic aberration or ' +
  'colocalization tasks.</p>';

window.helpText[ "snr" ] =
  '<p>The SNR controls the sharpness of the result: only with noise-free ' +
  'images you can safely demand very sharp results (high SNR values) without ' +
  'amplifying noise.</p>' +
  '<p>The different deconvolution algorithms have different requirements on ' +
  'the SNR parameter.</p>' +
  '<p>For the <strong>CMLE and GMLE algorithms</strong>, you are asked to give a ' +
  'numericalestimation of the SNR of your images. The SNR estimator can help you ' +
  'calculate the SNR for your images.</p>' +
  '<p>For the <strong>QMLE algorithm</strong>, only a coarser classification ' +
  'of the SNR is required.</p>';
 
window.helpText[ "background" ] =
  '<p>The background is any additive and approximately constant signal in ' +
  'your image that is not coming from the objects you are interested in, ' +
  'but from other sources, such as an electronic offset in your detector, ' +
  'or indirect light. It will be removed during the restoration, to increase ' +
  'contrast.</p>';

window.helpText[ "stopcrit" ] =
  '<p>The first stopping criterium reached will stop the restoration. ' +
  'The quality change criterium may apply first and stop the iterations ' +
  'before the maximum number is reached: set the quality change to a low ' +
  'value or zero if you want to make sure all the set iterations are run.</p>' +
  '<p>Please notice that the maximum number of iterations is limited. If ' +
  'you are using the CMLE algorithm and you feel like you would need more ' +
  'iterations to converge to a solution, you might probably want to try ' +
  'the GMLE algorithm instead.</p>';

window.helpText[ "zstabilization" ] =
  '<p>Due to the high lateral resolution, <b>STED</b> image ' +
  'acquisition is more sensitive to drift than other imaging modes.</p>' +
  '<p>It is <b>strongly recommended</b> to stabilize STED images ' +
  'along the Z direction.</p>';

window.helpText[ "autocrop" ] =
  '<p>The time needed to deconvolve an image increases more than ' +
  'proportionally with its volume. Therefore, deconvolution <b>can be ' +
  'accelerated</b> considerably by cropping the image.</p>' +
  '<p>Huygens will automatically survey the image to find a reasonable ' +
  'proposal for the crop region. In computing this initial proposal the ' +
  'Microscopic Parameters are taken into account, making sure that ' +
  'cropping will not have a negative impact on the deconvolution result.</p>';

window.helpText[ "arrayDetectorReductionMode" ] =
    '<p>The array detector reduction mode specifies which pixel reassignment method ' +
    'to use in order to combine the data from all the detectors in the array. ' +
    'Examples of such detectors are the Zeiss Airyscan and the SPAD detector, but ' +
    'other generic, customized detector layouts can also be used.</p>' +
    '<p>By selecting mode <b>all</b> the images ' +
    'from all detectors are centered and combined, thus increasing the SNR. ' +
    'The resulting, combined image is then deconvolved.</p> ' +
    '<p>Mode <b>no</b> uses the input of ' +
    'all detectors as separate inputs for the deconvolution.</p>' +
    '<p>Modes <b>core all</b> and <b>core no</b> behave the same way as <b>all</b> and ' +
    '<b>no</b> but use the core detectors only. These can be specially helpful when only ' +
    'the core (central) detectors behave well or capture enough light. </p>' +
    '<p>Mode <b>superXY</b> creates an image with double the samples in X ' +
    'and Y. Thus, producing an image with 4 times more samples. '  +    
    'This is a good option when dealing with images that have been acquired ' +
    'well under the Nyquist rate.</p>' + 
    '<p>Mode <b>superY</b> creates an image with 4 times as many samples in Y. ' +
    'This is mostly useful when dealing with images ' +
    'recorded with the Zeiss Airyscan in <b>fast mode</b>.</p>' +
    '<p>Lastly, mode <b>auto</b> will fall back to one of the above mentioned modes ' +
    'depending on the microscopic parameters and detector model of the image.</p>';  

window.helpText[ "default" ] =
  '<p>On this page you specify the parameters for restoration.</p>' +
  '<p>These parameters comprise the deconvolution algorithm, the ' +
  'signal-to-noise ratio (SNR) of the images, the mode for background ' +
  'estimation, and the stopping criteria.</p>';

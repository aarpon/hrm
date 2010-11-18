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
  'restoration methods.  HRM offers the possibility of using the two most ' +
  'important ones: the <b>CMLE algorithm</b> is optimally suited for ' +
  'low-signal images; the <b>QMLE algorithm</b> is faster than CMLE, ' +
  'but it only works well with noise-free images (for example, good quality ' +
  'widefield images).</p>';

window.helpText[ "snr" ] =
  '<p>The SNR controls the sharpness of the result: only with noise-free ' +
  'images you can safely demand very sharp results (high SNR values) without ' +
  'amplifying noise.</p>' +
  '<p>The different deconvolution algorithms have different requirements on ' +
  'the SNR parameter.</p>' +
  '<p>For the <strong>CMLE algorithm</strong>, you are asked to give a numerical ' +
  'estimation of the SNR of your images. ' +
  'With Hucore version 3.5.1 and newer, you can use the <strong>SNR estimator ' +
  '</strong> to automatically calculate the SNR for your images. With Hucore ' +
  '3.6.1, an even better algorithm is in place.</p>' +
  '<p>For the CMLE algorithm, the field SNR ' +
  'can accept multiple values per channel. This is a commodity tool to ' +
  'optimize restoration parameters. By setting the SNR value to "10 20 40" ' +
  'in a single channel image, for instance, three jobs will be run: the ' +
  'first with SNR value 10, the second with SNR value 20, and the third ' +
  'with SNR value 40.</p>' +
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
  '<p>The field <strong>number of iterations</strong> can accept multiple ' +
  'values. This is a commodity tool to optimize restoration parameters. By ' +
  'setting this field value to "20 50 70", for instance, three jobs will be ' +
  'run: the first with a maximum of 20 iterations, the second with a maximum ' +
  'of 50, and the third with a maximum of 70. Notice that the quality change ' +
  'criterium may apply first and stop the iterations earlier in all cases.</p>';

window.helpText[ "default" ] =
  '<p>On this page you specify the parameters for restoration.</p>' +
  '<p>These parameters comprise the deconvolution algorithm, the ' +
  'signal-to-noise ratio (SNR) of the images, the mode for background ' +
  'estimation, and the stopping criteria.</p>';

// Quick help for the Aberration Correction page
//
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Set the condition for the contextHelp div to 'default'
window.divCondition = "default";

// Initialize the help text
window.helpText = new Array();

window.helpText[ "enable" ] =
  '<p>The Huygens software can adapt the PSF to the sample depth to ' +
  '(partially) correct for spherical aberrations in case of refractive index ' +
  'mismatch. Here you can choose whether such correction should be performed ' +
  'or not.</p>';

window.helpText[ "orientation" ] =
  '<p>For depth-dependent correction to work properly, the relative position ' +
  'of the coverslip with respect to the first acquired plane of the dataset ' +
    'has to be specified.</p><p>A preview of the image, see the ' +
    '\'Raw images\' folder, can be helpful to assess the orientation saved ' +
    'by the acquisition system.</p>';

window.helpText[ "mode" ] =
  '<p>Automatic correction should work fine in most of the cases. For very ' +
  'large refractive index mismatches, however, some artifacts might be ' +
  'introduced by the correction. In this case, a few additional, advanced ' +
  'correction scheme are possible.</p>';

window.helpText[ "advanced" ] =
  '<p>A few advanced correction schemes can be applied in case the ' +
  'automatic correction was not satisfactory. Please look at the help ' +
  'for additional detials.</p>';

window.helpText[ "default" ] =
  '<p>The main cause of spherical aberration is a mismatch between the ' +
  'refractive index of the lens immersion medium and specimen embedding ' +
  'medium and causes the PSF to become asymmetric at depths of already ' +
  'a few &micro;m. SA is especially harmful for widefield microscope ' +
  'deconvolution. The HRM can correct for SA automatically, but in case of ' +
  'very large refractive index mismatches some artifacts can be generated. ' +
  'Advanced parameters allow for fine-tuning of the correction.</p>';

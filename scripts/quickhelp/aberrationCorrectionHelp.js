// Quick help for the Aberration Correction page
//
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Set the condition for the contextHelp div to 'default'
window.divCondition = "default";

// Initialize the help text
window.helpText = new Array();

window.helpText[ "enable" ] =
  '<p>Enable: fill in, please.</p>';

window.helpText[ "orientation" ] =
  '<p>Orientation: fill in, please.</p>';

window.helpText[ "mode" ] =
  '<p>Advanced: fill in, please.</p>';

window.helpText[ "advanced" ] =
  '<p>Objective: fill in, please.</p>';

window.helpText[ "default" ] =
  '<p>The main cause of spherical aberration is a mismatch between the ' +
  'refractive index of the lens immersion medium and specimen embedding ' +
  'medium and causes the PSF to become asymmetric at depths of already ' +
  'a few &micro;m. SA is especially harmful for widefield microscope ' +
  'deconvolution.</p> ' +
  '<p>The HRM can correct for SA automatically, but in case of very ' +
  'large refractive index mismatches some artifacts can be generated. ' +
  'Advanced parameters allow for fine-tuning of the correction.</p>';

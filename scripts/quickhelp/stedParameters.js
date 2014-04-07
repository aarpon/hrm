// Quick help for the STED Parameters page
//
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Set the condition for the contextHelp div to 'default'
window.divCondition = "default";

// Initialize the help text
window.helpText = new Array();

window.helpText[ "deplMode" ] =
    '<p>The mode of the STED depletion laser: <b>CW</b> for ' +
    '\'continuous wave\'; <b>gated</b> or <b>non-gated</b> depending on ' +
    'the detection type; ' +
    '<b>Pulsed</b> if the depletion laser was not set as CW; ' +
    '<b>Off / Confocal</b> if a particular channel was not recorded as ' +
    'STED but as regular confocal.</p>';

window.helpText[ "satFact" ] =
    '<p>How much fluoresence is suppressed by the STED beam. Typical values ' +
    'are in the range of 10 to 50. In practice it\'s best to estimate this ' +
    'value automatically with the Huygens PSF distiller, while creating a ' +
    'PSF from beads recorded with the STED system.</p>';

window.helpText[ "lambda" ] =
    '<p>The wavelength of the STED depletion laser beam in nanometers. The ' +
    'STED wavelength must be a value within the range of the fluorophore ' +
    'emission spectrum.</p>';

window.helpText[ "immunity" ] =
    '<p>The fraction of fluorophore molecules not susceptible, ‘immune’, to ' +
    'the depletion beam. The value that should be entered here is usually ' +
    'between 0% and 10%. Because this parameter can be difficult to quantify ' +
    'it is advised to use the Huygens PSF distiller to get an automatic ' +
    'estimation while creating a PSF from beads recorded with the STED ' +
    'system.</p>';

window.helpText[ "3d" ] =
    '<p>The percentage of power used in the Z depletion beam. The ' +
    'remaining power is used for the vortex beam path.</p>';

window.helpText[ "default" ] =
    '<p>On this page you specify the parameters of the STED sytem ' +
    'for your experimental setup.</p>' +
    '<p>These parameters consist of the STED depletion mode, wavelength, ' +
    'saturation factor, immunity percentage and, when applicable, ' +
    'STED 3D percentage.</p>';



    

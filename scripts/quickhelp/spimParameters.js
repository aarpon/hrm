// Quick help for the SPIM Parameters page
//
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Set the condition for the contextHelp div to 'default'
window.divCondition = "default";

// Initialize the help text
window.helpText = new Array();

window.helpText[ "excMode" ] =
    '<p>Indicate whether the excitation is achieved by using a scanning ' +
    'beam or a cylindrical lens.</p>';

window.helpText[ "gaussWidth" ] =
    '<p>A typical width definition for Gaussian beams is 4 times the ' +
    'the standard deviation of the beam, measured in microns.</p>';

window.helpText[ "focusOffset" ] =
    '<p><b>The focus offset</b> specifies the distance ' +
    'between the focal point of the detection lens and the focal point of ' +
    'the illumination lens along the optical axis of the detection lens.<p>';

window.helpText[ "centerOffset" ] =
    '<p><b>The center offset of the light sheet</b> specifies the distance ' +
    'between the focal point of the detection lens and the focal point of ' +
    'the illumination lens along the optical axis of the illumination lens.<p>';

window.helpText[ "NA" ] =
    '<p><b>Light sheet NA:</b> the numerical aperture of the illumination ' +
    'lens. This value can be different from the NA of the detection lens.</p>';

window.helpText[ "fillFactor" ] =
    '<p><b>The excitation fill factor</b> of the illumination lens. This is ' +
    'the ratio between the beam width and the diameter of the objective ' +
    'pupil.</p>';

window.helpText[ "direction" ] =
    '<p><b>The propagation direction of the light sheet</b> is represented ' +
    'by the position of the illumination objective with respect to the ' +
    'detection lens.</p>';

window.helpText[ "default" ] =
    '<p>On this page you specify the parameters of the SPIM sytem ' +
    'for your experimental setup.</p>' +
    '<p>These parameters consist of the SPIM excitation mode, focus offset, ' +
    'lateral offset, illumination direction and, when applicable, ' +
    'the NA of the illumination lens, its fill factor, and the width of ' +
    'a Gaussian sheet.</p>';



    

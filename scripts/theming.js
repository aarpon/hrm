// Theming functions
//
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Switch style to 'css_title'
function switch_style(css_title) {

    // Get links in <head>
    var links = $("head").find("link");

    $.each(links, function (key, value) {
        if (value.rel.indexOf("stylesheet") !== -1 &&
            (value.title.toLowerCase() === "dark" ||
                value.title.toLowerCase() === "light")) {

            // Disable stylesheet
            value.disabled = true;
            value.rel = "alternate stylesheet";

            // Enable the selected one
            if (value.title.toLowerCase() === css_title) {

                // Enable selected stylesheet
                value.disabled = false;
                value.rel = "stylesheet";

                // Store selection in the session storage
                localStorage.setItem('user_hrm_theme', css_title);
            }
        }
    });
}

// Apply the stored (or the default) theme
function apply_stored_or_default_theme() {

    // Retrieve stored theme
    var css_title = localStorage.getItem('user_hrm_theme');
    if (null === css_title) {

        // Set to default
        css_title = "dark";

        // Store default in the session storage
        localStorage.setItem('user_hrm_theme', css_title);
    }

    // Apply it
    switch_style(css_title);

}

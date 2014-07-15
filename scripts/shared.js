// Template sharing functions
//
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt


// Retrieve and fill in shared templates (both shared with and shared by the user)
function retrieveSharedTemplates(username, type) {

    // Query server for list of shared templates
    JSONRPCRequest({
        method : 'jsonGetSharedTemplateList',
        params: [username, type]
    }, function(response) {

        // Failure?
        if (response.success != "true") {

            $("#message").html("<p>Problem retrieving list of shared templates!</p>");
            return;

        }

        // Get the templates
        var sharedTemplatesWith = response.sharedTemplatesWith;

        // Sort them by previous owner
        sharedTemplatesWith.sort(sort_by_previous_owner);

        // Get the number of templates
        var numSharedTemplates = sharedTemplatesWith.length;

        // Write the notification
        if (numSharedTemplates == 0) {
            $("#templateSharingNotifier").html("You have no shared user templates.");
        } else {
            $("#templateSharingNotifier").html("You have " +
                "<a href='' onclick='toggleSharedTemplatesDiv(); return false;'><b>" +
                String(numSharedTemplates) + " user shared template" +
                (numSharedTemplates > 1 ? "s" : "") + "</b></a>!");
        }

        // Now fill in the shared template table
        fillInSharedTemplatesTable(sharedTemplatesWith, type);

    });
}

// Fill in the shared template table
function fillInSharedTemplatesTable(sharedTemplatesWith, type) {

    // Remove content from table
    var tbody = $("#sharedWithTemplatePickerTable tbody");
    tbody.empty();

    // Make sure to clear the template data
    tbody.data("sharedTemplatesWith", null);

    if (sharedTemplatesWith.length == 0) {
        tbody.append("<tr><td>No user templates shared with you.</td>/<tr>");
        return;
    }

    // Store the shared templates
    tbody.data("sharedTemplatesWith", sharedTemplatesWith);

    // Now fill the table
    var lastUser = null;
    for (var i = 0; i < sharedTemplatesWith.length; i++) {

        // Add 'header' row if needed
        if (sharedTemplatesWith[i].previous_owner != lastUser) {

            // Add "From 'user'" header
            tbody.append("<tr><td colspan='4' class='from_template'>" +
                "From <b>" + sharedTemplatesWith[i].previous_owner +
                "</b>:</td></tr>");

            lastUser = sharedTemplatesWith[i].previous_owner;
        }

        // Add template with actions
        var tdAccept = "<td class='accept_template' " +
            "title='Accept the template.' " +
            "onclick='acceptTemplate(" + String(i) + ", \"" + type + "\")' >" +
            "<a href='#'>&nbsp;</a></td>";

        var tdReject = "<td class='reject_template' " +
            "title='Reject the template.' " +
            "onclick='rejectTemplate(" + String(i) + ", \"" + type + "\")' >" +
            "<a href='#'>&nbsp;</a></td>";

        var tdPreview = "<td class='preview_template'  " +
            "title='Preview the template.' " +
            "onclick='previewTemplate(" + String(i) + ", \"" + type + "\")' >" +
            "<a href='#'>&nbsp;</a></td>";

        var tdTemplate = "<td class='name_template' " +
            "title='Shared with you on " +
            sharedTemplatesWith[i].sharing_date + ".' >" +
            sharedTemplatesWith[i].name + "</td>";

        tbody.append("<tr>" + tdAccept + tdReject +
            tdPreview + tdTemplate + "</tr>");

    }

}

// Prepare the page for template sharing
function prepareUserSelectionForSharing(username) {

    // Is there a selected template?
    var templateToShare = $("#setting").val();
    if (null === templateToShare) {
        // No template selected; inform and return
        $("#message").html("<p>Please pick a template to share!</p>");
        return;
    }

    // Query server for user list
    JSONRPCRequest({
        method : 'jsonGetUserList',
        params: [username]
    }, function(response) {

        // Failure?
        if (response.success != "true") {

            $("#message").html("<p>Could not retrieve user list!</p>");
            return;

        }

        // Add user names to the select widget
        $("#usernameselect").find('option').remove();
        for (var i = 0; i < response.users.length; i++ ) {
            var name = response.users[i]["name"];
            $("#usernameselect").append("<option value=" + name  + ">" + name + "</option>");
        }

        // Copy the shared template
        $("#templateToShare").val(templateToShare);

        // Hide the forms that are not relevant now
        $("#formTemplateTypeParameters").hide();
        $("#select").hide();

        // Display the user selection form
        $("#formUserList").show();

    });
}

// Accept the template with specified index
function acceptTemplate(template_index, type) {

    // Get the shared templates content from table
    var tbody = $("#sharedWithTemplatePickerTable tbody");
    var sharedTemplatesWith = tbody.data("sharedTemplatesWith");

    if (sharedTemplatesWith == null) {
        return;
    }

    // Send an asynchronous call to the server to accept the template
    JSONRPCRequest({
        method : 'jsonAcceptSharedTemplate',
        params: [sharedTemplatesWith[template_index], type]
    }, function(response) {

        // Failure?
        if (response.success != "true") {

            $("#message").html("<p>Could not accept template!</p>");
            return;

        }

        // Now reload the page to udpate everything
        location.reload(true);

        // Inform
        $("#message").html("<p>Template accepted!</p>");

    });

}

// Delete the template with specified index
function rejectTemplate(template_index, type) {

    // Get the shared templates content from table
    var tbody = $("#sharedWithTemplatePickerTable tbody");
    var sharedTemplatesWith = tbody.data("sharedTemplatesWith");

    if (sharedTemplatesWith == null) {
        return;
    }

    // Ask the user for confirmation
    if (! confirm("Are you sure you want to discard this template?")) {
        return;
    }

    // Send an asynchronous call to the server to accept the template
    JSONRPCRequest({
        method : 'jsonDeleteSharedTemplate',
        params: [sharedTemplatesWith[template_index], type]
    }, function(response) {

        // Failure?
        if (response.success != "true") {

            $("#message").html("<p>Could not delete template!</p>");
            return;

        }

        // Now reload the page to update everything
        location.reload(true);

        // Inform
        $("#message").html("<p>Template rejected.</p>");
    });

}

// Preview the template with specified index
function previewTemplate(template_index, type) {

    // Get the shared templates content from table
    var tbody = $("#sharedWithTemplatePickerTable tbody");
    var sharedTemplatesWith = tbody.data("sharedTemplatesWith");

    // Send an asynchronous call to the server to accept the template
    JSONRPCRequest({
        method : 'jsonPreviewSharedTemplate',
        params: [sharedTemplatesWith[template_index], type]
    }, function(response) {

        // Failure?
        if (response.success != "true") {

            $("#message").html("<p>Could not load template for preview!</p>");
            return;

        }

        // Replace the content of the 'info' div
        $("#info").html(response.preview);

    });

}

// Sort the templates by previous user
function sort_by_previous_owner(template1, template2) {
    var a = template1['previous_owner'];
    var b = template2['previous_owner'];
    if (a == b) {
        return 0;
    }
    return (a < b) ? -1 : 1;
}

// Toggles visibility of the shared templates div.
function toggleSharedTemplatesDiv() {
    $('#sharedTemplatePicker').toggle();
}

// Closes the shared templates div.
function closefSharedTemplatesDiv() {
    $('#sharedTemplatePicker').hide();
}

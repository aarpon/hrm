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
        var sharedTemplatesBy   = response.sharedTemplatesBy;

        // Sort them
        sharedTemplatesWith.sort(sort_by_previous_owner);
        sharedTemplatesBy.sort(sort_by_owner);

        // Get the number of templates
        var numSharedTemplates = sharedTemplatesWith.length +
            sharedTemplatesBy.length;

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
        fillInSharedTemplatesTable(sharedTemplatesWith, sharedTemplatesBy, type);

    });
}

// Fill in the shared template table
function fillInSharedTemplatesTable(sharedTemplatesWith, sharedTemplatesBy, type) {

    // Get the data container
    var templatePicker = $("#shareTemplatePickerBody");

    // Make sure to clear the template data
    templatePicker.data("sharedTemplatesWith", null);
    templatePicker.data("sharedTemplatesBy", null);

    // Get the shared with/by table bodies
    var tbodyWith = $("#sharedWithTemplatePickerTable tbody");
    var tbodyBy = $("#sharedByTemplatePickerTable tbody");

    // Remove content from tables
    tbodyWith.empty();
    tbodyBy.empty();

    // Now fill the tables
    if (sharedTemplatesWith.length == 0) {
        tbodyWith.append("<tr><td>No user templates shared with you.</td>/<tr>");
    }

    if (sharedTemplatesBy.length == 0) {
        tbodyBy.append("<tr><td>No user templates shared by you.</td>/<tr>");
    }

    // Store the shared templates
    templatePicker.data("sharedTemplatesWith", sharedTemplatesWith);
    templatePicker.data("sharedTemplatesBy", sharedTemplatesBy);

    // Now fill the shared with table
    var lastUser = null;
    for (var i = 0; i < sharedTemplatesWith.length; i++) {

        // Add 'header' row if needed
        if (sharedTemplatesWith[i].previous_owner != lastUser) {

            // Add "From 'user'" header
            tbodyWith.append("<tr><td colspan='4' class='from_by_template'>" +
                "From <b>" + sharedTemplatesWith[i].previous_owner +
                "</b>:</td></tr>");

            lastUser = sharedTemplatesWith[i].previous_owner;
        }

        // Add template with actions
        var tdAccept = "<td class='accept_template' " +
            "title='Accept the template.' " +
            "onclick='acceptSharedWithTemplate(" + String(i) + ", \"" + type + "\")' >" +
            "<a href='#'>&nbsp;</a></td>";

        var tdReject = "<td class='reject_template' " +
            "title='Reject the template.' " +
            "onclick='rejectSharedWithTemplate(" + String(i) + ", \"" + type + "\")' >" +
            "<a href='#'>&nbsp;</a></td>";

        var tdPreview = "<td class='preview_template'  " +
            "title='Preview the template.' " +
            "onclick='previewSharedWithTemplate(" + String(i) + ", \"" + type + "\")' >" +
            "<a href='#'>&nbsp;</a></td>";

        var tdTemplate = "<td class='name_template' " +
            "title='Shared with you on " +
            sharedTemplatesWith[i].sharing_date + ".' >" +
            sharedTemplatesWith[i].name + "</td>";

        tbodyWith.append("<tr>" + tdAccept + tdReject +
            tdPreview + tdTemplate + "</tr>");

    }

    // Now fill the shared by table
    var lastUser = null;
    for (var i = 0; i < sharedTemplatesBy.length; i++) {

        // Add 'header' row if needed
        if (sharedTemplatesBy[i].owner != lastUser) {

            // Add "With 'user'" header
            tbodyBy.append("<tr><td colspan='4' class='from_by_template'>" +
                "With <b>" + sharedTemplatesBy[i].owner +
                "</b>:</td></tr>");

            lastUser = sharedTemplatesBy[i].owner;
        }

        // Add template with actions
        var tdAccept = "<td class='blank_template'>&nbsp;</td>";

        var tdReject = "<td class='reject_template' " +
            "title='Reject the template.' " +
            "onclick='rejectSharedByTemplate(" + String(i) + ", \"" + type + "\")' >" +
            "<a href='#'>&nbsp;</a></td>";

        var tdPreview = "<td class='blank_template'>&nbsp;</td>";

        var tdTemplate = "<td class='name_template' " +
            "title='You shared with " + sharedTemplatesBy[i].owner + " on " +
            sharedTemplatesBy[i].sharing_date + ".' >" +
            sharedTemplatesBy[i].name + "</td>";

        tbodyBy.append("<tr>" + tdAccept + tdReject +
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
function acceptSharedWithTemplate(template_index, type) {

    // Get the data container
    var templatePicker = $("#shareTemplatePickerBody");

    // Get the shared templates content from the data container
    var sharedTemplatesWith = templatePicker.data("sharedTemplatesWith");
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
function rejectSharedWithTemplate(template_index, type) {

    // Get the data container
    var templatePicker = $("#shareTemplatePickerBody");

    // Get the shared templates content from the data container
    var sharedTemplatesWith = templatePicker.data("sharedTemplatesWith");
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

// Delete the template with specified index
function rejectSharedByTemplate(template_index, type) {

    // Get the data container
    var templatePicker = $("#shareTemplatePickerBody");

    // Get the shared templates content from the data container
    var sharedTemplatesBy = templatePicker.data("sharedTemplatesBy");
    if (sharedTemplatesBy == null) {
        return;
    }

    // Ask the user for confirmation
    if (! confirm("Are you sure you want to cancel sharing of this template?")) {
        return;
    }

    // Send an asynchronous call to the server to accept the template
    JSONRPCRequest({
        method : 'jsonDeleteSharedTemplate',
        params: [sharedTemplatesBy[template_index], type]
    }, function(response) {

        // Failure?
        if (response.success != "true") {

            $("#message").html("<p>Could not delete template!</p>");
            return;

        }

        // Now reload the page to update everything
        location.reload(true);

        // Inform
        $("#message").html("<p>Template sharing canceled.</p>");
    });

}

// Preview the template with specified index
function previewSharedWithTemplate(template_index, type) {

    // Get the data container
    var templatePicker = $("#shareTemplatePickerBody");

    // Get the shared templates content from the data container
    var sharedTemplatesWith = templatePicker.data("sharedTemplatesWith");
    if (sharedTemplatesWith == null) {
        return;
    }

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

// Sort the templates by user
function sort_by_owner(template1, template2) {
    var a = template1['owner'];
    var b = template2['owner'];
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
function closeSharedTemplatesDiv() {
    $('#sharedTemplatePicker').hide();
}

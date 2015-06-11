// functions for the interaction with Omero
//
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt


function omeroLogin() {
    setActionToUpdate();
    confirmSubmit();
    omeroCheckCredentials.submit();
}

function cancelOmeroSelection() {

    control = document.getElementById('selection').innerHTML;
    cancelSelection();
}

function omeroTransfer(form, fileSelection, browseFolder) {

    // TODO: multi selection!
    var node = $("#omeroTree").tree('getSelectedNode');

    if (browseFolder == "src") {
        // we're in the "src" folder view, this means we want to transfer
        // images from OMERO to the HRM (download):
        if (node.class == 'Image') {
            var image   = node;
            var dataset = image.parent;

            form.OmeImageId.value = image.id;
            form.OmeImageName.value = image.name;
            form.OmeDatasetId.value = dataset.id;
        }

    } else {

        // we're in the "dst" folder view, this means we want to transfer
        // images from the HRM to OMERO (upload):
        if (node.class == 'Image') {
            var image   = node;
            var dataset = image.parent;
        } else if (node.class = 'Dataset') {
            var dataset = node;
        } else {
            return false;
        }
        form.OmeDatasetId.value = dataset.id;

        // assemble a JSON array with the selected files:
        var filelist = new Array();
        for (i=0; i < fileSelection.options.length; i++) {
            if (fileSelection.options[i].selected) {
                filelist.push(fileSelection.options[i].text);
            }
        }
        form.selectedFiles.value = JSON.stringify(filelist);
    }
}



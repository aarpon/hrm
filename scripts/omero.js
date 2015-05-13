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

    var node = $("#omeroTree").tree('getSelectedNode');

    if (browseFolder == "src") {
        // we're in the "src" folder view, this means we want to transfer
        // images from OMERO to the HRM (download):
        if (node.class == 'Image') {
            var image   = node;
            var dataset = image.parent;
            var project = image.parent.parent;

            if (image.name.length > 0) {
                form.OmeImageId.value = image.id;
            }

            if (dataset.name.length > 0) {
                form.OmeImageName.value = image.name;
            }

            if (project.name.length > 0) {
                form.OmeDatasetId.value = dataset.id;
            }
        }
    } else {

        // we're in the "dst" folder view, this means we want to transfer
        // images from the HRM to OMERO (upload):
        if (node.class == 'Image') {
            var image   = node;
            var dataset = image.parent;
            var project = image.parent.parent;

        } else if (node.class = 'Dataset') {

            var dataset = node;
            var project = dataset.parent;
        }

        if (typeof dataset != 'undefined' && dataset.name.length > 0) {
            form.OmeDatasetId.value = dataset.id;
        }
    }

    form.selectedFiles.value = "";
    for (i=0; i < fileSelection.options.length; i++) {

        if (fileSelection.options[i].selected) {
            form.selectedFiles.value += fileSelection.options[i].value;
            form.selectedFiles.value += " ";
        }
    }
}



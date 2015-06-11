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
    oTree = $('#omeroTree');
    selected_nodes = oTree.tree('getSelectedNodes');
    selected_nodes.forEach(function(node) {
        oTree.tree('removeFromSelection', node);
    });
}

function omeroTransfer(form, fileSelection, browseFolder) {
    if (browseFolder == "src") {
        // we're in the "src" folder view, this means we want to transfer
        // images from OMERO to the HRM (download):
        form.OmeImages.value = getSelectedImages();

    } else {
        // we're in the "dst" folder view, this means we want to transfer
        // images from the HRM to OMERO (upload):
        form.OmeDatasetId.value = getSelectedDataset();

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

function getSelectedImages() {
        // return an array of image-dicts as a JSON object or an empty string
        // if no image node was selected in the tree
        var selected = $("#omeroTree").tree('getSelectedNodes');
        var images = new Array();
        selected.forEach(function(node) {
            if (node.class == 'Image') {
                var image = {
                    'id' : node.id,
                    'name' : node.name
                }
                images.push(image);
            }
        });
        if (images.length == 0) {
            return "";
        } else {
            return JSON.stringify(images);
        }
}

function getSelectedDataset() {
        // return the ID of the selected dataset node or the one of the parent
        // dataset in case an image is selected
        var node = $("#omeroTree").tree('getSelectedNode');
        if (node.class == 'Image') {
            var dataset = node.parent;
        } else if (node.class = 'Dataset') {
            var dataset = node;
        } else {
            return false;
        }
}

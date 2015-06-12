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
            return node.parent.id;
        } else if (node.class = 'Dataset') {
            return node.id;
        } else {
            return '';
        }
}

/*******  jqTree helper functions ********/

function processNodeHTML(node, li) {
    // This is supposed to be used in jqTree's "onCreateLi" option to process
    // the HTML of a <li> item created during tree assembly. It checks the
    // type of the node to be created and inserts an icon depending on this.

    // the mapping from node class to icons:
    var icons = {
        'ExperimenterGroup' : 'images/omero_group.png',
        'Experimenter' : 'images/omero_user.png',
        'Project' : 'images/omero_project.png',
        'Dataset' : 'images/omero_dataset.png',
        'Image' : 'images/omero_image.png'
    }
    // matching patterns for node types:
    var pat = {
        'folder' : 'jqtree-title-folder">',
        'terminal' : 'jqtree_common">',
    }
    var context = li.find('.jqtree-element').context;
    var orig = context.innerHTML;
    var css_class = 'jqtree-' + node.class;
    var icon = '<img class="' + css_class
        + '" src="' + icons[node.class] + '"> ';
    if (node.class == 'Image') {
        // console.log('this is a terminal node');
        context.innerHTML = orig.replace(
            pat['terminal'], pat['terminal'] + icon);
    } else {
        // console.log('this is a folder node');
        context.innerHTML = orig.replace(
            pat['folder'], pat['folder'] + icon);
    }
}

function handleMultiSelectClick(e) {
    // Handler function to bind to jqTree's 'tree.click' event to allow
    // multi-selection of nodes, depending on their type.

    e.preventDefault();  // disable single selection

    var oTree = $('#omeroTree');
    var clicked = e.node;

    if (oTree.tree('isNodeSelected', clicked)) {
        oTree.tree('removeFromSelection', clicked);
    } else {
        // allow only images to be selected:
        if (clicked.class == "Image") {
            oTree.tree('addToSelection', clicked);
        }
    }
}

function allowNodeSelect(node) {
    // Handler for jqTree's 'onCanSelectNode' function to determine if a node
    // is valid to be selected by clicking on it.

    // only an image or dataset may be selected:
    if ((node.class == "Image") || (node.class == "Dataset")) {
        return true;
    } else {
        return false;
    }
}

/*******  jqTree helper functions ********/

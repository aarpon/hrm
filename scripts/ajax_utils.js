// ajax utils
// 
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Requires jQuery
// Posts a request for a summary of the selected parameter set
// \param pSetType    : either 'setting' or 'task_setting'
// \param pSetName    : name of the setting to be returned
// \param pPublicSet  : either true (for public, template sets) or false
function ajaxGetParameterListForSet(pSetType, pSetName, pPublicSet) {
  $.ajaxSetup ({  
    cache: false  
  });
  $.post(
    "ajax/ajax.php",
    { action: "getParameterListForSet",
      setType: pSetType,
      setName: pSetName,
      publicSet: pPublicSet
    },
    function(data) {
      $('#info').html(data);
    }
  );
}

// Requires jQuery
// Posts a request for the number of jobs currently in the queue.
// The returned data is 'no jobs', '1 job', or '<n> jobs'
// id  : id of the div where the returned string will be placed
// pre : string to be attached before the returned data
// post: String to be appened to the returned data
function ajaxGetNumberOfUserJobsInQueue(id, pre, post) {
  $.ajaxSetup ({  
    cache: false  
  });
  $.post(
    "ajax/ajax.php",
    { action: "getNumberOfUserJobsInQueue"
    },
    function(data) {
      if (data != '') {
        data = pre + data + post;
      }
      $('#' + id).html(data);
    }
  );
}

// Requires jQuery
// Posts a request for the number of jobs currently in the queue
// The returned data is in the form "There {is|are} {'no jobs'|'1 job'|'<n> jobs'}
// in the queue."
// \param id : if of the div that will display the returned html (data)
function ajaxGetTotalNumberOfJobsInQueue(id) {
  $.ajaxSetup ({  
    cache: false  
  });
  $.post(
    "ajax/ajax.php",
    { action: "getTotalNumberOfJobsInQueue"
    },
    function(data) {
      $('#' + id).html(data);
    }
  );
}

// Requires jQuery
// Posts a request for the full job queue table
// \param id : if of the div that will display the returned html (data)
function ajaxGetJobQueueTable(id) {
  $.ajaxSetup ({  
    cache: false  
  });
  $.post(
    "ajax/ajax.php",
    { action: "getJobQueueTable"
    },
    function(data) {
      $('#' + id).html(data);
    }
  );
}

// Requires jQuery
// Stores the selected file format in the session
// \param pFormat: selected file format
function ajaxSetFileFormat(pFormat, _callback) {
  $.ajaxSetup ({  
    cache: false  
  });
  $.post(
    "ajax/ajax.php",
    { action: "setFileFormat",
      format: pFormat
    },
    function(data) {
        if (typeof _callback === "function") {
            _callback(data);
        }
    }
  );
}

// Requires jQuery
// Calls the subsequent javaScript code to retrieve an image preview (2D, 3D, mockup, etc).
// \param pFilename: name of the selected file 
// \param pFolder: whether 'src' or 'dst'.
function ajaxGetImgPreview(pFilename, pIndex, pFolder) {
  $.ajaxSetup ({  
    cache: false  
  });
  $.post(
    "ajax/ajax.php",
    { action: "getImgPreview",
      filename: pFilename,
      index:    pIndex,
      folder:   pFolder
    },
    function(data) {
      // 'data' should contain here a string with the subsequent
      // javaScript function (and correct arguments) to call and 
      // to retrieve the proper preview. Simply evaluate the string
      // to call the underlying javaScript.
      eval(data);
    }
  );
}
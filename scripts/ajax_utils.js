// ajax utils
// 
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

// Requires jQuery
// Posts a request for a summary of the selected parameter set
// \param pSetType    : either 'setting' or 'task_setting'
// \param pSetName    : name of the setting to be returned
// \param pPublicSet  : either true (for public, template sets) or false
function getParameterListForSet(pSetType, pSetName, pPublicSet) {
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
// Posts a request for the number of jobs currently in the queue
// id  : id of the div where the returned string will be placed
// pre : string to be attached before the returned data
// post: String to be appened to the returned data
function getNumberOfUserJobsInQueue(id, pre, post) {
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
function getTotalNumberOfJobsInQueue(id) {
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
function getJobQueuetable(id) {
  $.ajaxSetup ({  
    cache: false  
  });
  $.post(
    "ajax/ajax.php",
    { action: "getJobQueuetable"
    },
    function(data) {
      $('#' + id).html(data);
    }
  );
}

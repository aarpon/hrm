// ajax utils

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
function getNumberOfJobsInQueue() {
  $.ajaxSetup ({  
    cache: false  
  });
  $.post(
    "ajax/ajax.php",
    { action: "getNumberOfJobsInQueue"
    },
    function(data) {
      $('#jobsInQueue').html(data);
    }
  );
}

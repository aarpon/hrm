hrmApp.controller('hrmFileManagerController', function($scope, hrmQueryService) {
  
  //Initializations
  $scope.formats = [];
  $scope.selectedFormat = null;
  $scope.allFiles = [];
  
  
  // Start querying when the controller is loaded
  hrmQueryService.queryHRM('jsonGetImageFormats', []).then(function(result) {
    $scope.formats = result.data.formats;
    console.log($scope.formats);
  });
  
  // Function to update the list of files
  // TODO: Add the possibility to filter by Extension
  $scope.updateFileList = function()  { 
    hrmQueryService.queryHRM('jsonGetImagesList', $scope.selectedFormat.stuff).then(function(result) {
      $scope.allFiles = result.data.files;
    });
  };
                                                        

});
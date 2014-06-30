hrmApp.directive('hrmFooter', function() {
  return {
    restrict: 'E',
    templateUrl:'angular/templates/hrm-footer.html',
    controller: function($scope,hrmQueryService) {
       $scope.contributors = [];
      //       Get the emails
      hrmQueryService.queryHRM('jsonGetContributors', []).then(function(result) {
        $scope.contributors = result.data.contributors
      });
      
      $scope.showMessage = function(id) {
        if ($scope.contributors[id].email === "") {
          return 'Please login to contact contributor';
        }
        return 'Send us an email!';
      }
    }
  };
});

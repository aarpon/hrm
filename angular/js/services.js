hrmApp.factory('hrmQueryService', function($http) {
  // This is the configuration we need for sending things to PHP
  // Note the $.param that is needed for the data. Otherwise
  // We de not populate $_POST
  
  makeRequest = function(amethod, aparams){
     request = { 
       method:'POST',
       headers: {'Content-Type': 'application/x-www-form-urlencoded'},
       url:'ajax/json-rpc-server.php',
       data: {
         method: amethod,
         params: aparams,
         id: '1',
         jsonrpc: '2.0'
       }
     }; 
       // Prepare to send to PHP, need jQuery... ugh.
       request.data = $.param(request.data);
       return request;
  };
      
  return {
    queryHRM : function(method, params) {
      // Prepare config
      request = makeRequest(method,params);
      console.log(request);
      return $http(request).then(function(result) {
        console.log(result);
        return result;
        
      });
      
    }
  }
});
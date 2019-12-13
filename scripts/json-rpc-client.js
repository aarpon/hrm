// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

/**
 * Performs an asynchronous Ajax call to the (PHP) JSON-RPC (2.0) server 
 * (json-rpc-server.php) and passes the results in JSON format to the provided 
 * callback in the form function(response) {}
 *
 * @param {JSON object} data   Javascript object in JSON format; it must be in 
 *                             the form:
 *                             
 *                                 data = {
 *                                   method: 'someMethod',
 *                                   params: [ 'param1', 'param2', ... ]
 *                                 }
 *
 *                             the 'params' property is mandatory; if the
 *                             method does not take parameters, just pass
 *                             an empty array, like this:
 *                             
 *                                 data = {
 *                                   method: 'someMethod',
 *                                   params: [ ]
 *                                 }
 *                                 
 * @param {function} callback  Function to be called to process the returned
 *                             response, in the form:
 *                             
 *                                 function(response) { ... }
 * 
 * The actual call is then:
 * 
 *     var data = {
 *       method: 'someMethod',
 *       params: [ 'param1', 'param2', ... ]
 *     }
 *     JSONRPCRequest(data, function(response) {
 *       // ... do something with response 
 *     }
 */
function JSONRPCRequest(data, callback) {

    // Use ECMAScript 5 strict mode
    "use strict";
    
    // Append RPC info to the data array to be sent to
    // the server. We hard-code the id to be "1".
    data.id = "1";
    data.jsonrpc = "2.0";
    
    // Disable caching
    $.ajaxSetup({
        cache: false,
        timeout: 0
    });
    
    // Submit the asyncronous Ajax call
    $.ajax(
        {
            url: "ajax/json-rpc-server.php",
            type: "POST",
            dataType: "json",
            async: true,
            data: data,
            success: callback
        }
    );
}

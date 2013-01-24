/**
 * a library to multiplex various requests through one ajax requests
 *
 * @author Robert Schönthal <robert.schoenthal@gmail.com>
 */
Multiplexer = (function () {

    //defaults
    var multiplexerEndpoint = '/multiplex.json';
    var requestFormat = 'json';
    var requests = {};

    function addRequest(requestObject, successCallback, errorCallback) {
        if (!requestObject.uri) {
            throw "invalid Request Config";
        }
        if (typeof successCallback == 'undefined') {
            throw "invalid success Callback given";
        }

        if (typeof errorCallback == 'undefined') {
            throw "invalid error Callback given";
        }

        requests[requestObject.uri] = {'request': requestObject, 'success': successCallback, 'error': errorCallback};
    }

    function preparedRequests(filter) {
        var prepared = {};
        var filter = filter;

        $.each(requests, function(index, current) {
            //request filtering
            if(filter.length && -1 == $.inArray(current.request.uri, filter)) {
                return false;
            }

            prepared[current.request.uri] = {
                'uri' : current.request.uri,
                'method' : (typeof current.request.method != 'undefined' ? current.request.method : 'GET'),
                'parameters' : (typeof current.request.parameters != 'undefined' ? current.request.parameters : [])
            };
        });

        return {'requests' : prepared};
    }

    function callMasterRequest(filter, successCallback, errorCallback) {
        $.ajax(multiplexerEndpoint, {
            cache: false,
            data : preparedRequests(filter ? filter : []),
            dataType: requestFormat,
            error: (errorCallback ? errorCallback : onError),
            success: (successCallback ? successCallback : onSuccess)
        });
    }

    /*
     * default global onError Callback
     */
    function onError(xhr, status, error) {
        if(typeof console != 'undefined') {
            console.log(error);
        }
    }

    /*
     * default global onSuccess Callback
     */
    function onSuccess(data, status, xhr) {
        if ('json' == requestFormat) {
            $.each(data.responses, function(uri, response) {
                if (response.status < 400) {
                    requests[response.request].success(response.response);
                } else {
                    requests[response.request].error(response.response);
                }
            });
        } else {
            //TODO what todo with html, cant dispatch them across callbacks
        }
    }

    /**
     * public api
     */
    return {
        /**
         * set the serverside Multiplex Endpoint
         *
         * @param url
         */
        setEndpoint : function (url) {
            multiplexerEndpoint = url;
        },

        /**
         * set the exchange format (html|json), atm. only json is usable
         *
         * @param format
         */
        setFormat : function(format) {
            requestFormat = format;
        },

        /**
         * add a Request to the Multiplexer
         *
         * @param requestObject
         * {
         *   uri : '/foo/bar',
         *   method: 'GET',
         *   parameters : {
         *       'foo' : ['bar', 'bazz],
         *       'bar' : 'foo'
         *   }
         * }
         * @param successCallback the success callback to call if this particular request was ok
         * @param errorCallback the error callback to call if this particular request resulted in an error
         */
        add : function(requestObject, successCallback, errorCallback) {
            addRequest(requestObject, successCallback, errorCallback);
        },

        /**
         * multiplex the requests
         *
         * @param filter (only multiplex these requests)
         *  ['/foo', '/bar']
         *
         * @param successCallback will be called on overall success
         * @param errorCallback will be called on overall failure
         */
        call : function(filter, successCallback, errorCallback) {
            callMasterRequest(filter, successCallback, errorCallback);
        },

        /**
         * clear all requests
         */
        clear : function() {
            requests = {};
        },

        /**
         * removes a request
         *
         * @param uri
         */
        remove : function(uri) {
            delete  requests[uri];
        }
    }
}());
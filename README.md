UNMAINTAINED
============

This bundle is no longer maintained. Feel free to fork it if needed.

Overview [![Build Status](https://travis-ci.org/liip/LiipMultiplexBundle.png)](https://travis-ci.org/liip/LiipMultiplexBundle)
========

This Bundles enables "multiplexing" multiple requests into a single Request/Response:

for example this request:

http://foo.com/multiplex.json?requests[login][uri]=/session/new&requests[notification][uri]=/notifications&requests[notification][method]=get&requests[notification][parameters][]=broadcasts&requests[notification][parameters][]=personal

will internally call:

`/session/new` and `/notifications`

and return **one** Response:

``` json
{
    "/session/new"   : {"request" : "/session/new", "status": 200, "response": "the Response Content of /session/new"},
    "/notifications" : {"request" : "/notifications", "status": 403, "response": "Forbidden"}
}
```


*Attention*:
Installing this Bundle basically lets anyone side step the security firewall.
Therefore its important to either secure the multiplex route or to implement
security checks inside all relevant controllers

Installation
============

  1. Add this bundle to your project with Composer:

    ```
    $ php composer.phar require liip/multiplex-bundle
    ```

  2. Add this bundle to your application's kernel:

    ``` php
      // app/AppKernel.php
      public function registerBundles()
      {
          return array(
              // ...
              new Liip\MultiplexBundle\LiipMultiplexBundle(),
              // ...
          );
      }
    ```

Configuration
=============

The following Configuration Options exists:

* `allow_externals` : if enabled also external urls can be multiplexed (default: *true*)
* `display_errors`  : if enabled and an error occured, the error message will be returned, otherwise (if available) the http status code message (default: *true*)
* `route_option`    : only used in combination with `restrict_routes`, defines the route option which should be looked up if `restrict_routes` is on (default: *multiplex_expose*)
* `restrict_routes` : if enabled only routes with the `route_option` are multiplexable (default: *false*)

Application Configuration
-------------------------

here the default config

``` yml
liip_multiplex:
    allow_externals: true
    display_errors: true
    route_option: 'multiplex_expose'
    restrict_routes: false
```

Routing Configuration
---------------------
if you want to restrict multiplexing to specific routes, define the option in each route you want to expose

``` xml
<route id="_my_route" pattern="/foo/bar">
    <default key="_controller">MyBundle:Controller:index</default>
    <option key="multiplex_expose">true</option>
</route>
```

and don't forget to set `restrict_routes` to `true`!

Usage
=====

This Bundles provides a decent Javascript Library to use the Multiplexer Endpoint.

Integration of the Javascript
-----------------------------

``` twig
  {% javascripts
  "@LiipMultiplexBundle/Resources/public/js/ajax_multiplexer.js"
    output='js/multiplexer.js'
  %}
    <script src="{{ asset_url }}"></script>
  {% endjavascripts %}
```

Using the Javascript `Multiplexer`
------------------------------------

``` javascript

//configuring the Multiplexer
Multiplexer.setEndpoint('/path/to/multiplexer/endpoint'); //as in your routing defaults to /multiplex.json
Multiplexer.setFormat('json'); //only useful exchange format

//adding Requests to the Multiplexer
Multiplexer.add(
    {'uri' : '/foo/bar', 'method' : 'GET', 'parameters' : {'foo':'bar'}}, //the Request Object
    function(content) { /* ... */}, // the Success Callback
    function(content) { /* ... */}  // the Error Callback
);

Multiplexer.add(
    {'uri' : 'http://google.com', 'method' : 'GET', 'parameters' : {'q':'Symfony2'}},
    function(content) { /* this callback is called on success for this request*/},
    function(content) { /* this callback is called on error for this request*/}
);

//pushing all Requests to the Server
Multiplexer.call(
    function(r){ /* ... */ }, //the global success callback (optional)
    function(r){ /* ... */ } //the global error callback (optional)
);

//pushing only a set of Requests to the Server
Multiplexer.call(['/foo/bar']);

```

Tests
=====
  1. To run the unit tests, require all dependencies

    ```
    $ php composer.phar update --dev
    ```

  2. Run PHPUnit

    ```
    $ phpunit
    ```

  3. See Travis for automated Tests

    https://travis-ci.org/liip/LiipMultiplexBundle

TODO
====

* more output formats (usable html/xml formats)

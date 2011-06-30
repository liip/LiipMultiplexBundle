Overview
========

This Bundles enables "multiplexing" multiple requests into a single request/reponse:
http://foo.com/multiplex.json?requests[login][uri]=/session/new&requests[notification][uri]=/notifications&requests[notification][method]=get&requests[notification][parameters][]=broadcasts&requests[notification][parameters][]=personal

*Attention*:
Installing this Bundle basically lets anyone side step the security firewall.
Therefore its important to either secure the multiplex route or to implement
security checks inside all relevant controllers

Installation
============

  1. Add this bundle to your project as Git submodules:

          $ git submodule add git://github.com/liip/LiipMultiplexBundle.git vendor/bundles/Liip/MultiplexBundle

  2. Add the Liip namespace to your autoloader:

          // app/autoload.php
          $loader->registerNamespaces(array(
                'Liip' => __DIR__.'/../vendor/bundles',
                // your other namespaces
          ));

  3. Add this bundle to your application's kernel:

          // application/ApplicationKernel.php
          public function registerBundles()
          {
              return array(
                  // ...
                  new Liip\MultiplexBundle\LiipMultiplexBundle(),
                  // ...
              );
          }

  4. Configure the `multiplex` service in your config:

          # application/config/config.yml
          liip_multiplex: ~

  5. To run the unit tests, set SYMFONY to the src directory inside the symfony vendor dir in the phpunit.xml

         $ cp phpunit.xml.dist phpunit.xml
         $ vi phpunit.xml

  6. Copy the functional tests to your projects functional tests

         $ cp FunctionalTests/MultiplexTest.php ..

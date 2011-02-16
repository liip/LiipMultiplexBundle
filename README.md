Installation
============

  1. Add this bundle to your project as Git submodules:

          $ git submodule add git://github.com/liip/MultiplexBundle.git src/Liip/MultiplexBundle

  2. Add this bundle to your application's kernel:

          // application/ApplicationKernel.php
          public function registerBundles()
          {
              return array(
                  // ...
                  new Liip\MultiplexBundle\LiipMultiplexBundle(),
                  // ...
              );
          }

  3. Configure the `multiplex` service in your config:

          # application/config/config.yml
          liip_multiplex: ~

  4. To run the unit tests, set SYMFONY to the src directory inside the symfony vendor dir in the phpunit.xml

         $ cp phpunit.xml.dist phpunit.xml
         $ vi phpunit.xml

  5. Copy the functional tests to your projects functional tests

         $ cp FunctionalTests/MultiplexTest.php ..

  6. Add calls to your application like in the following example doing two requests at once:

         http://foo.com/multiplex.json?requests[login][uri]=/session/new&requests[notification][uri]=/notifications&requests[notification][method]=get&requests[notification][parameters][]=broadcasts&requests[notification][parameters][]=personal

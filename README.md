Installation
============

  1. Add this bundle to your project as Git submodules:

          $ git submodule add git://github.com/liip/LiipMultiplexBundle.git src/Bundle/Liip/MultiplexBundle

  2. Add this bundle to your application's kernel:

          // application/ApplicationKernel.php
          public function registerBundles()
          {
              return array(
                  // ...
                  new Bundle\Liip\MultiplexBundle\LiipMultiplexBundle(),
                  // ...
              );
          }

  3. Configure the `multiplex` service in your config:

          # application/config/config.yml
          multiplex.config: ~

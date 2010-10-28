<?php

namespace Liip\MultiplexBundle\Tests\DependencyInjection;

use Bundle\Liip\MultiplexBundle\DependencyInjection\MultiplexExtension;

class MultiplexBundleExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Bundle\Liip\MultiplexBundle\DependencyInjection\MultiplexExtension::getXsdValidationBasePath
     * @covers Bundle\Liip\MultiplexBundle\DependencyInjection\MultiplexExtension::getNamespace
     * @covers Bundle\Liip\MultiplexBundle\DependencyInjection\MultiplexExtension::getAlias
     */
    public function testBoilerplate()
    {
        $extension = new MultiplexExtension();

        $this->assertNotEmpty($extension->getXsdValidationBasePath());
        $this->assertNotEmpty($extension->getNamespace());
        $this->assertNotEmpty($extension->getAlias());
    }

    /**
     * @covers Bundle\Liip\MultiplexBundle\DependencyInjection\MultiplexExtension::configLoad
     */
    public function testConfigLoadLoadsYaml()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->disableOriginalConstructor()->getMock();
        $container->expects($this->once())
            ->method('hasDefinition')
            ->with('multiplex')
            ->will($this->returnValue(false));

        $fileloader = $this->getMockBuilder('Symfony\Component\DependencyInjection\Loader\YamlFileLoader')->disableOriginalConstructor()->getMock();
        $fileloader->expects($this->once())
            ->method('load');

        $extension = $this->getMockBuilder('Bundle\Liip\MultiplexBundle\DependencyInjection\MultiplexExtension')
            ->setMethods(array('getFileLoader'))->getMock();
        $extension->expects($this->once())
            ->method('getFileLoader')
            ->with($container)
            ->will($this->returnValue($fileloader));


        $extension->configLoad(array(), $container);
    }

    /**
     * @covers Bundle\Liip\MultiplexBundle\DependencyInjection\MultiplexExtension::configLoad
     */
    public function testConfigLoadSetParameter()
    {
        $key = 'foo';
        $value = 'bar';
        $config = array(
            $key => $value
        );
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->disableOriginalConstructor()->getMock();
        $container->expects($this->once())
            ->method('hasDefinition')
            ->with('multiplex')
            ->will($this->returnValue(true));

        $container->expects($this->once())
            ->method('setParameter')
            ->with('multiplex.'.$key, $value);

        $extension = new MultiplexExtension();

        $extension->configLoad($config, $container);
    }

    /**
     * @covers Bundle\Liip\MultiplexBundle\DependencyInjection\MultiplexExtension::configLoad
     */
    public function testConfigLoadHasDefinition()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->disableOriginalConstructor()->getMock();
        $container->expects($this->once())
            ->method('hasDefinition')
            ->with('multiplex')
            ->will($this->returnValue(true));

        $extension = $this->getMockBuilder('Bundle\Liip\MultiplexBundle\DependencyInjection\MultiplexExtension')
            ->setMethods(array('getFileLoader'))->getMock();
        $extension->expects($this->never())
            ->method('getFileLoader');

        $extension->configLoad(array(), $container);
    }


    /**
     * @covers Bundle\Liip\MultiplexBundle\DependencyInjection\MultiplexExtension::getFileLoader
     */
    public function testGetFileLoader()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->disableOriginalConstructor()->getMock();

        $extension = new MultiplexExtension();

        $fileloader = $extension->getFileLoader($container);

        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Loader\LoaderInterface', $fileloader);
    }
}

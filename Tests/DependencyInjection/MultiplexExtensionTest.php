<?php

namespace Liip\MultiplexBundle\Tests\DependencyInjection;

use Liip\MultiplexBundle\DependencyInjection\MultiplexExtension;

class MultiplexBundleExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Liip\MultiplexBundle\DependencyInjection\MultiplexExtension::getXsdValidationBasePath
     * @covers Liip\MultiplexBundle\DependencyInjection\MultiplexExtension::getNamespace
     * @covers Liip\MultiplexBundle\DependencyInjection\MultiplexExtension::getAlias
     */
    public function testBoilerplate()
    {
        $extension = new MultiplexExtension();

        $this->assertNotEmpty($extension->getAlias());
    }

    /**
     * @covers Liip\MultiplexBundle\DependencyInjection\MultiplexExtension::configLoad
     */
    public function testConfigLoadSetParameter()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->disableOriginalConstructor()->getMock();
        $container->expects($this->once())
            ->method('setParameter')
            ->with('liip_multiplex.foo', 'bar')
            ->will($this->returnValue(null));

        $fileloader = $this->getMockBuilder('Symfony\Component\DependencyInjection\Loader\XmlFileLoader')->disableOriginalConstructor()->getMock();
        $fileloader->expects($this->once())
            ->method('load');

        $extension = $this->getMockBuilder('Liip\MultiplexBundle\DependencyInjection\MultiplexExtension')
            ->setMethods(array('getFileLoader'))->getMock();
        $extension->expects($this->once())
            ->method('getFileLoader')
            ->with($container)
            ->will($this->returnValue($fileloader));

        $extension->configLoad(array(array('foo' => 'bar')), $container);
    }


    /**
     * @covers Liip\MultiplexBundle\DependencyInjection\MultiplexExtension::getFileLoader
     */
    public function testGetFileLoader()
    {

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->disableOriginalConstructor()->getMock();

        $extension = new MultiplexExtension();

        $fileloader = $extension->getFileLoader($container);

        $this->assertInstanceOf('Symfony\Component\Config\Loader\LoaderInterface', $fileloader);
    }
}

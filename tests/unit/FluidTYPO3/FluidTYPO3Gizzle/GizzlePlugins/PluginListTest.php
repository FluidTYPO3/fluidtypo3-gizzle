<?php
namespace FluidTYPO3\FluidTYPO3Gizzle\Tests\Unit\GizzlePlugins;

use NamelessCoder\Gizzle\PluginListInterface;
use FluidTYPO3\FluidTYPO3Gizzle\GizzlePlugins\PluginList;

/**
 * Class PluginListTest
 */
class PluginListTest extends \PHPUnit_Framework_TestCase {

	public function testGetPluginClassNamesReturnsValidClasses() {
		$pluginList = new PluginList();
		$classes = $pluginList->getPluginClassNames();
		foreach ($classes as $class) {
			$implementsInterface = is_a($class, 'NamelessCoder\\Gizzle\\PluginInterface', TRUE);
			$this->assertTrue(class_exists($class), 'Class "' . $class . '" does not exist');
			$this->assertTrue($implementsInterface, 'Class "' . $class . '" is not a valid Gizzle plugin');
		}
	}

}

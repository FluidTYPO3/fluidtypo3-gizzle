<?php
namespace FluidTYPO3\FluidTYPO3Gizzle\Tests\Unit\GizzlePlugins;

/**
 * Class SiteDeployPluginTest
 */
class SiteDeployPluginTest extends \PHPUnit_Framework_TestCase {

	public function testProcessCallsExpectedMethodSequence() {
		$payload = $this->getMock('NamelessCoder\\Gizzle\\Payload', array('dummy'), array(), '', FALSE);
		$mock = $this->getMock('FluidTYPO3\\FluidTYPO3Gizzle\\GizzlePlugins\\SiteDeployPlugin', array('pullLocalRepository'));
		$mock->expects($this->at(0))->method('pullLocalRepository');
		$mock->process($payload);
	}

}

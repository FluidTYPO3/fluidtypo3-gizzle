<?php
namespace FluidTYPO3\FluidTYPO3Gizzle\Tests\Unit\GizzlePlugins;
use FluidTYPO3\FluidTYPO3Gizzle\GizzlePlugins\SiteDeployPlugin;

/**
 * Class SiteDeployPluginTest
 */
class SiteDeployPluginTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @param string $branch
	 * @param string $repositoryName
	 * @param string $repositoryMasterBranch
	 * @param array $settings
	 * @param boolean $expected
	 * @dataProvider getTriggerParameters
	 */
	public function testTrigger($branch, $repositoryName, $repositoryMasterBranch, array $settings, $expected) {
		$repository = $this->getMock('NamelessCoder\\Gizzle\\Repository', array('getMasterBranch', 'getName'));
		$repository->expects($this->once())->method('getMasterBranch')->will($this->returnValue($repositoryMasterBranch));
		$repository->expects($this->once())->method('getName')->will($this->returnValue($repositoryName));
		$payload = $this->getMock('NamelessCoder\\Gizzle\\Payload', array('getRef', 'getRepository'), array(), '', FALSE);
		$payload->expects($this->once())->method('getRef')->will($this->returnValue('refs/heads/' . $branch));
		$payload->expects($this->exactly(2))->method('getRepository')->will($this->returnValue($repository));
		$mock = $this->getMock('FluidTYPO3\\FluidTYPO3Gizzle\\GizzlePlugins\\SiteDeployPlugin', array('getPullPlugin'));
		$mock->initialize($settings);
		$result = $mock->trigger($payload);
		$this->assertEquals($expected, $result);
	}

	public function testProcessCallsExpectedMethodSequence() {
		$payload = $this->getMock('NamelessCoder\\Gizzle\\Payload', array('dummy'), array(), '', FALSE);
		$mock = $this->getMock('FluidTYPO3\\FluidTYPO3Gizzle\\GizzlePlugins\\SiteDeployPlugin', array('pullLocalRepository'));
		$mock->expects($this->at(0))->method('pullLocalRepository');
		$mock->process($payload);
	}

	public function getTriggerParameters() {
		$monitored = SiteDeployPlugin::OPTION_MONITORED;
		$branch = SiteDeployPlugin::OPTION_BRANCH;
		return array(
			array('master', 'foobar', 'master', array($monitored => array('foobar'), $branch => 'master'), TRUE),
			array('master', 'foobar', 'master', array($monitored => array('foobar'), $branch => 'development'), FALSE),
		);
	}

	public function testPullLocalRepositoryCallsExpectedMethodSequence() {
		$repository = $this->getMock('NamelessCoder\\Gizzle\\Repository', array('getMasterBranch', 'getName'));
		$repository->expects($this->once())->method('getMasterBranch')->will($this->returnValue('master'));
		$repository->expects($this->once())->method('getName')->will($this->returnValue('FluidTYPO3/FluidTYPO3Gizzle'));
		$payload = $this->getMock('NamelessCoder\\Gizzle\\Payload', array('getRepository'), array(), '', FALSE);
		$payload->expects($this->exactly(2))->method('getRepository')->will($this->returnValue($repository));
		$pullPlugin = $this->getMock('NamelessCoder\\GizzleGitPlugins\\GizzlePlugins\\PullPlugin', array('process'));
		$pullPlugin->expects($this->once())->method('process')->with($payload);
		$mock = $this->getMock(
			'FluidTYPO3\\FluidTYPO3Gizzle\\GizzlePlugins\\SiteDeployPlugin',
			array('getPullPlugin', 'getSettingValue')
		);
		$mock->expects($this->once())->method('getPullPlugin')->will($this->returnValue($pullPlugin));
		$mock->expects($this->exactly(3))->method('getSettingValue')->will($this->returnValueMap(array('directory', '.', 'master')));
		$mock->process($payload);
	}

	public function testGetPullPluginReturnsInitializedPullPlugin() {
		$mock = new SiteDeployPlugin();
		$method = new \ReflectionMethod($mock, 'getPullPlugin');
		$method->setAccessible(TRUE);
		$settings = array('foo' => 'bar');
		$result = $method->invoke($mock, $settings);
		$this->assertEquals($settings, $this->getObjectAttribute($result, 'settings'));
	}

}

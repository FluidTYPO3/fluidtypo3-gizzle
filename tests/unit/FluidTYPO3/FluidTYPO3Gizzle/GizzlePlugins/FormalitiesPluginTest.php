<?php
namespace FluidTYPO3\FluidTYPO3Gizzle\Tests\Unit\GizzlePlugins;

use FluidTYPO3\FluidTYPO3Gizzle\GizzlePlugins\FormalitiesPlugin;
use NamelessCoder\Gizzle\Commit;
use NamelessCoder\Gizzle\Payload;

/**
 * Class FormalitiesPluginTest
 */
class FormalitiesPluginTest extends \PHPUnit_Framework_TestCase {

	public static function setUpBeforeClass() {
		define('GIZZLE_HOME', '');
	}

	public function testTrigger() {
		$payload = $this->getDummyPayload();
		$plugin = new FormalitiesPlugin();
		$payload->setAction(FormalitiesPlugin::ACTION_CLOSE);
		$this->assertFalse($plugin->trigger($payload));
		$payload->setAction('open');
		$this->assertTrue($plugin->trigger($payload));
	}

	public function testWarnAboutErrorsAndPayloadOrigin() {
		$payload = $this->getDummyPayload();
		$plugin = $this->getMock('FluidTYPO3\\FluidTYPO3Gizzle\\GizzlePlugins\\FormalitiesPlugin', array('storePullRequestComment'));
		$plugin->expects($this->once())->method('storePullRequestComment');
		$method = new \ReflectionMethod($plugin, 'warnAboutErrorsAndPayloadOrigin');
		$method->setAccessible(TRUE);
		$method->invokeArgs($plugin, array($payload));
	}

	public function testWarnAboutErrors() {
		$payload = $this->getDummyPayload();
		$plugin = $this->getMock('FluidTYPO3\\FluidTYPO3Gizzle\\GizzlePlugins\\FormalitiesPlugin', array('storePullRequestComment'));
		$plugin->expects($this->once())->method('storePullRequestComment');
		$method = new \ReflectionMethod($plugin, 'warnAboutErrors');
		$method->setAccessible(TRUE);
		$method->invokeArgs($plugin, array($payload));
	}

	/**
	 * @param Payload $payload
	 * @param Commit $commit
	 * @param string $url
	 * @param string $filename
	 * @param boolean $expectation
	 * @dataProvider getValidateCodeStyleTestValues
	 */
	public function testValidateCodeStyleOfPhpFile(Payload $payload, Commit $commit, $url, $filename, $expectation) {
		$plugin = $this->getMock(
			'FluidTYPO3\\FluidTYPO3Gizzle\\GizzlePlugins\\FormalitiesPlugin',
			array('passStdinToCommand')
		);
		$plugin->expects($this->any())->method('passStdinToCommand')->will($this->returnValue(array('', '')));
		$method = new \ReflectionMethod('FluidTYPO3\\FluidTYPO3Gizzle\\GizzlePlugins\\FormalitiesPlugin', 'validateCodeStyleOfPhpFile');
		$method->setAccessible(TRUE);
		$result = $method->invokeArgs($plugin, array($payload, $commit, $url, $filename));
		$this->assertEquals($expectation, $result);
	}

	/**
	 * @return array
	 */
	public function getValidateCodeStyleTestValues() {
		return array(
			array($this->getDummyPayload(), new Commit(), __FILE__, __FILE__, TRUE)
		);
	}

	public function testPassStdinToCommand() {
		$plugin = new FormalitiesPlugin();
		$method = new \ReflectionMethod($plugin, 'passStdinToCommand');
		$method->setAccessible(TRUE);
		$result = $method->invokeArgs($plugin, array('cat', 'Hello world'));
		$this->assertEmpty($result[1]);
		$this->assertEquals('Hello world', $result[0]);
	}

	/**
	 * @param array $methods
	 * @return Payload
	 */
	protected function getDummyPayload($methods = array('passStdinToCommand')) {
		$payload = $this->getMock('NamelessCoder\\Gizzle\\Payload', $methods, array(), '', FALSE);
		$pullRequest = $this->getMock('NamelessCoder\\Gizzle\\PullRequest', array('getUrlStatuses'));
		$pullRequest->expects($this->any())->method('getUrlStatuses')->will($this->returnValue('http://demo.demo/demo/demo'));
		$api = $this->getMock('Milo\\GitHub\\Api', array('get', 'post', 'decode'));
		$payload->setApi($api);
		$payload->setPullRequest($pullRequest);
		return $payload;
	}

}

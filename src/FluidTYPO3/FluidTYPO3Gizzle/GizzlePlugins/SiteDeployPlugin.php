<?php
namespace FluidTYPO3\FluidTYPO3Gizzle\GizzlePlugins;

use NamelessCoder\Gizzle\AbstractPlugin;
use NamelessCoder\Gizzle\Payload;
use NamelessCoder\Gizzle\PluginInterface;
use NamelessCoder\GizzleGitPlugins\GizzlePlugins\PullPlugin;

/**
 * Class SiteDeployPlugin
 */
class SiteDeployPlugin extends AbstractPlugin implements PluginInterface {

	const OPTION_MONITORED = 'monitored';
	const OPTION_BRANCH = 'branch';
	const OPTION_DOCUMENTROOT = 'documentRoot';
	const OPTION_DIRECTORY = 'directory';
	const OPTION_POST_TRUNCATE = 'truncate';
	const OPTION_POST_DELETE = 'delete';

	/**
	 * Analyse $payload and return TRUE if this plugin should
	 * be triggered in processing the payload.
	 *
	 * @param Payload $payload
	 * @return boolean
	 */
	public function trigger(Payload $payload) {
		$monitoredRepositoryNames = (array) $this->getSettingValue(self::OPTION_MONITORED, array());
		$defaultBranch = $payload->getRepository()->getMasterBranch();
		$isMonitored = TRUE === in_array($payload->getRepository()->getName(), $monitoredRepositoryNames);
		$matchesHead = 'refs/heads/' . $this->getSettingValue(self::OPTION_BRANCH, $defaultBranch) === $payload->getRef();
		return ($matchesHead && $isMonitored);
	}

	/**
	 * Perform whichever task the Plugin should perform based
	 * on the payload's data.
	 *
	 * @param Payload $payload
	 * @return void
	 */
	public function process(Payload $payload) {
		$this->pullLocalRepository($payload);
	}

	/**
	 * @param Payload $payload
	 * @return void
	 */
	protected function pullLocalRepository(Payload $payload) {
		$this->getPullPlugin(array(
			PullPlugin::OPTION_DIRECTORY => $this->getRepositoryPath($payload),
			PullPlugin::OPTION_BRANCH => $this->getSettingValue(self::OPTION_BRANCH, $payload->getRepository()->getMasterBranch())
		))->process($payload);
	}

	/**
	 * @param array $settings
	 * @return PullPlugin
	 */
	protected function getPullPlugin(array $settings) {
		$pull = new PullPlugin();
		$pull->initialize($settings);
		return $pull;
	}

	/**
	 * @param Payload $payload
	 * @return string
	 */
	protected function getRepositoryPath(Payload $payload) {
		$repositoryRelativePath = sprintf($this->getSettingValue(self::OPTION_DIRECTORY), $payload->getRepository()->getName());
		$repositoryBasePath = $this->getSettingValue(self::OPTION_DOCUMENTROOT, '/');
		$repositoryBasePath = sprintf($repositoryBasePath, substr($payload->getRef(), 11));
		$repositoryLocalPath = $repositoryBasePath . $repositoryRelativePath;
		return $repositoryLocalPath;
	}

}
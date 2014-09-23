<?php
namespace FluidTYPO3\FluidTYPO3Gizzle\GizzlePlugins;

use NamelessCoder\Gizzle\PluginListInterface;

/**
 * Class PluginList
 */
class PluginList implements PluginListInterface {

	/**
	 * Get all class names of plugins delivered from implementer package.
	 *
	 * @return string[]
	 */
	public function getPluginClassNames() {
		return array(
			'FluidTYPO3\\FluidTYPO3Gizzle\\GizzlePlugins\\SiteDeployPlugin'
		);
	}

}

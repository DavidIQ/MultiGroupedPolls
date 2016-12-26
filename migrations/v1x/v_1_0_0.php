<?php
/**
*
* @package Multi-Grouped Polls
* @copyright (c) 2016 DavidIQ.com
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace davidiq\MultiGroupedPolls\migrations\v1x;

class v_1_0_0 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['multigroupedpolls_version']) && version_compare($this->config['multigroupedpolls_version'], '1.0.0', '>=');
	}

	static public function depends_on()
	{
			return array('\phpbb\db\migration\data\v310\dev');
	}

	public function update_data()
	{
		return array(
			// Current version
			array('config.add', array('multigroupedpolls_version', '1.0.0')),
		);
	}
}

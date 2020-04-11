<?php declare( strict_types=1 );

namespace Tribe\Sq1\Tasks;

use Robo\Robo;
use Tribe\Sq1\Models\LocalDocker;
use Tribe\Sq1\Traits\LocalAwareTrait;

class GulpTask extends Sq1Task {

	use LocalAwareTrait;

	public const NVM_SOURCE = '. ~/.nvm/nvm.sh && nvm use';

	/**
	 * Run a Gulp command
	 *
	 * @command gulp
	 *
	 * @param  array  $args The Gulp Command, e.g. dist, watch etc...
	 */
	public function gulp( array $args ) {
		$command = $this->prepareCommand( $args );

		$this->taskExec( self::NVM_SOURCE . " && gulp ${command}" )
			->dir( Robo::config()->get( LocalDocker::CONFIG_PROJECT_ROOT ) )
			->run();
	}
}

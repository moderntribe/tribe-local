<?php declare( strict_types=1 );

namespace Tribe\Sq1\Tasks;

use Tribe\Sq1\Exceptions\Sq1Exception;

/**
 * Local Docker/Project Commands
 *
 * @package Tribe\Sq1\Tasks
 */
class LocalDockerTask extends GlobalDockerTask {

	/**
	 * Starts your local sq1 project, run anywhere in a sq1 project.
	 *
	 * @command start
	 *
	 * @throws Sq1Exception
	 */
	public function start(): self {
		$config = $this->getLocalDockerConfig();

		$cert = realpath( self::SCRIPT_PATH . sprintf( 'global/certs/%s.tribe.crt', $config['name'] ) );

		// Generate a certificate for this project if it doesn't exist
		if ( false === $cert || ! is_file( $cert ) ) {
			$this->globalCert( sprintf( '%s.tribe', $config['name'] ) );
			$this->globalRestart();
		}

		// Start global containers
		$this->globalStart();

		$composer_cache = $config['docker_dir'] . '/composer-cache';

		if ( ! is_dir( $composer_cache ) ) {
			mkdir( $composer_cache );
		}

		$composer_config = $config['docker_dir'] . '/composer/auth.json';

		if ( ! is_file( $composer_config ) ) {
			$this->runComposerConfig();
		}

		$this->say( sprintf( 'Starting docker-compose project: %s', $config['name'] ) );

		// Start the local project
		$this->taskDockerComposeUp()
		     ->files( $config['compose'] )
		     ->projectName( $config['name'] )
		     ->detachedMode()
		     ->forceRecreate()
		     ->run();

		$this->composer( 'install' );

		return $this;
	}

	/**
	 * Stops your local sq1 project, run anywhere in a sq1 project.
	 *
	 * @command stop
	 *
	 * @throws Sq1Exception
	 */
	public function stop(): self {
		$config = $this->getLocalDockerConfig();

		$this->taskDockerComposeDown()
		     ->files( $config['compose'] )
		     ->projectName( $config['name'] )
		     ->run();

		return $this;
	}

	/**
	 * Restarts your local sq1 project.
	 *
	 * @command restart
	 *
	 * @throws Sq1Exception
	 */
	public function restart() {
		$this->stop()->start();
	}

	/**
	 * Writes a user supplied GitHub token to the composer-config.json
	 *
	 * @throws Sq1Exception
	 */
	protected function runComposerConfig() {
		$config = $this->getLocalDockerConfig();

		$token = $this->ask( 'We have detected you have not configured a GitHub oAuth token. Please go to https://github.com/settings/tokens/new?scopes=repo and create one. Paste the token here:' );

		$this->taskWriteToFile( $config['docker_dir'] . '/composer/auth.json' )
		     ->line( sprintf( '{ "github-oauth": { "github.com": "%s" } }', trim( $token ) ) )
		     ->run();
	}

}
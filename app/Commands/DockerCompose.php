<?php declare( strict_types=1 );

namespace App\Commands;

use Throwable;
use App\Contracts\Runner;
use App\Services\Docker\Network;
use App\Recorders\ResultRecorder;
use App\Services\Docker\Local\Config;

/**
 * Docker Compose Facade / Proxy Command
 *
 * @package App\Commands
 */
class DockerCompose extends BaseCommand {

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'docker-compose ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Pass through for docker-compose binary';

    /**
     * DockerCompose constructor.
     */
    public function __construct() {
        parent::__construct();

        // Allow this command to receive any options/arguments
        $this->ignoreValidationErrors();
    }

    /**
     * Execute the console command.
     *
     * @param  \App\Contracts\Runner              $runner    The command runner.
     * @param  \App\Services\Docker\Network       $network   The network manager.
     * @param  \App\Recorders\ResultRecorder      $recorder  The command result recorder.
     * @param  \App\Services\Docker\Local\Config  $config    The docker configuration.
     *
     * @return int
     */
    public function handle( Runner $runner, Network $network, ResultRecorder $recorder, Config $config ): int {
        // Get the entire input passed to this command.
        $command = (string) $this->input;

        $tty = true;

        if ( str_contains( $command, '-T' ) ) {
            $tty = false;
        }

        $envVars = [
            Config::ENV_UID => $config->uid(),
            Config::ENV_GID => $config->gid(),
            'HOSTIP'        => $network->getGateWayIP(),
        ];

        try {
            $projectEnvVars = [
                Config::ENV_HOSTNAME       => $config->getProjectDomain(),
                Config::ENV_HOSTNAME_TESTS => $config->getProjectTestDomain(),
                Config::ENV_PROJECT_NAME   => $config->getProjectName(),
                Config::ENV_PROJECT_ROOT   => $config->getProjectRoot(),
            ];

            $envVars = array_merge( $envVars, $projectEnvVars );
        } catch ( Throwable $exception ) {
            // Do nothing, this isn't being run in a project folder
        }

        $response = $runner->output( $this )
                           ->tty( $tty )
                           ->withEnvironmentVariables( $envVars )
                           ->run( $command );

        $recorder->add( $response->process()->getOutput() );

        if ( ! $response->ok() ) {
            return self::EXIT_ERROR;
        }

        return self::EXIT_SUCCESS;
    }

}

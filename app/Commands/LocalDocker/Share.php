<?php declare(strict_types=1);

namespace App\Commands\LocalDocker;

use App\Contracts\File;
use App\Contracts\Runner;
use App\Services\Docker\Local\Config;
use App\Services\Settings\Groups\AllSettings;
use Illuminate\Filesystem\Filesystem;

/**
 * Share your local environment using ngrok
 *
 * @package App\Commands\LocalDocker
 */
class Share extends BaseLocalDocker {

	public const MU_PLUGIN = '0-so-ngrok.local.php';

	/**
	 * The signature of the command.
	 *
	 * @var string
	 *
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
	 */
	protected $signature = 'share';

	/**
	 * The description of the command.
	 *
	 * @var string
	 *
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
	 */
	protected $description = 'Share your local project on a temporary URL using ngrok';

	protected AllSettings $settings;

	/**
	 * Share constructor.
	 *
	 * @param  \App\Services\Settings\Groups\AllSettings  $settings
	 */
	public function __construct( AllSettings $settings ) {
		parent::__construct();
		$this->settings = $settings;
	}

	/**
	 * Execute the console command.
	 *
	 * @param  \App\Services\Docker\Local\Config  $config
	 * @param  \App\Contracts\Runner              $runner
	 * @param  \Illuminate\Filesystem\Filesystem  $filesystem
	 * @param  \App\Contracts\File                $file
	 *
	 * @return int
	 *
	 * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
	 */
	public function handle( Config $config, Runner $runner, Filesystem $filesystem, File $file ): int {
		$token = $this->settings->secrets->ngrok_token;

		if ( empty( $token ) ) {
			$this->info( 'Ngrok requires a free user account to proxy to https domains. Sign up: https://dashboard.ngrok.com/signup' );
			$authToken = $this->secret( 'Enter your authtoken found in your dashboard: https://dashboard.ngrok.com/auth/your-authtoken (input hidden)' );

			if ( empty( $authToken ) ) {
				$this->error( 'No token entered' );

				return self::EXIT_ERROR;
			}

			$this->settings->secrets->ngrok_token = $authToken;
			$this->settings->save();
		}

		$this->checkGitIgnore( $file, $config->getProjectRoot() );

		$source  = storage_path( sprintf( 'wordpress/mu-plugins/%s', self::MU_PLUGIN ) );
		$content = $filesystem->get( $source );
		$target  = sprintf( '%s/%s', $config->getProjectRoot(), sprintf( 'wp-content/mu-plugins/%s', self::MU_PLUGIN ) );

		$filesystem->replace( $target, $content );

		$runner->with( [
			'domain' => $config->getProjectDomain(),
			'token'  => $this->settings->secrets->ngrok_token,
		] )->tty( true )
			   ->run( 'docker run --rm -it --net global_proxy --link tribe-proxy wernight/ngrok ngrok http --authtoken {{ $token }} -host-header={{ $domain }} tribe-proxy:443' )
			   ->throw();

		$filesystem->delete( $target );

		return self::EXIT_SUCCESS;
	}

	/**
	 * Make sure the mu plugin will be ignored for this project.
	 *
	 * @param  \App\Contracts\File  $file
	 * @param  string               $projectRoot
	 */
	protected function checkGitIgnore( File $file, string $projectRoot ): void {
		$gitIgnore = sprintf( '%s/.gitignore', $projectRoot );

		if ( ! $file->exists( $gitIgnore ) ) {
			return;
		}

		$hasLocalIgnore = $file->contains( $gitIgnore, '*.local.php' );

		if ( $hasLocalIgnore ) {
			return;
		}

		$confirm = $this->confirm( 'Your project is missing ".local.php" in your .gitignore. Would you like to add it now?' );

		if ( ! $confirm ) {
			return;
		}

		$result = $file->append_content( $gitIgnore, sprintf( '%s %s', PHP_EOL . PHP_EOL . '# Added by so cli', PHP_EOL . '*.local.php' ) );

		if ( $result ) {
			$this->info( 'Added ".local.php" to .gitignore. Don\'t forget to commit this change!' );
		} else {
			$this->error( 'Unable to write to .gitignore.' );
		}
	}

}

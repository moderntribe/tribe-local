<?php

namespace Tests\Unit\Services\Docker\Dns\Resolvers;

use Tests\TestCase;
use App\Runners\CommandRunner;
use App\Commands\LocalDocker\Start;
use Illuminate\Filesystem\Filesystem;
use App\Services\Docker\Dns\Resolvers\ResolvConf;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

class ResolvConfTest extends TestCase {

    private $runner;
    private $filesystem;
    private $file;
    private $command;

    protected function setUp(): void {
        parent::setUp();

        $this->runner     = $this->mock( CommandRunner::class );
        $this->filesystem = $this->mock( Filesystem::class );
        $this->file       = storage_path( 'tests/resolv.conf.head' );
        $this->command    = $this->mock( Start::class );
    }

    public function test_it_is_supported() {
        $this->runner->shouldReceive( 'run' )
                     ->with( 'systemctl status resolvconf' )
                     ->once()
                     ->andReturnSelf();

        $this->runner->shouldReceive( 'ok' )
                     ->once()
                     ->andReturnTrue();

        $this->runner->shouldReceive( '__toString' )
                     ->once()
                     ->andReturn( 'Sample SystemD Resolved Output... active (exited)' );

        $resolver = new ResolvConf( $this->runner, $this->filesystem, $this->file );

        $this->assertTrue( $resolver->supported() );
    }

    public function test_it_is_not_supported() {
        $this->runner->shouldReceive( 'run' )
                     ->with( 'systemctl status resolvconf' )
                     ->once()
                     ->andReturnSelf();

        $this->runner->shouldReceive( 'ok' )
                     ->once()
                     ->andReturnTrue();

        $resolver = new ResolvConf( $this->runner, $this->filesystem, $this->file );

        $this->assertFalse( $resolver->supported() );
    }

    public function test_it_is_enabled() {
        $this->filesystem->shouldReceive( 'get' )
                         ->with( $this->file )
                         ->once()
                         ->andReturn( 'nameserver 127.0.0.1' );

        $resolver = new ResolvConf( $this->runner, $this->filesystem, $this->file );

        $this->assertTrue( $resolver->enabled() );
    }

    public function test_it_is_disabled_with_invalid_nameservers() {
        $this->filesystem->shouldReceive( 'get' )
                         ->with( $this->file )
                         ->once()
                         ->andReturn( 'nameserver 10.0.0.1' );

        $resolver = new ResolvConf( $this->runner, $this->filesystem, $this->file );

        $this->assertFalse( $resolver->enabled() );
    }

    public function test_it_is_disabled_with_missing_resolv_conf() {
        $this->filesystem->shouldReceive( 'get' )
                         ->with( $this->file )
                         ->once()
                         ->andThrow( FileNotFoundException::class );

        $resolver = new ResolvConf( $this->runner, $this->filesystem, $this->file );

        $this->assertFalse( $resolver->enabled() );
    }

    public function test_it_can_be_enabled() {
        $this->runner->shouldReceive( 'run' )
                     ->with( 'echo "nameserver 127.0.0.1" | sudo tee ' . $this->file )
                     ->once()
                     ->andReturnSelf();

        $this->runner->shouldReceive( 'throw' )->twice()->andReturnSelf();

        $this->filesystem->shouldReceive( 'dirname' )
                         ->once()
                         ->with( $this->file )
                         ->andReturn( storage_path( 'tests' ) );

        $this->filesystem->shouldReceive( 'exists' )
                         ->once()
                         ->with( storage_path( 'tests' ) )
                         ->andReturnFalse();

        $this->runner->shouldReceive( 'with' )
                     ->once()
                     ->with( [ 'directory' => storage_path( 'tests') ] )
                     ->andReturnSelf();

        $this->runner->shouldReceive( 'run' )
                     ->once()
                     ->with( 'sudo mkdir -p {{ $directory }}' )
                     ->andReturnSelf();

        $this->command->shouldReceive( 'task' )
                      ->with( sprintf( '<comment>??? Adding 127.0.0.1 nameservers to %s</comment>', $this->file ), null )
                      ->once()
                      ->andReturnTrue();

        $resolver = new ResolvConf( $this->runner, $this->filesystem, $this->file );

        $resolver->enable( $this->command );
    }

}

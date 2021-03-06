<?php declare(strict_types=1);

namespace App\Services\CustomCommands;

use Illuminate\Foundation\Console\ClosureCommand as ConsoleClosureCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClosureCommand extends ConsoleClosureCommand {

    /**
     * Overload the existing execute method and pass all inputs to the
     * callback.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface    $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     *
     * @return int
     */
    protected function execute( InputInterface $input, OutputInterface $output ): int {
        $inputs = array_merge( $input->getArguments(), $input->getOptions() );

        // Sometimes we're receiving a duplicated command name at index 0.
        if ( isset( $inputs[0] ) ) {
            unset( $inputs[0] );
        }

        return (int) $this->laravel->call(
            $this->callback->bindTo( $this, $this ), $inputs
        );
    }
}

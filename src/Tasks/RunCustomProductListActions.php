<?php

namespace Sunnysideup\EcommerceCustomProductLists\Tasks;

use Symfony\Component\Console\Input\InputInterface;
use SilverStripe\Console\PolyOutput;
use Symfony\Component\Console\Command\Command;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use Sunnysideup\EcommerceCustomProductLists\Model\CustomProductListAction;

use Symfony\Component\Console\Input\InputOption;

class RunCustomProductListActions extends BuildTask
{
    protected string $title = 'Run Custom Product List actions.';

    protected static string $description = 'Goes throught all the product custom lists actions and, if they are current, runs them.';

    protected static string $commandName = 'run-custom-product-list-actions';

    public function getOptions(): array
    {
        return [
            new InputOption('verbose', 'v', InputOption::VALUE_NONE, 'Show verbose output'),
        ];
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $verbose = $input->getOption('verbose');

        $lists = [
            'Start Actions' => CustomProductListAction::get_current_actions_to_start(),
            'End Actions' => CustomProductListAction::get_current_actions_to_end(),
        ];
        foreach ($lists as $title => $list) {
            if($verbose) {
                $output->writeln($title);
            }
            foreach ($list as $runner) {
                $messages = $runner->doRunNow();
                foreach ($messages as $message) {
                    if($verbose) {
                        $output->writeln('    ' . $message);
                    }
                }
            }
        }

        if($verbose) {
            $output->writeln('--- DONE ---');
        }

        return Command::SUCCESS;
    }
}

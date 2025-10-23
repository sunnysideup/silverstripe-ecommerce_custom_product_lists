<?php

namespace Sunnysideup\EcommerceCustomProductLists\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use Sunnysideup\EcommerceCustomProductLists\Model\CustomProductListAction;

class RunCustomProductListActions extends BuildTask
{
    protected $title = 'Run Custom Product List actions.';

    protected $description = 'Goes throught all the product custom lists actions and, if they are current, runs them.';

    protected $verbose = true;

    private static $segment = 'run-custom-product-list-actions';

    public function setVerbose(bool $b)
    {
        $this->verbose = $b;

        return $this;
    }

    protected array $messages = [];

    public function run($request)
    {
        $lists = [
            'Start Actions' => CustomProductListAction::get_current_actions_to_start(),
            'End Actions' => CustomProductListAction::get_current_actions_to_end(),
        ];
        foreach ($lists as $title => $list) {
            $this->outputMessage($title);
            foreach ($list as $runner) {
                $messages = $runner->doRunNow();
                foreach ($messages as $message) {
                    $this->outputMessage('    ' . $message);
                }
            }
        }
        $this->outputMessage('--- DONE ---');
        if (! $this->verbose) {
            return $this->messages;
        }
    }

    protected function outputMessage(string $message)
    {
        if ($this->verbose) {
            DB::alteration_message($message);
        } else {
            $this->messages[] = $message;
        }
    }
}

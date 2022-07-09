<?php
namespace Sunnysideup\EcommerceCustomProductLists\Tasks;

use SilverStripe\Dev\BuildTask;

use Sunnysideup\EcommerceCustomProductLists\Model\CustomProductList;

class RunCustomProductListActions extends BuildTask
{
    protected $title = 'Run Custom Product List actions.';

    protected $description = 'Goes throught all the product custom lists and, for the ones that have an action, if they are current, runs that action.';

    protected $verbose = true;

    public function setVerbose(bool $b)
    {
        $this->verbose = $b;
        return $this;
    }

    public function run($request)
    {
        $lists = CustomProductList::get_current_lists();
        foreach($lists as $list) {
            $action = $list->Action;
            if($list->isValidAction()) {
                $action::singleton();
                $action->run($list);
            }
        }

    }
}

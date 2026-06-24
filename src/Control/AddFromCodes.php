<?php

namespace Sunnysideup\EcommerceCustomProductLists\Control;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Security\Permission;
use Sunnysideup\Ecommerce\Model\Extensions\EcommerceRole;
use Sunnysideup\EcommerceCustomProductLists\Model\CustomProductList;

/**
 * provides data for the price cards that are printed in store!
 *
 */
class AddFromCodes extends Controller
{
    public function index($url)
    {
        if(! Permission::check(Config::inst()->get(EcommerceRole::class, 'admin_permission_code'))) {
            return $this->httpError(403, 'You do not have permissions to write this Custom Product List');
        }

        $codes = Convert::raw2sql($this->getRequest()->requestVar('codes'));
        if($codes) {
            print_r($codes);
            $list = (CustomProductList::create());
            $list
                ->AddProductCodesToString($codes)
                ->write();
            return $this->redirect($list->CMSEditLink());
        }

        return $this->httpError(500, 'Could not find list');
    }
}

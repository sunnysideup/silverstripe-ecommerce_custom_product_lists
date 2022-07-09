<?php

namespace Sunnysideup\EcommerceCustomProductLists\Api;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\Parsers\URLSegmentFilter;
use Sunnysideup\Ecommerce\Config\EcommerceConfig;
use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldBasicPageRelationConfigNoAddExisting;
use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldConfigForProducts;
use Sunnysideup\Ecommerce\Pages\Product;
use Sunnysideup\Ecommerce\Pages\ProductGroup;

use Sunnysideup\EcommerceCustomProductLists\Model\CustomProductList;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

use SilverStripe\Core\ClassInfo;

/**
 * 1. titles should not be identical
 * 2. when copying accross, we have to make sure
 * 3. onAfterWrite, do we add products from InternalItemCodeList?
 * 4. How can we remove products?
 */
abstract class AbstractCustomProductListAction
{
    use Configurable;
    use Injectable;

    /**
     * returns list of actions as array of ClassName => Title
     */
    public static function get_list_of_actions() : array
    {
        $classes = ClassInfo::subClassesFor(self::class, false);
        $array = [];
        foreach ($classes as $class) {
            $obj = $class::singleton();
            $array[$class] = $obj->getTitle();
        }

        return $array;

    }

    abstract public function getTitle() : string;

    abstract public function run(CustomProductList $list) : bool;

    public function errorCheck()
    {
        if(strlen(static::class) > 255) {
            user_error('Class Name for '.static::class.' is too long, please adjust');
        }
    }

}

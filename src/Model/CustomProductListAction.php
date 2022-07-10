<?php

namespace Sunnysideup\EcommerceCustomProductLists\Model;

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
class CustomProductListAction extends DataObject
{

    private static $table_name = 'CustomProductListAction';

    /**
     * returns list of actions as array of ClassName => Title
     */
    public static function get_list_of_action_types() : array
    {
        $classes = ClassInfo::subClassesFor(self::class, false);
        $array = [];
        foreach ($classes as $class) {
            $obj = $class::singleton();
            $array[$class] = $obj->getTitle();
        }

        return $array;
    }

    public static function get_current_actions_to_start() : DataList
    {
        // Start, Now, Stop
        // ---F---N---U-----
        $now = self::get_now_string_for_database();
        return CustomProductListAction::get()
            ->filter(
                [
                    'StartDateTime:LessThan' => $now,
                    'StopDateTime:GreaterThan' => $now,
                    'Started' => false,
                ],
            );
    }

    public static function get_current_actions_to_end() : DataList
    {
        // Start, Now, Stop
        // ---F------U---N---
        $now = self::get_now_string_for_database();
        return CustomProductListAction::get()
            ->filter(
                [
                    'StopDateTime:LessThan' => $now,
                    'Ended' => false,
                ],
            );
    }

    private static $db = [
        'Title' => 'Varchar',
        'StartDateTime' => 'DateTime',
        'Started' => 'Boolean',
        'StopDateTime' => 'DateTime',
        'Stopped' => 'Boolean',
        'RunNow' => 'Boolean',
    ];

    private static $many_many = [
        'CustomProductLists' => CustomProductList::class,
    ];

    private static $summary_fields = [
        'Title' => 'Title',
        'StartDateTime.Nice' => 'Start',
        'Started' => 'Started',
        'StopDateTime.Nice' => 'Stop',
        'Stopped' => 'Stopped',
        'CustomProductLists.Count' => 'Lists',
    ];

    private static $casting = [
        'ProductCount' => 'Int',
    ];

    private static $indexes = [
        'StartDateTime' => true,
        'StopDateTime' => true,
        'Started' => true,
        'Stopped' => true,
    ];

    private static $default_sort = [
        'ID' => 'DESC',
    ];

    public function RunNow()
    {
        if($this->isRunStartNow()) {
            $this->Started = $this->runToStart();
            $this->write();
        } elseif($this->isRunEndNow()) {
            $this->Ended = $this->runToEnd();
            $this->write();
        }
    }

    public function getShortName() : string
    {
        user_error('Please extend this method: ' .__CLASS__.'::'  . __FUNCTION__);
        return 'Error';
    }

    public function runToStart() : bool
    {
        user_error('Please extend this method: ' .__CLASS__.'::'  . __FUNCTION__);
        return false;
    }
    public function runToEnd() : bool
    {
        user_error('Please extend this method: ' .__CLASS__.'::'  . __FUNCTION__);
        return false;
    }

    /**
     * has an action and dates are current
     * @return bool
     */
    public function isRunStartNow() : bool
    {
        if($this->Started) {
            return false;
        }
        $now = strtotime('now');
        $from = strtotime($this->StartDateTime);
        $until = strtotime($this->StopDateTime);
        return $from > $now && $until < $now;
    }
    /**
     * has an action and dates are current
     * @return bool
     */
    public function isRunEndNow() : bool
    {
        if($this->Ended) {
            return false;
        }
        $now = strtotime('now');
        $until = strtotime($this->StopDateTime);
        return  $until > $now;
    }


    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        foreach(['Started', 'Stopped'] as $readOnlyField)
        $fields->replaceField(
            $readOnlyField,
            $this->dataFieldByName($readOnlyField)->performReadonlyTransformation()
        );
        return $fields;
    }

    public function canEdit($member = null)
    {
        return parent::canEdit($member);
        if($this->Started) {
            return false;
        }
    }

    public function canDelete($member = null)
    {
        return parent::canDelete($member);
        if($this->Started) {
            return false;
        }
    }

    protected static function get_now_string_for_database(string $phrase = 'now') : string
    {
        return Date('Y-m-d H:i:s', $phrase);
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if(! $this->Title) {
            $this->Title = $this->getShortName() . ', from '.$this->StartDateTime . ', until '.$this->StopDateTime;
        }
    }
}

<?php

namespace Sunnysideup\EcommerceCustomProductLists\Model;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\View\Parsers\URLSegmentFilter;
use Sunnysideup\Ecommerce\Config\EcommerceConfig;
use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldBasicPageRelationConfigNoAddExisting;
use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldConfigForProducts;

use Sunnysideup\CmsEditLinkField\Forms\Fields\CMSEditLinkField;

use Sunnysideup\CMSNiceties\Forms\CMSNicetiesLinkButton;
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

    private static $singular_name = 'Custom Product List Action - UNDEFINED';

    private static $plural_name = 'Custom Product List Actions - UNDEFINED';

    /**
     * returns list of actions as array of ClassName => Title
     */
    public static function get_list_of_action_types() : array
    {
        return ClassInfo::subClassesFor(self::class, false);
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
        'RunNow' => 'Boolean',
        'Title' => 'Varchar',
        'StartDateTime' => 'Datetime',
        'Started' => 'Boolean',
        'StopDateTime' => 'Datetime',
        'Stopped' => 'Boolean',
    ];

    private static $many_many = [
        'CustomProductLists' => CustomProductList::class,
    ];

    private static $summary_fields = [
        'ShortTitle' => 'Title',
        'StartDateTime.Nice' => 'Start',
        'StopDateTime.Nice' => 'Stop',
        'CustomProductListsNames' => 'Product lists',
        'ProductCount' => 'Products',
        'Activated.Nice' => 'Active',
    ];

    private static $casting = [
        'ShortTitle' => 'Varchar',
        'Activated' => 'Boolean',
        'ProductCount' => 'Int',
        'CustomProductListsNames' => 'Varchar',
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

    private static $field_labels = [
        'RunNow' => 'Apply now (if this should be active, dont wait for automatic application; just apply it now)',
    ];

    public function getProductCount() : int
    {
        $count = 0;
        foreach($this->CustomProductLists() as $list) {
            $count += $list->Products()->count();
        }

        return $count;
    }

    public function getActivated() : int
    {
        return $this->Started && ! $this->Stopped;
    }

    public function doRunNow() : string
    {
        $action = 'No change';
        if($this->isRunStartNow()) {
            $this->Started = $this->runToStart();
            $this->write();
            $action = 'Started';
        } elseif($this->isRunEndNow()) {
            $this->Ended = $this->runToEnd();
            $this->write();
            $action = 'Stopped';
        }
        return $this->Title .' ... '. $action;
    }

    public function runToStart() : bool
    {
        user_error('Please extend this method: ' .__CLASS__.'::'  . __FUNCTION__);
        return true;
    }

    public function runToEnd() : bool
    {
        user_error('Please extend this method: ' .__CLASS__.'::'  . __FUNCTION__);
        return true;
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
        return $from < $now && $until > $now;
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
        return  $until < $now;
    }


    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        foreach(['Title', 'Started', 'Stopped'] as $readOnlyField) {
            $fields->replaceField(
                $readOnlyField,
                $fields->dataFieldByName($readOnlyField)->performReadonlyTransformation()
            );
        }
        $customListsGridField = $fields->dataFieldByName('CustomProductLists');
        if($customListsGridField) {
            $customListsGridField->getConfig()
                ->getComponentByType(GridFieldAddExistingAutocompleter::class)
                ->setSearchFields(['Title'])
            ;
        }
        $fields->addFieldsToTab(
            'Root.CustomProductLists',
            [
                LiteralField::create(
                    'LinkToCustomProducLists',
                    '<p><a href="/admin/product-config/Sunnysideup-EcommerceCustomProductLists-Model-CustomProductList">View all lists</a></p>'
                )
            ]
        );

        if($this->isValid()) {
            $fields->addFieldsToTab(
                'Root.Main',
                [
                    CheckboxField::create('Activated', 'Active', $this->getActivated())->performReadonlyTransformation()
                        ->setDescription('We are in date range and action has been applied.'),
                    NumericField::create('ProductCount', 'Products affected', $this->getProductCount())->performReadonlyTransformation(),
                ],
                'StartDateTime'
            );
            $fields->addFieldsToTab(
                'Root.Debug',
                [
                    CheckboxField::create('IsRunNow', 'Should be started', $this->isRunStartNow())->performReadonlyTransformation(),
                    CheckboxField::create('isRunEndNow', 'Should be stopped', $this->isRunEndNow())->performReadonlyTransformation(),
                    $fields->dataFieldByName('Started'),
                    $fields->dataFieldByName('Stopped'),
                    $fields->dataFieldByName('RunNow'),
                ]
            );
        } else {
            $fields->removeByName(
                ['Started', 'Stopped','RunNow']
            );
        }

        $fields->dataFieldByName('StopDateTime')->setMinDatetime(date('Y-m-d'). '00:00:00');
        return $fields;
    }

    public function validate()
    {
        $result = parent::validate();
        if(! $this->isValid()) {
            $result->addError('Please check that dates are valid');
        }
        return $result;
    }

    public function getShortTitle()
    {
        return 'Error';
    }


    public function getCustomProductListsNames()
    {
        return implode(', ', $this->CustomProductLists()->column('Title'));
    }



    public function canEdit($member = null)
    {
        if($this->Started) {
            return false;
        }
        return parent::canEdit($member);
    }

    public function canDelete($member = null)
    {
        if($this->Started) {
            return false;
        }
        return parent::canDelete($member);
    }

    protected static function get_now_string_for_database(string $phrase = 'now') : string
    {
        return Date('Y-m-d H:i:s', $phrase);
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->Title = $this->calculateTitle();
        if($this->isValid()) {
            $this->RunNow = true;
        }
    }

    protected function calculateTitle() : string
    {
        return $this->i18n_singular_name() .
            ', from '.date('d-m-Y', strtotime($this->StartDateTime)) .
            ', until '.date('d-m-Y', strtotime($this->StopDateTime)).
            ', on '.$this->getProductCount().' products';
    }

    protected function isValid() {
        return
            $this->StartDateTime &&
            $this->StopDateTime &&
            strtotime($this->StopDateTime) > strtotime('now') &&
            strtotime($this->StartDateTime) < strtotime($this->StopDateTime);
    }

    protected $loopBuster = false;

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        if($this->RunNow && $this->loopBuster === false) {
            $this->loopBuster = true;
            $this->doRunNow();
            $this->RunNow = false;
            $this->write();
        }
    }

}

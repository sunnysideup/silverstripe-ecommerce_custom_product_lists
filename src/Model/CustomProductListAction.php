<?php

namespace Sunnysideup\EcommerceCustomProductLists\Model;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;

/**
 * 1. titles should not be identical
 * 2. when copying accross, we have to make sure
 * 3. onAfterWrite, do we add products from InternalItemCodeList?
 * 4. How can we remove products?
 *
 * @property bool $StartNow
 * @property bool $RunNow
 * @property string $Title
 * @property string $StartDateTime
 * @property bool $Started
 * @property string $StopDateTime
 * @property bool $Stopped
 * @method \SilverStripe\ORM\ManyManyList|\Sunnysideup\EcommerceCustomProductLists\Model\CustomProductList[] CustomProductLists()
 */
class CustomProductListAction extends DataObject
{
    protected $loopBuster = false;

    private static $table_name = 'CustomProductListAction';

    private static $singular_name = 'Custom Product List Action - UNDEFINED';

    private static $plural_name = 'Custom Product List Actions - UNDEFINED';

    private static $db = [
        'StartNow' => 'Boolean',
        'RunNow' => 'Boolean(1)',
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
        'Activated.NiceAndColourfull' => 'Active',
    ];

    private static $searchable_fields = [
        'ShortTitle' => 'PartialMatchFilter',
        'CustomProductListsNames' => 'PartialMatchFilter',
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
        'RunNow' => 'Apply start and stop on save rather than waiting for automatic application.',
        'StartNow' => 'Ignore start and end date, just apply now, on saving (if it has already started, we will end this promotion)- careful!',
    ];

    /**
     * returns list of actions as array of ClassName => Title.
     */
    public static function get_list_of_action_types(): array
    {
        return ClassInfo::subClassesFor(self::class, false);
    }

    public static function get_current_actions_to_start(): DataList
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
            )
        ;
    }

    public static function get_current_actions_to_end(): DataList
    {
        // Start, Now, Stop
        // ---F------U---N---
        $now = self::get_now_string_for_database();

        return CustomProductListAction::get()
            ->filter(
                [
                    'StopDateTime:LessThan' => $now,
                    'Stopped' => false,
                ],
            )
        ;
    }

    public function getProductCount(): int
    {
        $count = 0;
        /** @var DataList $list */
        foreach ($this->CustomProductLists() as $list) {
            $count += $list->Products()->count();
        }

        return $count;
    }

    public function getActivated()
    {
        $val = (bool) $this->Started === true && (bool) $this->Stopped === false;
        $val = false;
        return DBField::create_field('Boolean', $val);
    }

    public function getActivatedNotNice()
    {
        return (bool) $this->Started === true && (bool) $this->Stopped === false;
    }

    public function doRunNow(): string
    {
        $action = 'No change';
        if ($this->isRunStartNow()) {
            $this->Started = $this->runToStart();
            $this->write();
            $action = 'Started';
        } elseif ($this->isRunEndNow()) {
            $this->Stopped = $this->runToEnd();
            $this->write();
            $action = 'Stopped';
        }

        return $this->Title . ' ... ' . $action;
    }

    public function runToStart(): bool
    {
        user_error('Please extend this method: ' . __CLASS__ . '::' . __FUNCTION__);

        return true;
    }

    public function runToEnd(): bool
    {
        user_error('Please extend this method: ' . __CLASS__ . '::' . __FUNCTION__);

        return true;
    }

    /**
     * has an action and dates are current.
     */
    public function isRunStartNow(): bool
    {
        if ($this->Started) {
            return false;
        }
        if ($this->StartNow && ! $this->Started) {
            return true;
        }
        $now = strtotime('now');
        $from = strtotime((string) $this->StartDateTime);
        $until = strtotime((string) $this->StopDateTime);

        return $from < $now && $until > $now;
    }

    /**
     * has an action and dates are current.
     */
    public function isRunEndNow(): bool
    {
        if ($this->Stopped) {
            return false;
        }
        if ($this->Started && $this->StartNow && ! $this->Stopped) {
            return true;
        }
        $now = strtotime('now');
        $until = strtotime((string) $this->StopDateTime);

        return  $until < $now;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        foreach (['Title', 'Started', 'Stopped'] as $readOnlyField) {
            $fields->replaceField(
                $readOnlyField,
                $fields->dataFieldByName($readOnlyField)->performReadonlyTransformation()
            );
        }
        $customListsGridField = $fields->dataFieldByName('CustomProductLists');
        if ($customListsGridField) {
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
                ),
            ]
        );

        if ($this->isReadyToBeActioned()) {
            $fields->addFieldsToTab(
                'Root.Main',
                [
                    CheckboxField::create('ActivatedNotNice', 'Active', $this->getActivatedNotNice())->performReadonlyTransformation()
                        ->setDescription('We are in date range and action has been applied.'),
                    NumericField::create('ProductCount', 'Products affected', $this->getProductCount())->performReadonlyTransformation(),
                ],
                'StartDateTime'
            );

            $exampleProduct = $this->getAllProductsAsArrayList()->first();
            if ($exampleProduct) {
                $fields->addFieldsToTab(
                    'Root.Main',
                    [
                        ReadonlyField::create(
                            'ExampleProductLink',
                            'Example Product',
                            DBField::create_field('HTMLText', '<a href="' . $exampleProduct->Link() . '" target="_blank">' . $exampleProduct->Title . '</a>')
                        ),
                    ]
                );
            }
            $fields->addFieldsToTab(
                'Root.Debug',
                [
                    CheckboxField::create('IsRunNow', 'Should be started', $this->isRunStartNow())->performReadonlyTransformation(),
                    CheckboxField::create('isRunEndNow', 'Should be stopped', $this->isRunEndNow())->performReadonlyTransformation(),
                    $fields->dataFieldByName('Started'),
                    $fields->dataFieldByName('Stopped'),
                    $fields->dataFieldByName('RunNow'),
                    $fields->dataFieldByName('StartNow'),
                ]
            );
        } else {
            $fields->removeByName(
                ['Started', 'Stopped', 'RunNow', 'StartNow']
            );
        }
        if ($this->Stopped) {
            $fields->removeByName(
                ['RunNow', 'StartNow']
            );
        }
        $nextDay = date('Y-m-d h:i:s', strtotime('+2 hours'));
        $fields->dataFieldByName('StartDateTime')->setMinDatetime($nextDay);
        $fields->dataFieldByName('StopDateTime')->setMinDatetime($nextDay);

        if ($this->Started) {
            $fields->addFieldsToTab(
                'Root.Main',
                [
                    LiteralField::create(
                        'StartedWarning',
                        '<p class="message warning">
                            WARNING: this action has started.  Please do not delete or edit unless strictly necessary.
                            To end early, please change stop date.
                        </p>'
                    ),
                ],
                'Title'
            );
        }
        $sn = $fields->dataFieldByName('StartNow');
        if ($sn) {
            $sn
                ->setDescription($fields->dataFieldByName('StartNow')->Title())
                ->setTitle($this->Started ? 'Stop Now' : 'Start Now')
            ;
        }

        return $fields;
    }

    public function validate()
    {
        $result = parent::validate();
        if (! $this->isReadyToBeActioned()) {
            $result->addError('Please check all required data entry has been completed correctly.');
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

    public function getAllProducts(): array
    {
        $list = [];
        /** @var DataList $customList */
        foreach ($this->CustomProductLists() as $customList) {
            foreach ($customList->Products() as $product) {
                $list[$product->ID] = $product;
            }
        }

        return $list;
    }

    public function getAllProductsAsArrayList(): ArrayList
    {
        $al = ArrayList::create();
        foreach ($this->getAllProducts() as $p) {
            $al->push($p);
        }

        return $al;
    }

    protected function onAfterWrite()
    {
        parent::onAfterWrite();
        if (($this->RunNow || $this->StartNow) && false === $this->loopBuster) {
            $this->loopBuster = true;
            $this->doRunNow();
            $this->RunNow = false;
            $this->StartNow = false;
            $this->write();
        }
    }

    public function canEdit($member = null)
    {
        return $this->Stopped ? false : parent::canEdit($member);
    }

    protected static function get_now_string_for_database(string $phrase = 'now'): string
    {
        return date('Y-m-d H:i:s', strtotime((string) $phrase));
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->Title = $this->calculateTitle();
    }

    protected function calculateTitle(): string
    {
        return $this->i18n_singular_name() .
            ', from ' . date('d-m-Y', strtotime((string) $this->StartDateTime)) .
            ', until ' . date('d-m-Y', strtotime((string) $this->StopDateTime)) .
            ', on ' . $this->getProductCount() . ' products';
    }

    protected function isReadyToBeActioned(): bool
    {
        return
            $this->StartDateTime &&
            $this->StopDateTime &&
            strtotime((string) $this->StartDateTime) < strtotime((string) $this->StopDateTime);
    }

    protected function addNextMonthsTimeSpan()
    {
        $this->StartDateTime = DBDatetime::now()->modify('00:01,first day of next month')->Rfc2822();
        $this->StopDateTime = DBDatetime::now()->modify('23:59,last day of next month')->Rfc2822();

        return parent::populateDefaults();
    }
}

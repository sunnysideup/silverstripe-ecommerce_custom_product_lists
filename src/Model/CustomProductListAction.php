<?php

namespace Sunnysideup\EcommerceCustomProductLists\Model;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBBoolean;
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
        'Type' => 'Type',
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
        'Type' => 'Varchar',
        'ShortTitle' => 'Varchar',
        'Activated' => 'Boolean',
        'IsInFuture' => 'Boolean',
        'IsInPast' => 'Boolean',
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
        'RunNow' => 'Apply start and stop on save (respecting start and stop dates) rather than waiting for automatic application.',
        'StartNow' => 'Ignore start and end date, just apply now, on saving (note that if it has already started, ticking this box and saving will end this promotion - please use with caution!)',
    ];

    protected array $runMessages = [];

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

    public function getActivated(): DBBoolean
    {
        $val = (bool) $this->Started === true && (bool) $this->Stopped === false;
        return DBBoolean::create_field('Boolean', $val);
    }

    public function getIsInFuture(): bool
    {
        $now = strtotime('now');
        $from = strtotime((string) $this->StartDateTime);

        return $from > $now;
    }

    public function getIsInPast(): bool
    {
        $now = strtotime('now');
        $until = strtotime((string) $this->StopDateTime);

        return $until < $now;
    }

    public function getIsInNow(): bool
    {
        $now = strtotime('now');
        $from = strtotime((string) $this->StartDateTime);
        $until = strtotime((string) $this->StopDateTime);

        return $from < $now && $until > $now;
    }


    public function getActivatedNotNice()
    {
        return (bool) $this->Started === true && (bool) $this->Stopped === false;
    }

    public function doRunNow(): array
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
        $this->runMessages[] = $this->Title . ' ... ' . $action . ' COMPLETED';
        return $this->runMessages;
    }

    public function runToStart(): bool
    {
        user_error('You must implement runToStart in ' . __CLASS__, E_USER_ERROR);
        return false;
    }

    public function runToEnd(): bool
    {
        user_error('You must implement runToEnd in ' . __CLASS__, E_USER_ERROR);
        return false;
    }
    public function repeatablyRun(): bool
    {
        user_error('You must implement repeatablyRun in ' . __CLASS__, E_USER_ERROR);
        return false;
    }

    /**
     * has an action and dates are current.
     */
    public function isRunStartNow(): bool
    {
        $repeatableRun = $this->repeatablyRun();
        //cant repeat and already started: NO
        if (!$repeatableRun && $this->Started) {
            return false;
        }
        // can repeat and already started: YES
        if ($this->StartNow) {
            return true;
        }
        return $this->getIsInNow();
    }


    /**
     * has an action and dates are current.
     */
    public function isRunEndNow(): bool
    {
        if ($this->Stopped) {
            return false;
        }
        if ($this->getIsInPast()) {
            return true;
        }
        return false;
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
            $customListsGridField->setDescription('Select one or more custom product lists to which this action will apply.');
        }
        $customListsGridField->setName('CustomProductListsSelector');
        $fields->addFieldsToTab(
            'Root.CustomProductLists',
            [
                CheckboxSetField::create(
                    'CustomProductLists',
                    'Quick Selection of Custom Product Lists to which this action applies',
                    CustomProductList::get()
                        ->sort(['LastEdited' => 'DESC'])
                        ->map('ID', 'Title')
                ),
                LiteralField::create(
                    'LinkToCustomProductLists',
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

            $exampleProducts = $this->getAllProductsAsArrayList()->limit(10)->shuffle();
            if ($exampleProducts->exists()) {
                $count = $this->getAllProductsAsArrayList()->count();
                $linkArray = [];
                foreach ($exampleProducts as $exampleProduct) {
                    $linkArray[] = '- <a href="' . $exampleProduct->Link() . '" target="_blank">' . $exampleProduct->Title . '</a>';
                }
                $fields->addFieldsToTab(
                    'Root.Main',
                    [
                        ReadonlyField::create(
                            'ExampleProductLink',
                            'Example Products',
                            DBField::create_field('HTMLText', implode('<br /> ', $linkArray))
                        )
                            ->setDescription('Showing up to 10 of ' . $count . ' products affected.'),
                    ]
                );
            }
            $fields->addFieldsToTab(
                'Root.Status',
                [
                    HeaderField::create('RunNowHeader', 'Manual Actions'),
                    $fields->dataFieldByName('RunNow'),
                    $fields->dataFieldByName('StartNow'),
                    HeaderField::create('StatusHeader', 'Start and Stop'),
                    CheckboxField::create('isRunStartNowNice', 'Should be started', $this->isRunStartNow())->performReadonlyTransformation()
                        ->setDescription('Indicates whether the action will be applied (again).'),
                    $fields->dataFieldByName('Started'),
                    CheckboxField::create('isRunEndNowNice', 'Should be stopped', $this->isRunEndNow())->performReadonlyTransformation(),
                    $fields->dataFieldByName('Stopped'),
                    HeaderField::create('TimingHeader', 'Timing'),
                    CheckboxField::create('IsInNowNice', 'Is current', $this->getIsInNow())->performReadonlyTransformation(),
                    CheckboxField::create('IsInPastNice', 'Is in the past', $this->getIsInPast())->performReadonlyTransformation(),
                    CheckboxField::create('IsInFutureNice', 'Is in the future', $this->getIsInFuture())->performReadonlyTransformation(),
                ]
            );
        } else {
            $fields->removeByName(
                ['Started', 'Stopped', 'RunNow', 'StartNow']
            );
        }
        if ($this->Stopped) {
            $fields->removeByName(
                ['RunNow', 'StartNow', 'RunNowHeader']
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

    public function getType()
    {
        return $this->i18n_singular_name();
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
        /**
         * @var DataList $customLists
         */
        $customLists = $this->CustomProductLists();
        /**
         * @var DataObject $customList
         */
        foreach ($customLists as $customList) {
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

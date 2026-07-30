<?php

namespace Sunnysideup\EcommerceCustomProductLists\Model;

use SilverStripe\CMS\Forms\SiteTreeURLSegmentField;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\Tab;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\Parsers\URLSegmentFilter;
use Sunnysideup\CmsEditLinkField\Api\CMSEditLinkAPI;
use Sunnysideup\Ecommerce\Config\EcommerceConfig;
use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldBasicPageRelationConfig;
use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldBasicPageRelationConfigNoAddExisting;
use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldConfigForProductGroups;
use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldConfigForProducts;
use Sunnysideup\Ecommerce\Pages\Product;
use Sunnysideup\Ecommerce\Pages\ProductGroup;
use Sunnysideup\EcommerceCustomProductLists\Pages\CustomListPage;

/**
 * 1. titles should not be identical
 * 2. when copying accross, we have to make sure
 * 3. onAfterWrite, do we add products from InternalItemCodeList?
 * 4. How can we remove products?
 *
 * @property bool $HideFromWebsite
 * @property bool $UseForGoogleFeed
 * @property string $Title
 * @property bool $Locked
 * @property string $InternalItemCodeList
 * @property string $InternalItemCodeListCustom
 * @property bool $KeepAddingFromCategories
 * @property bool $KeepAddingFromCustomProductListsToAdd
 * @method \SilverStripe\ORM\ManyManyList|\Sunnysideup\Ecommerce\Pages\Product[] ProductsToAdd()
 * @method \SilverStripe\ORM\ManyManyList|\Sunnysideup\Ecommerce\Pages\Product[] ProductsToDelete()
 * @method \SilverStripe\ORM\ManyManyList|\Sunnysideup\Ecommerce\Pages\ProductGroup[] CategoriesToAdd()
 * @method \SilverStripe\ORM\ManyManyList|\Sunnysideup\EcommerceCustomProductLists\Model\CustomProductList[] CustomProductListsToAdd()
 * @method \SilverStripe\ORM\ManyManyList|\Sunnysideup\EcommerceCustomProductLists\Model\CustomProductListAction[] CustomProductListActions()
 * @method \SilverStripe\ORM\ManyManyList|\Sunnysideup\EcommerceCustomProductLists\Model\CustomProductListAction[] CustomProductListAddedTo()
 */
class CustomProductList extends DataObject
{
    /**
     * how are product codes separated?
     *
     * @var string
     */
    private static $separator = ',';
    private static $separator_name = 'comma';

    /**
     * if a product separator is used in the product code then
     * it will be replaced by this variable.
     *
     * @var string
     */
    private static $separator_alternative = ';';
    private static $definition_of_recently_edited = '-3 months';
    private static $scaffold_cms_fields_settings = [
        'includeRelations' => false,
    ];

    private static $core_rels = [
        'ProductsToAdd',
        'ProductsToDelete',
        'CategoriesToAdd',
        'MustAlsoBeInCategories',
        'CustomProductListsToAdd',
    ];

    private static $table_name = 'CustomProductList';

    private static $db = [
        'Title' => 'Varchar(255)',
        'URLSegment' => 'Varchar(255)',
        'Locked' => 'Boolean',
        'InternalItemCodeList' => 'Text',
        'InternalItemCodeListCustom' => 'Text',
        'KeepAddingFromCategories' => 'Boolean',
        'KeepAddingFromCustomProductListsToAdd' => 'Boolean',
    ];

    private static $indexes = [
        'ProductListIndex' => [
            'type' => 'unique',
            'columns' => ['Title'],
        ],
        'URLSegmentIndex' => [
            'type' => 'unique',
            'columns' => ['URLSegment'],
        ],
    ];

    private static $many_many = [
        'ProductsToAdd' => Product::class,
        'ProductsToDelete' => Product::class,
        'CategoriesToAdd' => ProductGroup::class,
        'MustAlsoBeInCategories' => ProductGroup::class,
        'CustomProductListsToAdd' => CustomProductList::class,
    ];

    private static $belongs_many_many = [
        'CustomProductListActions' => CustomProductListAction::class,
        'CustomProductListAddedTo' => CustomProductListAction::class,
    ];

    private static $searchable_fields = [
        'Title' => 'PartialMatchFilter',
        'Locked' => 'ExactMatchFilter',
        'InternalItemCodeList' => 'PartialMatchFilter',
    ];

    private static $summary_fields = [
        'Created.Nice' => 'Created',
        'LastEdited.Ago' => 'Last Edited',
        'Title' => 'FullName',
        'ProductCount' => 'Products',
        'Locked.NiceAndColourfull' => 'Locked',
        'UsedAnywhere.NiceAndColourfull' => 'Used anywhere?',
        'RecentlyEdited.NiceAndColourfull' => 'Recently Edited',

    ];

    private static $field_labels = [
        'InternalItemCodeList' => 'Included Codes',
        'InternalItemCodeListCustom' => 'Manually added codes',
        'ProductsToDelete' => 'Remove Products from List',
    ];

    private static $default_sort = [
        'LastEdited' => 'DESC',
    ];

    private static $casting = [
        'FullName' => 'Varchar',
        'ProductCount' => 'Int',
        'UsedAnywhere' => 'Boolean',
        'RecentlyEdited' => 'Boolean',
    ];

    public function getFullName()
    {
        return $this->Title . ' (' . $this->getProductCount() . ' products)';
    }

    public function getProductCount(): int
    {
        return $this->getProductsFromInternalItemIDs()->count();
    }

    /**
     * Deleting Permissions.
     *
     * @param null|mixed $member
     *
     * @return bool
     */
    public function canDelete($member = null)
    {
        if($this->Locked) {
            return false;
        }
        if($this->UsedAnywhere()->raw()) {
            return false;
        }
        if($this->RecentlyEdited()->raw()) {
            return false;
        }
        return parent::canDelete($member);
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldToTab(
            'Root',
            new Tab('AddProducts', _t('CustomProductList.PRODUCTS_TO_ADD', 'Add Products')),
            'Actions'
        );
        if ($this->exists()) {
            $fieldsToAdd = [];
            foreach ($this->config()->get('core_rels') as $rel) {
                $fields->removeByName($rel);
                $fieldsToAdd[$rel] = GridField::create(
                    $rel,
                    $this->fieldLabel($rel),
                    $this->$rel(),
                    GridFieldBasicPageRelationConfig::create()
                );
            }
            $ac = $fieldsToAdd['CustomProductListsToAdd']?->getConfig()?->getComponentByType(GridFieldAddExistingAutocompleter::class);
            if ($ac) {
                $ac->setSearchFields(['Title']);
                $ac->setResultsFormat('Title');
            }

        }
        $html =
            '<div id="Form_ItemEditForm_InternalItemCodeList_Holder" class="field readonly textarea">
           <label class="left" for="Form_ItemEditForm_InternalItemCodeList">Included Codes</label>
              <div class="middleColumn">
                <span id="Form_ItemEditForm_InternalItemCodeList" class="readonly textarea" style="word-break:break-all;">
                    ' . $this->InternalItemCodeList . '
                </span>
              </div>
              <label class="right" for="Form_ItemEditForm_InternalItemCodeList">
                  This is the <strong>master list</strong>
              </label>
        </div>
        ';
        $fields->replaceField(
            'InternalItemCodeList',
            LiteralField::create('InternalItemCodeList', $html)
        );
        $currentProductsField = GridField::create(
            'ProductsToBeShown',
            'Products to be shown',
            $this->Products(),
            GridFieldBasicPageRelationConfigNoAddExisting::create()
        );
        $currentProductsField->setDescription('Calculated products, based on the list of included product codes (see Main Tab).');

        $fields->addFieldToTab(
            'Root.Main',
            $currentProductsField
        );
        $fields->removeFieldFromTab('Root', 'Products');
        if ($this->Locked || ! $this->exists()) {
            $fields->removeFieldFromTab('Root.Main', 'InternalItemCodeListCustom');
        } else {
            //products to add
            $productsToAddField = $fieldsToAdd['ProductsToAdd'] ?? null;
            if ($productsToAddField) {
                $productsToAddField->setDescription('Use this field to add products, they will be remove again from this list after they have been added to main list.');
                $productsToAddField->setConfig(GridFieldConfigForProducts::create());
            }
            $fields->addFieldsToTab(
                'Root.Remove',
                [
                    CheckboxSetField::create(
                        'ProductsToDelete',
                        'Products to Remove',
                        $this->Products()->sort('Title')->map('ID', 'FullName')->toArray()
                    )->setDescription('Use this field to remove products, they will be removed again from this list after they have been removed from main list.')
                ]
            );
            $manualCodesField = $fields->dataFieldByName('InternalItemCodeListCustom');
            if ($manualCodesField) {
                $manualCodesField->setDescription(
                    'Separate codes by ' . $this->Config()->get('separator') . ' (' . $this->Config()->get('separator_name') . ').' .
                        '
                        Only use this option if products are not currently available on site.
                        If they are already part of the site then you can add them using the tools provided.
                    '
                );
                $fields->addFieldsToTab(
                    'Root.AddProducts',
                    [
                        HeaderField::create('ProductsToAddManually', 'Products to add manually', 1),
                        $manualCodesField,
                    ]
                );
            }
            $fields->addFieldsToTab(
                'Root.AddProducts',
                [
                    HeaderField::create('ProductsToAddFromCategories', 'Products to add from Categories', 1),
                    $fieldsToAdd['CategoriesToAdd']
                        ->setConfig(GridFieldConfigForProductGroups::create())
                        ->setDescription('All products in selected categories will be added. Make sure to select a category.'),
                    $fieldsToAdd['MustAlsoBeInCategories']
                        ->setConfig(GridFieldConfigForProductGroups::create())
                        ->setDescription('For the categories selected above, the products must also be in the categories listed here.'),
                    CheckboxField::create('KeepAddingFromCategories', 'Keep adding from catories?')
                        ->setDescription(
                            '
                            Everytime you save this list, we keep adding products from the categories you have selected below.
                            If you do not tick this box then we add the products from the selected categories only once - if ticked, everytime you save, we will try to keep adding more products from your selection.'
                        ),
                    HeaderField::create('ProductsToAddFromOtherCustomLists', 'Products to add from Other Custom Lists', 1),
                    $fieldsToAdd['CustomProductListsToAdd']
                        ->setConfig(GridFieldBasicPageRelationConfig::create())
                        ->setDescription('All products in selected custom product lists will be added. Make sure to select a list.'),
                    CheckboxField::create('KeepAddingFromCustomProductListsToAdd', 'Keep adding from other custom product lists?')
                        ->setDescription(
                            '
                            Everytime you save this list, we keep adding products from the other custom lists you have selected below.
                            If you do not tick this box then we add the products from the selected custom lists only once - if ticked, everytime you save, we will try to keep adding more products from your selection.'
                        ),

                ],
            );
        }

        if ($this->exists()) {
            foreach (CustomProductListAction::get_list_of_action_types() as $className) {
                $obj = $className::singleton();
                $title = $obj->i18n_singular_name();
                $fields->addFieldsToTab(
                    'Root.Actions',
                    [
                        HeaderField::create(
                            $title . ' Actions',
                            $title . ' Actions',
                            1
                        ),
                        GridField::create(
                            'ListFor' . $title,
                            $title,
                            $className::get()->filter(['CustomProductLists.ID' => $this->ID]),
                            GridFieldConfig_RecordViewer::create()
                        ),
                    ]
                );
            }
        }
        if ($this->exists()) {
            $fields->removeByName(
                [
                    'Root.CustomProductListActions',
                    'CustomProductListActions',
                ]
            );
            $fields->removeFieldsFromTab(
                'Root',
                [
                    'CustomProductListAddedTo',
                ]
            );
        }


        $fields->addFieldsToTab(
            'Root.Usage',
            [
                ReadonlyField::create('UsedAnywhereNice', 'Used Anywhere', $this->UsedAnywhere()->Nice()),
                ReadonlyField::create('ListOfUsagePointsNice', 'Usage', implode(', ', array_keys($this->ListOfUsagePoints()))),
                ReadonlyField::create('RecentlyEditedNice', 'Recently Edited', $this->RecentlyEdited()->Nice()),
                GridField::create(
                    'CustomProductListAddedTo',
                    'This custom lists adds products to the following other custom lists',
                    $this->CustomProductListAddedTo(),
                    GridFieldConfig_RecordViewer::create()
                )

            ]
        );
        $baseLink = $this->MyDisplayPage()?->Link('show') ?? '';
        if($baseLink) {
            $urlsegment = SiteTreeURLSegmentField::create(
                "URLSegment",
                $this->fieldLabel('URLSegment')
            )
                ->setURLPrefix($baseLink)
                ->setURLSuffix('/' . $this->ID)
                ->setDefaultURL($this->generateURLSegment(_t(
                    'SilverStripe\\CMS\\Controllers\\CMSMain.NEWPAGE',
                    'New {pagetype}',
                    ['pagetype' => $this->i18n_singular_name()]
                )));
            $helpText = '';
            if (!URLSegmentFilter::create()->getAllowMultibyte()) {
                $helpText .= _t('SilverStripe\\CMS\\Forms\\SiteTreeURLSegmentField.HelpChars', ' Special characters are automatically converted or removed.');
            }
            $urlsegment->setHelpText($helpText);
            $fields->replaceField('URLSegment', $urlsegment);
        }
        return $fields;
    }

    public function getCMSValidator()
    {
        return RequiredFields::create('Title');
    }

    public function populateDefaults()
    {
        $this->Title = $this->defaultTitle();

        return parent::populateDefaults();
    }

    public function getProductsAsInternalItemsArray(): array
    {
        $sep = Config::inst()->get(CustomProductList::class, 'separator');
        $list = explode($sep, (string) $this->InternalItemCodeList);
        foreach ($list as $key => $code) {
            $list[$key] = trim((string) $code);
        }
        if (!is_array($list)) {
            $list = [];
        }

        return $list;
    }

    /**
     * This is useful as a way to separate.
     */
    public function Products(): DataList
    {
        return $this->getProductsFromInternalItemIDs();
    }

    public function getProductsFromInternalItemIDs(): DataList
    {
        $className = EcommerceConfig::get(ProductGroup::class, 'base_buyable_class');

        return $className::get()->filter(['InternalItemID' => $this->getProductsAsInternalItemsArray()]);
    }

    protected $writeAgain = false;

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if ($this->Locked) {
            //do nothing
        } elseif ($this->exists()) {
            $this->AddProductsToString($this->ProductsToAdd(), $write = false);
            $this->AddProductCodesToString((string) $this->InternalItemCodeListCustom, $write = false);
            $this->RemoveProductsFromString($this->ProductsToDelete(), $write = false);
            $this->InternalItemCodeListCustom = '';
        } else {
            $this->writeAgain = true;
        }
        // If there is no Title set, generate one from Title
        $this->Title = $this->generateTitle();
        // Ensure that this object has a non-conflicting Title value.
        $count = 2;
        while ($this->titleExists()) {
            $this->Title = preg_replace('#-\d+$#', '', (string) $this->Title) . '-' . $count;
            ++$count;
        }
        if (!$this->Locked) {
            $this->addProductsFromCategories();
            $this->addProductsFromOtherLists();
        }
        // If there is no URLSegment set, generate one from Title
        $defaultSegment = 'custom-list-' . ($this->ID ?: rand(10000, 99999));
        if ((!$this->hasURLSegment() || $this->URLSegment == $defaultSegment) && $this->Title) {
            $this->URLSegment = $this->generateURLSegment($this->Title);
        } elseif ($this->isChanged('URLSegment', 2)) {
            // Do a strict check on change level, to avoid double encoding caused by
            // bogus changes through forceChange()
            $filter = URLSegmentFilter::create();
            $this->URLSegment = $filter->filter($this->URLSegment);
            // If after sanitising there is no URLSegment, give it a reasonable default
            if (!$this->hasURLSegment()) {
                $this->URLSegment = $defaultSegment;
            }
        }
    }
    private function hasURLSegment(): bool
    {
        return $this->URLSegment !== false && $this->URLSegment !== null && $this->URLSegment !== '';
    }

    public function generateURLSegment($title): string
    {
        $filter = URLSegmentFilter::create();
        $filteredTitle = $filter->filter($title);

        // Fallback to generic page name if path is empty (= no valid, convertable characters)
        if (!$filteredTitle || $filteredTitle == '-' || $filteredTitle == '-1') {
            $filteredTitle = "custom-list-$this->ID";
        }

        // Hook for extensions
        $this->extend('updateURLSegment', $filteredTitle, $title);

        return (string) $filteredTitle;
    }

    protected function addProductsFromCategories()
    {
        if ($this->CategoriesToAdd()->exists()) {
            $arrayToAdd = [];
            foreach ($this->CategoriesToAdd() as $category) {
                if ($category instanceof ProductGroup) {
                    $list = $category->getProducts();
                    if ($list->exists()) {
                        $arrayToAdd = array_merge($arrayToAdd, $category->getProducts()->columnUnique());
                    }
                }
            }
            if ($this->MustAlsoBeInCategories()->exists()) {
                $mustAlsoBeIn = [];
                foreach ($this->MustAlsoBeInCategories() as $category) {
                    if ($category instanceof ProductGroup) {
                        $list = $category->getProducts();
                        if ($list->exists()) {
                            $mustAlsoBeIn = array_merge($mustAlsoBeIn, $category->getProducts()->columnUnique());
                        }
                    }
                }
                $arrayToAdd = array_intersect($arrayToAdd, $mustAlsoBeIn);
            }
            if (count($arrayToAdd)) {
                $list = Product::get()->filter(['ID' => $arrayToAdd]);
                if ($list->exists()) {
                    $this->AddProductsToString($list);
                }
            }
            if (!$this->KeepAddingFromCategories) {
                $this->CategoriesToAdd()->removeAll();
            }
        }
    }

    protected function addProductsFromOtherLists()
    {
        if ($this->CustomProductListsToAdd()->exists()) {
            /** @var DataList $customProductLists */
            foreach ($this->CustomProductListsToAdd() as $customProductLists) {
                $list = $customProductLists->Products();
                if ($list->exists()) {
                    $this->AddProductsToString($list);
                }
            }
            if (!$this->KeepAddingFromCustomProductListsToAdd) {
                $this->CustomProductListsToAdd()->removeAll();
            }
        }
    }

    protected function onAfterWrite()
    {
        parent::onAfterWrite();
        $this->ProductsToAdd()->removeAll();
        $this->ProductsToDelete()->removeAll();
        if ($this->writeAgain) {
            $this->writeAgain = false;
            $this->write();
        }
    }

    /**
     * add many products.
     *
     * @param \SilverStripe\ORM\DataList $products
     * @param bool                       $write    -should the dataobject be written?
     */
    protected function AddProductsToString($products, ?bool $write = false)
    {
        /** @var Product $product */
        foreach ($products as $product) {
            $this->AddProductToString($product, $write);
        }

        return $this;
    }

    /**
     * add products, using comma separated InternalItemID string
     *
     * @param string $internalItemIDs
     * @param bool   $write           -should the dataobject be written?
     */
    public function AddProductCodesToString(string $internalItemIDs, ?bool $write = false)
    {
        $array = explode($this->Config()->get('separator'), $internalItemIDs);
        foreach ($array as $internalItemID) {
            $this->AddProductCodeToString($internalItemID, $write);
        }

        return $this;
    }

    /**
     * remove many products.
     *
     * @param DataList $products
     * @param bool     $write    -should the dataobject be written?
     */
    protected function RemoveProductsFromString(DataList $products, ?bool $write = false)
    {
        /** @var Product $product */
        foreach ($products as $product) {
            $this->RemoveProductFromString($product, $write);
        }

        return $this;
    }

    /**
     * add one product.
     *
     * @param ?bool $write -should the dataobject be written?
     */
    protected function AddProductToString(Product $product, ?bool $write = false)
    {
        $array = $this->getProductsAsInternalItemsArray();
        if (is_array($array) && in_array($product->InternalItemID, $array, true)) {
            return;
        }
        $array[] = $product->InternalItemID;
        $this->setProductsFromArray($array, $write);

        return $this;
    }

    /**
     * add one product, using InternalItemID.
     *
     * @param string $internalItemID
     * @param ?bool   $write          -should the dataobject be written?
     */
    protected function AddProductCodeToString($internalItemID, ?bool $write = false)
    {
        $array = $this->getProductsAsInternalItemsArray();
        if (is_array($array) && in_array($internalItemID, $array, true)) {
            return;
        }
        $array[] = $internalItemID;
        $this->setProductsFromArray($array, $write);

        return $this;
    }

    /**
     * remove one product.
     *
     * @param bool $write -should the dataobject be written?
     */
    protected function RemoveProductFromString(Product $product, ?bool $write = false)
    {
        $array = $this->getProductsAsInternalItemsArray();
        if (!in_array($product->InternalItemID, $array, true)) {
            return;
        }
        $array = array_diff($array, [$product->InternalItemID]);
        $this->setProductsFromArray($array, $write);

        return $this;
    }

    /**
     * @param bool $write -should the dataobject be written?
     */
    protected function setProductsFromArray(array $array, ?bool $write = false): array
    {
        $sep = Config::inst()->get(CustomProductList::class, 'separator');
        $alt = Config::inst()->get(CustomProductList::class, 'separator_alternative');
        foreach ($array as $key => $value) {
            if ($value) {
                $value = trim((string) $value);
                $value = str_replace($sep, $alt, $value);
                if ($value) {
                    $array[$key] = $value;
                } else {
                    unset($array[$key]);
                }
            } else {
                unset($array[$key]);
            }
        }
        $newString = implode($sep, $array);
        $this->InternalItemCodeList = $newString;
        if ($write) {
            $this->write();
        }

        return $array;
    }

    protected function defaultTitle(): string
    {
        return _t(
            'CMSMain.NEWPAGE',
            'Custom Product List'
        )
            . ($this->ID ? ' ' . $this->ID : '');
    }

    protected function generateTitle(): string
    {
        $list = $this->Products();
        $title = $this->Title;
        if (!$title) {
            $title = ($list->exists() ? implode('; ', $list->column('Title')) : $this->defaultTitle());
        }
        $filter = URLSegmentFilter::create();
        $title = $filter->filter($title);

        // Fallback to generic page name if path is empty (= no valid, convertable characters)
        if (!$title || '-' === $title || '-1' === $title) {
            $title = $this->defaultTitle();
        }

        return $title;
    }

    protected function titleExists(): bool
    {
        // Check existence
        return (bool) CustomProductList::get()
            ->filter(['Title' => $this->Title])
            ->exclude(['ID' => $this->ID])
            ->exists();
    }

    public function RecentlyEdited(): DBBoolean
    {
        $v = strtotime((string) $this->LastEdited) > strtotime($this->config()->get('definition_of_recently_edited'));
        return DBBoolean::create_field(DBBoolean::class, $v);
    }

    public function UsedAnywhere(): DBBoolean
    {
        $v = $this->ListOfUsagePoints() !== [];
        return DBBoolean::create_field(DBBoolean::class, $v);
    }

    public function ListOfUsagePoints(): array
    {
        $rels = $this->ListOfRelationships();
        foreach ($rels as $method => $class) {
            $relObjectOrObjects = $this->$method();
            if(!$relObjectOrObjects->exists()) {
                unset($rels[$method]);
            }
        }
        return $rels;
    }

    public function ListOfUsagePointsCheck()
    {
        $v = '';
        foreach ($this->ListOfRelationships() as $method => $class) {
            $v .= '<li>' . $method . ': ' . $this->$method()->count() . '</li>';
        }
        $v = $v ? '<ul>' . $v . '</ul>' : '';
        return DBHTMLText::create_field(DBHTMLText::class, $v);
    }


    protected static array $_listOfRelationships = [];
    public function ListOfRelationships(): array
    {
        if(self::$_listOfRelationships === []) {
            $relNames = [
                'has_one',
                'has_many',
                'many_many',
                'belongs_to',
                'belongs_many_many',
            ];
            $rels = [];
            foreach ($relNames as $relName) {
                $check = $this->config()->get($relName);
                if (is_array($check)) {
                    foreach ($check as $name => $class) {
                        if(is_array($class)) {
                            $class = $class['through'] ?? '';
                            $name = $class['from'] ?? $name;
                        }
                        $rels[$name] = $class;
                    }
                }

            }
            self::$_listOfRelationships = $rels;
        }
        return self::$_listOfRelationships;
    }

    public function CMSEditLink(): string
    {
        return CMSEditLinkAPI::find_edit_link_for_object($this);
    }


    public function Link($action = null): string
    {
        return $this->getLink($action);
    }

    public function getLink($action = null): string
    {
        // Implement the logic for generating the link here
        if(! $this->URLSegment) {
            $this->write();
        }
        $page = $this->MyDisplayPage() ;
        if($page) {
            $page->setCustomList($this);
            return $page->Link();
        }
        return '';

    }

    public function MyDisplayPage(): ?CustomListPage
    {
        return CustomListPage::get()->first();
    }

}

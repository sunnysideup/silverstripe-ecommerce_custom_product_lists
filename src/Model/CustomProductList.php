<?php

namespace Sunnysideup\EcommerceCustomProductLists\Model;

use SilverStripe\CMS\Forms\SiteTreeURLSegmentField;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\Tab;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\SS_List;
use SilverStripe\View\Parsers\URLSegmentFilter;
use Sunnysideup\CmsEditLinkField\Api\CMSEditLinkAPI;
use Sunnysideup\Ecommerce\Config\EcommerceConfig;
use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldBasicPageRelationConfig;
use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldBasicPageRelationConfigNoAddExisting;
use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldConfigForProductGroups;
use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldConfigForProducts;
use Sunnysideup\Ecommerce\Pages\Product;
use Sunnysideup\Ecommerce\Pages\ProductGroup;
use Sunnysideup\Ecommerce\Traits\UniqueNameTrait;
use Sunnysideup\EcommerceCustomProductLists\Forms\GridField\GridFieldExportWithCustomFileNameButton;
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
 * @method \SilverStripe\ORM\HasManyList|\Sunnysideup\EcommerceCustomProductLists\Model\CustomProductListChange[] Changes()
 */
class CustomProductList extends DataObject
{
    use UniqueNameTrait;

    /**
     * how are product codes separated?
     *
     * @var string
     */
    private static string $separator = ',';
    private static string $separator_name = 'comma';

    /**
     * if a product separator is used in the product code then
     * it will be replaced by this variable.
     *
     * @var string
     */
    private static string $separator_alternative = ';';
    private static string $definition_of_recently_edited = '-6 months';
    private static array $scaffold_cms_fields_settings = [
        'includeRelations' => false,
    ];

    /**
     * Relationships that should NOT count towards "is this list used anywhere?"
     * (and therefore should not block deletion). The change-history has_many is
     * an internal log, not a usage of the list.
     */
    private static array $usage_relationships_to_ignore = [
        'Changes',
    ];

    private static array $core_rels = [
        'ProductsToAdd' => 'products',
        'ProductsToDelete' => 'products',
        'CategoriesToAdd' => 'categories',
        'MustAlsoBeInCategories' => 'categories',
        'CustomProductListsToAdd' => 'custom_product_lists',
        'MustAlsoBeInCustomProductLists' => 'custom_product_lists',
    ];

    private static $table_name = 'CustomProductList';

    private static $db = [
        'Title' => 'Varchar(255)',
        'URLSegment' => 'Varchar(255)',
        'PubliclyAvailable' => 'Boolean',
        'Locked' => 'Boolean',
        'InternalItemCodeList' => 'Text',
        'InternalItemCodeListCustom' => 'Text',
        'KeepAddingFromCategories' => 'Boolean',
        'KeepRemovingFromCategories' => 'Boolean',
        'KeepAddingFromCustomProductListsToAdd' => 'Boolean',
        'KeepRemovingFromMustAlsoBeInCustomProductLists' => 'Boolean',
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
        'MustAlsoBeInCustomProductLists' => CustomProductList::class,
    ];

    private static $has_many = [
        'Changes' => CustomProductListChange::class,
    ];

    private static $belongs_many_many = [
        'CustomProductListActions' => CustomProductListAction::class,
        'CustomProductListAddedTo' => CustomProductListAction::class,
    ];

    // remove the change-history when the list itself is removed.
    private static $cascade_deletes = [
        'Changes',
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
        'UsedAnywhere.NiceAndColourfull' => 'Used at all?',
        'RecentlyEdited.NiceAndColourfull' => 'Recently Edited',

    ];

    private static $field_labels = [
        'UsedAnywhere' => 'In Use',
        'InternalItemCodeList' => 'Included Codes',
        'InternalItemCodeListCustom' => 'Manually add codes',
        'ProductsToDelete' => 'Remove Products from List',
        'Locked' => 'Locked: do not remove - here to stay - change with care or unlock first',
        'ProductsToAdd' => 'Add Products to List',
        'CategoriesToAdd' => 'Categories to add',
        'MustAlsoBeInCategories' => 'Products must also be in these Categories',
        'CustomProductListsToAdd' => 'Custom product lists products to add',
        'MustAlsoBeInCustomProductLists' => 'Products must also be in these Custom Product Lists',
        'KeepAddingFromCategories' => 'Keep adding from categories?',
        'KeepAddingFromCustomProductListsToAdd' => 'Keep adding from other custom product lists?',
        'KeepRemovingFromCategories' => 'Keep ensuring products are also in these categories?',
        'KeepRemovingFromMustAlsoBeInCustomProductLists' => 'Keep ensuring products are also in these custom product lists?',
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
        if ($this->Locked) {
            return false;
        }
        if ($this->UsedAnywhere()->raw()) {
            return false;
        }
        if ($this->RecentlyEdited()->raw()) {
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
        $fields->addFieldToTab(
            'Root',
            new Tab('MustAlsoBeInOtherLists', _t('CustomProductList.MUST_ALSO_BE_IN_OTHER_LISTS', 'Must also be present in')),
            'Actions'
        );
        $fields->addFieldToTab(
            'Root',
            new Tab('Remove', _t('CustomProductList.PRODUCTS_TO_REMOVE', 'Remove Products')),
            'Actions'
        );

        // NOTE: relations are NOT scaffolded ($scaffold_cms_fields_settings['includeRelations'] === false),
        // so the relation GridFields below are built by hand. All *scaffolded* fields (the DB columns,
        // including every Boolean checkbox) are reused from parent::getCMSFields() further down rather
        // than being recreated - see reuseScaffoldedField().
        $fieldsToAdd = [];
        if ($this->exists()) {
            $rels = $this->config()->get('core_rels') ?: [];
            foreach ($rels as $rel => $type) {
                $config = match ($type) {
                    'categories' => GridFieldConfigForProductGroups::create(),
                    default => GridFieldBasicPageRelationConfig::create(),
                };
                $fields->removeByName($rel);
                $fieldsToAdd[$rel] = GridField::create(
                    $rel,
                    $this->fieldLabel($rel),
                    $this->{$rel}(),
                    $config
                );
            }
            foreach (['MustAlsoBeInCustomProductLists', 'CustomProductListsToAdd'] as $key) {
                $ac = $fieldsToAdd[$key]?->getConfig()?->getComponentByType(GridFieldAddExistingAutocompleter::class);
                if ($ac) {
                    $ac->setSearchFields(['Title']);
                    $ac->setResultsFormat('$Title');
                    $ac->setSearchList(CustomProductList::get()->exclude('ID', $this->ID));
                }
            }
        }

        // InternalItemCodeList: master list shown read-only.
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
                ->addComponent((new GridFieldExportWithCustomFileNameButton('buttons-before-right'))
                    ->setFileNameTemplate($this->Title . '-export-{now}.csv'))
                ->removeComponentsByType(GridFieldDeleteAction::class)
        );
        $currentProductsField->setDescription('Calculated products, based on the list of included product codes (see Main Tab).');

        $fields->addFieldToTab('Root.Main', $currentProductsField);
        $fields->removeFieldFromTab('Root', 'Products');

        if ($this->Locked || ! $this->exists()) {
            $fields->removeFieldFromTab('Root.Main', 'InternalItemCodeListCustom');
            $fields->removeByName('KeepAddingFromCategories');
            $fields->removeByName('KeepRemovingFromCategories');
            $fields->removeByName('KeepAddingFromCustomProductListsToAdd');
            $fields->removeByName('KeepRemovingFromMustAlsoBeInCustomProductLists');
            $fields->removeFieldFromTab('Root', 'AddProducts');
            $fields->removeFieldFromTab('Root', 'MustAlsoBeInOtherLists');
            $fields->removeFieldFromTab('Root', 'Remove');
        } else {
            // ------------------------------------------------------------------
            // Reuse the scaffolded Boolean checkboxes (point 1): grab the fields
            // that parent::getCMSFields() already created, relabel/describe them,
            // and move them to the correct tab instead of building duplicates.
            // ------------------------------------------------------------------
            $keepAddingFromCategories = $this->reuseScaffoldedField(
                $fields,
                'KeepAddingFromCategories',
                'If ticked, every time you save this list we keep adding products from the categories selected above. If unticked, the products from the selected categories are added once only.'
            );
            $keepAddingFromLists = $this->reuseScaffoldedField(
                $fields,
                'KeepAddingFromCustomProductListsToAdd',
                'If ticked, every time you save this list we keep adding products from the other custom lists selected above. If unticked, the products are added once only.'
            );
            $keepRemovingFromCategories = $this->reuseScaffoldedField(
                $fields,
                'KeepRemovingFromCategories',
                'If ticked, every time you save this list we keep removing products that are not in the categories selected above. If unticked, this happens once only.'
            );
            $keepRemovingFromLists = $this->reuseScaffoldedField(
                $fields,
                'KeepRemovingFromMustAlsoBeInCustomProductLists',
                'If ticked, every time you save this list we keep removing products that are not in the custom lists selected above. If unticked, this happens once only.'
            );

            // ProductsToAdd
            $productsToAddField = $fieldsToAdd['ProductsToAdd'] ?? null;
            if ($productsToAddField) {
                $productsToAddField->setDescription('Use this field to add products. They will be removed again from this list after they have been added to the master list.');
                $productsToAddField->setConfig(GridFieldConfigForProducts::create());
                $fields->addFieldToTab('Root.AddProducts', $productsToAddField);
            }

            // Remove tab.
            $fields->addFieldsToTab(
                'Root.Remove',
                [
                    CheckboxSetField::create(
                        'ProductsToDelete',
                        'Products to Remove',
                        $this->Products()->sort('Title')->map('ID', 'FullName')->toArray()
                    )->setDescription('Use this field to remove products. They will be removed again from this list after they have been removed from the master list.'),
                ]
            );

            // Reuse the scaffolded "manually add codes" text field.
            $manualCodesField = $fields->dataFieldByName('InternalItemCodeListCustom');
            if ($manualCodesField) {
                $manualCodesField->setDescription(
                    'Separate codes by ' . $this->config()->get('separator') . ' (' . $this->config()->get('separator_name') . ').' .
                    '
                        Only use this option if products are not currently available on site.
                        If they are already part of the site then you can add them using the tools provided.
                    '
                );
                $fields->removeByName('InternalItemCodeListCustom');
            }

            // Add Products tab.
            $fields->addFieldsToTab(
                'Root.AddProducts',
                array_filter([
                    HeaderField::create('AddManuallyHeader', 'Add Manually', 1),
                    $manualCodesField,
                    HeaderField::create('AddFromCategoriesHeader', 'Categories', 1),
                    $fieldsToAdd['CategoriesToAdd']
                        ->setDescription('All products in the selected categories will be added. Make sure to select a category.'),
                    $keepAddingFromCategories,
                    HeaderField::create('AddFromListsHeader', 'Custom Lists', 1),
                    $fieldsToAdd['CustomProductListsToAdd']
                        ->setDescription('All products in the selected custom product lists will be added. Make sure to select a list.'),
                    $keepAddingFromLists,
                ])
            );

            // Must also be in tab.
            $fields->addFieldsToTab(
                'Root.MustAlsoBeInOtherLists',
                array_filter([
                    HeaderField::create('MustBeInCategoriesHeader', 'Categories', 1),
                    $fieldsToAdd['MustAlsoBeInCategories']
                        ->setDescription('Products in this list must also be in one of the categories selected here; any that are not will be removed.'),
                    $keepRemovingFromCategories,
                    HeaderField::create('MustBeInListsHeader', 'Custom Lists', 1),
                    $fieldsToAdd['MustAlsoBeInCustomProductLists']
                        ->setDescription('Products in this list must also be in one of the custom product lists selected here; any that are not will be removed.'),
                    $keepRemovingFromLists,
                ])
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
                ReadonlyField::create('UsedAnywhereNice', 'In use', $this->UsedAnywhere()->Nice()),
                ReadonlyField::create('ListOfUsagePointsNice', 'Usage', implode(', ', array_keys($this->ListOfUsagePoints()))),
                ReadonlyField::create('RecentlyEditedNice', 'Recently Edited', $this->RecentlyEdited()->Nice()),
                GridField::create(
                    'CustomProductListAddedTo',
                    'This custom list adds products to the following other custom lists',
                    $this->CustomProductListAddedTo(),
                    GridFieldConfig_RecordViewer::create()
                ),
            ]
        );

        // Change history (read-only log, auto-maintained on save).
        if ($this->exists()) {
            $historyField = GridField::create(
                'Changes',
                'Change history',
                $this->Changes(),
                GridFieldConfig_RecordViewer::create()
            );
            $historyField->setDescription(
                'Automatically maintained log of changes applied to this list (categories / custom lists added, ' .
                'restrictions applied, products added or removed). Re-applying the same change moves its entry to ' .
                'the most recent instead of duplicating it.'
            );
            $fields->addFieldToTab('Root.History', $historyField);
        }

        if ($this->PubliclyAvailable) {
            $baseLink = $this->MyDisplayPage()?->Link('show') ?? '';
            if ($baseLink) {
                $urlsegment = SiteTreeURLSegmentField::create(
                    'URLSegment',
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
                if (! URLSegmentFilter::create()->getAllowMultibyte()) {
                    $helpText .= _t('SilverStripe\\CMS\\Forms\\SiteTreeURLSegmentField.HelpChars', ' Special characters are automatically converted or removed.');
                }
                $urlsegment->setHelpText($helpText);
                $fields->replaceField('URLSegment', $urlsegment);
            }
        } else {
            $fields->removeByName('URLSegment');
        }

        return $fields;
    }

    /**
     * Take a field that parent::getCMSFields() already scaffolded, relabel /
     * describe it, and detach it from wherever it currently lives so the caller
     * can re-add it to the right tab. Returns null when the field is missing.
     */
    private function reuseScaffoldedField(FieldList $fields, string $name, string $description): ?FormField
    {
        $field = $fields->dataFieldByName($name);
        if (! $field) {
            return null;
        }
        $field
            ->setTitle($this->fieldLabel($name))
            ->setDescription($description);
        // detach from its scaffolded position; the caller re-adds it in order.
        $fields->removeByName($name);

        return $field;
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

    /**
     * The stored (canonical) list of InternalItemID codes, trimmed and with
     * empty entries removed.
     *
     * @return string[]
     */
    public function getProductsAsInternalItemsArray(): array
    {
        $sep = (string) $this->config()->get('separator');
        $codes = array_map('trim', explode($sep, (string) $this->InternalItemCodeList));

        return array_values(array_filter($codes, static fn ($code): bool => $code !== ''));
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
        /** @var string|Product $className */
        $className = EcommerceConfig::get(ProductGroup::class, 'base_buyable_class');
        $codes = $this->getProductsAsInternalItemsArray();
        if ($codes === []) {
            // guaranteed-empty list (avoids "InternalItemID IN ('')" matching blank codes)
            return $className::get()->filter('ID', -1);
        }

        return $className::get()->filter(['InternalItemID' => $codes]);
    }

    protected $writeAgain = false;

    /**
     * Change descriptors queued during onBeforeWrite / syncProductsFromRelations
     * and flushed into CustomProductListChange records in onAfterWrite (where an
     * ID is guaranteed to exist). Keyed by signature to de-dupe within one save.
     *
     * @var array<string, array{type:string, signature:string, description:string}>
     */
    protected array $pendingChanges = [];

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if ($this->Locked) {
            // do nothing
        } elseif ($this->exists()) {
            // note what is being added/removed before we mutate the string list.
            $this->queueProductChanges($this->ProductsToAdd(), 'add-product', 'Added product "%s" (%s)');
            $this->queueManualCodeChanges((string) $this->InternalItemCodeListCustom);
            $this->queueProductChanges($this->ProductsToDelete(), 'remove-product', 'Removed product "%s" (%s)');

            $this->AddProductsToString($this->ProductsToAdd(), false);
            $this->AddProductCodesToString((string) $this->InternalItemCodeListCustom, false);
            $this->RemoveProductsFromString($this->ProductsToDelete(), false);
            $this->InternalItemCodeListCustom = '';
        } else {
            $this->writeAgain = true;
        }
        // If there is no Title set, generate one from the default
        $this->Title = $this->generateTitle();
        // Ensure that this object has a non-conflicting Title value.

        if (! $this->Locked) {
            $this->syncProductsFromRelations();
        }

        // If there is no URLSegment set, generate one from Title
        $defaultSegment = 'custom-list-' . ($this->ID ?: rand(10000, 99999));
        if ((! $this->hasURLSegment() || $this->URLSegment === $defaultSegment) && $this->Title) {
            $this->URLSegment = $this->generateURLSegment($this->Title);
        } elseif ($this->isChanged('URLSegment', 2)) {
            // Do a strict check on change level, to avoid double encoding caused by
            // bogus changes through forceChange()
            $filter = URLSegmentFilter::create();
            $this->URLSegment = $filter->filter($this->URLSegment);
            // If after sanitising there is no URLSegment, give it a reasonable default
            if (! $this->hasURLSegment()) {
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
        if (! $filteredTitle || $filteredTitle === '-' || $filteredTitle === '-1') {
            $filteredTitle = "custom-list-{$this->ID}";
        }

        // Hook for extensions
        $this->extend('updateURLSegment', $filteredTitle, $title);

        return (string) $filteredTitle;
    }

    /**
     * Single entry point that reconciles the master code list with every
     * relation that can change it:
     *   - CategoriesToAdd / CustomProductListsToAdd            -> ADD (append)
     *   - MustAlsoBeInCategories / MustAlsoBeInCustomProductLists -> RESTRICT (intersect)
     *
     * Adds are appended. "Must also be in" relations are applied as an
     * intersection: a product is kept only if it appears in *each* active
     * constraint type (AND across the two types, OR within one type).
     *
     * Every relation that is actually applied is logged (before it is emptied)
     * so the change-history reflects what happened.
     */
    protected function syncProductsFromRelations(): void
    {
        // -----------------------------------------------------------------
        // 1. ADD - append every product found in the "add" relations.
        // -----------------------------------------------------------------
        $codesToAdd = array_merge(
            $this->collectProductCodes($this->CategoriesToAdd()),
            $this->collectProductCodes($this->CustomProductListsToAdd()),
        );
        if ($codesToAdd !== []) {
            $merged = array_merge($this->getProductsAsInternalItemsArray(), $codesToAdd);
            $this->setProductsFromArray(array_values(array_unique($merged)));
        }

        // "add once" relations are logged, then cleared unless kept.
        if ($this->CategoriesToAdd()->exists()) {
            $this->queueRelationChanges($this->CategoriesToAdd(), 'add-category', 'Added products from category "%s"');
            if (! $this->KeepAddingFromCategories) {
                $this->CategoriesToAdd()->removeAll();
            }
        }
        if ($this->CustomProductListsToAdd()->exists()) {
            $this->queueRelationChanges($this->CustomProductListsToAdd(), 'add-list', 'Added products from custom list "%s"');
            if (! $this->KeepAddingFromCustomProductListsToAdd) {
                $this->CustomProductListsToAdd()->removeAll();
            }
        }

        // -----------------------------------------------------------------
        // 2. RESTRICT - keep only products that satisfy every constraint.
        // -----------------------------------------------------------------
        $constraintSets = [];
        if ($this->MustAlsoBeInCategories()->exists()) {
            $constraintSets[] = $this->collectProductCodes($this->MustAlsoBeInCategories());
        }
        if ($this->MustAlsoBeInCustomProductLists()->exists()) {
            $constraintSets[] = $this->collectProductCodes($this->MustAlsoBeInCustomProductLists());
        }
        if ($constraintSets !== []) {
            $kept = $this->getProductsAsInternalItemsArray();
            foreach ($constraintSets as $allowedCodes) {
                $kept = array_intersect($kept, $allowedCodes);
            }
            $this->setProductsFromArray(array_values($kept));
        }

        // "restrict once" relations are logged, then cleared unless kept.
        if ($this->MustAlsoBeInCategories()->exists()) {
            $this->queueRelationChanges($this->MustAlsoBeInCategories(), 'restrict-category', 'Kept only products also in category "%s"');
            if (! $this->KeepRemovingFromCategories) {
                $this->MustAlsoBeInCategories()->removeAll();
            }
        }
        if ($this->MustAlsoBeInCustomProductLists()->exists()) {
            $this->queueRelationChanges($this->MustAlsoBeInCustomProductLists(), 'restrict-list', 'Kept only products also in custom list "%s"');
            if (! $this->KeepRemovingFromMustAlsoBeInCustomProductLists) {
                $this->MustAlsoBeInCustomProductLists()->removeAll();
            }
        }
    }

    /**
     * Return the unique, canonicalised InternalItemID codes for every product
     * in the supplied relation. Works for both ProductGroup (categories) and
     * CustomProductList relations, so the two "add" and two "must also be in"
     * relations can all be gathered the same way.
     *
     * @param SS_List<ProductGroup|CustomProductList> $relation
     *
     * @return string[]
     */
    private function collectProductCodes(SS_List $relation): array
    {
        $sep = (string) $this->config()->get('separator');
        $alt = (string) $this->config()->get('separator_alternative');

        $codes = [];
        foreach ($relation as $item) {
            $products = match (true) {
                $item instanceof ProductGroup => $item->getProducts(),
                $item instanceof CustomProductList => $item->Products(),
                default => null,
            };
            if ($products && $products->exists()) {
                foreach ($products->columnUnique('InternalItemID') as $code) {
                    $code = str_replace($sep, $alt, trim((string) $code));
                    if ($code !== '') {
                        $codes[$code] = $code; // dedupe on the value
                    }
                }
            }
        }

        return array_values($codes);
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
        // persist queued history entries now that we definitely have an ID.
        $this->flushPendingChanges();
    }

    // ---------------------------------------------------------------------
    // Change-history helpers
    // ---------------------------------------------------------------------

    /**
     * Queue one change. Keyed by signature so repeating the same change within a
     * single save collapses to one entry.
     */
    protected function queueChange(string $type, string $signature, string $description): void
    {
        $this->pendingChanges[$signature] = [
            'type' => $type,
            'signature' => $signature,
            'description' => $description,
        ];
    }

    /**
     * Queue a change entry per item of a ProductGroup / CustomProductList relation.
     *
     * @param SS_List<ProductGroup|CustomProductList> $relation
     */
    private function queueRelationChanges(SS_List $relation, string $type, string $descriptionFormat): void
    {
        foreach ($relation as $item) {
            if (! ($item instanceof ProductGroup) && ! ($item instanceof CustomProductList)) {
                continue;
            }
            $this->queueChange(
                $type,
                $type . '-' . $item->ID,
                sprintf($descriptionFormat, (string) $item->Title)
            );
        }
    }

    /**
     * Queue a change entry per product of a Product relation.
     *
     * @param SS_List<Product> $products
     */
    private function queueProductChanges(SS_List $products, string $type, string $descriptionFormat): void
    {
        foreach ($products as $product) {
            if (! ($product instanceof Product)) {
                continue;
            }
            $this->queueChange(
                $type,
                $type . '-' . $product->ID,
                sprintf($descriptionFormat, (string) $product->Title, (string) $product->InternalItemID)
            );
        }
    }

    /**
     * Queue a change entry per manually-entered code.
     */
    private function queueManualCodeChanges(string $internalItemIDs): void
    {
        $sep = (string) $this->config()->get('separator');
        foreach (array_filter(array_map('trim', explode($sep, $internalItemIDs))) as $code) {
            $this->queueChange('add-code', 'add-code-' . $code, sprintf('Manually added code "%s"', $code));
        }
    }

    /**
     * Write the queued change descriptors to CustomProductListChange records.
     * Called from onAfterWrite so $this->ID exists.
     */
    protected function flushPendingChanges(): void
    {
        if ($this->pendingChanges === [] || ! $this->exists()) {
            return;
        }
        $pending = $this->pendingChanges;
        $this->pendingChanges = [];
        foreach ($pending as $change) {
            $this->logChange($change['type'], $change['signature'], $change['description']);
        }
    }

    /**
     * Record a single change against this list. If an entry with the same
     * signature already exists it is "redone": Occurrences is incremented (which
     * bumps LastEdited, moving it to the end of the LastEdited-sorted history),
     * rather than a duplicate row being created.
     */
    public function logChange(string $type, string $signature, string $description): CustomProductListChange
    {
        if (! $this->exists()) {
            // nothing to attach the change to yet; queue instead.
            $this->queueChange($type, $signature, $description);

            return CustomProductListChange::create();
        }

        $change = CustomProductListChange::get()->filter([
            'CustomProductListID' => $this->ID,
            'Signature' => $signature,
        ])->first();

        $isRedo = (bool) $change;
        if (! $change) {
            $change = CustomProductListChange::create();
            $change->CustomProductListID = $this->ID;
            $change->Signature = $signature;
        }
        $change->Type = $type;
        $change->Description = $description;
        // Occurrences always changes, so write() persists and LastEdited is bumped
        // (that is what moves a "redone" entry to the end of the history).
        $change->Occurrences = $isRedo ? ((int) $change->Occurrences + 1) : 1;
        $change->write();

        return $change;
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
        $array = explode($this->config()->get('separator'), $internalItemIDs);
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
        if (in_array($product->InternalItemID, $array, true)) {
            return $this;
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
        if (in_array($internalItemID, $array, true)) {
            return $this;
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
        if (! in_array($product->InternalItemID, $array, true)) {
            return $this;
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
            $value = trim((string) $value);
            if ($value !== '') {
                $array[$key] = str_replace($sep, $alt, $value);
            } else {
                unset($array[$key]);
            }
        }
        $this->InternalItemCodeList = implode($sep, $array);
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

    public function RecentlyEdited(): DBBoolean
    {
        $v = strtotime((string) $this->LastEdited) > strtotime($this->config()->get('definition_of_recently_edited'));

        return DBBoolean::create_field(DBBoolean::class, $v);
    }

    public function UsedAnywhere(): DBBoolean
    {
        $v = $this->ListOfUsagePoints(false) !== [];

        return DBBoolean::create_field(DBBoolean::class, $v);
    }

    public function ListOfUsagePoints(bool $isNiceList = true): array
    {
        $rels = $this->ListOfRelationships();
        foreach ($rels as $method => $class) {
            $relObjectOrObjects = $this->{$method}();
            if (! $relObjectOrObjects->exists()) {
                unset($rels[$method]);
            }
        }
        $niceList = [];
        if ($isNiceList) {
            foreach ($rels as $method => $class) {
                $niceList[$this->methodToName($method)] = Injector::inst()->get($class)->i18n_singular_name();
            }
        }

        return $isNiceList ? $niceList : $rels;
    }

    public function ListOfUsagePointsCheck()
    {
        $v = '';
        foreach ($this->ListOfRelationships() as $method => $class) {
            $name = $this->methodToName($method);
            $v .= '<li>' . $name . ': ' . $this->{$method}()->count() . '</li>';
        }
        $v = $v ? '<ul>' . $v . '</ul>' : '';

        return DBHTMLText::create_field(DBHTMLText::class, $v);
    }

    protected function methodToName(string $method): string
    {
        return $this->fieldLabel($method) ?: ucfirst(str_replace('_', ' ', $method))    ;
    }

    protected static array $_listOfRelationships = [];

    public function ListOfRelationships(): array
    {
        if (self::$_listOfRelationships === []) {
            $ignore = (array) ($this->config()->get('usage_relationships_to_ignore') ?: []);
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
                        if (in_array($name, $ignore, true)) {
                            continue;
                        }
                        if (is_array($class)) {
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
        if (! $this->URLSegment) {
            $this->write();
            if (! $this->URLSegment) {
                user_error('Can not write URL Segment for CustomProductList with ID ' . $this->ID);
            }
        }
        $page = $this->MyDisplayPage();
        if ($page) {
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

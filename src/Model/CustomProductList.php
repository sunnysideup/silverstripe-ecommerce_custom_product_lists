<?php

namespace Sunnysideup\EcommerceCustomProductLists\Model;

use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\GridField\GridField;

use SilverStripe\Forms\CheckboxSetField;

use SilverStripe\Forms\HeaderField;
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

use Sunnysideup\EcommerceCustomProductLists\Model\CustomProductListAction;


/**
 * 1. titles should not be identical
 * 2. when copying accross, we have to make sure
 * 3. onAfterWrite, do we add products from InternalItemCodeList?
 * 4. How can we remove products?
 */
class CustomProductList extends DataObject
{
    /**
     * how are product codes separated?
     *
     * @var string
     */
    private static $separator = ',';

    /**
     * if a product separator is used in the product code then
     * it will be replaced by this variable.
     *
     * @var string
     */
    private static $separator_alternative = ';';

    private static $table_name = 'CustomProductList';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Locked' => 'Boolean',
        'InternalItemCodeList' => 'Text',
        'InternalItemCodeListCustom' => 'Text',
        'KeepAddinFromCategories' => 'Boolean',
    ];

    private static $indexes = [
        'ProductListIndex' => [
            'type' => 'unique',
            'columns' => ['Title'],
        ],
    ];

    private static $many_many = [
        'ProductsToAdd' => Product::class,
        'ProductsToDelete' => Product::class,
        'CategoriesToAdd' => ProductGroup::class,
    ];

    private static $belongs_many_many = [
        'CustomProductListActions' => CustomProductListAction::class,
    ];

    private static $searchable_fields = [
        'Title' => 'PartialMatchFilter',
        'Locked' => 'ExactMatchFilter',
        'InternalItemCodeList' => 'PartialMatchFilter',
    ];

    private static $summary_fields = [
        'Title' => 'FullName',
        'ProductCount' => 'Products',
        'Locked.Nice' => 'Locked',
    ];

    private static $field_labels = [
        'InternalItemCodeList' => 'Included Codes',
        'InternalItemCodeListCustom' => 'Manually added codes',
    ];

    private static $default_sort = [
        'LastEdited' => 'DESC',
    ];

    private static $casting = [
        'FullName' => 'Varchar',
        'ProductCount' => 'Int',
    ];


    public function getFullName()
    {
        return $this->Title . ' (' . $this->getProductCount() . ' products)';
    }

    public function getProductCount() : int
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
        return $this->Locked ? false : Injector::inst()->get(ProductGroup::class)->canDelete($member);
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $html =
        '<div id="Form_ItemEditForm_InternalItemCodeList_Holder" class="field readonly textarea">
           <label class="left" for="Form_ItemEditForm_InternalItemCodeList">Included Codes</label>
              <div class="middleColumn">
                <span id="Form_ItemEditForm_InternalItemCodeList" class="readonly textarea" style="word-break:break-all;">
                    ' . $this->InternalItemCodeList . '
                </span>
              </div>
              <label class="right" for="Form_ItemEditForm_InternalItemCodeList">
                  This is the <strong>master list</strong><br>
                  Separate codes by ' . $this->Config()->get('separator') . '
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
        if ($this->Locked) {
            $fields->removeFieldFromTab('Root', 'ProductsToAdd');
            $fields->removeFieldFromTab('Root', 'ProductsToDelete');
            $fields->removeFieldFromTab('Root.Main', 'InternalItemCodeListCustom');
        } else {
            //products to add
            $productsToAddField = $fields->dataFieldByName('ProductsToAdd');
            if ($productsToAddField) {
                $productsToAddField->setDescription('Use this field to add products, they will be remove again from this list after they have been added to main list.');
                $productsToAddField->setConfig(GridFieldConfigForProducts::create());
            }
            //products to remove
            $productsToRemoveField = $fields->dataFieldByName('ProductsToDelete');
            if ($productsToRemoveField) {
                $productsToRemoveField->setDescription('Use this field to remove products, they will be removed again from this list after they have been removed from main list.');
                $productsToRemoveField->setConfig(GridFieldConfigForProducts::create());
            }
            $manualCodesField = $fields->dataFieldByName('InternalItemCodeListCustom');
            if ($manualCodesField) {
                $manualCodesField->setDescription(
                    'Separate codes by ' . $this->Config()->get('separator') . '.' .
                    ' Only use this option if products are not currently available on site.'
                );
            }
        }

        if($this->exists()) {
            foreach(CustomProductListAction::get_list_of_action_types() as $className) {
                $obj = $className::singleton();
                $title = $obj->i18n_singular_name();
                $fields->addFieldsToTab(
                    'Root.Actions',
                    [
                        HeaderField::create(
                            $title. ' Actions',
                            $title. ' Actions',
                            1
                        ),
                        GridField::create(
                            'ListFor'.$title,
                            $title,
                            $className::get()->filter(['CustomProductLists.ID' => $this->ID]),
                            GridFieldConfig_RecordViewer::create()
                        )
                    ]
                );
            }
            $fields->addFieldsToTab(
                'Root.CategoriesToAdd',
                CheckboxSetField::create('CategoriesToAdd', 'Categories to add', ProductGroup::get()->map('ID', 'Breadcrumbs'))
            );
        }
        $fields->removeByName(
            [
                'Root.CustomProductListActions', 'CustomProductListActions'
            ]
        );

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
        $list = explode($sep, $this->InternalItemCodeList);
        foreach ($list as $key => $code) {
            $list[$key] = trim($code);
        }
        if (! is_array($list)) {
            $list = [];
        }

        return $list;
    }

    /**
     * This is useful as a way to separate.
     *
     * @return \SilverStripe\ORM\DataList
     */
    public function Products() : DataList
    {
        return $this->getProductsFromInternalItemIDs();
    }

    /**
     * @return \SilverStripe\ORM\DataList
     */
    public function getProductsFromInternalItemIDs(): DataList
    {
        $className = EcommerceConfig::get(ProductGroup::class, 'base_buyable_class');

        return $className::get()->filter(['InternalItemID' => $this->getProductsAsInternalItemsArray()]);
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if ($this->Locked) {
            //do nothing
        } else {
            $this->AddProductsToString($this->ProductsToAdd(), $write = false);
            $this->AddProductCodesToString($this->InternalItemCodeListCustom, $write = false);
            $this->RemoveProductsFromString($this->ProductsToDelete(), $write = false);
            $this->InternalItemCodeListCustom = '';
        }
        // If there is no Title set, generate one from Title
        $this->Title = $this->generateTitle();
        // Ensure that this object has a non-conflicting Title value.
        $count = 2;
        while ($this->titleExists()) {
            $this->Title = preg_replace('#-\d+$#', null, $this->Title) . '-' . $count;
            ++$count;
        }
        if($this->CategoriesToAdd()->exists()) {
            foreach($this->CategoriesToAdd() as $category) {
                $list = $category->getProducts();
                if($list->exists()) {
                    $this->AddProductsToString($list);
                }
            }
            if(! $this->KeepAddingFromCategories) {
                $this->CategoriesToAdd()->removeAll();
            }
        }
    }

    protected function onAfterWrite()
    {
        parent::onAfterWrite();
        $this->ProductsToAdd()->removeAll();
        $this->ProductsToDelete()->removeAll();
    }

    /**
     * add many products.
     *
     * @param \SilverStripe\ORM\DataList $products
     * @param bool                       $write    -should the dataobject be written?
     */
    protected function AddProductsToString($products, ?bool $write = false)
    {
        foreach ($products as $product) {
            $this->AddProductToString($product, $write);
        }
        return $this;
    }

    /**
     * add one product, using InternalItemID.
     *
     * @param string $internalItemIDs
     * @param bool   $write           -should the dataobject be written?
     */
    protected function AddProductCodesToString($internalItemIDs, $write = false)
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
    protected function RemoveProductsFromString($products, $write = false)
    {
        foreach ($products as $product) {
            $this->RemoveProductFromString($product, $write);
        }
        return $this;
    }

    /**
     * add one product.
     *
     * @param bool $write -should the dataobject be written?
     */
    protected function AddProductToString(Product $product, $write = false)
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
     * @param bool   $write          -should the dataobject be written?
     */
    protected function AddProductCodeToString($internalItemID, $write = false)
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
    protected function RemoveProductFromString(Product $product, $write = false)
    {
        $array = $this->getProductsAsInternalItemsArray();
        if (! in_array($product->InternalItemID, $array, true)) {
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
                $value = trim($value);
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

    protected function defaultTitle() : string
    {
        return _t(
            'CMSMain.NEWPAGE',
            'Custom Product List'
        )
        . ($this->ID ? ' ' . $this->ID : '');
    }

    protected function generateTitle() : string
    {
        $list = $this->Products();
        $title = $this->title;
        if (! $title) {
            $title = ($list->exists() ? implode('; ', $list->column('Title')) : $this->defaultTitle());
        }
        $filter = URLSegmentFilter::create();
        $title = $filter->filter($title);

        // Fallback to generic page name if path is empty (= no valid, convertable characters)
        if (! $title || '-' === $title || '-1' === $title) {
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
            ->exists()
        ;
    }


}

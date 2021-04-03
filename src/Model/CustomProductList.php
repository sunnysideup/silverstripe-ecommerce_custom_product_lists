<?php

namespace Sunnysideup\EcommerceCustomProductLists\Model;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\Parsers\URLSegmentFilter;
use Sunnysideup\Ecommerce\Config\EcommerceConfig;
use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldBasicPageRelationConfig;
use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldBasicPageRelationConfigNoAddExisting;
use Sunnysideup\Ecommerce\Pages\Product;
use Sunnysideup\Ecommerce\Pages\ProductGroup;

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
    ];

    private static $searchable_fields = [
        'Title' => 'PartialMatchFilter',
        'Locked' => 'ExactMatchFilter',
        'InternalItemCodeList' => 'PartialMatchFilter',
    ];

    private static $summary_fields = [
        'Title' => 'Title',
        'Locked.Nice' => 'Locked',
        'InternalItemCodeList' => 'Included',
    ];

    private static $field_labels = [
        'InternalItemCodeList' => 'Included Codes',
        'InternalItemCodeListCustom' => 'Manually added codes',
    ];

    private static $default_sort = [
        'LastEdited' => 'DESC',
    ];

    /**
     * Deleting Permissions
     * @return bool
     */
    public function canDelete($member = null, $context = [])
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
                $productsToAddField->setConfig(GridFieldBasicPageRelationConfig::create());
            }
            //products to remove
            $productsToRemoveField = $fields->dataFieldByName('ProductsToDelete');
            if ($productsToRemoveField) {
                $productsToRemoveField->setDescription('Use this field to remove products, they will be removed again from this list after they have been removed from main list.');
                $productsToRemoveField->setConfig(GridFieldBasicPageRelationConfig::create());
            }
            $manualCodesField = $fields->dataFieldByName('InternalItemCodeListCustom');
            if ($manualCodesField) {
                $manualCodesField->setDescription(
                    'Separate codes by ' . $this->Config()->get('separator') . '.' .
                    ' Only use this option if products are not currently available on site.'
                );
            }
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

    /**
     * @return array
     */
    public function getProductsAsArray()
    {
        $sep = Config::inst()->get(CustomProductList::class, 'separator');
        $list = explode($sep, $this->InternalItemCodeList);
        foreach ($list as $key => $code) {
            $list[$key] = trim($code);
        }
        if (! is_array($list)) {
            $list = [];
        }
        // if(! count($list)) {
        //     $list = array(0 => 0);
        // }
        return $list;
    }

    /**
     * @return \SilverStripe\ORM\DataList
     */
    public function Products()
    {
        return $this->getProductsFromInternalItemIDs();
    }

    /**
     * @return \SilverStripe\ORM\DataList
     */
    public function getProductsFromInternalItemIDs()
    {
        $className = EcommerceConfig::get(ProductGroup::class, 'base_buyable_class');
        return $className::get()->filter(['InternalItemID' => $this->getProductsAsArray()]);
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
    }

    protected function onAfterWrite()
    {
        parent::onAfterWrite();
        $this->ProductsToAdd()->removeAll();
        $this->ProductsToDelete()->removeAll();
    }

    /**
     * add many products
     * @param \SilverStripe\ORM\SS_List $products
     * @param bool $write -should the dataobject be written?
     */
    protected function AddProductsToString($products, $write = false)
    {
        foreach ($products as $product) {
            $this->AddProductToString($product, $write);
        }
    }

    /**
     * add one product, using InternalItemID
     * @param string $internalItemIDs
     * @param bool $write -should the dataobject be written?
     */
    protected function AddProductCodesToString($internalItemIDs, $write = false)
    {
        $array = explode($this->Config()->get('separator'), $internalItemIDs);
        foreach ($array as $internalItemID) {
            $this->AddProductCodeToString($internalItemID, $write);
        }
    }

    /**
     * remove many products
     * @param \SilverStripe\ORM\SS_List $products
     * @param bool $write -should the dataobject be written?
     */
    protected function RemoveProductsFromString($products, $write = false)
    {
        foreach ($products as $product) {
            $this->RemoveProductFromString($product, $write);
        }
    }

    /**
     * add one product
     * @param bool $write -should the dataobject be written?
     */
    protected function AddProductToString(Product $product, $write = false)
    {
        $array = $this->getProductsAsArray();
        if (is_array($array) && in_array($product->InternalItemID, $array, true)) {
            return;
        }
        $array[] = $product->InternalItemID;
        $this->setProductsFromArray($array, $write);
    }

    /**
     * add one product, using InternalItemID
     * @param string $internalItemID
     * @param bool $write -should the dataobject be written?
     */
    protected function AddProductCodeToString($internalItemID, $write = false)
    {
        $array = $this->getProductsAsArray();
        if (is_array($array) && in_array($internalItemID, $array, true)) {
            return;
        }
        $array[] = $internalItemID;
        $this->setProductsFromArray($array, $write);
    }

    /**
     * remove one product
     * @param bool $write -should the dataobject be written?
     */
    protected function RemoveProductFromString(Product $product, $write = false)
    {
        $array = $this->getProductsAsArray();
        if (! in_array($product->InternalItemID, $array, true)) {
            return;
        }
        $array = array_diff($array, [$product->InternalItemID]);
        $this->setProductsFromArray($array, $write);
    }

    /**
     * @param array $array
     * @param bool $write -should the dataobject be written?
     * @return array
     */
    protected function setProductsFromArray(array $array, ?bool $write = false) : array
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

    protected function defaultTitle()
    {
        return _t(
            'CMSMain.NEWPAGE',
            'Custom Product List'
        )
        . ($this->ID ? ' ' . $this->ID : '');
    }

    protected function generateTitle()
    {
        $list = $this->Products();
        $title = $this->title;
        if (! $title) {
            $title = $list->count() ? implode('; ', $list->column('Title')) : $this->defaultTitle();
        }
        $filter = URLSegmentFilter::create();
        $title = $filter->filter($title);

        // Fallback to generic page name if path is empty (= no valid, convertable characters)
        if (! $title || $title === '-' || $title === '-1') {
            $title = $this->defaultTitle();
        }

        return $title;
    }

    /**
     * @return bool
     */
    protected function titleExists()
    {
        // Check existence
        $existingListsWithThisTitleCount = CustomProductList::get()
            ->filter(['Title' => $this->Title])
            ->exclude(['ID' => $this->ID])
            ->count();
        return (bool) $existingListsWithThisTitleCount;
    }
}

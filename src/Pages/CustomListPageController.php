<?php

namespace Sunnysideup\EcommerceCustomProductLists\Pages;
use SilverStripe\Core\Config\Config;
use Sunnysideup\Ecommerce\Pages\ProductGroupController;
use Sunnysideup\EcommerceCustomProductLists\Model\CustomProductList;

/**
 * Class \Sunnysideup\Ecommerce\Pages\ProductGroupSearchPageController
 *
 * @property \Sunnysideup\Ecommerce\Pages\ProductGroupSearchPage $dataRecord
 * @method \Sunnysideup\Ecommerce\Pages\ProductGroupSearchPage data()
 * @mixin \Sunnysideup\Ecommerce\Pages\ProductGroupSearchPage
 */
class CustomListPageController extends ProductGroupController
{
    public function getSearchFilterHeader(): string
    {
        return _t('Ecommerce.SEARCH_THIS_LIST', 'Search this list');
    }

    private static $allowed_actions = [
        'show'
    ];

    protected ?CustomProductList $customList = null;
    protected ?string $customTitle = null;

    public function show()
    {
        return $this->index();
    }
    public function ResetPreferencesLink($action = null): string
    {
        return (string) $this->customList?->Link();
    }

    protected function init()
    {
        parent::init();
        $urlSegment = $this->getRequest()->param('ID');
        $id = (int) $this->getRequest()->param('OtherID');
        if ($urlSegment) {
            $customList = CustomProductList::get()->filter(['URLSegment' => $urlSegment, 'ID' => $id])->first();

            if ($customList) {
                $this->customList = $customList;
                $this->data()->setCustomList($this->customList);
            }
        }
        if (!$this->customList) {
            return $this->httpError(404, _t('CustomListPageController.CUSTOMLISTNOTFOUND', 'Custom list not found'));
        }
    }

    protected static $finalProductListCache = null;

    public function getFinalProductList($extraFilter = null, $alternativeSort = null)
    {
        if(self::$finalProductListCache) {
            return self::$finalProductListCache;
        }
        $list = null;
        if ($this->customList) {
            $list = parent::getFinalProductList();
            $buyableClassName = Config::inst()->get($this->ClassName, 'base_buyable_class');
            $list->setProducts($buyableClassName::get()->filter(['AllowPurchase' => true]));
            if ($list) {
                $filterIds = $this->customList->getProductsAsInternalItemsArray();
                if ($filterIds) {
                    $list = $list->setExtraFilter(['InternalItemID' => $filterIds]);
                } else {
                    $this->customList = null;
                }
            } else {
                $this->customList = null;
            }
        }
        if (!$this->HasCustomList()) {
            return $this->httpError(404, _t('CustomListPageController.CUSTOMLISTNOTFOUND', 'Custom list not found'));
        }
        self::$finalProductListCache = $list;
        return $list;
    }

    public function HasCustomList(): bool
    {
        return (bool) $this->customList;
    }

    public function getTitle()
    {
        if ($this->customList) {
            if (! $this->customTitle) {
                $this->customTitle = $this->customList->Title;
                $this->data()->Title = $this->customList->Title;
                $this->data()->MetaTitle = $this->customList->MetaTitle;
            }
            if (! $this->customTitle) {
                $this->customTitle = $this->data()->Title;
            }
            return $this->customTitle;
        }
        return parent::getTitle();
    }
    public function HasManyProducts(): bool
    {
        return $this->getFinalProductList()?->getProducts()?->count() > 3;
    }


}

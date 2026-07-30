<?php

namespace Sunnysideup\EcommerceCustomProductLists\Pages;

use Sunnysideup\Ecommerce\Pages\ProductGroup;

/**
 * Class \Sunnysideup\EcommerceCustomProductLists\Pages\CustomListPage
 *
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 * @mixin \Sunnysideup\AutomatedContentManagement\Extensions\DataObjectExtensionForLLM
 * @mixin \Sunnysideup\SimpleTemplateCaching\Extensions\DataObjectExtension
 * @mixin \Sunnysideup\YesNoAnyFilter\FixBooleanSearchAsExtension
 */
class CustomListPage extends ProductGroup
{
    private static $table_name = 'CustomListPage';

    private static $description = 'This page provides a holder to show custom lists.';
    private static $icon = 'sunnysideup/ecommerce:client/images/icons/productgroupsearchpage-file.gif';
    private static $singular_name = 'Product Custom Lists Page';

    private static $plural_name = 'Product Custom Lists Pages';

    public function i18n_singular_name()
    {
        return _t('CustomListPage.SINGULARNAME', 'Product Custom Lists Page');
    }

    public function i18n_plural_name()
    {
        return _t('CustomListPage.PLURALNAME', 'Product Custom Lists Pages');
    }
    private static $allowed_children = 'none';

    private static $default_child = null;

    private static $can_be_root = true;

    private static $defaults = [
        'ShowInMenus' => false,
        'ShowInSearch' => false,
    ];

    public function canCreate($member = null, $context = [])
    {
        if (CustomListPage::get()->exists()) {
            return false;
        }
        return $this->canEdit($member);
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        return $fields;
    }

    /**
     * @var int
     */
    private static $maximum_number_of_products_to_list_for_search = 1000;

    /**
     * @var string
     */
    private static $best_match_key = 'bestmatch';


    public function getMyLevelOfProductsToShow(?int $defauult = 99): int
    {
        return 2;
    }


    public function setCustomList($customList): void
    {
        $this->customList = $customList;
    }

    protected $customList = null;


    public function Link($action = null): string
    {
        return $this->getLink($action);
    }

    public function getLink($action = null): string
    {
        if ($action) {
            return parent::Link($action);
        } elseif ($this->customList) {
            return parent::Link('show/' . $this->customList->URLSegment . '/' . $this->customList->ID);
        }
        return parent::Link();
    }
}

2020-07-06 02:44

# running php upgrade upgrade see: https://github.com/silverstripe/silverstripe-upgrader
cd /c/Users/PC/Documents/www/upgrades/ecommerce_custom_product_lists
php /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/bin/upgrade-code upgrade /c/Users/PC/Documents/www/upgrades/ecommerce_custom_product_lists/ecommerce_custom_product_lists  --root-dir=/c/Users/PC/Documents/www/upgrades/ecommerce_custom_product_lists --write -vvv
Writing changes for 2 files
Running upgrades on "/c/Users/PC/Documents/www/upgrades/ecommerce_custom_product_lists/ecommerce_custom_product_lists"
[2020-07-06 14:44:26] Applying RenameClasses to CustomProductList.php...
[2020-07-06 14:44:27] Applying ClassToTraitRule to CustomProductList.php...
[2020-07-06 14:44:27] Applying UpdateConfigClasses to config.yml...
[2020-07-06 14:44:27] Applying RenameClasses to _config.php...
[2020-07-06 14:44:27] Applying ClassToTraitRule to _config.php...
modified:	src/model/CustomProductList.php
@@ -2,17 +2,31 @@

 namespace Sunnysideup\EcommerceCustomProductLists\Model;

-use DataObject;
-use Injector;
-use LiteralField;
-use GridField;
-use GridFieldBasicPageRelationConfigNoAddExisting;
-use GridFieldBasicPageRelationConfig;
-use RequiredFields;
-use Product;
-use Config;
-use EcommerceConfig;
-use URLSegmentFilter;
+
+
+
+
+
+
+
+
+
+
+
+use Sunnysideup\Ecommerce\Pages\Product;
+use SilverStripe\Core\Injector\Injector;
+use Sunnysideup\Ecommerce\Pages\ProductGroup;
+use SilverStripe\Forms\LiteralField;
+use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldBasicPageRelationConfigNoAddExisting;
+use SilverStripe\Forms\GridField\GridField;
+use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldBasicPageRelationConfig;
+use SilverStripe\Forms\RequiredFields;
+use SilverStripe\Core\Config\Config;
+use Sunnysideup\EcommerceCustomProductLists\Model\CustomProductList;
+use Sunnysideup\Ecommerce\Config\EcommerceConfig;
+use SilverStripe\View\Parsers\URLSegmentFilter;
+use SilverStripe\ORM\DataObject;
+

 /**
  * 1. titles should not be identical
@@ -69,8 +83,8 @@
     );

     private static $many_many = array(
-        "ProductsToAdd" => "Product",
-        "ProductsToDelete" => "Product"
+        "ProductsToAdd" => Product::class,
+        "ProductsToDelete" => Product::class
     );

     private static $searchable_fields = array(
@@ -101,7 +115,7 @@
      */
     public function canDelete($member = null, $context = [])
     {
-        return $this->Locked ? false : Injector::inst()->get('ProductGroup')->canDelete($member);
+        return $this->Locked ? false : Injector::inst()->get(ProductGroup::class)->canDelete($member);
     }

     public function getCMSFields()
@@ -275,8 +289,8 @@
      */
     protected function setProductsFromArray($array, $write = false)
     {
-        $sep = Config::inst()->get('CustomProductList', 'separator');
-        $alt = Config::inst()->get('CustomProductList', 'separator_alternative');
+        $sep = Config::inst()->get(CustomProductList::class, 'separator');
+        $alt = Config::inst()->get(CustomProductList::class, 'separator_alternative');

         foreach ($array as $key => $value) {
             if ($value) {
@@ -305,7 +319,7 @@
      */
     public function getProductsAsArray()
     {
-        $sep = Config::inst()->get('CustomProductList', 'separator');
+        $sep = Config::inst()->get(CustomProductList::class, 'separator');
         $list =  explode($sep, $this->InternalItemCodeList);
         foreach ($list as $key => $code) {
             $list[$key] = trim($code);
@@ -343,7 +357,7 @@
   * EXP: Check if the class name can still be used as such
   * ### @@@@ STOP REPLACEMENT @@@@ ###
   */
-        $className = EcommerceConfig::get('ProductGroup', 'base_buyable_class');
+        $className = EcommerceConfig::get(ProductGroup::class, 'base_buyable_class');

 /**
   * ### @@@@ START REPLACEMENT @@@@ ###

Warnings for src/model/CustomProductList.php:
 - src/model/CustomProductList.php:356 PhpParser\Node\Expr\Variable
 - WARNING: New class instantiated by a dynamic value on line 356

modified:	_config/config.yml
@@ -6,8 +6,7 @@
   - 'cms/*'
   - 'ecommerce/*'
 ---
+Sunnysideup\Ecommerce\Cms\ProductConfigModelAdmin:
+  managed_models:
+    - Sunnysideup\EcommerceCustomProductLists\Model\CustomProductList

-ProductConfigModelAdmin:
-  managed_models:
-    - CustomProductList
-

Writing changes for 2 files
✔✔✔
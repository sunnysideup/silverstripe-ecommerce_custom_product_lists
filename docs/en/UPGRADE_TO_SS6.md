# Upgrade Guide to Silverstripe 6

This document outlines the necessary steps to upgrade your project to be compatible with `sunnysideup/ecommerce_custom_product_lists` for Silverstripe CMS 6.

## ⚠️ BREAKING CHANGE: Core Dependencies

Your project's `composer.json` must be updated to meet new minimum requirements.

- **`sunnysideup/ecommerce`**: Now requires version `^33.0` (previously `5.x-dev`).
- **`silverstripe/recipe-cms`**: Now requires version `^6.0` (previously `^4.0 || ^5.0`).

```json
"require": {
    "sunnysideup/ecommerce": "^33.0",
    "silverstripe/recipe-cms": "^6.0"
}
```

## ⚠️ BREAKING CHANGE: Configuration

Configuration for database class name remapping has been updated.

- In your YAML configuration, replace the deprecated `SilverStripe\ORM\DatabaseAdmin` with `SilverStripe\Dev\DbBuild`.

**Before:**
```yaml
SilverStripe\ORM\DatabaseAdmin:
  classname_value_remapping:
    # ...
```

**After:**
```yaml
SilverStripe\Dev\DbBuild:
  classname_value_remapping:
    # ...
```

## ⚠️ BREAKING CHANGE: API Updates

Several classes and method signatures have been updated.

### Validation

- The deprecated `SilverStripe\Forms\RequiredFields` has been replaced with `SilverStripe\Forms\Validation\RequiredFieldsValidator`. Update your `getCMSValidator()` methods accordingly.

**Before:**
```php
use SilverStripe\Forms\RequiredFields;

public function getCMSValidator()
{
    return RequiredFields::create('Title');
}
```

**After:**
```php
use SilverStripe\Forms\Validation\RequiredFieldsValidator;

public function getCMSValidator()
{
    return RequiredFieldsValidator::create('Title');
}
```

### Namespace Imports

- The `SilverStripe\ORM\ArrayList` class has been moved. Update the import to `SilverStripe\Model\List\ArrayList`.

**Before:**
```php
use SilverStripe\ORM\ArrayList;
```

**After:**
```php
use SilverStripe\Model\List\ArrayList;
```

### Method Overrides

- The native PHP `#[Override]` attribute has been added to all methods that extend a parent class method (e.g., `getCMSFields`, `onBeforeWrite`, `canDelete`). Ensure you add `use Override;` where this attribute is used if you have extended these classes.

## ⚠️ BREAKING CHANGE: Build Tasks to Console Commands

The `BuildTask` for running custom product list actions has been refactored into a Silverstripe Console Command.

- **Class**: `Sunnysideup\EcommerceCustomProductLists\Tasks\RunCustomProductListActions`
- **Command**: `run-custom-product-list-actions`

The `run()` method has been replaced by `execute()` with a different signature. Direct calls to `run()` will fail. Use the console command runner instead. Verbose output is now controlled via a command-line option (`-v`).

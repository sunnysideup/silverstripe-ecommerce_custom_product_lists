2020-07-06 02:44

# running php upgrade inspect see: https://github.com/silverstripe/silverstripe-upgrader
cd /c/Users/PC/Documents/www/upgrades/ecommerce_custom_product_lists
php /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/bin/upgrade-code inspect /c/Users/PC/Documents/www/upgrades/ecommerce_custom_product_lists/ecommerce_custom_product_lists/src  --root-dir=/c/Users/PC/Documents/www/upgrades/ecommerce_custom_product_lists --write -vvv
Array
(
    [0] => Running post-upgrade on "/c/Users/PC/Documents/www/upgrades/ecommerce_custom_product_lists/ecommerce_custom_product_lists/src"
    [1] => [2020-07-06 14:44:53] Applying ApiChangeWarningsRule to CustomProductList.php...
    [2] => PHP Fatal error:  Cannot declare class Sunnysideup\EcommerceCustomProductLists\Model\CustomProductList, because the name is already in use in /c/Users/PC/Documents/www/upgrades/ecommerce_custom_product_lists/ecommerce_custom_product_lists/src/model/CustomProductList.php on line 39
    [3] => PHP Stack trace:
    [4] => PHP   1. {main}() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/bin/upgrade-code:0
    [5] => PHP   2. Symfony\Component\Console\Application->run() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/bin/upgrade-code:55
    [6] => PHP   3. Symfony\Component\Console\Application->doRun() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/symfony/console/Application.php:147
    [7] => PHP   4. Symfony\Component\Console\Application->doRunCommand() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/symfony/console/Application.php:271
    [8] => PHP   5. SilverStripe\Upgrader\Console\InspectCommand->run() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/symfony/console/Application.php:1000
    [9] => PHP   6. SilverStripe\Upgrader\Console\InspectCommand->execute() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/symfony/console/Command/Command.php:255
    [10] => PHP   7. SilverStripe\Upgrader\Upgrader->upgrade() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/src/Console/InspectCommand.php:88
    [11] => PHP   8. SilverStripe\Upgrader\UpgradeRule\PHP\ApiChangeWarningsRule->upgradeFile() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/src/Upgrader.php:61
    [12] => PHP   9. SilverStripe\Upgrader\UpgradeRule\PHP\ApiChangeWarningsRule->mutateSourceWithVisitors() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/src/UpgradeRule/PHP/ApiChangeWarningsRule.php:60
    [13] => PHP  10. SilverStripe\Upgrader\UpgradeRule\PHP\ApiChangeWarningsRule->transformWithVisitors() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/src/UpgradeRule/PHP/ApiChangeWarningsRule.php:88
    [14] => PHP  11. PhpParser\NodeTraverser->traverse() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/src/UpgradeRule/PHP/PHPUpgradeRule.php:28
    [15] => PHP  12. PhpParser\NodeTraverser->traverseArray() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/nikic/php-parser/lib/PhpParser/NodeTraverser.php:85
    [16] => PHP  13. SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\PHPStanScopeVisitor->enterNode() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/nikic/php-parser/lib/PhpParser/NodeTraverser.php:159
    [17] => PHP  14. PHPStan\Analyser\NodeScopeResolver->processNodes() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/src/UpgradeRule/PHP/Visitor/PHPStanScopeVisitor.php:82
    [18] => PHP  15. PHPStan\Analyser\NodeScopeResolver->processNode() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Analyser/NodeScopeResolver.php:176
    [19] => PHP  16. PHPStan\Analyser\NodeScopeResolver->processNodes() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Analyser/NodeScopeResolver.php:699
    [20] => PHP  17. PHPStan\Analyser\NodeScopeResolver->processNode() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Analyser/NodeScopeResolver.php:176
    [21] => PHP  18. PHPStan\Analyser\NodeScopeResolver->processNodes() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Analyser/NodeScopeResolver.php:699
    [22] => PHP  19. PHPStan\Analyser\NodeScopeResolver->processNode() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Analyser/NodeScopeResolver.php:176
    [23] => PHP  20. SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\PHPStanScopeVisitor->SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\{closure:/c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/src/UpgradeRule/PHP/Visitor/PHPStanScopeVisitor.php:73-82}() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Analyser/NodeScopeResolver.php:316
    [24] => PHP  21. PHPStan\Rules\Methods\ExistingClassesInTypehintsRule->processNode() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/src/UpgradeRule/PHP/Visitor/PHPStanScopeVisitor.php:80
    [25] => PHP  22. PHPStan\Rules\FunctionDefinitionCheck->checkFunction() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Rules/Methods/ExistingClassesInTypehintsRule.php:44
    [26] => PHP  23. PHPStan\Rules\FunctionDefinitionCheck->checkParametersAcceptor() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Rules/FunctionDefinitionCheck.php:81
    [27] => PHP  24. PHPStan\Broker\Broker->hasClass() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Rules/FunctionDefinitionCheck.php:159
    [28] => PHP  25. class_exists() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Broker/Broker.php:220
    [29] => PHP  26. spl_autoload_call() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Broker/Broker.php:220
    [30] => PHP  27. SilverStripe\Upgrader\Autoload\CollectionAutoloader->loadClass() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Broker/Broker.php:220
    [31] => PHP  28. SilverStripe\Upgrader\Autoload\CollectionAutoloader->loadItem() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/src/Autoload/CollectionAutoloader.php:100
    [32] => PHP  29. require_once() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/src/Autoload/CollectionAutoloader.php:159
)


------------------------------------------------------------------------
To continue, please use the following parameter: startFrom=InspectAPIChanges-1
e.g. php runme.php startFrom=InspectAPIChanges-1
------------------------------------------------------------------------
            
# running php upgrade inspect see: https://github.com/silverstripe/silverstripe-upgrader
cd /c/Users/PC/Documents/www/upgrades/ecommerce_custom_product_lists
php /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/bin/upgrade-code inspect /c/Users/PC/Documents/www/upgrades/ecommerce_custom_product_lists/ecommerce_custom_product_lists/src  --root-dir=/c/Users/PC/Documents/www/upgrades/ecommerce_custom_product_lists --write -vvv
Array
(
    [0] => Running post-upgrade on "/c/Users/PC/Documents/www/upgrades/ecommerce_custom_product_lists/ecommerce_custom_product_lists/src"
    [1] => [2020-07-06 15:00:20] Applying ApiChangeWarningsRule to CustomProductList.php...
    [2] => PHP Fatal error:  Cannot declare class Sunnysideup\EcommerceCustomProductLists\Model\CustomProductList, because the name is already in use in /c/Users/PC/Documents/www/upgrades/ecommerce_custom_product_lists/ecommerce_custom_product_lists/src/model/CustomProductList.php on line 38
    [3] => PHP Stack trace:
    [4] => PHP   1. {main}() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/bin/upgrade-code:0
    [5] => PHP   2. Symfony\Component\Console\Application->run() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/bin/upgrade-code:55
    [6] => PHP   3. Symfony\Component\Console\Application->doRun() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/symfony/console/Application.php:147
    [7] => PHP   4. Symfony\Component\Console\Application->doRunCommand() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/symfony/console/Application.php:271
    [8] => PHP   5. SilverStripe\Upgrader\Console\InspectCommand->run() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/symfony/console/Application.php:1000
    [9] => PHP   6. SilverStripe\Upgrader\Console\InspectCommand->execute() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/symfony/console/Command/Command.php:255
    [10] => PHP   7. SilverStripe\Upgrader\Upgrader->upgrade() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/src/Console/InspectCommand.php:88
    [11] => PHP   8. SilverStripe\Upgrader\UpgradeRule\PHP\ApiChangeWarningsRule->upgradeFile() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/src/Upgrader.php:61
    [12] => PHP   9. SilverStripe\Upgrader\UpgradeRule\PHP\ApiChangeWarningsRule->mutateSourceWithVisitors() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/src/UpgradeRule/PHP/ApiChangeWarningsRule.php:60
    [13] => PHP  10. SilverStripe\Upgrader\UpgradeRule\PHP\ApiChangeWarningsRule->transformWithVisitors() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/src/UpgradeRule/PHP/ApiChangeWarningsRule.php:88
    [14] => PHP  11. PhpParser\NodeTraverser->traverse() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/src/UpgradeRule/PHP/PHPUpgradeRule.php:28
    [15] => PHP  12. PhpParser\NodeTraverser->traverseArray() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/nikic/php-parser/lib/PhpParser/NodeTraverser.php:85
    [16] => PHP  13. SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\PHPStanScopeVisitor->enterNode() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/nikic/php-parser/lib/PhpParser/NodeTraverser.php:159
    [17] => PHP  14. PHPStan\Analyser\NodeScopeResolver->processNodes() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/src/UpgradeRule/PHP/Visitor/PHPStanScopeVisitor.php:82
    [18] => PHP  15. PHPStan\Analyser\NodeScopeResolver->processNode() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Analyser/NodeScopeResolver.php:176
    [19] => PHP  16. PHPStan\Analyser\NodeScopeResolver->processNodes() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Analyser/NodeScopeResolver.php:699
    [20] => PHP  17. PHPStan\Analyser\NodeScopeResolver->processNode() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Analyser/NodeScopeResolver.php:176
    [21] => PHP  18. PHPStan\Analyser\NodeScopeResolver->processNodes() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Analyser/NodeScopeResolver.php:699
    [22] => PHP  19. PHPStan\Analyser\NodeScopeResolver->processNode() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Analyser/NodeScopeResolver.php:176
    [23] => PHP  20. SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\PHPStanScopeVisitor->SilverStripe\Upgrader\UpgradeRule\PHP\Visitor\{closure:/c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/src/UpgradeRule/PHP/Visitor/PHPStanScopeVisitor.php:73-82}() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Analyser/NodeScopeResolver.php:316
    [24] => PHP  21. PHPStan\Rules\Methods\ExistingClassesInTypehintsRule->processNode() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/src/UpgradeRule/PHP/Visitor/PHPStanScopeVisitor.php:80
    [25] => PHP  22. PHPStan\Rules\FunctionDefinitionCheck->checkFunction() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Rules/Methods/ExistingClassesInTypehintsRule.php:44
    [26] => PHP  23. PHPStan\Rules\FunctionDefinitionCheck->checkParametersAcceptor() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Rules/FunctionDefinitionCheck.php:81
    [27] => PHP  24. PHPStan\Broker\Broker->hasClass() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Rules/FunctionDefinitionCheck.php:159
    [28] => PHP  25. class_exists() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Broker/Broker.php:220
    [29] => PHP  26. spl_autoload_call() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Broker/Broker.php:220
    [30] => PHP  27. SilverStripe\Upgrader\Autoload\CollectionAutoloader->loadClass() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/phpstan/phpstan/src/Broker/Broker.php:220
    [31] => PHP  28. SilverStripe\Upgrader\Autoload\CollectionAutoloader->loadItem() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/src/Autoload/CollectionAutoloader.php:100
    [32] => PHP  29. require_once() /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/src/Autoload/CollectionAutoloader.php:159
)


------------------------------------------------------------------------
To continue, please use the following parameter: startFrom=InspectAPIChanges-1
e.g. php runme.php startFrom=InspectAPIChanges-1
------------------------------------------------------------------------
            
# running php upgrade inspect see: https://github.com/silverstripe/silverstripe-upgrader
cd /c/Users/PC/Documents/www/upgrades/ecommerce_custom_product_lists
php /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/bin/upgrade-code inspect /c/Users/PC/Documents/www/upgrades/ecommerce_custom_product_lists/ecommerce_custom_product_lists/src  --root-dir=/c/Users/PC/Documents/www/upgrades/ecommerce_custom_product_lists --write -vvv
Writing changes for 0 files
Running post-upgrade on "/c/Users/PC/Documents/www/upgrades/ecommerce_custom_product_lists/ecommerce_custom_product_lists/src"
[2020-07-06 15:38:26] Applying ApiChangeWarningsRule to CustomProductList.php...
[2020-07-06 15:38:28] Applying UpdateVisibilityRule to CustomProductList.php...
Writing changes for 0 files
✔✔✔
# running php upgrade inspect see: https://github.com/silverstripe/silverstripe-upgrader
cd /c/Users/PC/Documents/www/upgrades/ecommerce_custom_product_lists
php /c/Users/PC/Documents/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/bin/upgrade-code inspect /c/Users/PC/Documents/www/upgrades/ecommerce_custom_product_lists/ecommerce_custom_product_lists/src  --root-dir=/c/Users/PC/Documents/www/upgrades/ecommerce_custom_product_lists --write -vvv
Writing changes for 0 files
Running post-upgrade on "/c/Users/PC/Documents/www/upgrades/ecommerce_custom_product_lists/ecommerce_custom_product_lists/src"
[2020-07-06 15:39:03] Applying ApiChangeWarningsRule to CustomProductList.php...
[2020-07-06 15:39:05] Applying UpdateVisibilityRule to CustomProductList.php...
Writing changes for 0 files
✔✔✔
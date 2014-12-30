composer-updates
================

Composer plugin to check for available updates and upgrades

Usage
-----

Add requirement to your composer.json
    
    composer require artursvonda/composer-updates @stable

You can add this dependency to global composer file using
    
    composer global require artursvonda/composer-updates @stable
    
To check for available updates, run following command

    composer run-script check-updates
    
To do
-----

 * Check for available updates when using source
 * Check constraints to see what's holding back newer version
 * Changelog output

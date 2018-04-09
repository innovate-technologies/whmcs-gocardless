GoCardless for WHMCS
===================
This gateway module for WHMCS allows you to take single payments (subscriptions coming soon) via direct debit powered by GoCardless.

This module was developed at SHOUTca.st on WHMCS 6, later versions should work but haven't been tested. 

## Install
1) Place the files inside WHMCS
2) Go into 'modules/gateways/gocardless/' and install the GC API using [Composer](https://getcomposer.org/) `php composer.phar install` or `composer install` (depending on setup)
3) Enable the module in WHMCS
4) Create a Read/Write Access token inside your GC dashboard
5) Create a webhook to `https://YOURSITE/modules/gateways/callback/gocardless.php` with a self-generated [random](https://random.org) secret
6) Configure the WHMCS module with those in your settings
7) Ready to get rid of your cards!


### Note
This plugin is not oficially supported/endorsed by GoCardless. 

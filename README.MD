# **!!!Deprecated!!!**

With WHMCS 7.7 having GoCardless integration we're no longer maintaining this module. WHMCS 7.7 also could break this module as there are conflicts with older dependancies used in WHMCS while newer versions are needed by the GoCardless PHP library

GoCardless for WHMCS
===================
This gateway module for WHMCS allows you to take single payments (subscriptions coming soon) via direct debit powered by GoCardless.

This module was developed at SHOUTca.st on WHMCS 6 and works with later versions (last tested on 7.5.1).

## Install
1) Place the files inside WHMCS
2) Go into 'modules/gateways/gocardless/' and install the GC API using [Composer](https://getcomposer.org/) `php composer.phar install` or `composer install` (depending on setup)
3) Enable the module in WHMCS
4) Create a Read/Write Access token inside your GC dashboard
5) Create a webhook to `https://YOURSITE/modules/gateways/callback/gocardless.php` with a self-generated [random](https://random.org) secret
6) Configure the WHMCS module with those in your settings
7) Add an admin user with username `API` for the local API requests to work.
7) Ready to get rid of your cards!


### Note
This plugin is not oficially supported/endorsed by GoCardless. 

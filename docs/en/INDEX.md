Campaign Monitor Module
================================================================================

Connects a Silverstripe Website with the Campaign Monitor
API. Here are some examples:

![Sign Up Page](/docs/screenshots/SignupForm.png)

![Quick Sign Up Form](/docs/screenshots/QuickSignupForm.png)

![Back-End](/docs/screenshots/Backend.png)


Developer
-----------------------------------------------
Nicolaas Francken [at] sunnysideup.co.nz


Requirements
-----------------------------------------------
see composer.json


Documentation
-----------------------------------------------
Please contact author for more details.

Any bug reports and/or feature requests will be
looked at in detail

We are also very happy to provide personalised support
for this module in exchange for a small donation.


Installation Instructions
-----------------------------------------------

1. Find out how to add modules to SS and add module as per usual.
   You will need to add this module using composer
   e.g. `composer require sunnysideup/campaingmonitor`

2. Review configs and add entries to app/_config/config.yml
(or similar) as necessary.
In the _config/ folder of this module
you can usually find some examples of config options (if any).


further setup
-----------------------------------------------

#### MUST DO FIRST

 * create client and list on the Campaign Monitor website.

 * set up the api details from Campaign Monitor in the _config/* files

 * create a sign up page in the CMS and link it to the list on Campaign Monitor

#### AVAILABLE FEATURES

 * The `Member` class gets a bunch of additional methods - see [`CampaignMonitorMemberDOD` Class](code/decorators/CampaignMonitorMemberDOD.php)

 * set up sign-up page for Campaign Monitor list in the CMS for one or all lists. This page has a ton of features.

 * lots of API calls can be made through an API class. This class can be used as follows (example only):

```php

    private static $api = null;

    /**
     *
     * @return CampaignMonitorAPIConnector
     */
     public function getAPI(){
       if(!self::$api) {
         self::$api = CampaignMonitorAPIConnector::create();
         self::$api->init();
       }
       return self::$api;
     }

     function doSomething(){
       $api = $this->api();
       $api->doSomething();
       $api->doSomeMore();
     }
```

A full list of api calls can be found in the CampaignMonitorAPIConnector.

 * To test the API, you can visit /create-send-test/

 * adding a quick sign-up form on all your pages:

```php
    class Page_Controller extends ContentController {

        private static $allowed_actions = array (
            "CampaignMonitorStartForm" => true
        );

        function CampaignMonitorStartForm(){
            if($this->dataRecord instanceof CampaignMonitorSignupPage) {
            }
            else {
                $page = CampaignMonitorSignupPage::get_ready_ones()->first();
                if($page) {
                    return $page->CampaignMonitorStartForm($this);
                }
            }
        }

    }

```

and add this in the your template:

```html
    $CampaignMonitorStartForm
```



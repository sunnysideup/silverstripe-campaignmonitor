Campaign Monitor Module
================================================================================

Connects a Silverstripe Website with the Campaign Monitor
API.


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

2. Review configs and add entries to mysite/_config/config.yml
(or similar) as necessary.
In the _config/ folder of this module
you can usually find some examples of config options (if any).


further setup
-----------------------------------------------

#### MUST DO FIRST

1. create client and list on the Campaign Monitor website.

2. set up the api details from Campaign Monitor in the _config/* files

3. create a sign up page in the CMS and link it to the list on Campaign Monitor

#### AVAILABLE FEATURES

1. set up sign-up page for Campaign Monitor list in the CMS for one or all lists. This page has a ton of features.

2. create "starter" form for sign-up page by calling the method

```
    CampaignMonitorSignupPage::CampaignMonitorStartForm();
```

3. lots of API calls can be made through an API class. This class can be used as follows (example only):

```
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

4. To test the API, you can visit www.mysite.com/create-send-test/



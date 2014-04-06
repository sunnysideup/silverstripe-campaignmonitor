###############################################
Campaign Monitor Module
###############################################

Connects a Silverstripe Website with the Campaign Monitor
API.

Also see: https://github.com/tractorcow/silverstripe-campaignmonitor
This is a completely separate module, but it may be better than
this one. I may look at merging the two.


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
you should to find some examples of config options (if any).


Synchronisation options
-----------------------------------------------

--- MUST DO FIRST ---
1. create list on CM
2. identify list as THE list for synchronisation
(there could be more than one list)
3. group on silverstripe that represents that list in CM

--- WHAT CAN BE DONE ---
1. subscribe: member is added to group and added to CM list
2. unsubscribe: member is removed from list and unsubscribed from CM list
3. synchronise: group membership is emptied and all members on CM list
who are also Members in SilverStripe will be added to group in SilverStripe

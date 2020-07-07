2020-07-07 12:52

# running php upgrade upgrade see: https://github.com/silverstripe/silverstripe-upgrader
cd /var/www/upgrades/ss_campaignmonitor
php /var/www/upgrades/upgrader_tool/vendor/silverstripe/upgrader/bin/upgrade-code upgrade /var/www/upgrades/ss_campaignmonitor/campaignmonitor  --root-dir=/var/www/upgrades/ss_campaignmonitor --write -vvv
Writing changes for 16 files
Running upgrades on "/var/www/upgrades/ss_campaignmonitor/campaignmonitor"
[2020-07-07 12:52:50] Applying RenameClasses to _config.php...
[2020-07-07 12:52:50] Applying ClassToTraitRule to _config.php...
[2020-07-07 12:52:50] Applying UpdateConfigClasses to config.yml...
[2020-07-07 12:52:50] Applying UpdateConfigClasses to routes.yml...
[2020-07-07 12:52:50] Applying RenameClasses to CampaignmonitorTest.php...
[2020-07-07 12:52:50] Applying ClassToTraitRule to CampaignmonitorTest.php...
[2020-07-07 12:52:50] Applying RenameClasses to CampaignMonitorSyncAllMembers.php...
[2020-07-07 12:52:50] Applying ClassToTraitRule to CampaignMonitorSyncAllMembers.php...
[2020-07-07 12:52:50] Applying RenameClasses to CampaignMonitorAddOldCampaigns.php...
[2020-07-07 12:52:50] Applying ClassToTraitRule to CampaignMonitorAddOldCampaigns.php...
[2020-07-07 12:52:50] Applying RenameClasses to CampaignMonitorAPIConnector_TestController.php...
[2020-07-07 12:52:50] Applying ClassToTraitRule to CampaignMonitorAPIConnector_TestController.php...
[2020-07-07 12:52:50] Applying RenameClasses to CampaignMonitorMemberDOD.php...
[2020-07-07 12:52:50] Applying ClassToTraitRule to CampaignMonitorMemberDOD.php...
[2020-07-07 12:52:50] Applying RenameClasses to CampaignMonitorGroupDOD.php...
[2020-07-07 12:52:50] Applying ClassToTraitRule to CampaignMonitorGroupDOD.php...
[2020-07-07 12:52:50] Applying RenameClasses to CampaignMonitorAPIConnector.php...
[2020-07-07 12:52:50] Applying ClassToTraitRule to CampaignMonitorAPIConnector.php...
[2020-07-07 12:52:50] Applying RenameClasses to CampaignMonitorCampaign.php...
[2020-07-07 12:52:50] Applying ClassToTraitRule to CampaignMonitorCampaign.php...
[2020-07-07 12:52:50] Applying RenameClasses to CampaignMonitorCampaignStyle.php...
[2020-07-07 12:52:50] Applying ClassToTraitRule to CampaignMonitorCampaignStyle.php...
[2020-07-07 12:52:50] Applying RenameClasses to CampaignMonitorSegment.php...
[2020-07-07 12:52:50] Applying ClassToTraitRule to CampaignMonitorSegment.php...
[2020-07-07 12:52:50] Applying RenameClasses to CampaignMonitorCustomField.php...
[2020-07-07 12:52:50] Applying ClassToTraitRule to CampaignMonitorCustomField.php...
[2020-07-07 12:52:50] Applying RenameClasses to CampaignMonitorSignupPage_Controller.php...
[2020-07-07 12:52:50] Applying ClassToTraitRule to CampaignMonitorSignupPage_Controller.php...
[2020-07-07 12:52:50] Applying RenameClasses to CampaignMonitorSignupPageController.php...
[2020-07-07 12:52:50] Applying ClassToTraitRule to CampaignMonitorSignupPageController.php...
[2020-07-07 12:52:50] Applying RenameClasses to CampaignMonitorSignupPage.php...
[2020-07-07 12:52:50] Applying ClassToTraitRule to CampaignMonitorSignupPage.php...
modified:	_config/config.yml
@@ -3,12 +3,9 @@
 Before: 'app/*'
 After: 'framework/*','cms/*'
 ---
-
-
 SilverStripe\Security\Member:
   extensions:
-    - CampaignMonitorMemberDOD
+    - Sunnysideup\CampaignMonitor\Decorators\CampaignMonitorMemberDOD
+Sunnysideup\CampaignMonitor\Decorators\CampaignMonitorMemberDOD:
+  campaign_monitor_signup_fieldname: CampaignMonitorSubscriptions

-CampaignMonitorMemberDOD:
-  campaign_monitor_signup_fieldname: "CampaignMonitorSubscriptions"
-

modified:	_config/routes.yml
@@ -3,5 +3,5 @@
 ---
 SilverStripe\Control\Director:
   rules:
-    'create-send-test//$Action/$ID/$OtherID': 'CampaignMonitorAPIConnector_TestController'
+    create-send-test//$Action/$ID/$OtherID: Sunnysideup\CampaignMonitor\Control\CampaignMonitorAPIConnector_TestController


modified:	tests/CampaignmonitorTest.php
@@ -1,4 +1,6 @@
 <?php
+
+use SilverStripe\Dev\SapphireTest;

 class CampaignmonitorTest extends SapphireTest
 {

modified:	src/Tasks/CampaignMonitorSyncAllMembers.php
@@ -2,13 +2,22 @@

 namespace Sunnysideup\CampaignMonitor\Tasks;

-use BuildTask;
-use DB;
-use Director;
-use Member;
-use Config;
-use CampaignMonitorAPIConnector;
+
+
+
+
+
+
 use PWUpdateGetData;
+use SilverStripe\ORM\DB;
+use SilverStripe\Control\Director;
+use SilverStripe\Security\Member;
+use SilverStripe\Core\Config\Config;
+use Sunnysideup\CampaignMonitor\Tasks\CampaignMonitorSyncAllMembers;
+use Sunnysideup\CampaignMonitor\Api\CampaignMonitorAPIConnector;
+use SilverStripe\Control\Email\Email;
+use SilverStripe\Dev\BuildTask;
+


 /**
@@ -106,7 +115,7 @@
                     } elseif (isset($this->previouslyBouncedSubscribers[$member->Email])) {
                         DB::alteration_message("deleting bounced member: ".$member->Email, "deleted");
                         if (!$this->debug) {
-                            $api->deleteSubscriber(Config::inst()->get("CampaignMonitorSyncAllMembers", "mailing_list_id"), $member->Email);
+                            $api->deleteSubscriber(Config::inst()->get(CampaignMonitorSyncAllMembers::class, "mailing_list_id"), $member->Email);
                         }
                     } else {
                         if (!isset($alreadyCompleted[$member->Email])) {
@@ -163,11 +172,11 @@
         $api = $this->getAPI();
         for ($i = 1; $i < 100; $i++) {
             $list = $api->getActiveSubscribers(
-                $listID = Config::inst()->get("CampaignMonitorSyncAllMembers", "mailing_list_id"),
+                $listID = Config::inst()->get(CampaignMonitorSyncAllMembers::class, "mailing_list_id"),
                 $daysAgo = 3650,
                 $page = $i,
                 $pageSize = 999,
-                $sortByField = "Email",
+                $sortByField = Email::class,
                 $sortDirection = "ASC"
             );
             if (isset($list->NumberOfPages) && $list->NumberOfPages) {
@@ -201,11 +210,11 @@
         $api = $this->getAPI();
         for ($i = 1; $i < 100; $i++) {
             $list = $api->getBouncedSubscribers(
-                $listID = Config::inst()->get("CampaignMonitorSyncAllMembers", "mailing_list_id"),
+                $listID = Config::inst()->get(CampaignMonitorSyncAllMembers::class, "mailing_list_id"),
                 $daysAgo = 3650,
                 $page = $i,
                 $pageSize = 999,
-                $sortByField = "Email",
+                $sortByField = Email::class,
                 $sortDirection = "ASC"
             );
             if (isset($list->NumberOfPages) && $list->NumberOfPages) {
@@ -234,11 +243,11 @@
         $api = $this->getAPI();
         for ($i = 1; $i < 100; $i++) {
             $list = $api->getUnsubscribedSubscribers(
-                $listID = Config::inst()->get("CampaignMonitorSyncAllMembers", "mailing_list_id"),
+                $listID = Config::inst()->get(CampaignMonitorSyncAllMembers::class, "mailing_list_id"),
                 $daysAgo = 3650,
                 $page = $i,
                 $pageSize = 999,
-                $sortByField = "Email",
+                $sortByField = Email::class,
                 $sortDirection = "ASC"
             );
             if (isset($list->NumberOfPages) && $list->NumberOfPages) {
@@ -277,7 +286,7 @@
                         $alreadyListed = true;
                         DB::alteration_message("".$email." is already listed");
                         foreach ($valuesArray as $key => $value) {
-                            if ($key != "Email") {
+                            if ($key != Email::class) {
                                 if (!isset($this->previouslyExported[$email][$key])) {
                                     if ($value == "tba" || $value == "No" || strlen(trim($value)) < 1) {
                                         //do nothing
@@ -304,7 +313,7 @@
                     if ($updateDetails) {
                         if (!$this->debug) {
                             $api->updateSubscriber(
-                                $listID = Config::inst()->get("CampaignMonitorSyncAllMembers", "mailing_list_id"),
+                                $listID = Config::inst()->get(CampaignMonitorSyncAllMembers::class, "mailing_list_id"),
                                 $oldEmailAddress = $email,
                                 $memberArray[$email],
                                 $finalCustomFields[$email],
@@ -325,7 +334,7 @@
                     if (count($memberArray) == count($finalCustomFields)) {
                         DB::alteration_message("<h3>adding: ".count($memberArray)." subscribers</h3>", "created");
                         if (!$this->debug) {
-                            $api->addSubscribers(Config::inst()->get("CampaignMonitorSyncAllMembers", "mailing_list_id"), $memberArray, $finalCustomFields, true, false, false);
+                            $api->addSubscribers(Config::inst()->get(CampaignMonitorSyncAllMembers::class, "mailing_list_id"), $memberArray, $finalCustomFields, true, false, false);
                         }
                     } else {
                         DB::alteration_message("Error, memberArray (".count($memberArray).") count is not the same as finalCustomFields (".count($finalCustomFields).") count.", "deleted");
@@ -336,7 +345,7 @@
                 foreach ($unsubscribeArray as $email => $member) {
                     DB::alteration_message("Now doing Blacklisting: ".$member->Email, "deleted");
                     if (!$this->debug) {
-                        $api->unsubscribeSubscriber(Config::inst()->get("CampaignMonitorSyncAllMembers", "mailing_list_id"), $member);
+                        $api->unsubscribeSubscriber(Config::inst()->get(CampaignMonitorSyncAllMembers::class, "mailing_list_id"), $member);
                     }
                 }
             } else {

modified:	src/Tasks/CampaignMonitorAddOldCampaigns.php
@@ -2,10 +2,15 @@

 namespace Sunnysideup\CampaignMonitor\Tasks;

-use BuildTask;
-use CampaignMonitorCampaign;
-use CampaignMonitorAPIConnector;
-use DB;
+
+
+
+
+use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaign;
+use Sunnysideup\CampaignMonitor\Api\CampaignMonitorAPIConnector;
+use SilverStripe\ORM\DB;
+use SilverStripe\Dev\BuildTask;
+


 class CampaignMonitorAddOldCampaigns extends BuildTask

modified:	src/Control/CampaignMonitorAPIConnector_TestController.php
@@ -2,12 +2,19 @@

 namespace Sunnysideup\CampaignMonitor\Control;

-use Controller;
-use Config;
-use Director;
-use CampaignMonitorCampaign;
-use Member;
-use CampaignMonitorAPIConnector;
+
+
+
+
+
+
+use SilverStripe\Core\Config\Config;
+use Sunnysideup\CampaignMonitor\Api\CampaignMonitorAPIConnector;
+use SilverStripe\Control\Director;
+use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaign;
+use SilverStripe\Security\Member;
+use SilverStripe\Control\Controller;
+



@@ -72,7 +79,7 @@
     protected function init()
     {
         parent::init();
-        if (!Config::inst()->get("CampaignMonitorAPIConnector", "client_id")) {
+        if (!Config::inst()->get(CampaignMonitorAPIConnector::class, "client_id")) {
             user_error("To use the campaign monitor module you must set the basic authentication credentials such as CampaignMonitorAPIConnector.client_id");
         }
         $this->egData["listTitle"] = $this->egData["listTitle"].rand(0, 999999999999);

modified:	src/Decorators/CampaignMonitorMemberDOD.php
@@ -2,15 +2,26 @@

 namespace Sunnysideup\CampaignMonitor\Decorators;

-use DataExtension;
-use CampaignMonitorAPIConnector;
-use CampaignMonitorSignupPage;
-use Config;
-use OptionsetField;
-use CompositeField;
-use CheckboxSetField;
-use ReadonlyField;
-use Group;
+
+
+
+
+
+
+
+
+
+use Sunnysideup\CampaignMonitor\Api\CampaignMonitorAPIConnector;
+use Sunnysideup\CampaignMonitor\CampaignMonitorSignupPage;
+use SilverStripe\Core\Config\Config;
+use Sunnysideup\CampaignMonitor\Decorators\CampaignMonitorMemberDOD;
+use SilverStripe\Forms\OptionsetField;
+use SilverStripe\Forms\CompositeField;
+use SilverStripe\Forms\CheckboxSetField;
+use SilverStripe\Forms\ReadonlyField;
+use SilverStripe\Security\Group;
+use SilverStripe\ORM\DataExtension;
+


 /**
@@ -76,7 +87,7 @@
         }
         $field = null;
         if (!$fieldName) {
-            $fieldName = Config::inst()->get("CampaignMonitorMemberDOD", "campaign_monitor_signup_fieldname");
+            $fieldName = Config::inst()->get(CampaignMonitorMemberDOD::class, "campaign_monitor_signup_fieldname");
         }
         $api = $this->getCMAPI();
         $currentValues = null;
@@ -99,7 +110,7 @@
                 $field = CompositeField::create($subscribeField);
                 $field->addExtraClass("CMFieldsCustomFieldsHolder");
                 //add custom fields
-                $linkedMemberFields = Config::inst()->get("CampaignMonitorMemberDOD", "custom_fields_member_field_or_method_map");
+                $linkedMemberFields = Config::inst()->get(CampaignMonitorMemberDOD::class, "custom_fields_member_field_or_method_map");
                 $customFields = $listPage->CampaignMonitorCustomFields()->filter(array("Visible" => 1));
                 foreach ($customFields as $customField) {
                     $valueSet = false;

modified:	src/Decorators/CampaignMonitorGroupDOD.php
@@ -2,8 +2,11 @@

 namespace Sunnysideup\CampaignMonitor\Decorators;

-use DataExtension;
-use CampaignMonitorSignupPage;
+
+
+use Sunnysideup\CampaignMonitor\CampaignMonitorSignupPage;
+use SilverStripe\ORM\DataExtension;
+


 /**

modified:	src/Api/CampaignMonitorAPIConnector.php
@@ -2,16 +2,22 @@

 namespace Sunnysideup\CampaignMonitor\Api;

-use ViewableData;
+
 use CS_REST_General;
 use CS_REST_Clients;
 use CS_REST_Templates;
-use SiteConfig;
+
 use CS_REST_Lists;
-use Config;
+
 use CS_REST_Campaigns;
-use Member;
+
 use CS_REST_Subscribers;
+use SilverStripe\SiteConfig\SiteConfig;
+use SilverStripe\Core\Config\Config;
+use SilverStripe\Control\Email\Email;
+use SilverStripe\Security\Member;
+use SilverStripe\View\ViewableData;
+


 /**
@@ -1095,7 +1101,7 @@

         $fromEmail = $campaignMonitorCampaign->FromEmail;
         if (!$fromEmail) {
-            $fromEmail = Config::inst()->get('Email', 'admin_email');
+            $fromEmail = Config::inst()->get(Email::class, 'admin_email');
         }

         $replyTo = $campaignMonitorCampaign->ReplyTo;

modified:	src/Model/CampaignMonitorCampaign.php
@@ -2,15 +2,28 @@

 namespace Sunnysideup\CampaignMonitor\Model;

-use DataObject;
-use CampaignMonitorSignupPage;
-use OptionsetField;
-use CheckboxSetField;
-use LiteralField;
-use Director;
-use Config;
-use Requirements;
-use CampaignMonitorAPIConnector;
+
+
+
+
+
+
+
+
+
+use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaign;
+use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaignStyle;
+use Sunnysideup\CampaignMonitor\CampaignMonitorSignupPage;
+use SilverStripe\Forms\OptionsetField;
+use SilverStripe\Forms\CheckboxSetField;
+use SilverStripe\Forms\LiteralField;
+use SilverStripe\Control\Director;
+use SilverStripe\Core\Config\Config;
+use SilverStripe\View\SSViewer;
+use SilverStripe\View\Requirements;
+use Sunnysideup\CampaignMonitor\Api\CampaignMonitorAPIConnector;
+use SilverStripe\ORM\DataObject;
+


 /**
@@ -40,7 +53,7 @@
      *
      * @var string
      */
-    private static $default_template = "CampaignMonitorCampaign";
+    private static $default_template = CampaignMonitorCampaign::class;


 /**
@@ -89,11 +102,11 @@
     );

     private static $has_one = array(
-        "CampaignMonitorCampaignStyle" => "CampaignMonitorCampaignStyle"
+        "CampaignMonitorCampaignStyle" => CampaignMonitorCampaignStyle::class
     );

     private static $many_many = array(
-        "Pages" => "CampaignMonitorSignupPage"
+        "Pages" => CampaignMonitorSignupPage::class
     );

     private static $searchable_fields = array(
@@ -228,9 +241,9 @@
                 $allCSS .= fread($cssFileHandler, filesize($cssFileLocation));
                 fclose($cssFileHandler);
             }
-            $isThemeEnabled = Config::inst()->get('SSViewer', 'theme_enabled');
+            $isThemeEnabled = Config::inst()->get(SSViewer::class, 'theme_enabled');
             if (!$isThemeEnabled) {
-                Config::modify()->update('SSViewer', 'theme_enabled', true);
+                Config::modify()->update(SSViewer::class, 'theme_enabled', true);
             }
             Requirements::clear();
             $templateName = $this->getRenderWithTemplate();
@@ -245,7 +258,7 @@
   */
             $html = $this->RenderWith($templateName);
             if (!$isThemeEnabled) {
-                Config::modify()->update('SSViewer', 'theme_enabled', false);
+                Config::modify()->update(SSViewer::class, 'theme_enabled', false);
             }
             $emogrifier = new \Pelago\Emogrifier($html, $allCSS);
             $addMediaTypes = $this->Config()->get("emogrifier_add_allowed_media_types");
@@ -305,7 +318,7 @@
   * EXP: Check that the template location is still valid!
   * ### @@@@ STOP REPLACEMENT @@@@ ###
   */
-        return $this->RenderWith("CampaignMonitorCampaign");
+        return $this->RenderWith(CampaignMonitorCampaign::class);
     }

     protected $countOfWrites = 0;

modified:	src/Model/CampaignMonitorCampaignStyle.php
@@ -2,12 +2,19 @@

 namespace Sunnysideup\CampaignMonitor\Model;

-use DataObject;
-use TextField;
-use ReadonlyField;
-use Director;
+
+
+
+
 use DOMDocument;
-use SS_FileFinder;
+
+use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaign;
+use SilverStripe\Forms\TextField;
+use SilverStripe\Forms\ReadonlyField;
+use SilverStripe\Control\Director;
+use SilverStripe\Assets\FileFinder;
+use SilverStripe\ORM\DataObject;
+


 /**
@@ -29,7 +36,7 @@
     );

     private static $has_many = array(
-        "CampaignMonitorCampaigns" => "CampaignMonitorCampaign"
+        "CampaignMonitorCampaigns" => CampaignMonitorCampaign::class
     );

     private static $searchable_fields = array(
@@ -44,7 +51,7 @@

     private static $plural_name = "Campaign Templates";

-    private static $default_template = "CampaignMonitorCampaign";
+    private static $default_template = CampaignMonitorCampaign::class;

     public function getCMSFields()
     {
@@ -122,7 +129,7 @@
     public function getFileLocation()
     {
         if (!$this->TemplateName) {
-            $this->TemplateName = "CampaignMonitorCampaign";
+            $this->TemplateName = CampaignMonitorCampaign::class;
         }
         foreach ($this->getFoldersToSearch() as $folder) {
             $fileLocation = $folder.$this->TemplateName.".ss";
@@ -180,7 +187,7 @@
         parent::requireDefaultRecords();
         $templates = [];
         foreach ($this->getFoldersToSearch() as $folder) {
-            $finder = new SS_FileFinder();
+            $finder = new FileFinder();
             $finder->setOption('name_regex', '/^.*\.ss$/');
             $found = $finder->find($folder);
             foreach ($found as $key => $value) {
@@ -205,7 +212,7 @@
     public function onBeforeWrite()
     {
         parent::onBeforeWrite();
-        if ($this->TemplateName == "CampaignMonitorCampaign") {
+        if ($this->TemplateName == CampaignMonitorCampaign::class) {
             $this->Title = "Default Template";
         }
     }

modified:	src/Model/CampaignMonitorSegment.php
@@ -2,7 +2,10 @@

 namespace Sunnysideup\CampaignMonitor\Model;

-use DataObject;
+
+use Sunnysideup\CampaignMonitor\CampaignMonitorSignupPage;
+use SilverStripe\ORM\DataObject;
+


 /**
@@ -30,7 +33,7 @@
     );

     private static $has_one = array(
-        "CampaignMonitorSignupPage" => "CampaignMonitorSignupPage"
+        "CampaignMonitorSignupPage" => CampaignMonitorSignupPage::class
     );



modified:	src/Model/CampaignMonitorCustomField.php
@@ -2,8 +2,16 @@

 namespace Sunnysideup\CampaignMonitor\Model;

-use DataObject;
-use CampaignMonitorSignupPage;
+
+
+use Sunnysideup\CampaignMonitor\CampaignMonitorSignupPage;
+use SilverStripe\Forms\OptionsetField;
+use SilverStripe\Forms\TextField;
+use SilverStripe\Forms\NumericField;
+use SilverStripe\Forms\CheckboxSetField;
+use SilverStripe\Forms\DateField;
+use SilverStripe\ORM\DataObject;
+


 /**
@@ -39,7 +47,7 @@
     );

     private static $has_one = array(
-        "CampaignMonitorSignupPage" => "CampaignMonitorSignupPage"
+        "CampaignMonitorSignupPage" => CampaignMonitorSignupPage::class
     );

     private static $default_sort = array(
@@ -51,11 +59,11 @@
      * @return array
      */
     private static $field_translator = array(
-        "MultiSelectOne" => "OptionsetField",
-        "Text" => "TextField",
-        "Number" => "NumericField",
-        "MultiSelectMany" => "CheckboxSetField",
-        "Date" => "DateField"
+        "MultiSelectOne" => OptionsetField::class,
+        "Text" => TextField::class,
+        "Number" => NumericField::class,
+        "MultiSelectMany" => CheckboxSetField::class,
+        "Date" => DateField::class
     );



Warnings for src/Model/CampaignMonitorCustomField.php:
 - src/Model/CampaignMonitorCustomField.php:149 PhpParser\Node\Expr\Variable
 - WARNING: New class instantiated by a dynamic value on line 149

modified:	src/CampaignMonitorSignupPage_Controller.php
@@ -3,23 +3,42 @@
 namespace Sunnysideup\CampaignMonitor;

 use PageController;
-use Requirements;
-use Member;
-use ReadonlyField;
-use EmailField;
-use FieldList;
-use FormAction;
-use RequiredFields;
-use Form;
-use Convert;
-use Security;
-use SS_HTTPRequest;
-use Director;
-use Controller;
-use CampaignMonitorCampaign;
-use HTTP;
-use Permission;
-use DB;
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
+
+
+
+
+
+
+use SilverStripe\View\Requirements;
+use SilverStripe\Security\Member;
+use SilverStripe\Control\Email\Email;
+use SilverStripe\Forms\ReadonlyField;
+use SilverStripe\Forms\EmailField;
+use SilverStripe\Forms\FieldList;
+use SilverStripe\Forms\FormAction;
+use SilverStripe\Forms\RequiredFields;
+use SilverStripe\Forms\Form;
+use SilverStripe\Core\Convert;
+use SilverStripe\Security\Security;
+use SilverStripe\Control\HTTPRequest;
+use SilverStripe\Control\Director;
+use SilverStripe\Control\Controller;
+use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaign;
+use SilverStripe\Control\HTTP;
+use SilverStripe\Security\Permission;
+use SilverStripe\ORM\DB;
+



@@ -113,11 +132,11 @@
                 $this->email = $member->Email;
                 if ($this->email) {
                     $emailRequired = false;
-                    $emailField = new ReadonlyField('CampaignMonitorEmail', _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL", 'Email'), $this->email);
+                    $emailField = new ReadonlyField('CampaignMonitorEmail', _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL", Email::class), $this->email);
                 }
             }
             if (!$emailField) {
-                $emailField = new EmailField('CampaignMonitorEmail', _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL", 'Email'), $this->email);
+                $emailField = new EmailField('CampaignMonitorEmail', _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL", Email::class), $this->email);
             }
             if ($this->ShowAllNewsletterForSigningUp) {
                 $signupField = $member->getCampaignMonitorSignupField(null, "SubscribeManyChoices");
@@ -309,7 +328,7 @@
      * action
      * @param HTTPRequest
      */
-    public function preloademail(SS_HTTPRequest $request)
+    public function preloademail(HTTPRequest $request)
     {
         $data = $request->requestVars();
         if (isset($data["CampaignMonitorEmail"])) {

modified:	src/CampaignMonitorSignupPageController.php
@@ -3,23 +3,42 @@
 namespace Sunnysideup\CampaignMonitor;

 use PageController;
-use Requirements;
-use Member;
-use ReadonlyField;
-use EmailField;
-use FieldList;
-use FormAction;
-use RequiredFields;
-use Form;
-use Convert;
-use Security;
-use SS_HTTPRequest;
-use Director;
-use Controller;
-use CampaignMonitorCampaign;
-use HTTP;
-use Permission;
-use DB;
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
+
+
+
+
+
+
+use SilverStripe\View\Requirements;
+use SilverStripe\Security\Member;
+use SilverStripe\Control\Email\Email;
+use SilverStripe\Forms\ReadonlyField;
+use SilverStripe\Forms\EmailField;
+use SilverStripe\Forms\FieldList;
+use SilverStripe\Forms\FormAction;
+use SilverStripe\Forms\RequiredFields;
+use SilverStripe\Forms\Form;
+use SilverStripe\Core\Convert;
+use SilverStripe\Security\Security;
+use SilverStripe\Control\HTTPRequest;
+use SilverStripe\Control\Director;
+use SilverStripe\Control\Controller;
+use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaign;
+use SilverStripe\Control\HTTP;
+use SilverStripe\Security\Permission;
+use SilverStripe\ORM\DB;
+



@@ -113,11 +132,11 @@
                 $this->email = $member->Email;
                 if ($this->email) {
                     $emailRequired = false;
-                    $emailField = new ReadonlyField('CampaignMonitorEmail', _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL", 'Email'), $this->email);
+                    $emailField = new ReadonlyField('CampaignMonitorEmail', _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL", Email::class), $this->email);
                 }
             }
             if (!$emailField) {
-                $emailField = new EmailField('CampaignMonitorEmail', _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL", 'Email'), $this->email);
+                $emailField = new EmailField('CampaignMonitorEmail', _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL", Email::class), $this->email);
             }
             if ($this->ShowAllNewsletterForSigningUp) {
                 $signupField = $member->getCampaignMonitorSignupField(null, "SubscribeManyChoices");
@@ -309,7 +328,7 @@
      * action
      * @param HTTPRequest
      */
-    public function preloademail(SS_HTTPRequest $request)
+    public function preloademail(HTTPRequest $request)
     {
         $data = $request->requestVars();
         if (isset($data["CampaignMonitorEmail"])) {

modified:	src/CampaignMonitorSignupPage.php
@@ -3,37 +3,71 @@
 namespace Sunnysideup\CampaignMonitor;

 use Page;
-use Injector;
-use CampaignMonitorCampaign;
-use GridFieldConfig_RelationEditor;
-use GridField;
-use HiddenField;
-use CampaignMonitorCampaignStyle;
-use GridFieldConfig_RecordEditor;
-use TabSet;
-use Tab;
-use TextField;
-use HTMLEditorField;
-use LiteralField;
-use Config;
-use DropdownField;
-use CheckboxField;
-use CheckboxSetField;
-use GridFieldConfig_RecordViewer;
-use CampaignMonitorAPIConnector;
-use Controller;
-use Requirements;
-use FieldList;
-use EmailField;
-use FormAction;
-use Form;
-use Convert;
-use Member;
-use Group;
-use CampaignMonitorSegment;
-use CampaignMonitorCustomField;
-use CampaignMonitorAddOldCampaigns;
-use DB;
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
+
+
+
+
+
+
+
+
+
+use SilverStripe\Security\Group;
+use Sunnysideup\CampaignMonitor\Model\CampaignMonitorSegment;
+use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCustomField;
+use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaign;
+use SilverStripe\Core\Injector\Injector;
+use Sunnysideup\CampaignMonitor\Control\CampaignMonitorAPIConnector_TestController;
+use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
+use SilverStripe\Forms\GridField\GridField;
+use SilverStripe\Forms\HiddenField;
+use Sunnysideup\CampaignMonitor\Model\CampaignMonitorCampaignStyle;
+use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
+use SilverStripe\Forms\TextField;
+use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
+use SilverStripe\Forms\Tab;
+use SilverStripe\Forms\TabSet;
+use SilverStripe\Core\Config\Config;
+use Sunnysideup\CampaignMonitor\Api\CampaignMonitorAPIConnector;
+use SilverStripe\Forms\LiteralField;
+use SilverStripe\Forms\DropdownField;
+use SilverStripe\Forms\CheckboxField;
+use SilverStripe\Forms\CheckboxSetField;
+use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
+use SilverStripe\Control\Controller;
+use SilverStripe\View\Requirements;
+use SilverStripe\Control\Email\Email;
+use SilverStripe\Forms\EmailField;
+use SilverStripe\Forms\FieldList;
+use SilverStripe\Forms\FormAction;
+use SilverStripe\Forms\Form;
+use SilverStripe\Core\Convert;
+use SilverStripe\Security\Member;
+use Sunnysideup\CampaignMonitor\Tasks\CampaignMonitorAddOldCampaigns;
+use SilverStripe\ORM\DB;
+


 /**
@@ -119,7 +153,7 @@
      * @inherited
      */
     private static $has_one = array(
-        "Group" => "Group"
+        "Group" => Group::class
     );

     /**
@@ -127,8 +161,8 @@
      * @inherited
      */
     private static $has_many = array(
-        "CampaignMonitorSegments" => "CampaignMonitorSegment",
-        "CampaignMonitorCustomFields" => "CampaignMonitorCustomField"
+        "CampaignMonitorSegments" => CampaignMonitorSegment::class,
+        "CampaignMonitorCustomFields" => CampaignMonitorCustomField::class
     );

     /**
@@ -136,7 +170,7 @@
      * @inherited
      */
     private static $belongs_many_many = array(
-        "CampaignMonitorCampaigns" => "CampaignMonitorCampaign"
+        "CampaignMonitorCampaigns" => CampaignMonitorCampaign::class
     );

     /**
@@ -180,7 +214,7 @@
         } else {
             $groupLink = '<p>No Group has been selected yet.</p>';
         }
-        $testControllerLink = Injector::inst()->get("CampaignMonitorAPIConnector_TestController")->Link();
+        $testControllerLink = Injector::inst()->get(CampaignMonitorAPIConnector_TestController::class)->Link();
         $campaignExample = CampaignMonitorCampaign::get()->Last();
         $campaignExampleLink = $this->Link();
         if ($campaignExample) {
@@ -224,7 +258,7 @@
                 'Options',
                 new Tab(
                     'MainSettings',
-                    new LiteralField('CreateNewCampaign', '<p>To create a new mail out go to <a href="'. Config::inst()->get("CampaignMonitorAPIConnector", "campaign_monitor_url") .'">Campaign Monitor</a> site.</p>'),
+                    new LiteralField('CreateNewCampaign', '<p>To create a new mail out go to <a href="'. Config::inst()->get(CampaignMonitorAPIConnector::class, "campaign_monitor_url") .'">Campaign Monitor</a> site.</p>'),
                     new LiteralField('ListIDExplanation', '<p>Each sign-up page needs to be associated with a campaign monitor subscription list.</p>'),
                     new DropdownField('ListID', 'Related List from Campaign Monitor (*)', array(0 => "-- please select --") + $this->makeDropdownListFromLists()),
                     new CheckboxField('ShowAllNewsletterForSigningUp', 'Allow users to sign up to all lists')
@@ -359,7 +393,7 @@
                 //user_error("You first need to setup a Campaign Monitor Page for this function to work.", E_USER_NOTICE);
                 return false;
             }
-            $fields = new FieldList(new EmailField("CampaignMonitorEmail", _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL", "Email")));
+            $fields = new FieldList(new EmailField("CampaignMonitorEmail", _t("CAMPAIGNMONITORSIGNUPPAGE.EMAIL", Email::class)));
             $actions = new FieldList(new FormAction("campaignmonitorstarterformstartaction", $this->SignUpButtonLabel));
             $form = new Form(
                 $controller,

Writing changes for 16 files
✔✔✔
<?php

/**
 * @author nicolaas [at] sunnysideup.co.nz
 *
 **/

class CampaignMonitorGroupDOD extends DataExtension {

	/**
	 * Is this a group for newsletter signing up.
	 * @return Boolean
	 */
	function IsCampaignMonitorSubscriberGroup() {
		return CampaignMonitorSignupPage::get()->filter(array("GroupID" => $this->owner->ID))->count() ? true : false;
	}

}

<% if HasMessage %>
	<% if ShowThankYouMessage %>
<div id="CampaignMonitorSignupPageThankYou" class="CampaignMonitorSignupPageDiv">
	$ThankYouMessage
</div>
	<% else %>
		<% if ShowSadToSeeYouGoMessage %>
<div id="CampaignMonitorSignupPageSadToSeeYouGo" class="CampaignMonitorSignupPageDiv">
	$SadToSeeYouGoMessage
</div>
		<% else %>
	<p>An error occured</p>
		<% end_if %>
	<% end_if %>
<% else %>
	<div id="CampaignMonitorSignupPageFormHMTL" class="CampaignMonitorSignupPageDiv">
		$FormHTML
	</div>
<% end_if %>
<% if CampaignMonitorCampaigns %>
<h2>Previous Campaigns</h2>
<ul>
<% control CampaignMonitorCampaigns %><li>$SentDate, $Subject</li><% end_control %>
</ul>
<% end_if %>

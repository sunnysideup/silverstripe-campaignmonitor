<h1 class="pageTitle">$Title</h1>
<div class="mainMessage">$Content</div>
<div id="SignupForm">$SignupForm</div>
<div id="CampaignMonitorCampaigns">
<% if HasCampaign %>
	<% with Campaign %>
	<iframe src="$WebVersionURL" seamless="seamless" name="CampaignMonitorCampaign"></iframe>
	<% end_with %>
<% end_if %>

<% if CampaignStats %>
<hr />
$CampaignStats
<hr />
<% end_if %>

<% if PreviousCampaignMonitorCampaigns %>
	<h2>Previous Messages</h2>
	<ul>
	<% loop PreviousCampaignMonitorCampaigns %><li>$SentDate.Nice, $Subject</li><% end_loop %>
	</ul>
<% end_if %>
</div>

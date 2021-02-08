<h1 class="pageTitle">$Title</h1>
<div class="mainMessage">$Content</div>
<div id="SignupForm">$SignupForm</div>

<% if $CurrentMember %><p><a href="Security/logout/">Log out (sign up as someone else).</a></p><% end_if %>


<div id="CampaignMonitorCampaigns">
<% if HasCampaign %>
    <% with Campaign %>
    <iframe src="$WebVersionURL" seamless="seamless" name="CampaignMonitorCampaign" width="100%" height="900"></iframe>
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
    <% loop PreviousCampaignMonitorCampaigns %><li><a href="$Link">$SentDate.Nice, $Subject</a> - <a href="$WebVersionURL">view online</a></li><% end_loop %>
    </ul>
<% end_if %>
</div>

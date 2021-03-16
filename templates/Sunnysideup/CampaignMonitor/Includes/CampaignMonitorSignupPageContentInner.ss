$Content
<% if $ShowForm %>
<div id="SignupForm">$SignupForm</div>
<% end_if %>

<% if $CurrentMember %><p class="sign-out-link"><a href="Security/logout/?BackURL=$Link">Sign up as someone else</a></p><% end_if %>

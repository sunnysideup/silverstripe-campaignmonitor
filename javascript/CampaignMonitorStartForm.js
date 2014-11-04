jQuery(document).ready(
	function() {
		CampaignMonitorStartForm.init();
	}
);


var CampaignMonitorStartForm = {

	formSelector: "Form_CampaignMonitorStarterForm",

	errorMessage: "Sorry, an error occurred. Please try again later. ",

	init:function(){
		// Attach a submit handler to the form
		jQuery( "#" + this.formSelector).submit(
			function( event ) {
				// Stop form from submitting normally
				event.preventDefault();
				alert("DDD");
				// Get some values from elements on the page:
				var $form = jQuery( this ),
					securityID = $form.find( "input[name='SecurityID']" ).val(),
					email = $form.find( "input[name='Email']" ).val(),
					url = $form.attr( "action" );
				// Send the data using post
				var posting = jQuery.post( url, { SecurityID: securityID, Email: email } )
				// Put the results in a div
				posting.done(
					function( data ) {
						jQuery( "#" + CampaignMonitorStartForm.formSelector ).parent().empty().append( data );
					}
				)
				.fail(
					function() {
						alert( CampaignMonitorStartForm.errorMessage );
					}
				);
			}
		);
	}

}

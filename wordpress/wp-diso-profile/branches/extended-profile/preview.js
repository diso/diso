function preview_hcard() {
	var template = '<div class="vcard hcard-profile">';
	if(jQuery('#photo').val()) template += '<img class="photo" alt="photo" src="'+jQuery('#photo').val()+'" />\n';
	template += '<h2 class="fn">'+jQuery('#display_name').val()+'</h2>';
	if( jQuery('#first_name').val() || jQuery('#additional-name').val() || jQuery('#last_name').val() ) {
		if(jQuery('#url').val())
			template += '<a class="url uid" rel="me" href="'+jQuery('#url').val()+'">';
		else
			template += '<span class="n">';
		if(jQuery('#last_name').val()) template += '<span class="family-name">'+jQuery('#last_name').val()+'</span>,\n';
		if(jQuery('#first_name').val()) template += '<span class="given-name">'+jQuery('#first_name').val()+'</span>\n';
		if(jQuery('#additional-name').val()) template += '<span class="additional-name">'+jQuery('#additional-name').val()+'</span>\n';
		if(jQuery('#url').val())
			template += '</a>';
		else
			template += '</span>';
	}//end if name
	if(jQuery('#nickname').val()) template += '"<span class="nickname">'+jQuery('#nickname').val()+'</span>"\n';
	if(jQuery('#org').val()) template += '(<span class="org">'+jQuery('#org').val()+'</span>)\n';
	if(jQuery('#description').val()) template += '<p class="note">'+jQuery('#description').val()+'</p>\n';
	
	template += '<h3>Contact Information</h3>';
	template += '<dl class="contact">';
	if(jQuery('#urls').val()) {
		var urls = jQuery('#urls').val().split(/[\s]+/);
		template += '<dt>On the web:</dt> <dd> <ul>';
		for(var i in urls)
			template += '<li><a class="url" rel="me" href="'+urls[i]+'">'+urls[i]+'</a></li>';
		template += '</ul> </dd>\n';
	}//end if urls
	if(jQuery('#aim').val()) template += '<dt>AIM:</dt> <dd><a class="url" href="aim:goim?screenname='+jQuery('#aim').val()+'">'+jQuery('#aim').val()+'</a></dd>\n';
	if(jQuery('#yim').val()) template += '<dt>Y!IM:</dt> <dd><a class="url" href="ymsgr:sendIM?'+jQuery('#yim').val()+'">'+jQuery('#yim').val()+'</a></dd>\n';
	if(jQuery('#jabber').val()) template += '<dt>Jabber:</dt> <dd><a class="url" href="xmpp:'+jQuery('#jabber').val()+'">'+jQuery('#jabber').val()+'</a></dd>\n';
	if(jQuery('#email').val()) template += '<dt>Email:</dt> <dd><a class="email" href="mailto:'+jQuery('#email').val()+'">'+jQuery('#email').val()+'</a></dd>\n';
	if(jQuery('#tel').val()) template += '<dt>Telephone:</dt> <dd class="tel">'+jQuery('#tel').val()+'</dd>\n';
	if( jQuery('#streetaddress').val() || jQuery('#locality').val() || jQuery('#region').val() || jQuery('#postalcode').val() || jQuery('#countryname').val() ) {
		template += '<dt>Current Address:</dt> <dd class="adr">';
		if(jQuery('#streetaddress').val()) template += '<div class="street-address">'+jQuery('#streetaddress').val()+'</div>\n';
		if(jQuery('#locality').val()) template += '<span class="locality">'+jQuery('#locality').val()+'</span>,\n';
		if(jQuery('#region').val()) template += '<span class="region">'+jQuery('#region').val()+'</span>\n';
		if(jQuery('#postalcode').val()) template += '<div class="postal-code">'+jQuery('#postalcode').val()+'</div>\n';
		if(jQuery('#countryname').val()) template += '<div class="country-name">'+jQuery('#countryname').val()+'</div>\n';
		template += '</dd>';
	}//end if adr
	template += '</dl>';
	template += '</div>';

	jQuery('#hcard-preview').html(template);
}//end preview_hcard

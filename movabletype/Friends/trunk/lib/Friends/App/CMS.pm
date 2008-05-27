package Friends::App::CMS;

use strict;
use base qw( MT::App );

#use CGI::Carp 'fatalsToBrowser';
use JSON;
use Data::Dumper qw(Dumper);
use Web::Scraper;
use URI;

our $LOGTYPE = 'log4mt';    # 'mt'
our $logger;

sub _log_params {
	my $app = shift;
	foreach my $p ($app->param) {
		_log ($p . ": " . $app->param($p));
	}
}

sub _log {
    my $msg = shift;
    if ( $LOGTYPE eq 'log4mt' ) { # && MT->component('log4mt')) {
        if ( !$logger ) {
            $logger = MT::Log->get_logger();
        }
        $logger->debug($msg);
    }
    else {
        MT->log($msg);
   	}
}

sub _permission_check {
    my $app = MT->instance;
    return ( $app->user
          && $app->user->blog_perm( $app->param('blog_id') )
          ->can_edit_templates );
}

sub users_content_nav {
    my ( $cb, $app, $html_ref ) = @_;

    $$html_ref =~ s{class=["']active["']}{}xmsg
      if $app->mode eq 'list_friends' || $app->mode eq 'discover_friends';

    $$html_ref =~
      m{ "> ((?:<b>)?) <__trans \s phrase="Permissions"> ((?:</b>)?) </a> }xms;
    my ( $open_bold, $close_bold ) = ( $1, $2 );

    my $html = <<"EOF";
    <mt:if var="USER_VIEW">
		<li<mt:if name="list_friends"> class="active"</mt:if>><a href="<mt:var name="SCRIPT_URL">?__mode=list_friends&amp;id=<mt:var name="EDIT_AUTHOR_ID">">$open_bold<__trans phrase="Friends">$close_bold</a></li>
    	<!--li<mt:if name="discover_friends"> class="active"</mt:if>><a href="<mt:var name="SCRIPT_URL">?__mode=discover_friends&amp;id=<mt:var name="EDIT_AUTHOR_ID">">$open_bold<__trans phrase="Import Friends">$close_bold</a></li-->
	</mt:if>
    <mt:if var="edit_author">
        <li<mt:if name="list_friends"> class="active"</mt:if>><a href="<mt:var name="SCRIPT_URL">?__mode=list_friends&amp;id=<mt:var name="id">">$open_bold<__trans phrase="Friends">$close_bold</a></li>
		<!--li<mt:if name="discover_friends"> class="active"</mt:if>><a href="<mt:var name="SCRIPT_URL">?__mode=discover_friends&amp;id=<mt:var name="id">">$open_bold<__trans phrase="Discover Friends">$close_bold</a></li-->
    </mt:if>
EOF

    $$html_ref =~ s{(?=</ul>)}{$html}xmsg;
}

sub itemset_hide_friends {
    my ($app) = @_;
    $app->validate_magic or return;

    my @friends = $app->param('id');

    for my $friend_id (@friends) {
        my $friend = MT->model('friend')->load($friend_id)
          or next;
        next
          if $app->user->id != $friend->author_id
              && !$app->user->is_superuser();
        $friend->visible(0);
        $friend->save;
    }

    $app->add_return_arg( hidden => 1 );
    $app->call_return;
}

sub itemset_show_friends {
    my ($app) = @_;
    $app->validate_magic or return;

    my @friends = $app->param('id');

    for my $friend_id (@friends) {
        my $friend = MT->model('friend')->load($friend_id)
          or next;
        next
          if $app->user->id != $friend->author_id
              && !$app->user->is_superuser();
        $friend->visible(1);
        $friend->save;
    }

    $app->add_return_arg( shown => 1 );
    $app->call_return;
}

sub itemset_delete_friends {
    my ($app) = @_;
    $app->validate_magic or return;

    my @friends = $app->param('id');

    for my $friend_id (@friends) {
        my $friend = MT->model('friend')->load($friend_id)
          or next;
        next
          if $app->user->id != $friend->author_id
              && !$app->user->is_superuser();
        $friend->remove();
    }
    $app->call_return;
}

sub _itemset_hide_show_all_friends {
    my ( $app, $new_visible ) = @_;
    $app->validate_magic or return;
    my $friend_class = MT->model('friend');

    # Really we should work directly from the selected author ID, but as an
    # itemset event we only got some friend IDs. So use its.
    my ($friend_id) = $app->param('id');
    my $friend = $friend_class->load($friend_id)
      or return $app->error(
        $app->translate( 'No such friend [_1]', $friend_id ) );

    my $author_id = $friend->author_id;
    return $app->error('Not permitted to modify')
      if $author_id != $app->user->id && !$app->is_superuser();

    my $driver = $friend_class->driver;
    my $stmt   = $driver->prepare_statement(
        $friend_class,
        {

            # TODO: include filter value when we have filters
            author_id => $author_id,
            visible   => $new_visible ? 0 : 1,
        }
    );

    my $sql =
        "UPDATE "
      . $driver->table_for($friend_class) . " SET "
      . $driver->dbd->db_column_name( $friend_class->datasource, 'visible' )
      . " = ? "
      . $stmt->as_sql_where;

   # Work around error in MT::ObjectDriver::Driver::DBI::sql by doing it inline.
    my $dbh = $driver->rw_handle;
    $dbh->do( $sql, {}, $new_visible, @{ $stmt->{bind} } )
      or return $app->error( $dbh->errstr );

    return 1;
}

sub itemset_hide_all_friends {
    my $app = shift;
    _itemset_hide_show_all_friends( $app, 0 ) or return;
    $app->add_return_arg( all_hidden => 1 );
    $app->call_return;
}

sub itemset_show_all_friends {
    my $app = shift;
    _itemset_hide_show_all_friends( $app, 1 ) or return;
    $app->add_return_arg( all_shown => 1 );
    $app->call_return;
}

=item edit_friend

edit friend and edit list of uris for that friend
OLD CODE DON"T USE"

=cut

sub _edit_friend {
    my $app = shift;
    my ($param) = @_;

    # my # $logger  = MT::Log->get_logger();
    # $logger->debug( Dumper(@_) );

    # id == author_id
    return $app->return_to_dashboard( redirect => 1 )
      unless $app->param('id');

    return $app->return_to_dashboard( permission => 1 )
      unless _permission_check();

    my $author_id = $app->param('author_id');
    my $friend_id = $app->param('id');

    require Friends::Friend;
    my $type = $param->{type} || Friends::Friend->class_type;
    my $pkg = $app->model($type) or return $app->error("Invalid request.");
    my $friend = ($friend_id) ? $pkg->load($friend_id) : undef;

    if ( !$author_id ) {
        $author_id = $friend->author_id;
    }

    require Friends::URI;
    $type = $param->{type} || Friends::URI->class_type;
    $pkg = $app->model($type) or return $app->error("Invalid request.");

    ## TODO: Properly load Friend and URIs
    ## OK, weird thing here is - I want to edit the friend AND list the URIs for that friend
    ## on the same page; I can't find a good example anywhere.
    ## Right now list_uris is a carbon copy of the list_notifications template
    ## I like the inline editing of the Address Book but then I want the friends fields up above.
    ## I can layout the UI but getting it to run is trickier.
    ## Hopefully someone can tell me a better way to approach this problem?

    my @uris = Friends::URI->load( { friend_id => $friend->id } );

    ## $logger->debug( Dumper(@uris) );

    my $tmpl = MT->component('Friends')->load_tmpl('edit_friend.tmpl');
    return $app->listing(
        {
            type     => 'uri',
            template => $tmpl,
            terms    => {

                #author_id => $author_id,
                friend_id => $friend_id,
            },
            params => {
                author_id => $author_id,
                friend_id => $friend_id,
                %{ $friend->column_values },    # include all friend fields
            },
        }
    );
}

sub edit_uri {
    my $app = shift;
    my ($param) = @_;

    _log( "edit_uri: " . Dumper( $app->param ) );

    my $author_id = $app->param('author_id');
    my $uri_id    = $app->param('id') || 0;
    my $friend_id = $app->param('friend_id');    # if new, should have this

    # uri_id or friend_id required
    # uri_id -> edit this uri
    # friend_id -> create new uri, probably
    _log("uri_id: $uri_id; friend_id: $friend_id");
    unless ( $uri_id || $friend_id ) {
        return $app->error(
            "Invalid request. Requires one of uri_id or friend_id.");
    }

    # load the Friend package
    require Friends::Friend;
    require Friends::URI;
    my $type = $param->{type} || Friends::URI->class_type;
    my $pkg = $app->model($type) or return $app->error("Invalid request.");

    # grab the URI we want to edit
    my $obj = ($uri_id) ? $pkg->load($uri_id) : undef;

    # if no friend_id and we've got a uri, get the friend_id from there
    if ( !$friend_id && $obj ) {
        $friend_id = $obj->friend_id;
    }
    my $friend = Friends::Friend->load($friend_id);

    # if no author_id and we've got a friend, get the author_id from there
    if ( !$author_id && $friend ) {
        $author_id = $friend->author_id;
    }

    my %param;
    if ($obj) {
        $param = {
            id => $uri_id,
            %{ $obj->column_values() },
        };
    }
    else {
        $param = { new_object => 1, };
    }

    $param->{author_id} = $author_id;
    $param->{friend_id} = $friend_id;

    $param->{object_type} = 'uri';
    $param->{saved}       = $app->param('saved');
    $param->{deleted}     = $app->param('deleted');

    _log( Dumper($param) );

    return $app->build_page( 'edit_uri.tmpl', $param );
}

sub save_uri {
    my $app = shift;
    my ($param) = @_;

    my $friend_id = $app->param('friend_id');
    my $author_id = $app->param('author_id');
    my $uri_id    = $app->param('id') || 0;

    require Friends::URI;
    my $type = $param->{type} || Friends::URI->class_type;
    my $pkg = $app->model($type) or return $app->error("Invalid request.");

    my $obj;
    if ($uri_id) {
        $obj = $pkg->load($uri_id)
          or return $app->error( 'Invalid uri ID: ' . $uri_id );
    }
    else {
        $obj = $pkg->new;
        $obj->init();
        $obj->friend_id($friend_id);
    }

    for my $field (qw( uri description author_id)) {
        $obj->$field( $app->param($field) );
    }
    my $uri = $app->param('uri');
    if ($uri =~ /\/$/) {
    	$obj->uri($uri =~ s/\/$//);
    }
    
    # $logger->debug("Saving object:");
    # $logger->debug( Dumper($obj) );

    $obj->save() or die "Saving failed: ", $obj->errstr;

   # return edit_friend page - this gets submitted to _top so just load the page
    $app->redirect( $app->path
          . $app->script
          . "?__mode=edit_friend&_type=friend&id=$friend_id&author_id="
          . $obj->author_id );

    # my $tmpl = MT->component('Friends')->load_tmpl('edit_friend.tmpl');
}

sub delete_uri {
    my $app = shift;
    my ($param) = @_;

    my $uri_id = $app->param('id') || 0;
    unless ($uri_id) {
        return $app->error("Invalid request. Delete requires uri_id.");
    }

    require Friends::URI;
    Friends::URI->remove( { id => $uri_id } )
      or return $app->error( 'Could not delete URI ' . $uri_id );

    $app->call_return( deleted => 1, saved_changes => 1 );
}

=item list_friends

list contacts for editing

=cut

sub list_friends {

    # my # $logger = MT::Log->get_logger();

    my $app = shift;
    my ($param) = @_;

    return $app->return_to_dashboard( redirect => 1 )
      unless $app->param('id');

    return $app->return_to_dashboard( permission => 1 )
      unless _permission_check();

    my $author_id = $app->param('id');
    my @data;

    ### get friends (MT::App::CMS.pm::list_entries,12157)
    require Friends::Friend;
    my $type = $param->{type} || Friends::Friend->class_type;
    my $pkg = $app->model($type) or return $app->error("Invalid request.");

    ## TODO: Include list of URLs (or first few?)
    ## I can't seem to figure out how to do this so that in the listing, i can list the URIs
    ## for that friend.

    return $app->listing(
        {
            type           => 'friend',
            listing_screen => 1,
            template =>
              MT->component('Friends')->load_tmpl('list_friends.tmpl'),
            terms => { author_id => $author_id, },
            code  => sub {
                my ( $obj, $row ) = @_;
                $row->{uris} = $obj->uris;
            },
            params => {
                object_type => 'friend',
                id          => $author_id
            }
        }
    );
}

=item edit_friend

load friend/contact for editing

=cut

sub edit_friend {
    my $app = shift;
    my ($param) = @_;

    return $app->return_to_dashboard( redirect => 1 )
      unless ( $app->param('id') || $app->param('author_id') );

    return $app->return_to_dashboard( permission => 1 )
      unless _permission_check();

    my $author_id = $app->param('author_id');
    my $friend_id = $app->param('id') || 0;

    # my # $logger = MT::Log->get_logger();

    # load the Friend package
    require Friends::Friend;
    require Friends::URI;
    my $type = $param->{type} || Friends::Friend->class_type;
    my $pkg = $app->model($type) or return $app->error("Invalid request.");

    # grab the Friend we want to edit
    my $obj = ($friend_id) ? $pkg->load($friend_id) : undef;

    if ( !$author_id ) {
        $author_id = $obj->author_id;
    }

    my %param;
    if ($obj) {
        $param = {
            id => $friend_id,
            %{ $obj->column_values() },
        };
    }
    else {
        $param = { new_object => 1, };
    }

    $param->{author_id}   = $author_id;
    $param->{object_type} = 'friend';
    $param->{uris}        = $obj ? $obj->uris : [];

    # $logger->debug("edit_friend");
    # $logger->debug( Dumper($obj) );

    return $app->build_page( 'edit_friend_old.tmpl', $param );
}

=item save_friend

save edited or new contact

=cut

sub save_friend {
    my $app = shift;
    my ($param) = @_;

	_log_params ($app);
	
    return $app->return_to_dashboard( redirect => 1 )
      unless $app->param('author_id') || $app->param('id');

    return $app->return_to_dashboard( permission => 1 )
      unless _permission_check();

    my $author_id = $app->param('author_id');
    my $friend_id = $app->param('id') || 0;

    require Friends::Friend;
    require Friends::URI;
    my $type = $param->{type} || Friends::Friend->class_type;
    my $pkg = $app->model($type) or return $app->error("Invalid request.");

    my $obj;
    if ($friend_id) {
        $obj = $pkg->load($friend_id)
          or return $app->error('Invalid friend ID');
    }
    else {
        $obj = $pkg->new;
        $obj->init();
        $obj->visible(1);
    }
    if ( !$author_id ) {
        $author_id = $obj->author_id;
    }
    else { $obj->author_id($author_id); }

    for my $field (qw( name rel notes)) {
        $obj->$field( $app->param($field) );
    }
    if ( !$app->param('visible') ) {
        $obj->visible(0);
    }
    else {
        $obj->visible(1);
    }

    $obj->save or die "Saving failed: ", $obj->errstr;
    _log ( "friend: " . Dumper( $obj) );

    if ( $app->param('uri') ) {

        # create new URI
        my $uri = Friends::URI->new();
        $uri->init();
        $uri->friend_id( $obj->id );
        $uri->uri( $app->param('uri') );
        $uri->description( $app->param('description') );
        $uri->target( $app->param('target') );
        $uri->save or die "Saving URI failed: ", $uri->errstr;

		_log ("uri: " . Dumper($uri));
    }

    return $app->redirect(
        $app->uri(
            mode => 'list_friends',
            args => {
                id          => $author_id,
                saved_added => 1,
            },
        )
    );

}

=item delete_friend

save edited or new contact

=cut

sub delete_friend {
    my $app = shift;
    my ($param) = @_;

    _log("delete_friend");

    my $friend_id = $app->param('id') || 0;
    unless ($friend_id) {
        return $app->error("Invalid request. Delete requires friend_id.");
    }

    require Friends::Friend;
    require Friends::URI;

    my $friend = Friends::Friend->load( { id => $friend_id } );

    unless ($friend) {
        return $app->error("Invalid request. Delete requires valid Friend.");
    }

    my $author_id = $friend->author_id;

    Friends::URI->remove( { friend_id => $friend_id } )
      or
      return $app->error( 'Could not delete URIs for Friend: ' . $friend_id );

    $friend->remove()
      or return $app->error( 'Could note delete Friend: ' . $friend_id );

    return $app->redirect(
        $app->uri(
            mode => 'list_friends',
            args => {
                id          => $author_id,
                saved_added => 1,
            },
        )
    );
}

##
# _get_claimed uses Google's Social Graph API L<http://code.google.com/apis/socialgraph/> to find
#	other URLs that the user has identified as their own via rel=mes
#
sub _get_claimed {
    require MT::Log;

    # my # $logger = MT::Log->get_logger();

    my $start_at = shift;

    # $logger->debug( '_get_claimed for ' . $start_at );

    my @source_data;

    return @source_data unless $start_at =~ /^http/;

    my %opts = (
        edo => 0,
        fme => 1,
        edi => 0
    );

    require Net::SocialGraph;
    my $sg_client = Net::SocialGraph->new(%opts);
    my $sg        = $sg_client->get($start_at);

    while ( my ( $source_uri, $source ) = each %{ $sg->{nodes} } ) {
        push @source_data, $source_uri unless $source_uri !~ /^http/;
    }

# $logger->debug( "claimed data for " . $start_at . ": " . Dumper(@source_data) );

    return @source_data;
}

sub _get_meta_for_uri {
    my $uri = shift;

    # get hcard name (if there) and page title
    my $scraper = scraper {
        process '.vcard .fn',        'name'  => 'TEXT';
        process '//html/head/title', 'title' => 'TEXT';
        process '//a[contains(concat(" ", normalize-space(@rel), " ")," me ")]',
          'other_uris[]' => '@href';
        process '//link[@rel="openid.delegate"]', 'openid' => '@href';
		process '//link[@rel="openid.local_id"]', 'openid2' => '@href';

        #process 'a[rel="me"]',	'fmes[]' => '@href';
    };

    $scraper->user_agent(
        ua(
            {
                'url'     => $uri,
                'scraper' => $scraper
            }
        )
    );
    my $items;
    eval { $items = $scraper->scrape( URI->new($uri) ); };
    if ($@) {
        _log($@);
    }
    return $items;
}

sub _get_contacts_for_uri {
    # require MT::Log;

    # my # $logger = MT::Log->get_logger();

    # $logger->debug('_get_contacts_for_uris');
    my ( $uri, $author_id ) = @_;

	_log ("Get Contacts for: $uri");

    require Net::SocialGraph;
    my %opts = (
        edo => 1,
        fme => 0,
    );
    my $sg_client = Net::SocialGraph->new(%opts);

    # $logger->debug("calling Google");
    my $sg = $sg_client->get($uri);

    # $logger->debug("done with Google");
    my @source_data;
    while ( my ( $source_uri, $source ) = each %{ $sg->{nodes} } ) {
        my @data;

      URI: for my $referenced_uri ( keys %{ $source->{nodes_referenced} } ) {
            my $refuri_node = $source->{nodes_referenced}->{$referenced_uri};

            next URI if $referenced_uri !~ /^http\:\/\//;

            my $meta = _get_meta_for_uri($referenced_uri);
            _log( "meta for $referenced_uri: " . Dumper($meta) );

            # is there uri like this already?
            require Friends::URI;

            # TODO: research: how to do LIKE here
            # TODO: get author and limit URIs to this author's URIs
            $referenced_uri =~ s/\/$//;
			_log ("does $referenced_uri already exist?");
			
			my $uri = Friends::URI->load(
                { uri => $referenced_uri } ); #, author_id => $author_id } );
			_log("result: " . Dumper($uri));
			
			if ($uri) {
                $refuri_node->{duplicate} = 1;
                _log( "found existing URI for $referenced_uri: " . Dumper($uri) );
                $refuri_node->{dupuri} = $uri->uri;
            }
			$refuri_node->{uri} = $referenced_uri; # . ($refuri_node->{duplicate} ? " (duplicate)" : "");

			_log( Dumper( $meta->{other_uris} ) );
			
            if ( $meta->{other_uris} ) {
                for ( my $i = 0 ; $i < scalar @{ $meta->{other_uris} } ; $i++ ) {
                    my $other_uri_str   = $meta->{other_uris}[$i]->as_string;
					$meta->{other_uris}[$i] = $other_uri_str;
					_log ("does $other_uri_str already exist?");
                    my $other_uri = Friends::URI->load(
                        { uri => $other_uri_str } ); #, author_id => $author_id } );
					_log (Dumper($other_uri));
                    
					if ($other_uri) {
						$refuri_node->{duplicate} = 1;
						$refuri_node->{dupuri} = $other_uri->uri;
                        $meta->{other_uris}[$i] = $other_uri_str . " (duplicate)";
                        _log( "found existing URI for $other_uri_str: " . Dumper($other_uri) );
                    }
                }
            }
            

            if ( $meta->{other_uris} ) {
                $refuri_node->{other_profiles} = [];
                foreach my $fme ( @{ $meta->{other_uris} } ) {
					push @{ $refuri_node->{other_profiles} },
                      $fme;  # I think it's a URI instance. Not sure.
                }
            }
            if ( $meta->{name} ) {
                $refuri_node->{name}      = $meta->{name};
                $refuri_node->{has_hcard} = 1;
            }
            if ( $meta->{title} ) {
                $refuri_node->{title} = $meta->{title};
            }
            if ( $meta->{openid} ) {
                $refuri_node->{openid} = $meta->{openid}->as_string;
            }
			if ( $meta->{openid2} ) {
                $refuri_node->{openid} = $meta->{openid2}->as_string;
            }
            $refuri_node->{rel} = join( " ", @{ $refuri_node->{types} } );
            push @data, $refuri_node unless $refuri_node->{rel} =~ /\bme\b/;
        }
        unless ( scalar @data == 0 ) {
            return \@data;
        }
    }
    return [];
}

sub get_claimed_uris {
    my $app = shift;

    require MT::Log;

    # my # $logger = MT::Log->get_logger();
    # $logger->debug("get_claimed_uris");

    my $start_uri = $app->param('source_uri');

    my @claimed = _get_claimed($start_uri);

    # $logger->debug( "claimed: " . Dumper(@claimed) );
    return $app->build_page( 'claimed_uris.tmpl', { claimed => \@claimed, } );
}

=item discover_friends:
	
* fetch related links from Google's social graph api (http://code.google.com/apis/socialgraph/)
* list contacts from those sites
* allow user to select contacts to import into local Friends/blogroll
	
right now this just lists the contacts. import not implemented.

=cut

##
# new thoughts:
# 	- with each contact in the discovery phase, store:
#		- source uri (string)
#		- uri (string)
#		- relationship (string)
#
#	- add to Friend:
#		- source_uri (string)
#		- is_subscribed (bool)
#		- last_updated
#
#	- create an update function
# 		- find list of subscribed hCards
#		- fetch and update each one
#			- if last_updated is within a certain time-frame
#
sub discover_friends {
    my $app = shift;

    # require MT::Log;

    # my # $logger = MT::Log->get_logger();

    my ($param) = @_;

    my $step = $app->param('step') || "start";

    # $logger->debug( "step: " . $step );
    my $getclaimed = $app->param('getclaimed');

    # $logger->debug( "getclaimed: " . $getclaimed );
    if ($getclaimed) {
        $step = 'getclaimed';
    }

    # $logger->debug( "step: " . $step );
    my $author_id = $app->param('author_id') || 1;
    my @data;
    my @source_data;

    my $user = $app->user;

    my $as_plugin = MT->component('ActionStreams');

    ##
    # Initial state: show the form
  STEP: {
        if ( $step =~ /start/ ) {

            _log("Discovery: Start");

            my $profiles;

            $profiles = $as_plugin ? $user->other_profiles() : [];

            _log( Dumper($profiles) );

            return $app->build_page(
                'discover_friends.tmpl',
                {
                    step        => $step,
                    id          => $author_id,
                    object_type => 'friend',
                    profiles    => \@$profiles
                }
            );
            last STEP;
        }

        if ( $step =~ /getclaimed/ ) {

            # $logger->debug("in getclaimed");

            my $start_uri = $app->param('source_uri');

            return $app->return_to_dashboard( redirect => 1 )
              unless $start_uri;

            my @claimed = _get_claimed($start_uri);

            # $logger->debug( "source_data: " . Dumper(@claimed) );
            return $app->build_page(
                'discover_friends.tmpl',
                {
                    step      => $step,
                    id        => $author_id,
                    start_uri => $start_uri,
                    claimed   => \@claimed,
                }
            );
            last STEP;
        }

        if ( $step =~ /find/ ) {
            my $uri = $app->param('source_uri');
            return $app->error("Param source_uri required to Find contacts")
              unless $uri;

            _log( Dumper($uri) );
            my @contacts = @{ _get_contacts_for_uri( $uri, $author_id ) };

            # TODO: check if URI is already in database

            _log( "contacts: " . Dumper(@contacts) );
            my $tmpl =
              MT->component('Friends')->load_tmpl('discover_friends.tmpl');
            return $app->build_page(
                'discover_friends.tmpl',
                {
                    listing_screen => 1,
                    source         => $uri,
                    step           => $step,
                    id             => $author_id,
                    contacts       => \@contacts,
                }
            );
            last STEP;
        }

        elsif ( $step =~ /import/ ) {

            my @uris = $app->param("uris");

            require Friends::Friend;
            require Friends::URI;
            my @created_friends = [];

            my $i;
            for ( $i = 0 ; $i < scalar @uris ; $i++ ) {
                _log( $uris[$i] );
                my ( $n, $u, $dup ) = split( /\|/, $uris[$i] );
                _log("$n: $u $dup");

				# 1) is there a Friend already?
				my ($uri, $friend);
				if ($dup) {
					_log ("loading uri for: $dup");
					$uri = Friends::URI->load({uri=>$dup});
					unless ($uri) {
						die "Cannot load uri for: $dup";
					}
					_log("dup uri: " . Dumper($uri));
					$friend = Friends::Friend->load($uri->friend_id);
	                _log( "friend for dup uri: " . Dumper($friend) );
				} else {
	                # 1) create Friend
					$friend = Friends::Friend->new();
	                $friend->init();
	                $friend->name($n);
	                $friend->author_id($author_id);
	                $friend->save() or die "Error saving friend: $!";
	                _log( "made new friend: " . Dumper($friend) );
				}

                # 2) create URI
                $uri = Friends::URI->new();
                $uri->init();
                $uri->uri($u);
                $uri->description($n);
                $uri->friend_id( $friend->id );
                $uri->author_id($author_id);
                $uri->save() or die "Error saving uri: $!";

                _log( Dumper($uri) );
                push @created_friends, $friend;
            }

            return $app->listing(
                {
                    type           => 'friend',
                    listing_screen => 1,
                    template =>
                      MT->component('Friends')->load_tmpl('list_friends.tmpl'),
                    object_loop => \@created_friends,
                    code        => sub {
                        my ( $obj, $row ) = @_;
                        $row->{uris} = $obj->uris;
                    },
                    params => {
                        object_type => 'friend',
                        id          => $author_id
                    }
                }
            );
            last STEP;
        }
    }
}

# import_to_friend ($uri, $name)
sub import_to_friend {
    my ( $uri, $name ) = @_;
}

##
# totally cribbed straight from action streams, by mark@sixapart
#
sub _author_ids_for_args {
    my ( $ctx, $args, $cond ) = @_;

    my @author_ids;
    if ( my $ids = $args->{author_ids} || $args->{author_id} ) {
        @author_ids = split /\s*,\s*/, $ids;
    }
    elsif ( my $disp_names = $args->{display_names}
        || $args->{display_name} )
    {
        my @names = split /\s*,\s*/, $disp_names;
        my @authors = MT->model('author')->load( { nickname => \@names } );
        @author_ids = map { $_->id } @authors;
    }
    elsif ( my $names = $args->{author} || $args->{authors} ) {
        my @names = split /\s*,\s*/, $names;
        my @authors = MT->model('author')->load( { name => \@names } );
        @author_ids = map { $_->id } @authors;
    }
    elsif ( my $author = $ctx->stash('author') ) {
        @author_ids = ( $author->id );
    }
    elsif ( my $blog = $ctx->stash('blog') ) {
        my @authors = MT->model('author')->load(
            {
                status => 1,    # enabled
            },
            {
                join => MT->model('permission')->join_on(
                    'author_id',
                    { blog_id => $blog->id },
                    { unique  => 1 }
                ),
            }
        );
        @author_ids = map { $_->id } @authors;
    }

    return \@author_ids;
}

my $ua;

sub ua {
    my $class  = shift;
    my %params = @_;

    if ( !$ua ) {
        my %agent_params = ();
        my @classes      = (qw( LWPx::ParanoidAgent LWP::UserAgent ));
        while ( my $maybe_class = shift @classes ) {
            if ( eval "require $maybe_class; 1" ) {
                $ua = $maybe_class->new(%agent_params);
                $ua->timeout(10);
                last;
            }
        }
    }

    $ua->agent(
          $params{default_useragent}
        ? $ua->_agent
        : "mt-friends-lwp/" . MT->component('Friends')->version
    );
    return $ua;
}

##########################

sub upgrade_uri_add_authorid {
    my ($uri) = @_;
    require Friends::Friend;
    my $friend = Friends::Friend->load( { id => $uri->friend_id } );
    if ($friend) {
        $uri->author_id( $friend->author_id );
        $uri->save;
    }
}

##########################

=head2 Template Tags

=item <MTBlogRoll></MTBlogRoll>

Legacy tag - wraps MTFriends which should not be used instead.

=cut

sub tag_blog_roll_block {
    return tag_friends_block(@_);
}

=item <mt:friends></mt:friends>

Loops over all the friends for the blog. 

Attributes: display_name(s)="author names" || author_id(s)="id(s)"

=cut

sub tag_friends_block {
    my %terms = (
        author_id => _author_ids_for_args(@_),
        visible => 1,    # only show Friends that are marked "public" (visible)
    );

    my ( $ctx, $args, $cond ) = @_;
    my $builder = $ctx->stash('builder');
    my $tokens  = $ctx->stash('tokens');

    my $res = "";
    if ( my $blog = $ctx->stash('blog') ) {
        require Friends::Friend;
        my @friends = Friends::Friend->search( \%terms );

        # _log( "friends: " . Dumper(@friends) );

      FRIEND: for my $friend (@friends) {
            require Friends::URI;
            my @uris = Friends::URI->load( { friend_id => $friend->id } );

            #my $friendlinkscount = @uris;
            #local $ctx->{__stash}{friendlinkscount} = $friendlinkscount;
            next FRIEND if ( !@uris );
            local $ctx->{__stash}{friend} = $friend;

            defined( my $out = $builder->build( $ctx, $tokens, $cond ) )
              or return $ctx->error( $builder->errstr );
            $res .= $out;
        }
    }
    return $res;
}

=item <mt:friendlinks></mt:friendlinks>

Loops over all the links for this friend.

=cut

sub tag_friend_links_block {

    my ( $ctx, $args, $cond ) = @_;
    my $builder = $ctx->stash('builder');
    my $tokens  = $ctx->stash('tokens');

    my $res = "";
    require Friends::Friend;
    if ( my $friend = $ctx->stash('friend') ) {

        # _log( "friend: " . Dumper($friend) );
        require Friends::URI;
        my @uris = Friends::URI->load( { friend_id => $friend->id } );

        # _log( "uris: " . Dumper(@uris) );
      URI: for my $uri (@uris) {
            next URI unless ( $uri && $uri->uri );
            local $ctx->{__stash}{uri} = $uri;

            defined( my $out = $builder->build( $ctx, $tokens, $cond ) )
              or return $ctx->error( $builder->errstr );
            $res .= $out;
        }
    }
    return $res;
}

=item <$mt:friendname$>

Outputs the friend name.

context: C<<MTBlogRoll>>

=cut

sub tag_friend_name {
    my ( $ctx, $arg, $cond ) = @_;
    my $friend = $ctx->stash('friend')
      or return $ctx->error("Used FriendName in a non-friend context!");
    return $friend->name || '<!-- no friend name provided -->';
}

=item <$mt:friendrel$>

Outputs the rel attribute for the link. This is a space-seperated list of XFN rel values.

context: <MTBlogRoll>

=cut

sub tag_friend_rel {
    my ( $ctx, $arg, $cond ) = @_;
    my $friend = $ctx->stash('friend')
      or return $ctx->error("Used FriendLinkRel in a non-link context!");
    return $friend->rel || '';
}

=item <$mt:friendlinkname$>

Outputs the link label.

context: C<<MTBlogRoll>>

=cut

sub tag_friend_link_name {
    my ( $ctx, $arg, $cond ) = @_;
    my $uri = $ctx->stash('uri')
      or return $ctx->error("Used FriendLinkName in a non-link context!");
    return $uri->description || '';
}

=item <$mt:friendlinkuri$>

Outputs the friend URI.

context: C<<MTBlogRoll>>

=cut

sub tag_friend_link_uri {
    my ( $ctx, $arg, $cond ) = @_;
    my $uri = $ctx->stash('uri')
      or return $ctx->error("Used FriendLinkUri in a non-link context!");
    return $uri->uri || '';
}

=item <$mt:friendlinknotes$>

Outputs the link Notes.

context: <MTBlogRoll>

=cut

sub tag_friend_link_notes {
    my ( $ctx, $arg, $cond ) = @_;
    my $uri = $ctx->stash('uri')
      or
      return $ctx->error("Used FriendLinkDescription in a non-link context!");
    return $uri->notes || '';
}

=item <$mt:friendlinklabel$>

Outputs either the name, or uri if the name is empty.

context: <MTBlogRoll>

=cut

sub tag_friend_link_label {
    my ( $ctx, $arg, $cond ) = @_;
    my $uri = $ctx->stash('uri')
      or return $ctx->error("Used FriendLinkLabel in a non-uri context!");
    return $uri->description ? $uri->description : $uri->uri;
}

1;

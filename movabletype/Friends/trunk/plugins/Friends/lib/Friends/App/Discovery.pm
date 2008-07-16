package Friends::App::Discovery;

use strict;
use base qw( MT::App );

use JSON;
use Data::Dumper qw(Dumper);
use Web::Scraper;
use URI;

sub _log_params {
    my $app = shift;
    for my $p ( $app->param ) {
        _log( $p . ": " . $app->param($p) );
    }
}

sub _log {
    my $msg = shift;
    MT->log($msg);
}

##
# _get_contacts uses Google's Social Graph API L<http://code.google.com/apis/socialgraph/> to find
#   other URLs that the user has identified as contacts or friends
#
sub _get_contacts {
    require MT::Log;

    my $start_at = shift;

    my @source_data;

    my %opts = (
        edo => 1,
        fme => 0,
        edi => 0
    );

    require Net::SocialGraph;
    my $sg_client = Net::SocialGraph->new(%opts);
    my $sg        = $sg_client->get($start_at);

    _log( "_get_contacts: " . Dumper($sg) );
    $start_at = $sg->{canonical_mapping}->{$start_at};
    while ( my ( $source_uri, $data ) =
        each %{ $sg->{nodes}->{$start_at}->{nodes_referenced} } )
    {

        # strip out non http links
        push @source_data, $source_uri
          unless ( $source_uri !~ /^http/ || $source_uri eq $start_at );
    }
    _log( "_get_contacts source_data: " . Dumper(@source_data) );

    return @source_data;
}

##
# _get_related uses Google's Social Graph API L<http://code.google.com/apis/socialgraph/> to find
#   other URLs that the user has identified as their own via rel=mes
#
sub _get_related {
    require MT::Log;

    my $start_at = shift;

    my @source_data;

    my %opts = (
        edo => 0,
        fme => 1,
        edi => 0
    );

    require Net::SocialGraph;
    my $sg_client = Net::SocialGraph->new(%opts);
    my $sg        = $sg_client->get($start_at);

    _log( "_get_contacts: " . Dumper($sg) );
    $start_at = $sg->{canonical_mapping}->{$start_at};
    while ( my ( $source_uri, $data ) = each %{ $sg->{nodes} } ) {

        # strip out non http links
        push @source_data, $source_uri unless $source_uri !~ /^http/;
    }
    _log( "_get_rlated source_data: " . Dumper(@source_data) );

    return @source_data;
}

sub _get_meta_for_uri {
    my $uri = shift;
    _log( "get_meta_for_uri: " . $uri );

    # get hcard name (if there) and page title
    my $scraper = scraper {
        process '.vcard .fn',        'name'  => 'TEXT';
        process '.vcard .photo',     'photo' => '@href';
        process '//html/head/title', 'title' => 'TEXT';

        process
          '//a[contains(concat(" ", normalize-space(@rel), " ")," me ")]',
          'other_uris[]' => '@href';

        #process '//link[@rel="openid.delegate"]', 'openid'  => '@href';
        #process '//link[@rel="openid.local_id"]', 'openid2' => '@href';

        #process 'a[rel="me"]', 'fmes[]' => '@href';
    };

    $scraper->user_agent(
        ua(
            {
                'url'     => $uri,
                'scraper' => $scraper
            }
        )
    );
    my $items = {};
    eval { $items = $scraper->scrape( URI->new($uri) ); };

    #$items->{other_uris} = _get_related($uri);

    if ( $items->{other_uris} ) {
        for ( my $i = 0 ; $i < scalar @{ $items->{other_uris} } ; $i++ ) {
            $items->{other_uris}[$i] = $items->{other_uris}[$i]->as_string;
        }
    }

    #if ( $items->{openid} ) {
    #    $items->{openid} = $items->{openid}->as_string;
    #}
    #if ( $items->{openid2} ) {
    #    $items->{openid} = $items->{openid2}->as_string;
    #}

    if ($@) {
        _log($@);
    }
    return $items;
}

sub _get_contact_list_for_uri {
    my $uri       = shift;
    my $author_id = shift;

    require Net::SocialGraph;
    my %opts = (
        edo => 1,
        fme => 0,
    );
    my $sg_client = Net::SocialGraph->new(%opts);

    my $sg = $sg_client->get($uri);

    my @nodes = keys %{ $sg->{nodes} };

    return @nodes;
}

sub _get_contacts_for_uri {

    # require MT::Log;

    my $uri         = shift;
    my $author_id   = shift;
    my $get_related = shift || 1;

    _log("Get Contacts for: $uri");

    require Net::SocialGraph;
    my %opts = (
        edo => 1,
        fme => 0,
    );
    my $sg_client = Net::SocialGraph->new(%opts);

    my $sg = $sg_client->get($uri);

    my @source_data;
    while ( my ( $source_uri, $source ) = each %{ $sg->{nodes} } ) {
        my @data;

      URI: for my $referenced_uri ( keys %{ $source->{nodes_referenced} } ) {
            my $refuri_node = $source->{nodes_referenced}->{$referenced_uri};

            next URI if $referenced_uri !~ /^http\:\/\//;

            my $meta = _get_meta_for_uri( $referenced_uri, $get_related );

            #_log( "meta for $referenced_uri: " . Dumper($meta) );

            # is there link like this already?
            my $link_class = MT->model('link');

            # TODO: research: how to do LIKE here
            # TODO: get author and limit URIs to this author's URIs
            $referenced_uri = URI->new($referenced_uri)->canonical->as_string;

            _log("does $referenced_uri already exist?");

            my $link =
              $link_class->load( { uri => $referenced_uri } )
              ;    #, author_id => $author_id } );
            _log( "\$link: " . Dumper($link) );

            if ($link) {
                $refuri_node->{duplicate} = 1;

               #_log(
               #    "found existing URI for $referenced_uri: " . Dumper($uri) );
                $refuri_node->{dupuri} = $link->uri;
            }
            $refuri_node->{uri} = $referenced_uri
              . ( $refuri_node->{duplicate} ? " (duplicate)" : "" );

            #_log( Dumper( $meta->{other_uris} ) );

            if ( $meta->{other_uris} ) {
                for ( my $i = 0 ; $i < scalar @{ $meta->{other_uris} } ; $i++ )
                {
                    my $other_uri_str = $meta->{other_uris}[$i];

                    #_log("does $other_uri_str already exist?");
                    my $other_link =
                      $link_class->load( { uri => $other_uri_str } )
                      ;    #, author_id => $author_id } );

                    if ($other_link) {
                        $refuri_node->{duplicate} = 1;
                        $refuri_node->{dupuri} =
                          URI->new( $other_link->uri )->canonical->as_string;
                        $meta->{other_uris}[$i] =
                          $other_uri_str . " (duplicate)";
                    }
                }
            }

            if ( $meta->{other_uris} ) {
                $refuri_node->{other_profiles} = [];
                for my $fme ( @{ $meta->{other_uris} } ) {
                    push @{ $refuri_node->{other_profiles} },
                      URI->new($fme)->canonical->as_string;    # now string.
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

                # call as_string b/c this is a Net::URI object
                $refuri_node->{openid} = $meta->{openid}->canonical->as_string;
            }
            if ( $meta->{openid2} ) {

                # call as_string b/c this is a Net::URI object
                $refuri_node->{openid} = $meta->{openid2}->canonical->as_string;
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

# get a service name from the action streams profile registry
sub _lookup_service_name {
    my $app = shift;
    my $uri = shift;

	_log ("_lookup_service_name: $uri");

    #return URI->new($uri)->canonical->authority;

    my $host      = URI->new($uri)->canonical->authority;
	$host =~ s/^(www|\%s)\.//;
	_log ($host);
    my $as_plugin = MT->component('ActionStreams');

    if ( !$as_plugin ) {
        return "Blog";
    }

    my $profile_services = $app->registry('profile_services');

    for my $svc ( values %{ $profile_services } ) {
        #_log( "profile service: " . Dumper($svc) );
        my $svc_host = URI->new( $svc->{url} )->canonical->authority;
		_log ("$svc_host, $host");
		my $svc_name = $svc->{name} if ( $host =~ m/$svc_host/ );
        return $svc_name if ( $svc_name !~ m/Website/ );
    }
    return "Blog";
}

=item discover_friends:
    
* fetch related links from Google's social graph api (http://code.google.com/apis/socialgraph/)
* list contacts from those sites
* allow user to select contacts to import into local Friends/blogroll

=cut

# APP METHOD
sub discover_friends {
    my $app = shift;

    my ($param) = @_;

    my $step = $app->param('step') || "start";

    my $author_id = $app->param('id') || 0;
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

            #_log( Dumper($profiles) );

            my @all_contacts = [];

            my $friend_class = MT->model('friend');
            @all_contacts = $friend_class->load( { author_id => $author_id } );

            return $app->build_page(
                'dialog/discover_friends.tmpl',
                {
                    step         => $step,
                    id           => $author_id,
                    object_type  => 'friend',
                    profiles     => \@$profiles,
                    all_contacts => \@all_contacts
                }
            );
            last STEP;
        }

        if ( $step =~ /find/ ) {
            _log("Discovery: Find");

            my $uri = $app->param('source_uri');

            return $app->error("Param contact_data required to Find contacts")
              unless $uri;

            _log( Dumper($uri) );
            my @contacts = @{ _get_contacts_for_uri( $uri, $author_id ) };

            _log( "contacts: " . Dumper(@contacts) );

            return $app->build_page(
                'find_contacts.tmpl',
                {
                    listing_screen => 1,
                    source         => $uri,
                    step           => $step,
                    id             => $author_id,
                    contacts       => \@contacts,
                    show_actions   => 0
                }
            );

            last STEP;
        }

        elsif ( $step =~ /import/ ) {
            my @uris = $app->param("uris");

            my $friend_class    = MT->model('friend');
            my $link_class      = MT->model('link');
            my @created_friends = [];

            my $i;
            for ( $i = 0 ; $i < scalar @uris ; $i++ ) {
                _log( $uris[$i] );
                my ( $n, $u, $dup ) = split( /\|/, $uris[$i] );
                _log("$n: $u $dup");

                # 1) is there a Friend already?
                my ( $link, $friend );
                if ($dup) {
                    _log("loading uri for: $dup");
                    $link = $link_class->load( { uri => $dup } );
                    unless ($link) {
                        die "Cannot load link for: $dup";
                    }
                    _log( "dup link: " . Dumper($link) );
                    $friend = $friend_class->load( $link->friend_id );
                    _log( "friend for dup link: " . Dumper($friend) );
                }
                else {

                    # 1) create Friend
                    $friend = $friend_class->new();
                    $friend->init();
                    $friend->name($n);
                    $friend->author_id($author_id);
                    $friend->save() or die "Error saving friend: $!";
                    _log( "made new friend: " . Dumper($friend) );
                }

                # 2) create Link
                $link = $link_class->new();
                $link->init();
                $link->uri($u);
                $link->label($n);
                $link->friend_id( $friend->id );
                $link->author_id($author_id);
                $link->save() or die "Error saving link: $!";

                _log( Dumper($link) );
                push @created_friends, $friend;
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
            last STEP;
        }
    }
}

# get the list of contacts as json
# the app will then call back to get_contacts_data with a set of urls
#
# APP METHOD
sub get_contacts_list_json {
    _log('find_contacts_json');
    my $app = shift;

    my $uri = $app->param('source_uri');
    $uri = ( $uri eq "other" ) ? $app->param('source_uri_other') : $uri;
    _log( "uri: " . $uri );

    my @contacts = _get_contacts($uri);

    _log( 'get_contacts_list_json: ' . Dumper(@contacts) );
    require JSON;
    return JSON::objToJson( \@contacts );
}

# get meta info for each passed in
# APP METHOD
sub get_contacts_data {
    _log("get_contacts_data");
    my $app           = shift;
    my $contacts_json = $app->param('contacts_json');
    _log( "get_contacts_data_json: json? - " . $contacts_json );
    my $contact_resp = JSON::jsonToObj($contacts_json);
    my $contact_uris = %{$contact_resp}->{contacts};

    _log(   "get_contacts_data_json: "
          . Dumper( @{$contact_uris} )
          . " size: "
          . @{$contact_uris} );

    my @contacts;

    for my $contact_uri ( @{$contact_uris} ) {
        _log( 'for ' . Dumper($contact_uri) );
        my $meta = _get_meta_for_uri($contact_uri);
        $meta->{uri} = $contact_uri;
        push @contacts, $meta if ( keys %{$meta} );
    }

    # import as "pending" for review
    import_pending_contacts( $app, @contacts );

    _log( "get_contacts_data: " . Dumper( { contacts => \@contacts } ) );
    return JSON::objToJson( { contacts => \@contacts } );
}

sub import_pending_contacts {
    _log("import_pending_contacts");
    my $app  = shift;
    my @data = @_;

    _log( "author_id: " . $app->param('author_id') );
    my $author_id = $app->param('author_id') || die('author_id required!');

    my $friend_class = MT->model('friend');
    my $link_class   = MT->model('link');

    for my $contact (@data) {
        _log( "check contact: " . Dumper($contact) );

        # do we have a link already?
        my @uris =
          $contact->{other_uris}
          ? ( $contact->{uri}, @{ $contact->{other_uris} } )
          : ( $contact->{uri} );
        _log( "uris to check: " . Dumper(@uris) );
        my @links = ();
        my $friend;

        # fetch all links that match passed in any of the passed-in uri
        my @links = $link_class->load( { uri => \@uris } );

        _log( "found links: " . Dumper(@links) );

        if (@links) {

            _log( "link[0]: " . Dumper( $links[0] ) );
            $friend = $friend_class->load( { id => $links[0]->friend_id } );

            _log( "friend: " . Dumper($friend) );

        }
        else {

# create new friend since none of the uris exist yet... i don't like this, since it's very possible
# that we get only a new link from a site, and the site does not rel=me to a known link. this is basically lame.
# might need to use the google api again to find ALL the rel=me sites for this one so I make sure to find a match.

            $friend = $friend_class->new();
            $friend->init();
            $friend->name(
                $contact->{name} || $contact->{title} || $contact->{uri});
            $friend->author_id($author_id);
            $friend->pending(1);
            $friend->save() or die "Error saving friend: " . $friend->errstr;
            _log( "made new friend: " . Dumper($friend) );
        }

        for my $uri (@uris) {

       # FIXME: there are more efficient ways to do this, but my brain is tired.
            if ( !$link_class->load( { uri => $uri } ) ) {

                # this uri was not one of the ones fetched from the db
                # so create a pending link for it
                my $link = $link_class->new();
                $link->init();
                $link->pending(1);
                $link->friend_id( $friend->id );
                $link->author_id($author_id);
                $link->uri($uri);
                $link->label( _lookup_service_name( $app, $uri ) );
                $link->save or die "Error saving link: " . $link->errstr;
                _log( "made new link: " . Dumper($link) );
            }
        }
    }
}

# called by itemset_import_contacts
# APP METHOD
sub import_contacts {
    my $app = shift;

    my @contact_ids = $app->param("id");
    my @link_ids = $app->param("links");
    
    _log ('import contacts -- contact ids: ' . Dumper (@contact_ids));
    _log ('import contacts -- link ids: ' . Dumper (@link_ids));
    
    my $author_id = $app->param('author_id') || 1;
    
    my $friend_class = MT->model('friend');
    my $link_class   = MT->model('link');
    
    for my $cid (@contact_ids) {
        my $friend = $friend_class->load($cid);
        $friend->pending(0);
        $friend->visible(1);
        $friend->save or die ("Could not save contact! $!");
    }
    
    for my $lid (@link_ids) {
        my $link = $link_class->load($lid);
        $link->pending(0);
        $link->save or die ("Could not save link! $!");
    }
    
    #$friend_class->remove({pending=>1});
    #$link_class->remove({pending=>1});
}

sub itemset_import_contacts {
    my ($app) = @_;
    $app->validate_magic or return;
    my $author_id = $app->param('author_id') || 1;

    import_contacts($app);

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

#############################
#
# UTIL

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
        : "mt-friends/" . MT->component('Friends')->version
    );
    return $ua;
}

1;

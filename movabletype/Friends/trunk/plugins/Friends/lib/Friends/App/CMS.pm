package Friends::App::CMS;

use strict;
use base qw( MT::App );

#use CGI::Carp 'fatalsToBrowser';
use JSON;
use Data::Dumper qw(Dumper);
use Web::Scraper;
use URI;

sub _log_params {
    my $app = shift;
    PARAM: for my $p ( $app->param ) {
        _log( $p . ": " . $app->param($p) );
    }
}

sub _log {
    my $msg = shift;
    MT->log($msg);
}

sub _permission_check {
    my $app = MT->instance;
    return ( $app->user );
}

sub _pending_contacts_for_author {
	my $author_id = shift;
	
	my $friend_class = MT->model('friend');
	
    my @p_contacts = $friend_class->load( { author_id => $author_id, pending=>1 } );
	#_log ("pending: ". Dumper(@p_contacts));
	return @p_contacts;
}

sub users_content_nav {
    my ( $cb, $app, $html_ref ) = @_;

    $$html_ref =~ s{class=["']active["']}{}xmsg
      if $app->mode eq 'list_friends'
          || $app->mode eq 'discover_friends'
          || $app->mode eq 'edit_friend'
          || $app->mode eq 'view_friend'
          || $app->mode eq 'list_pending';

	my $id = $app->param('id');
	
    $$html_ref =~
      m{ "> ((?:<b>)?) <__trans \s phrase="Permissions"> ((?:</b>)?) </a> }xms;
    my ( $open_bold, $close_bold ) = ( $1, $2 );

    my $html = <<"EOF";
    <mt:if var="USER_VIEW">
        <li<mt:if name="friends"> class="active"</mt:if>><a 
			href="<mt:var name="SCRIPT_URL">?__mode=list_friends&amp;id=<mt:var name="EDIT_AUTHOR_ID">">$open_bold<__trans phrase="People I Know">$close_bold</a></li>
		</mt:if>
    <mt:if var="edit_author">
        <li<mt:if name="friends"> class="active"</mt:if>><a 
				  href="<mt:var name="SCRIPT_URL">?__mode=list_friends&amp;id=<mt:var name="id">">$open_bold<__trans phrase="People I Know">$close_bold</a></li>
        </mt:if>
EOF
	
    $$html_ref =~ s{(?=</ul>)}{$html}xmsg;
}

sub itemset_hide_friends {
    my ($app) = @_;
    $app->validate_magic or return;

    my @friends = $app->param('id');

    FRIEND: for my $friend_id (@friends) {
        my $friend = MT->model('friend')->load($friend_id)
          or next FRIEND;
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

    FRIEND: for my $friend_id (@friends) {
        my $friend = MT->model('friend')->load($friend_id)
          or next FRIEND;
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

    FRIEND: for my $friend_id (@friends) {
        my $friend = MT->model('friend')->load($friend_id)
          or next FRIEND;
        next
          if $app->user->id != $friend->author_id
              && !$app->user->is_superuser();
        my @links = MT->model('link')->load({friend_id=>$friend_id});
        for my $link (@links)
        {
            $link->remove();
        }
        $friend->remove();
    }
    $app->call_return;
}

sub itemset_merge_friends {
    my ($app) = @_;
    $app->validate_magic or return;

    my @friends_to_merge = $app->param('id');
	
	# get lowest # id and assume that's the one to merge into
	@friends_to_merge = sort(@friends_to_merge);
	_log ("merging: " . Dumper(@friends_to_merge));
	
	my $lowest_id = shift @friends_to_merge;
	_log ("lowest id: " . Dumper($lowest_id));
	
    FRIEND: for my $friend_id (@friends_to_merge) {
        my $friend = MT->model('friend')->load($friend_id)
          or next FRIEND;
        next
          if $app->user->id != $friend->author_id
              && !$app->user->is_superuser();
        my @links = MT->model('link')->load({friend_id=>$friend_id});
		_log ("links to merge: " . Dumper(@links));
		
        LINK: for my $link (@links)
        {
            $link->friend_id($lowest_id);
			$link->save();
        }
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

sub edit_link {
    my $app = shift;
    my ($param) = @_;

    _log( "edit_link: " . Dumper( $app->param ) );

    my $author_id = $app->param('author_id');
    my $link_id   = $app->param('id') || 0;
    my $friend_id = $app->param('friend_id');    # if new, should have this

    # link_id or friend_id required
    # link_id -> edit this link
    # friend_id -> create new link, probably
    # _log("link_id: $link_id; friend_id: $friend_id");
    if ( !$link_id && !$friend_id ) {
        return $app->error(
            "Invalid request. Requires one of link_id or friend_id.");
    }

    # load the Friend package
    my $friend_class = MT->model('friend');
    my $link_class   = MT->model('link');
    my $type         = $param->{type} || $link_class->class_type;
    my $pkg = $app->model($type) or return $app->error("Invalid request.");

    # grab the Link we want to edit
    my $obj = ($link_id) ? $pkg->load($link_id) : undef;

    # if no friend_id and we've got a link, get the friend_id from there
    if ( !$friend_id && $obj ) {
        $friend_id = $obj->friend_id;
    }
    my $friend = $friend_class->load($friend_id);

    # if no author_id and we've got a friend, get the author_id from there
    if ( !$author_id && $friend ) {
        $author_id = $friend->author_id;
    }

    my %param;
    if ($obj) {
        $param = {
            id => $link_id,
            %{ $obj->column_values() },
        };
    }
    else {
        $param = { new_object => 1, };
    }

    $param->{author_id} = $author_id;
    $param->{friend_id} = $friend_id;

    $param->{object_type} = 'link';
    $param->{saved}       = $app->param('saved');
    $param->{deleted}     = $app->param('deleted');

    _log( Dumper($param) );

    return $app->build_page( 'dialog/edit_link.tmpl', $param );
}

sub save_link {
    my $app = shift;
    my ($param) = @_;

    my $friend_id = $app->param('friend_id');
    my $author_id = $app->param('author_id');
    my $link_id   = $app->param('id') || 0;

    my $link_class = MT->model('link');
    my $type = $param->{type} || $link_class->class_type;
    my $pkg = $app->model($type) or return $app->error("Invalid request.");

    my $obj;
    if ($link_id) {
        $obj = $pkg->load($link_id)
          or return $app->error( 'Invalid link ID: ' . $link_id );
    }
    else {
        $obj = $pkg->new;
        $obj->init();
        $obj->friend_id($friend_id);
    }

    FIELD: for my $field (qw( uri label author_id)) {
        $obj->$field( $app->param($field) );
    }
    MT->log( Dumper($obj) );

    my $uri = $app->param('uri');
    $uri = URI->new($uri)->canonical->as_string;
    $obj->uri($uri);

    $obj->save() or die "Saving failed: ", $obj->errstr;

   # return edit_friend page - this gets submitted to _top so just load the page
    $app->redirect(
        $app->uri(
            mode => 'view_friend',
            type => 'friend',
            args => {
                friend_id   => $friend_id,
                author_id   => $obj->author_id,
                saved_added => 1,
            },
        )
    );

    # my $tmpl = MT->component('Friends')->load_tmpl('edit_friend.tmpl');
}

sub delete_link {
    my $app = shift;
    my ($param) = @_;

    my $link_id = $app->param('id') || 0;
    if ( !$link_id ) {
        return $app->error("Invalid request. Delete requires link_id.");
    }

    my $link_class   = MT->model('link');
    my $friend_class = MT->model('friend');
    my $link         = $link_class->load( { id => $link_id } );
    my $friend_id    = $link->friend_id;
    my $friend       = $friend_class->load( { id => $friend_id } );

    $link_class->remove( { id => $link_id } )
      or return $app->error( 'Could not delete Link ' . $link_id );

    #$app->call_return( deleted => 1, saved_changes => 1 );
    $app->redirect( $app->path
          . $app->script
          . "?__mode=edit_friend&_type=friend&id=$friend_id&author_id="
          . $friend->author_id );
}

=item list_friends

list contacts for editing

=cut

sub list_friends {

    my $app = shift;
    my ($param) = @_;

    return $app->return_to_dashboard( redirect => 1 )
      unless $app->param('id');

    return $app->return_to_dashboard( permission => 1 )
      unless _permission_check();

    my $author_id = $app->param('id');
    my @data;

    ### get friends (MT::App::CMS.pm::list_entries,12157)
    my $friend_class = MT->model('friend');
    my $type = $param->{type} || 'friend';
    my $pkg = $app->model($type) or return $app->error("Invalid request.");
	my $p_contacts = _pending_contacts_for_author ($author_id);
    ## TODO: Include list of URLs (or first few?)
    ## I can't seem to figure out how to do this so that in the listing, i can list the Links
    ## for that friend.

    return $app->listing(
        {
            type           => 'friend',
            listing_screen => 1,
            template =>
              MT->component('Friends')->load_tmpl('list_friends.tmpl'),
            terms => {
				author_id => $author_id,
				pending => 1,
			},
			args => {
				not => { pending => 1 },
			},
            code  => sub {
                my ( $obj, $row ) = @_;
                $row->{links} = $obj->links({pending=>0});
            },
            params => {
                object_type => 'friend',
                id          => $author_id,
				has_pending => ($p_contacts > 0),
            }
        }
    );
}

# lazy copy-paste implementation of the "review pending" controller
sub list_pending_friends {

    my $app = shift;
    my ($param) = @_;

    return $app->return_to_dashboard( redirect => 1 )
      unless $app->param('id');

    return $app->return_to_dashboard( permission => 1 )
      unless _permission_check();

    my $author_id = $app->param('id');
    my @data;

    ### get friends (MT::App::CMS.pm::list_entries,12157)
    my $friend_class = MT->model('friend');
    my $link_class = MT->model('link');
    my $type = $param->{type} || 'friend';
    my $pkg = $app->model($type) or return $app->error("Invalid request.");
	my $p_contacts = _pending_contacts_for_author ($author_id);

    ## TODO: Include list of URLs (or first few?)
    ## I can't seem to figure out how to do this so that in the listing, i can list the Links
    ## for that friend.
    
    return $app->listing(
        {
            type           => 'friend',
            terms          => { author_id => $author_id },
            args           => {
                'join' => $link_class->join_on ('friend_id',
                    { pending => 1 },
                    { unique => 1 }
                )
            },
            listing_screen => 1,
            template =>
              MT->component('Friends')->load_tmpl('list_pending_friends.tmpl'),
            code  => sub {
                my ( $obj, $row ) = @_;
                $row->{links} = $obj->links();
            },
            params => {
                object_type => 'friend',
                id          => $author_id,
				has_pending => ($p_contacts > 0),
            }
        }
    );
}

=item view_friend

load friend/contact for viewing

=cut

sub view_friend {
    my $app = shift;
    my ($param) = @_;

    return $app->return_to_dashboard( redirect => 1 )
      unless ( $app->param('id') || $app->param('author_id') );

    return $app->return_to_dashboard( permission => 1 )
      unless _permission_check();

	_log("view_friend: id param: " . $app->param('id'));
    my $author_id = $app->param('id');
    if ( !$author_id ) {
        $author_id = $app->param('author_id');
    }
    my $friend_id = $app->param('friend_id') || 0;

    # load the Friend package
    my $friend_class = MT->model('friend');
    my $link_class   = MT->model('link');
    my $type         = $param->{type} || $friend_class->class_type;
    my $pkg = $app->model($type) or return $app->error("Invalid request.");

    # grab the Friend we want to view
    my $obj = ($friend_id) ? $pkg->load($friend_id) : undef;

    if ( !$author_id && $obj ) {
        $author_id = $obj->author_id;
    }

    #$param->{id}             = $author_id;
    #$param->{object_type}    = 'link';
    #$param->{listing_screen} = 1;
    #$param->{object_loop}    = $obj ? $obj->links : [];
	
	my $column_values = $obj->column_values();
	delete $column_values->{id};
	
    return $app->listing(
        {
            type           => 'link',
            listing_screen => 1,
            template => MT->component('Friends')->load_tmpl('view_friend.tmpl'),
            terms    => { friend_id => $friend_id },
            params   => {
                object_type => 'link',
                id          => $author_id,
                friend_id   => $friend_id,
                %{ $column_values },
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

    my $author_id = $app->param('id');
    if ( !$author_id ) {
        $author_id = $app->param('author_id');
    }
    my $friend_id = $app->param('friend_id') || 0;

    # load the Friend package
    my $friend_class = MT->model('friend');
    my $link_class   = MT->model('link');
    my $type         = $param->{type} || $friend_class->class_type;
    my $pkg = $app->model($type) or return $app->error("Invalid request.");

    # grab the Friend we want to edit
    my $obj = ($friend_id) ? $pkg->load($friend_id) : undef;

    if ( !$author_id && $obj ) {
        $author_id = $obj->author_id;
    }

    my %param;
    if ($obj) {
        $param = {
            author_id => $author_id,
            friend_id => $friend_id,
            %{ $obj->column_values() },
        };
    }
    else {
        $param = { new_object => 1, };
    }

    $param->{id}             = $author_id;
    $param->{object_type}    = 'friend';
    $param->{listing_screen} = 1;
    $param->{object_loop}    = $obj ? $obj->links : [];

    return $app->build_page( 'edit_friend.tmpl', $param );
}

=item save_friend

save edited or new contact

=cut

sub save_friend {
    my $app = shift;
    my ($param) = @_;

    _log_params($app);

    return $app->return_to_dashboard( redirect => 1 )
      unless $app->param('author_id') || $app->param('id');

    return $app->return_to_dashboard( permission => 1 )
      unless _permission_check();
	
	my $new_object = $app->param('new_object') || 0;
    my $author_id = $app->param('author_id');
    my $friend_id = $app->param('id') || 0;
	
    my $friend_class = MT->model('friend');
    my $link_class   = MT->model('link');

    my $type = $param->{type} || $friend_class->class_type;
    my $pkg = $app->model($type) or return $app->error("Invalid request.");

    my $friend;

	if ($new_object) {	
        $friend = $pkg->new;
        $friend->init();
        $friend->visible(1);
		$friend->pending(0);
		if ( !$author_id ) {
	        return $app->error('Author ID required!');
	    }
		$friend->author_id($author_id);
		_log ("friend: " . Dumper($friend_id));
    }    
	else {
        $friend = $pkg->load($friend_id)
          or return $app->error('Invalid friend ID');
		if ( !$author_id ) {
	        $author_id = $friend->author_id;
	    }
    }
    
	if (!$friend) {
		return $app->error("Could not get/make friend with friend_id: $friend_id " . $friend);
	}
	
    FIELD: for my $field (qw( name rel notes)) {
        $friend->$field( $app->param($field) );
    }
    if ( !$app->param('visible') ) {
        $friend->visible(0);
    }
    else {
        $friend->visible(1);
    }

    $friend->save or die "Saving failed: ", $friend->errstr;

    #_log( "friend: " . Dumper($friend) );
    _log( "uri? " . $app->param('uri') );
    if ( $app->param('uri') ) {
        _log("creating first link");

        # create new Link
        my $link = $link_class->new();
        $link->init();
        $link->friend_id( $friend->id );
        $link->uri( $app->param("uri") );
        $link->label( $app->param("label") );
        $link->author_id($author_id);
        $link->pending(0);
        $link->save or die "Saving Link failed: ", $link->errstr;

        _log( "created first link: " . Dumper($link) );
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

    my $friend_class = MT->model('friend');
    my $link_class   = MT->model('link');

    my $friend = $friend_class->load( { id => $friend_id } );

    unless ($friend) {
        return $app->error("Invalid request. Delete requires valid Friend.");
    }

    my $author_id = $friend->author_id;

    $link_class->remove( { friend_id => $friend_id } )
      or
      return $app->error( 'Could not delete Links for Friend: ' . $friend_id );

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
        CLASS: while ( my $maybe_class = shift @classes ) {
            if ( eval "require $maybe_class; 1" ) {
                $ua = $maybe_class->new(%agent_params);
                $ua->timeout(10);
                last CLASS;
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

sub upgrade_link_add_authorid {
    my ($link) = @_;
    my $friend_class = MT->model('friend');
    my $friend = $friend_class->load( { id => $link->friend_id } );
    if ($friend) {
        $link->author_id( $friend->author_id );
        $link->save;
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
        visible =>
          1,    # only show Friends that are marked "Show In Blogroll" (visible)
    );

    my ( $ctx, $args, $cond ) = @_;
    my $builder = $ctx->stash('builder');
    my $tokens  = $ctx->stash('tokens');

    my $res = "";
    if ( my $blog = $ctx->stash('blog') ) {
        my $friend_class = MT->model('friend');
        my @friends      = $friend_class->search( \%terms );

        # _log( "friends: " . Dumper(@friends) );

      FRIEND: for my $friend (@friends) {
            my $link_class = MT->model('link');
            my @links = $link_class->load( { friend_id => $friend->id } );

            #my $friendlinkscount = @links;
            #local $ctx->{__stash}{friendlinkscount} = $friendlinkscount;
            next FRIEND if ( !@links );
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

    my $res          = "";
    my $friend_class = MT->model('friend');
    if ( my $friend = $ctx->stash('friend') ) {

        # _log( "friend: " . Dumper($friend) );
        my $link_class = MT->model('link');
        my @links = $link_class->load( { friend_id => $friend->id } );

        # _log( "links: " . Dumper(@links) );
      LINK: for my $link (@links) {
            next LINK unless ( $link && $link->uri );
            local $ctx->{__stash}{link} = $link;

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

=item <$mt:friendlinkname$>

Outputs the link label.

context: C<<MTBlogRoll>>

=cut

sub tag_friend_link_name {
    my ( $ctx, $arg, $cond ) = @_;
    my $link = $ctx->stash('link')
      or return $ctx->error("Used FriendLinkName in a non-link context!");
    return $link->label || '';
}

=item <$mt:friendlinklink$>

Outputs the friend Link.

context: C<<MTBlogRoll>>

=cut

sub tag_friend_link_uri {
    my ( $ctx, $arg, $cond ) = @_;
    my $link = $ctx->stash('link')
      or return $ctx->error("Used FriendLinkUri in a non-link context!");
    return $link->uri || '';
}

=item <$mt:friendlinknotes$>

Outputs the link Notes.

context: <MTBlogRoll>

=cut

sub tag_friend_link_notes {
    my ( $ctx, $arg, $cond ) = @_;
    my $link = $ctx->stash('link')
      or
      return $ctx->error("Used FriendLinkDescription in a non-link context!");
    return $link->notes || '';
}

=item <$mt:friendlinklabel$>

Outputs either the name, or uri if the name is empty.

context: <MTBlogRoll>

=cut

sub tag_friend_link_label {
    my ( $ctx, $arg, $cond ) = @_;
    my $link = $ctx->stash('link')
      or return $ctx->error("Used FriendLinkLabel in a non-link context!");
    return $link->label ? $link->label : $link->uri;
}

1;

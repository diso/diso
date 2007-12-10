phpMyID - A standalone, single user, OpenID Identity Provider

by: CJ Niemira <siege (at) siege (dot) org>
(c) 2006-2007
http://siege.org/projects/phpMyID


phpMyID is a small, fairly lightweight, standalone, single user Identity
Provider for OpenID authentication. It comprises a single PHP script that can be
used by one individual to run their own personal OpenID "IdP."

This program requires no external libraries, and has very minimal requirements.
It should run on any PHP server (v4.2+), and can support OpenID in 'Smart Mode.'
This program caches all data using built-in PHP session handling, so it requires
no database, and no explicit write access to the file system.

User authentication is done using HTTP Digest authentication, so your password
is never transmitted over the wire in plain text.

NOTE: 'Smart Mode' OpenID authentication requires the use of "big math"
      functions. If you do not have bcmath or GMP available, you can use a built
      in pure-PHP "big math" library, but it is not very efficient. You are
      encouraged to use bcmath if at all possible.

      The name 'phpMy...' does NOT indicate that MySQL or any other database is
      required. This software does not use a backend database by design!

      There's no reason that phpMyID should not work under IIS. However, getting
      HTTP Digest authentication to work properly may require jumping through
      some hoops. Please see here: http://php.net/http-auth


FOR MORE INFORMATION ON OpenID, PLEASE SEE http://openid.net


************
INSTALLATION
************

phpMyID can be installed on just about any PHP server. It is recommended that
you use a server that you own and control.


1) Decide how you want to install phpMyID. It requires at least two files, and
   can be installed in a number of ways. The two files you will be uploading
   are:

   MyID.php		The application library
			You can rename it if you like

   MyID.config.php	This is the file you'll be visiting in your browser
			It contains your user profile, creds, and options
			You can rename it too

   Three suggestions for installation are:

   a) Don't bother renaming the files because there's no reason to
   b) Rename MyID.config.php to index.php and plan to put them in a new
      directory
   c) Rename 'em both. Like, change MyID.config.php to whatevermakesyouhappy.php
      and MyID.php to somethingjustassilly.php. This option is kinda like a
      vanity license plate... pointless, but if it makes you happy, go nuts.


2) Upload the files. That's right, just upload 'em. Unless you decided you need
   to rename MyID.php, you shouldn't have edited them yet. Put 'em wherever you
   want. I suggest your root URL, but you can do whatever you need to make them
   web accessable, as long as you can figure out what the URL should be.

   If you did rename MyID.php, then you must change the last line of whatever
   your config file is now called to reflect the new name (the 'include' line).


3) Visit your config file (the one that used to be MyID.config.php - 'cause I
   know you just *had* to rename it) in a web browser. You should see a message
   that says "This is an OpenID server endpoint." You should also see a
   "Server" URL, and a "Realm" string.

   If you don't see all of these things, proceed to the Troubleshooting section
   of this document.


4) If your "Realm" is anything other than the string 'phpMyID' (like say, if it
   has a number after it) then make note of the value. This means that PHP is
   running in "safe mode," and while I disagree for the reasons they change the
   realm, there's nothing I can do about it.

   Edit your config file, and change the key "auth_realm" to reflect the "Realm"
   value displayed in your browser. The default is 'phpMyID' and it only needs
   to be changed if the string you see in your browser is something different.

   If you're going to want to change this to some custom value (again, pointless
   to do so, but if it makes you happy, you can) just edit the "auth_realm" key
   to read whatever you want. Remember, however, that you need to double check
   your realm value by visiting MyID.config.php in a browser after you make the
   change (and upload it). 


5) Now you get to decide your login name and password. This is what you will use
   to authenticate yourself to phpMyID. Your login name can be anything you
   like.

   To create your password, you will need an MD5 hashing utility. If you are a
   Linux or OSX user, you can use openssl. Simply open a terminal and type:

    $ echo -n 'username:realm:password' | openssl md5

   If you are a Windows user, and do not already have an MD5 hashing tool, one
   is available at http://siege.org/projects/phpMyID . To use it, download
   the exe, and open a cmd session. Use it as follows:

    C:\Documents and Settings\cniemira>cd Desktop
    C:\Documents and Settings\cniemira\Desktop>md5.exe -d"username:realm:password"

   In either case, make sure to substitute your username and password where
   indicated. You must also substitute "realm" for your authentication realm as
   determined in step 4. The resulting output, which will be a long alphanumeric
   string, is your Digest password that must now be input into your config file
   in the 'auth_password' field. While you're at it, enter the username you
   just used as the 'auth_username' key.

   Note that the default username and password are both "test," and were encoded
   with the realm "phpMyID". That means you can probably test logging in right
   of the bat as 'test'/'test'.


6) Upload your config file again, replacing the one that was already there.

   Visit your config file in a browser again, or refresh the current page. The
   output shouldn't change, you're just looking to be sure there are no errors.

   Be certain that the 'Realm' listed exactly matches the value you used when
   you created your password hash in step 5.

   Click 'Login' - you should be redirected a couple of times and then presented
   with a login dialogue box. Enter your username and password and click ok.
   Again, you should be bounced around for a sec, then get a message which says
   you're logged in as whoever your username is.

   If you can't log in, if you get an error, or if doesn't work in some other
   way, proceed to the Troubleshooting section.


7) The "Server" URL, is your Identity Provider. This is this URL you must link
   as your openid.server and openid.delegate.

   The preferred way of setting this up is to determine the URL you wish to
   authenticate as (for example "http://siege.org", in my case), and add the
   following to the HTML <head> section for that document:

    <link rel="openid.server" href="http://siege.org/MyID.config.php">
    <link rel="openid.delegate" href="http://siege.org/MyID.config.php">

   Remember, BOTH the openid.sever and openid.delegate values should be set to
   the same thing.

   You may now use your URL with OpenID (again, "http://siege.org" in my case).


#####
USAGE
#####

There isn't much to using phpMyID other than pointing at it, and logging in.
If you wish, you can log out by visiting:

  http://yourdomain.com/path/to/MyID.php?openid.mode=logout

There is also a 'log in' mode that will prompt you for credentials without
having to be referred from a client site.

 http://yourdomain.com/path/to/MyID.php?openid.mode=login


####################################
SIMPLE REGISTRATION EXTENSION (SREG)
####################################

OpenID features something called 'sreg' which is a way to supply commonly
requested personal information to any site which you log into. Typically, a
client site uses this to create your profile the first time you log in. This
'sreg' information comprises such things as your nickname, email address, time
zone preference, etc...

All of the SREG keys are optional. To enable the use of any of them, simply
uncomment the line by removing the hashmark, and replace the value with your
own information. If you don't feel like supplying a particular detail, just
leave it commented out.


####################
ADVANCED CONFIG KEYS
####################

Several other configuration keys exist and can be set in the 'profile' array:

'allow_gmp'	When set to true, the GMP (Gnu MP Bignum library) extension,
		if available, can be used for big math calculations (ie
		encryption, or "Smart Mode").

'allow_test'	If set to true, this key will allow phpMyID to run a special
		test mode. Set the key to true, and visit your IdP url. You
		should see a "Test" link that was not previously available.
		By clicking it, phpMyID will conduct a series of tests designed
		to validate the functionality of the internal signature and
		math functions. This information is useful for troubleshooting.

'debug'		If set to true, phpMyID will do perform debug logging. If you 
		turn this on, you are strongly encouraged to also set the key
		'logfile' to explicitly set the path to your debug log. If not,
		phpMyID will attempt to automatically ascertain the correct
		location to put a 'phpMyID.debug.log' file, and will cause an
		internal error if it cannot write to that location.

'force_bigmath'	If set to true, the internal pure-PHP big math library will be
		used to ensure "Smart Mode" is always available, even if neither
		bcmath nor gmp extensions can be used. Note that using this may
		result in a severe performance degredation for your system. You
		should only switch this on if you *really* need to use "Smart
		Mode," and cannot otherwise get bcmath or gmp installed on your
		system.

'logfile'	When the 'debug' key is true, this key is used to define the
		absolute path to a debug log.


###########################
REALLY ADVANCED CONFIG KEYS
###########################

'auth_domain'	This is the domain value used in the HTTP Digest authentication.
		It defaults to your idp_url (see below).

'lifetime'	This key defines how long an OpenID session is valid for. The
		default value takes into consideration both the internal PHP
		session lifespan as well as the default frequency of garbage
		collection on your system.

'idp_url'	This key defines the identity that phpMyID will allow you to
		claim. It is almost always set correctly by default.


###############
TROUBLESHOOTING
###############

*) Received error: "Missing expected authorization header."

   phpMyID must be able to read http request headers which are only available if
   PHP is running as a webserver module. If you are using PHP in CGI mode, you
   must convert the HTTP 'Authorization' header into an environment variable
   ("PHP_AUTH_DIGEST") or query parameter ("auth") that can then be used to
   perform the authorization.

   If you are using Apache, the included 'htaccess' file contains three examples
   of how you can use mod_rewrite or mod_setenvif directives to to set the
   necessary variable. If you need to use this technique, it is recommended
   that you place phpMyID in its own directory, isolated from the rest of your
   web site.


*) Login never works? Double check your authentication realm

   Digest authentication can be a bit tricky to set up properly. One of the
   biggest stumbling blocks has to do with the authentication realm as described
   under installation step 1. If you change servers or if any settings on your
   server changes, it is possible that your authentication realm will change as
   well. If that happens, you will no longer be able to authenticate to phpMyID,
   and must re-create your password hash.

   When you log in to phpMyID, your web browser will present the authentication
   realm to you in the login box it pops up. It will say something like this:

    Enter username and password for "phpMyID-101" at http://siege.org

  The quoted value in the above is your authentication realm. Make sure that is
  the same value you used when you created your password hash.


*) Login still broken? Try re-encoding the test values.

   Sometimes different computers, using different character sets, will produce
   different md5 values. Try generating an MD5 hash for "test:phpMyID:test"
   (no quotes). If the value you get back isn't this:

	37fa04faebe5249023ed1f6cc867329b

   then you may have a character set problem. The quick solution for this is
   to have your webserver do the hashing for you. It's not the most secure
   option in the world, but you can create a temporary php file containing
   the following:

	<?php
	echo md5("user:realm:pass");
	?>

   Then upload it, hit it in your browser, get your hash, and delete it.


*) Still can't figure it out?

   Sometimes the unexpected happens. Sometimes bugs creep into the code. For
   all these times, please consult the phpMyID forum at:

	https://www.siege.org/forum/


EOF

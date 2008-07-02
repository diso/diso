#!/usr/bin/perl
use GnipPublisher;
use GnipSubscriber;

# Be sure to fill these in if you want to run the test cases
my $TEST_USERNAME = '';
my $TEST_PASSWORD = '';
my $TEST_PUBLISHER = '';

print "Testing Gnip Perl convenience methods\n\n";

my $gnipPublisher = new GnipPublisher($TEST_USERNAME, $TEST_PASSWORD, $TEST_PUBLISHER);
my $gnipSubscriber = new GnipSubscriber($TEST_USERNAME, $TEST_PASSWORD);

my $numFailed = 0;
my $numPassed = 0;

my $formatter = DateTime::Format::Strptime->new( 
   pattern => '%Y-%m-%dT%H:%M:%S+00:00' );
my $currentTimeString =  DateTime->from_epoch( epoch => time(), formatter => $formatter );

my $activity = '<?xml version="1.0" encoding="UTF-8"?>' . 
	'<activities>' .
        '  <activity at="' . $currentTimeString . '" type="added_friend" uid="me"/>' .
        '</activities>';

my $response = "";

# Test publish()
$response = $gnipPublisher->publish($activity);
if(-1 == index($response, 'Success'))
{
   print "FAIL - Test of publish() failed!!\n";
   print "--- Response = " . $response . "\n";
   $numFailed += 1;
}
else
{
    print "pass - Test of publish() passed\n";
    $numPassed += 1;
}

# Test get()
$response = $gnipSubscriber->get($TEST_PUBLISHER);
if(-1 == index($response, $currentTimeString))
{
    print "FAIL - Test of get() failed!!\n";
    print "--- Response = " . $response . "\n";
    $numFailed += 1;
}
else
{
    print "pass - Test of get() passed\n";
    $numPassed += 1;
}

$collectionXml = '<collection name="perlTest123">' . 
      ' <uid publisher.name="' . $TEST_PUBLISHER . '" name="me"/>' . 
      '</collection>';

# Test create_collection()
$response = $gnipSubscriber->create_collection($collectionXml);
if(-1 == index($response, 'Success'))
{
    print "FAIL - Test of create_collection() failed!!\n";
    print "--- Response = " . $response . "\n";
    $numFailed += 1;
}
else
{
    print "pass - Test of create_collection() passed\n";
    $numPassed += 1;
}

# Test find_collection()
$response = $gnipSubscriber->find_collection("perlTest123");
if(-1 == index($response, 'perlTest123'))
{
    print "FAIL - Test of find_collection() failed!!\n";
    print "--- Response = " . $response . "\n";
    $numFailed += 1;
}
else
{
    print "pass - Test of find_collection() passed\n";
    $numPassed += 1;
}

$updatedCollectionXml = '<collection name="perlTest123">' . 
      ' <uid publisher.name="' . $TEST_PUBLISHER . '" name="me"/>' . 
      ' <uid publisher.name="' . $TEST_PUBLISHER . '" name="you"/>' .
      '</collection>';

# Test update_collection()
$response = $gnipSubscriber->update_collection("perlTest123", $updatedCollectionXml);
if(-1 == index($response, 'Success'))
{
    print "FAIL - Test of update_collection() failed!!\n";
    print "--- Response = " . $response . "\n";
    $numFailed += 1;
}
else
{
    print "pass - Test of update_collection() passed\n";
    $numPassed += 1;
}

# Test get_collection()
$response = $gnipSubscriber->get_collection("perlTest123");
if(-1 == index($response, '<activities>'))
{
    print "FAIL - Test of get_collection() failed!!\n";
    print "--- Response = " . $response . "\n";
    $numFailed += 1;
}
else
{
    print "pass - Test of get_collection() passed\n";
    $numPassed += 1;
}

# Test delete_collection()
$response = $gnipSubscriber->delete_collection("perlTest123");
if(-1 == index($response, 'Success'))
{
    print "FAIL - Test of delete_collection() failed!!\n";
    print "--- Response = " . $response . "\n";
    $numFailed += 1;
}
else
{
    print "pass - Test of delete_collection() passed\n";
    $numPassed += 1;
}

print "\n" . $numPassed . " tests passed, "
    . $numFailed . " tests failed";
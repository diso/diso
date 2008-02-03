<?php
// get the api
require_once 'sgapi.php';

// instantiate the client
$sga = new SocialGraphApi(Array('edgesout'=>0,'edgesin'=>0,'followme'=>'1',
'sgn'=>0));

// get some data
$mydata = $sga->get('http://redmonk.net');

// print
echo "<pre>" . print_r($mydata,true) . "</pre>";


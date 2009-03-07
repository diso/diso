<?php

header('Content-Type: text/plain;charset=utf-8');

while($contact = next_contact()) :
	$n = explode(' ',$contact['fn']);
	?>

BEGIN:VCARD
VERSION:2.1
N:<?php echo $n[1].';'.$n[0]; ?>

FN:<?php echo $contact['fn']; ?>
<?php foreach($contact['url'] as $url) : ?>

URL:<?php echo str_replace(':','\:',$url); ?>
<?php endforeach; ?>

CATEGORIES:<?php echo implode(';',explode(' ',$contact['rel'])); ?>

NOTE:<?php echo $contact['note']; ?>

END:VCARD
	<?php
endwhile;

?>

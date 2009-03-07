<?php

header('Content-Type: text/html;profile=hcard;charset=utf-8');

while($contact = next_contact()) :
	$n = explode(' ',$contact['fn']);
	?>

<div class="vcard">
	<span class="fn"><?php echo $contact['fn']; ?></span>
	<ul>
<?php foreach($contact['url'] as $url) : ?>

	<li><a class="url" href="<?php echo htmlspecialchars($url); ?>" rel="<?php echo htmlspecialchars($contact['rel']); ?>"><?php echo htmlspecialchars($url); ?></a></li>
<?php endforeach; ?>
</ul>

	<div class="note"><?php echo $contact['note']; ?></div>

</div>
	<?php
endwhile;

?>

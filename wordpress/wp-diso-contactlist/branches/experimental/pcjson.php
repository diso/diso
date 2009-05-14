<?php

header('Content-Type: text/javascript;charset=utf-8');

//JSON only, format only
//No reason the other bits of PC can't be thrown in around here
//vCard is reccomended for interoperability purposes

?>
{
	"startIndex": 0,
	"entry": [
<?php

while($contact = next_contact()) :
	?>

		{

			"displayName": "<?php echo $contact['fn']; ?>"
			"urls": [
<?php foreach($contact['url'] as $url) : ?>
				"<?php echo addslashes($url); ?>",
<?php endforeach; ?>
			]

			"tags": ["<?php echo implode('","',explode(' ',$contact['rel'])); ?>"]

		},

	<?php
endwhile;

?>
]
}

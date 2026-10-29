<?php

$config = \Drupal::configFactory()->getEditable('metatag.metatag_defaults.front');
$config->set('tags.bing', 'F8120FF758A15942F8A89026760FBFC2')->save();
echo "Saved Bing verification token in Drupal Front page metatag defaults.\n";

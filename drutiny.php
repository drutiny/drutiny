<?php

$messages = [
  'Drutiny is preparing a super position of quantum uncertainty ... please wait 🧪',
  'Drutiny is reversing polarity with a sonic screwdriver ... please wait',
  'Drutiny is co-ordinating with Ziggy for a Quantum Leap ... please wait ⚛️',
  'Drutiny is hailing the USS Discovery ... Black Alert 🌌',
  'Wubba Lubba Dub Dub! 🎉',
];
$container->setParameter('progress_bar.loading_message', $messages[array_rand($messages)]);

// Unique identify used for things like localized caching.
$container->setParameter('instance_id', hash('md5', __FILE__));

$container->setParameter('drutiny.core.directory', __DIR__);
$container->setParameter('drutiny_is_vendored', str_contains(__DIR__, 'vendor/drutiny/drutiny'));

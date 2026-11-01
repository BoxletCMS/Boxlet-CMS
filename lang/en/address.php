<?php

// An address the server calls on someone's say-so: a form's web address, a picture an AI client
// asks for (PLAN.md O-77, O-78; App\Support\PublicAddress).
return [
    'address.https' => 'Give a full address that starts with https://.',
    'address.private' => 'This address is on a private network or this server itself; only addresses on the public internet can be used.',
    'address.unknown_host' => ':host could not be found.',
    'address.unreachable' => ':host could not be reached: :error',
];

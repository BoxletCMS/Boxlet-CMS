<?php

// A video's cover picture, fetched once by the server for the Embed block (PLAN.md D-147).
return [
    'embed.poster_fetch' => 'Take the picture from the video',
    'embed.poster_fetching' => 'Fetching the video’s picture…',
    'embed.poster_fetched' => 'The video’s own picture is the cover now.',
    'embed.poster_not_video' => 'That address is not a YouTube or Vimeo video, so there is no picture to take from it. Choose a cover picture yourself.',
    'embed.poster_unreachable' => 'The video’s picture could not be fetched. Check the address, or choose a cover picture yourself.',
    'embed.poster_no_network' => 'This server may not fetch files from other sites (allow_url_fopen is off), so the video’s picture cannot be taken from it. Choose a cover picture yourself.',
];

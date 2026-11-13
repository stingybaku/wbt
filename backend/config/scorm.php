<?php

return [
    // Enable only after importing a generated package into the target LMS.
    'enabled' => env('SCORM_EXPORT_ENABLED', false),
    'player_path' => storage_path('app/scorm-player'),
];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Route groups
    |--------------------------------------------------------------------------
    |
    | @routes serialises the whole named-route table into every document it
    | renders (~75KB on this app, 40KB of which is admin.*). The public site
    | never resolves an admin route, so app.blade.php emits the "public" group
    | for visitors and the unfiltered table for anyone who can actually reach
    | the panel.
    |
    | Ziggy treats a filter list where every pattern starts with "!" as a
    | reject list, so this is "everything except these".
    |
    | Anything added here must be unreachable from the public/user bundles —
    | route() throws on an unknown name, so a wrong entry is a runtime error,
    | not a graceful degradation.
    |
    */

    'groups' => [
        'public' => [
            '!admin.*',
            '!horizon.*',
        ],
    ],

];

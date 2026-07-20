<?php

use App\Providers\AccessLevelServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\MailConfigServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    MailConfigServiceProvider::class,
    AccessLevelServiceProvider::class,
];

<?php

/*
|--------------------------------------------------------------------------
| Contact form
|--------------------------------------------------------------------------
|
| Destination address for the Enterprise "Nous contacter" form on /tarifs.
| Override with CONTACT_ADDRESS in .env without redeploying.
|
*/

return [
    'address' => env('CONTACT_ADDRESS', 'contact@globalafricaplus.com'),
];

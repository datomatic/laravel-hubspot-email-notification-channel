<?php

// config/hubspot.php

return [
    'access_token' => env('HUBSPOT_ACCESS_TOKEN'),
    'hubspot_owner_id' => env('HUBSPOT_OWNER_ID'),
    /*
    *  Associate the email also with company associated to the contact
    */
    'company_email_associations' => true,
    /*
    *  Hubspot enforces its rate limit over a ten second window,
    *  so a retry has to outlast that window to be worth making.
    */
    'retry' => [
        'times' => env('HUBSPOT_RETRY_TIMES', 3),
        'sleep_milliseconds' => env('HUBSPOT_RETRY_SLEEP_MILLISECONDS', 11 * 1000),
    ],
];

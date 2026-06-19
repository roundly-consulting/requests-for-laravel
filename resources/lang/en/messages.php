<?php

declare(strict_types=1);

return [

    'status' => [
        'New' => 'New',
        'Approved' => 'Approved',
        'Rejected' => 'Rejected',
        'Cancelled' => 'Cancelled',
        'Expired' => 'Expired',
    ],

    'invalid_status_transition' => 'Cannot transition a request from :from to :to.',
    'request_already_resolved' => 'The request is already resolved (:status) and cannot be changed.',

];

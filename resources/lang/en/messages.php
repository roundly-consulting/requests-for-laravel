<?php

declare(strict_types=1);

return [

    'invalid_status_transition' => 'Cannot transition a request from :from to :to.',
    'request_already_resolved' => 'The request is already resolved (:status) and cannot be changed.',
    'approver_not_a_model' => 'An approver must be an Eloquent model, :type given: a bare id names no model type, so it could not be enforced.',
    'approver_missing' => 'The approval round cannot be reopened: approver :type #:id no longer exists.',

];

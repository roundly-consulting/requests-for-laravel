<?php

declare(strict_types=1);

return [

    'invalid_status_transition' => 'Stav žiadosti nie je možné zmeniť z „:from“ na „:to“.',
    'request_already_resolved' => 'Žiadosť je už uzavretá (:status) a nie je možné ju zmeniť.',
    'approver_not_a_model' => 'Schvaľovateľ musí byť model Eloquent, zadaný bol :type: samotné ID neurčuje typ modelu, preto ho nebolo možné vynútiť.',
    'approver_missing' => 'Kolo schvaľovania nie je možné znovu otvoriť: schvaľovateľ :type #:id už neexistuje.',

];

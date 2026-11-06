<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Unit invitation message and human-entered code
    |--------------------------------------------------------------------------
    |
    | The invitation text is server-owned so every invitation uses the same
    | approved wording. SMS delivery uses the unit.invitation template with the short code,
    | recipient name and building/unit label as param1, param2 and param3.
    |
    */
    'message' => env(
        'UNIT_INVITATION_MESSAGE',
        'شما برای عضویت در یک واحد در سامانه بیلدینو دعوت شده‌اید.'
    ),

    'code_digits' => (int) env(
        'UNIT_INVITATION_CODE_DIGITS',
        8
    ),
];

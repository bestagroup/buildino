<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Unit invitation message and human-entered code
    |--------------------------------------------------------------------------
    |
    | The invitation text is server-owned so every invitation uses the same
    | approved wording. SMS delivery uses the existing Ghasedak verify
    | template and sends only the short invitation code as param1.
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

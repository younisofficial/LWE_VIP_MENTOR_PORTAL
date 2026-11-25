<?php

use App\Services\Phone;

it('normalizes Pakistani mobile numbers', function (string $input, ?string $expected) {
    expect(Phone::normalize($input))->toBe($expected);
})->with([
    ['03001234567', '923001234567'],
    ['+92 300 1234567', '923001234567'],
    ['923001234567', '923001234567'],
    ['0092-300-1234567', '923001234567'],
    ['3001234567', '923001234567'],
    ['12345', null],
    ['04235761234', null], // landline
]);

it('displays and masks numbers', function () {
    expect(Phone::display('923001234567'))->toBe('0300-1234567')
        ->and(Phone::masked('923001234567'))->toBe('0300-•••4567');
});

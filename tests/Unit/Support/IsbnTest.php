<?php

use App\Support\Isbn;

it('normalizes a hyphenated ISBN-10 to ISBN-13', function () {
    // NDL stores Berserk vol. 1 as an ISBN-10.
    expect(Isbn::fromString('4-592-13574-1')->value)->toBe('9784592135746');
});

it('accepts ISBN-10 with an X check digit', function () {
    expect(Isbn::fromString('4-592-13575-X')->value)->toBe('9784592135753')
        ->and(Isbn::fromString('059382024x')->value)->toBe('9780593820247');
});

it('strips hyphens, spaces and full-width digits from an ISBN-13', function (string $input) {
    expect(Isbn::fromString($input)->value)->toBe('9784592144403');
})->with([
    '978-4-592-14440-3',
    '978 4 592 14440 3',
    '９７８４５９２１４４４０３',
]);

it('rejects a bad checksum', function (string $input) {
    expect(Isbn::tryFrom($input))->toBeNull()
        ->and(Isbn::isValid($input))->toBeFalse();
})->with([
    '4-592-13574-6',
    '9784592135747',
    '978459213574',
    'not an isbn',
    '',
]);

it('throws on an invalid ISBN in fromString', function () {
    Isbn::fromString('9784592135747');
})->throws(InvalidArgumentException::class);

it('compares by normalized value', function () {
    expect(Isbn::fromString('4-592-13574-1')->equals(Isbn::fromString('9784592135746')))->toBeTrue();
});

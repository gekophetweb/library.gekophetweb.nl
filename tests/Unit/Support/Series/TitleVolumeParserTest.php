<?php

use App\Support\Series\CreatorName;
use App\Support\Series\TitleVolumeParser;

it('splits a title into series title and volume', function (string $input, string $title, string $volume) {
    $result = (new TitleVolumeParser)->parse($input);

    expect($result)->not->toBeNull()
        ->and($result->title)->toBe($title)
        ->and((string) $result->volume)->toBe($volume);
})->with([
    ['ベルセルク 41', 'ベルセルク', '41'],
    ['ベルセルク. 1', 'ベルセルク', '1'],
    ['ベルセルク　４２', 'ベルセルク', '42'],
    ['ベルセルク 第12巻', 'ベルセルク', '12'],
    ['ONE PIECE 1', 'ONE PIECE', '1'],
    ['Berserk Volume 1', 'Berserk', '1'],
    ['Title, Vol. 3', 'Title', '3'],
    ['Dungeon Crawler Carl #2', 'Dungeon Crawler Carl', '2'],
    ['Berserk (12)', 'Berserk', '12'],
]);

it('does not see a volume in titles that only contain a number', function (string $input) {
    expect((new TitleVolumeParser)->parse($input))->toBeNull();
})->with([
    'Catch-22',
    '1984',
    'ベルセルク',
    'Dungeon Crawler Carl',
]);

it('normalizes creator names', function (?string $input, ?string $expected) {
    expect(CreatorName::normalize($input))->toBe($expected);
})->with([
    ['三浦, 建太郎, 1966-2021', '三浦建太郎'],
    ['三浦, 建太郎', '三浦建太郎'],
    ['三浦建太郎 著', '三浦建太郎'],
    ['三浦建太郎 原作', '三浦建太郎'],
    ['Dinniman, Matt', 'Matt Dinniman'],
    ['Sartre, Jean-Paul, 1905-1980', 'Jean-Paul Sartre'],
    ['Matt Dinniman', 'Matt Dinniman'],
    ['', null],
    [null, null],
]);

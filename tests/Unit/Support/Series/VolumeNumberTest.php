<?php

use App\Support\Series\VolumeNumber;

it('parses volume tokens', function (string $input, string $expected) {
    expect((string) VolumeNumber::parse($input))->toBe($expected);
})->with([
    ['1', '1'],
    ['４２', '42'],
    ['第12巻', '12'],
    ['12巻', '12'],
    ['Vol. 12', '12'],
    ['vol 12', '12'],
    ['Volume 7', '7'],
    ['#3', '3'],
    ['(12)', '12'],
    ['［２］', '2'],
    ['3.5', '3.5'],
    ['007', '7'],
    ['5(番外編)', '5'],
    ['5（番外編）', '5'],
    ['第3巻 [完]', '3'],
    ['12〈完結〉', '12'],
    // NDL, 斉木楠雄のΨ難: a reading in brackets inside the note.
    ['4 (うるΨ(サイ)お正月)', '4'],
    ['5 (Ψ(サイ)を振れ!波乱の期末テスト)', '5'],
    ['6 (超能力者搭Ψ(サイ)旅客機でGO!)', '6'],
    ['11 (夏休み前のΨ難)', '11'],
]);

it('rejects strings that are not just a volume', function (?string $input) {
    expect(VolumeNumber::parse($input))->toBeNull();
})->with([
    'EPISODE:１',
    '黄金時代篇 1 覇王の卵',
    'L-1',
    '新装版',
    '(番外編)',
    '番外編 5',
    '5(番外編) 2',
    '1(2)',
    '1 (note(2))',
    '1 (a(b(c)))',
    '',
    null,
]);

it('sorts decimals between whole volumes', function () {
    $volumes = collect(['4', '3.5', '10', '3'])
        ->map(VolumeNumber::parse(...))
        ->sort(fn (VolumeNumber $a, VolumeNumber $b) => $a->compare($b))
        ->map(fn (VolumeNumber $v) => (string) $v)
        ->values()
        ->all();

    expect($volumes)->toBe(['3', '3.5', '4', '10']);
});

it('reports whole and decimal volumes', function () {
    expect(VolumeNumber::fromNumeric(3)->isWhole())->toBeTrue()
        ->and(VolumeNumber::fromNumeric('3.50')->isWhole())->toBeFalse()
        ->and((string) VolumeNumber::fromNumeric('3.50'))->toBe('3.5')
        ->and(VolumeNumber::fromNumeric('0.5')->toFloat())->toBe(0.5);
});

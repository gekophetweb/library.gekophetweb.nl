<?php

use App\Support\Series\Text;

it('builds comparison keys', function (?string $input, string $expected) {
    expect(Text::key($input))->toBe($expected);
})->with([
    ['ＹＯＵＮＧ　ＡＮＩＭＡＬ', 'younganimal'],
    ['ONE PIECE', 'onepiece'],
    [null, ''],
]);

it('ignores a reading in brackets inside a title', function (string $input) {
    expect(Text::key($input))->toBe(Text::key('斉木楠雄のΨ難'));
})->with([
    '斉木楠雄のΨ (サイ) 難',
    '斉木楠雄のΨ(サイ)難',
    '斉木楠雄のΨ（サイ）難',
    '斉木楠雄のΨ（さい）難',
]);

it('keeps brackets that are not a kana reading', function (string $input, string $expected) {
    expect(Text::key($input))->toBe($expected);
})->with([
    ['ベルセルク (1)', 'ベルセルク(1)'],
    ['十二国記 (上)', '十二国記(上)'],
    ['Title (Deluxe)', 'title(deluxe)'],
    ['(サイ)', '(サイ)'],
]);

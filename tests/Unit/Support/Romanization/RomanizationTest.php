<?php

use App\Support\Romanization\Hepburn;
use App\Support\Romanization\RevisedRomanization;

describe('Hepburn (kana → romaji, with macrons)', function () {
    it('romanizes kana', function (string $kana, string $romaji) {
        expect(Hepburn::fromKana($kana))->toBe($romaji);
    })->with([
        'plain' => ['ベルセルク', 'beruseruku'],
        'long vowel mark' => ['ワンピース', 'wanpīsu'],
        'ou and oo' => ['ケンタロウ オオサカ', 'kentarō ōsaka'],
        'uu' => ['クウキ', 'kūki'],
        'hiragana' => ['しょうがっこう', 'shōgakkō'],
        'small tsu before ch' => ['マッチャ', 'matcha'],
        'middle dot between names' => ['ジョン・スミス', 'jon sumisu'],
        'n before a vowel or y' => ['ゲンイン ホンヤ', "gen'in hon'ya"],
        'n before b, m, p stays n' => ['シンブン', 'shinbun'],
        'shi chi tsu fu ji zu' => ['シチツフ ヂヅ ジズ', 'shichitsufu jizu jizu'],
        'wo is o' => ['ヲ', 'o'],
        'loanword sounds' => ['ヴァイオリン ティー ファイル ウェブ チェック ディスク', 'vaiorin tī fairu webu chekku disuku'],
        'half-width katakana' => ['ﾍﾞﾙｾﾙｸ', 'beruseruku'],
        'other characters kept' => ['ベルセルク 1 (DX)', 'beruseruku 1 (DX)'],
    ]);

    it('says nothing for text with kanji, which needs a reading', function () {
        expect(Hepburn::fromKana('三浦 建太郎'))->toBeNull()
            ->and(Hepburn::fromKana(''))->toBeNull();
    });
});

describe('Revised Romanization (Hangul → Latin)', function () {
    it('romanizes Hangul', function (string $hangul, string $latin) {
        expect(RevisedRomanization::fromHangul($hangul))->toBe($latin);
    })->with([
        'liaison' => ['한국어', 'hangugeo'],
        'words' => ['시인과 촌장', 'siingwa chonjang'],
        'place' => ['서울', 'seoul'],
        'initial g' => ['김치', 'gimchi'],
        'double l' => ['빨래', 'ppallae'],
        'nasalization' => ['박물관', 'bangmulgwan'],
        'h after a vowel' => ['혼자 하는', 'honja haneun'],
        'other characters kept' => ['나 혼자만 레벨업 1', 'na honjaman rebereop 1'],
    ]);

    it('says nothing for text with Hanja', function () {
        expect(RevisedRomanization::fromHangul('韓國語'))->toBeNull();
    });
});

<?php

namespace Omnistate\AnnuaireEntreprises;

use Omnistate\Model\Headcount;

/** INSEE's headcount ranges (tranches d'effectif salarié). */
final class Headcounts
{
    /** @var array<string, array{int, ?int}> code => [min, max] */
    public const RANGES = [
        'NN' => [0, 0], '00' => [0, 0], '01' => [1, 2], '02' => [3, 5], '03' => [6, 9],
        '11' => [10, 19], '12' => [20, 49], '21' => [50, 99], '22' => [100, 199], '31' => [200, 249],
        '32' => [250, 499], '41' => [500, 999], '42' => [1000, 1999], '51' => [2000, 4999],
        '52' => [5000, 9999], '53' => [10000, null],
    ];

    public static function of(?string $code, mixed $year = null): ?Headcount
    {
        if (null === $code || !isset(self::RANGES[$code])) {
            return null;
        }
        [$min, $max] = self::RANGES[$code];

        return new Headcount($min, $max, is_numeric($year) ? (int) $year : null, $code);
    }
}

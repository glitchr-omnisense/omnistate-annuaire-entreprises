<?php

namespace Omnistate\AnnuaireEntreprises;

use Omnistate\Model\LegalForm;

/**
 * INSEE's legal categories (catégories juridiques, level III): the codes a
 * company is registered under, their names and the short names in use. The
 * most common ones: another code keeps its number, without a name.
 */
final class LegalForms
{
    /** @var array<string, array{string, ?string}> code => [name, short name] */
    public const FORMS = [
        '1000' => ['Entrepreneur individuel', 'EI'],
        '2110' => ['Indivision entre personnes physiques', null],
        '2210' => ['Société créée de fait entre personnes physiques', null],
        '2310' => ['Société en participation entre personnes physiques', null],
        '5191' => ['Société de caution mutuelle', null],
        '5202' => ['Société en nom collectif', 'SNC'],
        '5306' => ['Société en commandite simple', 'SCS'],
        '5308' => ['Société en commandite par actions', 'SCA'],
        '5385' => ["Société d'exercice libéral en commandite par actions", 'SELCA'],
        '5458' => ['SARL coopérative ouvrière de production', 'SCOP SARL'],
        '5460' => ['Autre SARL coopérative', null],
        '5485' => ["Société d'exercice libéral à responsabilité limitée", 'SELARL'],
        '5498' => ['SARL unipersonnelle', 'EURL'],
        '5499' => ['Société à responsabilité limitée (sans autre indication)', 'SARL'],
        '5505' => ['SA à participation ouvrière à conseil d\'administration', null],
        '5510' => ['SA nationale à conseil d\'administration', null],
        '5585' => ["Société d'exercice libéral à forme anonyme à conseil d'administration", 'SELAFA'],
        '5599' => ["SA à conseil d'administration (s.a.i.)", 'SA'],
        '5699' => ['SA à directoire (s.a.i.)', 'SA'],
        '5710' => ['Société par actions simplifiée', 'SAS'],
        '5720' => ['Société par actions simplifiée à associé unique', 'SASU'],
        '5785' => ["Société d'exercice libéral par action simplifiée", 'SELAS'],
        '5800' => ['Société européenne', 'SE'],
        '6316' => ["Coopérative d'utilisation de matériel agricole en commun", 'CUMA'],
        '6317' => ['Société coopérative agricole', null],
        '6540' => ['Société civile immobilière', 'SCI'],
        '6589' => ['Société civile de moyens', 'SCM'],
        '6595' => ['Caisse locale de crédit mutuel', null],
        '6599' => ['Autre société civile', null],
        '7112' => ['Autorité administrative ou publique indépendante', null],
        '7120' => ['Service central d\'un ministère', null],
        '7210' => ['Commune et commune nouvelle', null],
        '7220' => ['Département', null],
        '7230' => ['Région', null],
        '7346' => ['Communauté de communes', null],
        '7348' => ["Communauté d'agglomération", null],
        '7383' => ['Établissement public national à caractère scientifique culturel et professionnel', null],
        '8110' => ['Régime général de la Sécurité Sociale', null],
        '8210' => ['Mutuelle', null],
        '9210' => ['Association non déclarée', null],
        '9220' => ['Association déclarée', null],
        '9230' => ["Association déclarée, reconnue d'utilité publique", null],
        '9260' => ['Association de droit local (Bas-Rhin, Haut-Rhin et Moselle)', null],
        '9300' => ['Fondation', null],
    ];

    public static function of(?string $code): ?LegalForm
    {
        if (null === $code || '' === $code) {
            return null;
        }
        [$label, $short] = self::FORMS[$code] ?? [null, null];

        return new LegalForm($code, $label, $short);
    }
}

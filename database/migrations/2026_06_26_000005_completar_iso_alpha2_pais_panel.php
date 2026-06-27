<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $mapa = [
            'Anguilla'                                      => 'AI',
            'Antillas holandesas'                           => 'AN',
            'Azerbaiyan'                                    => 'AZ',
            'Bosnia  y  herzegovina'                        => 'BA',
            'Brunei darussalam'                             => 'BN',
            'Camerún, república unida del'                  => 'CM',
            'Ceilan'                                        => 'LK',
            'Cocos (keeling), islas'                        => 'CC',
            'Cocos, islas'                                  => 'CC',
            'Congo, república del'                          => 'CG',
            'Cook, islas'                                   => 'CK',
            'Corea (norte), república popular democrática de' => 'KP',
            'Corea (norte).republica popular democratica de'  => 'KP',
            'Corea (sur). republica de'                     => 'KR',
            'Costa del marfil'                              => 'CI',
            'Curazao'                                       => 'CW',
            'Djibouti'                                      => 'DJ',
            'Dominica'                                      => 'DM',
            'Emiratos arabes unidos'                        => 'AE',
            'Falkland islas (malvinas)'                     => 'FK',
            'Caiman, islas'                                 => 'KY',
            'Feroe, islas'                                  => 'FO',
            'Georgia del sur y sandwich del sur, islas'     => 'GS',
            'Guayana francesa'                              => 'GF',
            'Guinea - bissau'                               => 'GW',
            'Haiti'                                         => 'HT',
            'Irán, república islámica del'                  => 'IR',
            'Iran. republica islamica del'                  => 'IR',
            'Irlanda (eire)'                                => 'IE',
            'Isla de man'                                   => 'IM',
            'Isla de navidad'                               => 'CX',
            'Islas pitcairn'                                => 'PN',
            'Jersey'                                        => 'JE',
            'Kazajstan'                                     => 'KZ',
            'Kirguistan'                                    => 'KG',
            'Kirguizistán'                                  => 'KG',
            'Kosovo'                                        => 'XK',
            'Laos, república popular democrática de'        => 'LA',
            'Lesotho'                                       => 'LS',
            'Macedonia'                                     => 'MK',
            'Marshall, islas'                               => 'MH',
            'Micronesia, estados federados de'              => 'FM',
            'Monaco'                                        => 'MC',
            'Montserrat, isla'                              => 'MS',
            'Niger'                                         => 'NE',
            'Niue, isla'                                    => 'NU',
            'Norfolk, isla'                                 => 'NF',
            'Paises bajos'                                  => 'NL',
            'Pakistan'                                      => 'PK',
            'Palau, islas'                                  => 'PW',
            'Panama'                                        => 'PA',
            'Papua nueva  guinea'                           => 'PG',
            'Peru'                                          => 'PE',
            'Pitcairn, isla'                                => 'PN',
            'Republica checa'                               => 'CZ',
            'Republica democratica del congo'               => 'CD',
            'Republica dominicana'                          => 'DO',
            'Rumania'                                       => 'RO',
            'Rusia federacion de'                           => 'RU',
            'Salomon, islas'                                => 'SB',
            'Samoa americana'                               => 'AS',
            'San  cristobal y nieves'                       => 'KN',
            'San  vicente y las granadinas'                 => 'VC',
            'San bartolome'                                 => 'BL',
            'San pedro y miquelon'                          => 'PM',
            'Santa elena'                                   => 'SH',
            'Santa elena, ascensión y tristán de acuña'     => 'SH',
            'Santa lucia'                                   => 'LC',
            'Santa sede'                                    => 'VA',
            'Serbia y montenegro'                           => 'CS',
            'Siria, república arabe de'                     => 'SY',
            'Siria. republica arabe de'                     => 'SY',
            'Sudafrica. republica de'                       => 'ZA',
            'Sudan del sur'                                 => 'SS',
            'Svalbard y jan mayen, islas'                   => 'SJ',
            'Swasilandia'                                   => 'SZ',
            'Tadjikistán'                                   => 'TJ',
            'Tanzania, república unida de'                  => 'TZ',
            'Territorio palestino ocupado'                  => 'PS',
            'Timor leste'                                   => 'TL',
            'Timor leste (timor del este)'                  => 'TL',
            'Tokelau'                                       => 'TK',
            'Tunez'                                         => 'TN',
            'Turcas y caicos, islas'                        => 'TC',
            'Turquia'                                       => 'TR',
            'Viet-nam'                                      => 'VN',
            'Virgenes, islas  (reino unido)'                => 'VG',
            'Virgenes, islas (estados unidos)'              => 'VI',
            'Wallis y futuna, islas'                        => 'WF',
            'Pacífico, islas del (estados unidos)'          => 'UM',
            'Pacifico, islas menores del (estados unidos)'  => 'UM',
        ];

        foreach ($mapa as $nombre => $iso) {
            DB::table('pais_panel')
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
                ->whereNull('iso_alpha2')
                ->update(['iso_alpha2' => $iso]);
        }
    }

    public function down(): void {}
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $mapa = [
            'Afganistán' => 'AF', 'Albania' => 'AL', 'Alemania' => 'DE',
            'Andorra' => 'AD', 'Angola' => 'AO', 'Antigua y barbuda' => 'AG',
            'Arabia saudita' => 'SA', 'Arabia saudí' => 'SA', 'Argelia' => 'DZ',
            'Argentina' => 'AR', 'Armenia' => 'AM', 'Australia' => 'AU',
            'Austria' => 'AT', 'Azerbaiyán' => 'AZ', 'Bahamas' => 'BS',
            'Bahréin' => 'BH', 'Bahrein' => 'BH', 'Bangladesh' => 'BD',
            'Barbados' => 'BB', 'Bélgica' => 'BE', 'Belgica' => 'BE',
            'Belice' => 'BZ', 'Benín' => 'BJ', 'Benin' => 'BJ',
            'Bielorrusia' => 'BY', 'Bolivia' => 'BO',
            'Bosnia y herzegovina' => 'BA', 'Botswana' => 'BW',
            'Brasil' => 'BR', 'Brunéi' => 'BN', 'Bulgaria' => 'BG',
            'Burkina faso' => 'BF', 'Burundi' => 'BI', 'Bután' => 'BT',
            'Cabo verde' => 'CV', 'Camboya' => 'KH', 'Camerún' => 'CM',
            'Canadá' => 'CA', 'Catar' => 'QA', 'Qatar' => 'QA',
            'Chad' => 'TD', 'Chile' => 'CL', 'China' => 'CN',
            'Chipre' => 'CY', 'Colombia' => 'CO', 'Comoras' => 'KM',
            'Congo' => 'CG', 'Corea del norte' => 'KP', 'Corea del sur' => 'KR',
            'Costa rica' => 'CR', 'Costa de marfil' => 'CI',
            'Croacia' => 'HR', 'Cuba' => 'CU', 'Dinamarca' => 'DK',
            'Yibuti' => 'DJ', 'Ecuador' => 'EC', 'Egipto' => 'EG',
            'El salvador' => 'SV', 'Emiratos árabes unidos' => 'AE',
            'Eritrea' => 'ER', 'Eslovaquia' => 'SK', 'Eslovenia' => 'SI',
            'España' => 'ES', 'Estados unidos' => 'US', 'Estonia' => 'EE',
            'Etiopía' => 'ET', 'Filipinas' => 'PH', 'Finlandia' => 'FI',
            'Fiyi' => 'FJ', 'Fiji' => 'FJ', 'Francia' => 'FR',
            'Gabón' => 'GA', 'Gambia' => 'GM', 'Georgia' => 'GE',
            'Ghana' => 'GH', 'Granada' => 'GD', 'Grecia' => 'GR',
            'Guatemala' => 'GT', 'Guinea' => 'GN',
            'Guinea ecuatorial' => 'GQ', 'Guinea-bisáu' => 'GW',
            'Guyana' => 'GY', 'Haití' => 'HT', 'Honduras' => 'HN',
            'Hungría' => 'HU', 'India' => 'IN', 'Indonesia' => 'ID',
            'Irak' => 'IQ', 'Irán' => 'IR', 'Irlanda' => 'IE',
            'Islandia' => 'IS', 'Israel' => 'IL', 'Italia' => 'IT',
            'Jamaica' => 'JM', 'Japón' => 'JP', 'Jordania' => 'JO',
            'Kazajistán' => 'KZ', 'Kenia' => 'KE', 'Kirguistán' => 'KG',
            'Kiribati' => 'KI', 'Kuwait' => 'KW', 'Laos' => 'LA',
            'Lesoto' => 'LS', 'Letonia' => 'LV', 'Líbano' => 'LB',
            'Liberia' => 'LR', 'Libia' => 'LY', 'Liechtenstein' => 'LI',
            'Lituania' => 'LT', 'Luxemburgo' => 'LU', 'Madagascar' => 'MG',
            'Malasia' => 'MY', 'Malaui' => 'MW', 'Malawi' => 'MW',
            'Maldivas' => 'MV', 'Malí' => 'ML', 'Malta' => 'MT',
            'Marruecos' => 'MA', 'Mauricio' => 'MU', 'Mauritania' => 'MR',
            'México' => 'MX', 'Micronesia' => 'FM', 'Moldavia' => 'MD',
            'Mónaco' => 'MC', 'Mongolia' => 'MN', 'Montenegro' => 'ME',
            'Mozambique' => 'MZ', 'Myanmar' => 'MM', 'Namibia' => 'NA',
            'Nauru' => 'NR', 'Nepal' => 'NP', 'Nicaragua' => 'NI',
            'Níger' => 'NE', 'Nigeria' => 'NG', 'Noruega' => 'NO',
            'Nueva zelanda' => 'NZ', 'Nueva zelandia' => 'NZ',
            'Omán' => 'OM', 'Países bajos' => 'NL', 'Holanda' => 'NL',
            'Pakistán' => 'PK', 'Palaos' => 'PW', 'Panamá' => 'PA',
            'Papúa nueva guinea' => 'PG', 'Paraguay' => 'PY',
            'Perú' => 'PE', 'Polonia' => 'PL', 'Portugal' => 'PT',
            'Reino unido' => 'GB', 'República centroafricana' => 'CF',
            'República checa' => 'CZ', 'Chequia' => 'CZ',
            'República democrática del congo' => 'CD',
            'República dominicana' => 'DO', 'Ruanda' => 'RW',
            'Rumanía' => 'RO', 'Rusia' => 'RU', 'Samoa' => 'WS',
            'San marino' => 'SM', 'San vicente y las granadinas' => 'VC',
            'Santa lucía' => 'LC', 'Santo tomé y príncipe' => 'ST',
            'Senegal' => 'SN', 'Serbia' => 'RS', 'Seychelles' => 'SC',
            'Sierra leona' => 'SL', 'Singapur' => 'SG', 'Siria' => 'SY',
            'Somalia' => 'SO', 'Sri lanka' => 'LK', 'Sudáfrica' => 'ZA',
            'Sudán' => 'SD', 'Sudán del sur' => 'SS', 'Suecia' => 'SE',
            'Suiza' => 'CH', 'Surinam' => 'SR', 'Tailandia' => 'TH',
            'Tanzania' => 'TZ', 'Tayikistán' => 'TJ',
            'Timor oriental' => 'TL', 'Togo' => 'TG', 'Tonga' => 'TO',
            'Trinidad y tobago' => 'TT', 'Túnez' => 'TN',
            'Turkmenistán' => 'TM', 'Turquía' => 'TR', 'Tuvalu' => 'TV',
            'Ucrania' => 'UA', 'Uganda' => 'UG', 'Uruguay' => 'UY',
            'Uzbekistán' => 'UZ', 'Vanuatu' => 'VU', 'Venezuela' => 'VE',
            'Vietnam' => 'VN', 'Yemen' => 'YE', 'Zambia' => 'ZM',
            'Zimbabue' => 'ZW', 'Zimbabwe' => 'ZW',
            'Guernsey' => 'GG', 'Aland islas' => 'AX',
            'Hong kong' => 'HK', 'Taiwán' => 'TW', 'Palestina' => 'PS',
            'Puerto rico' => 'PR', 'Aruba' => 'AW', 'Bermudas' => 'BM',
            'Gibraltar' => 'GI', 'Groenlandia' => 'GL', 'Guam' => 'GU',
            'Islas caimán' => 'KY', 'Islas feroe' => 'FO',
            'Islas malvinas' => 'FK', 'Macao' => 'MO',
            'Martinica' => 'MQ', 'Guadalupe' => 'GP',
            'Nueva caledonia' => 'NC', 'Polinesia francesa' => 'PF',
            'San pedro y miquelón' => 'PM', 'Islas cook' => 'CK',
            'Islas vírgenes británicas' => 'VG',
            'San cristóbal y nieves' => 'KN',
            'Reunión' => 'RE', 'Mayotte' => 'YT',
            'Territorios palestinos' => 'PS',
            'Sahara occidental' => 'EH',
            'Esuatini' => 'SZ', 'Suazilandia' => 'SZ',
            'Birmania' => 'MM',
            'Bósnia' => 'BA',
            'Cabo de verde' => 'CV',
            'Corea' => 'KR',
            'Émirats arabes unis' => 'AE',
            'Federación de rusia' => 'RU',
            'Gran bretaña' => 'GB',
            'Países bajos (holanda)' => 'NL',
            'República árabe siria' => 'SY',
            'República de corea' => 'KR',
            'República popular china' => 'CN',
            'Tayikistán' => 'TJ',
            'Territorios del sur de francia' => 'TF',
            'Wallis y futuna' => 'WF',
        ];

        foreach ($mapa as $nombre => $iso) {
            DB::table('pais_panel')
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
                ->whereNull('iso_alpha2')
                ->update(['iso_alpha2' => $iso]);
        }
    }

    public function down(): void
    {
        DB::table('pais_panel')->update(['iso_alpha2' => null]);
    }
};

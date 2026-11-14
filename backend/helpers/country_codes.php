<?php
declare(strict_types=1);

/**
 * Country Calling Codes and Flags Mapping
 */
function sf_get_country_calling_codes(): array
{
    static $codes = [
        // Priority
        'Philippines' => ['code' => '+63', 'flag' => 'PH'],
        // Asia
        'Bangladesh' => ['code' => '+880', 'flag' => 'BD'],
        'Bhutan' => ['code' => '+975', 'flag' => 'BT'],
        'Brunei' => ['code' => '+673', 'flag' => 'BN'],
        'Cambodia' => ['code' => '+855', 'flag' => 'KH'],
        'China' => ['code' => '+86', 'flag' => 'CN'],
        'Hong Kong' => ['code' => '+852', 'flag' => 'HK'],
        'India' => ['code' => '+91', 'flag' => 'IN'],
        'Indonesia' => ['code' => '+62', 'flag' => 'ID'],
        'Japan' => ['code' => '+81', 'flag' => 'JP'],
        'Kazakhstan' => ['code' => '+7', 'flag' => 'KZ'],
        'Kyrgyzstan' => ['code' => '+996', 'flag' => 'KG'],
        'Laos' => ['code' => '+856', 'flag' => 'LA'],
        'Macau' => ['code' => '+853', 'flag' => 'MO'],
        'Malaysia' => ['code' => '+60', 'flag' => 'MY'],
        'Maldives' => ['code' => '+960', 'flag' => 'MV'],
        'Mongolia' => ['code' => '+976', 'flag' => 'MN'],
        'Myanmar' => ['code' => '+95', 'flag' => 'MM'],
        'Nepal' => ['code' => '+977', 'flag' => 'NP'],
        'North Korea' => ['code' => '+850', 'flag' => 'KP'],
        'Pakistan' => ['code' => '+92', 'flag' => 'PK'],
        'Singapore' => ['code' => '+65', 'flag' => 'SG'],
        'South Korea' => ['code' => '+82', 'flag' => 'KR'],
        'Sri Lanka' => ['code' => '+94', 'flag' => 'LK'],
        'Taiwan' => ['code' => '+886', 'flag' => 'TW'],
        'Tajikistan' => ['code' => '+992', 'flag' => 'TJ'],
        'Thailand' => ['code' => '+66', 'flag' => 'TH'],
        'Timor-Leste' => ['code' => '+670', 'flag' => 'TL'],
        'Turkmenistan' => ['code' => '+993', 'flag' => 'TM'],
        'Uzbekistan' => ['code' => '+998', 'flag' => 'UZ'],
        'Vietnam' => ['code' => '+84', 'flag' => 'VN'],
        // Middle East
        'Bahrain' => ['code' => '+973', 'flag' => 'BH'],
        'Iran' => ['code' => '+98', 'flag' => 'IR'],
        'Iraq' => ['code' => '+964', 'flag' => 'IQ'],
        'Israel' => ['code' => '+972', 'flag' => 'IL'],
        'Jordan' => ['code' => '+962', 'flag' => 'JO'],
        'Kuwait' => ['code' => '+965', 'flag' => 'KW'],
        'Lebanon' => ['code' => '+961', 'flag' => 'LB'],
        'Oman' => ['code' => '+968', 'flag' => 'OM'],
        'Palestine' => ['code' => '+970', 'flag' => 'PS'],
        'Qatar' => ['code' => '+974', 'flag' => 'QA'],
        'Saudi Arabia' => ['code' => '+966', 'flag' => 'SA'],
        'Syria' => ['code' => '+963', 'flag' => 'SY'],
        'United Arab Emirates' => ['code' => '+971', 'flag' => 'AE'],
        'Yemen' => ['code' => '+967', 'flag' => 'YE'],
        // Europe
        'Albania' => ['code' => '+355', 'flag' => 'AL'],
        'Andorra' => ['code' => '+376', 'flag' => 'AD'],
        'Armenia' => ['code' => '+374', 'flag' => 'AM'],
        'Austria' => ['code' => '+43', 'flag' => 'AT'],
        'Azerbaijan' => ['code' => '+994', 'flag' => 'AZ'],
        'Belarus' => ['code' => '+375', 'flag' => 'BY'],
        'Belgium' => ['code' => '+32', 'flag' => 'BE'],
        'Bosnia and Herzegovina' => ['code' => '+387', 'flag' => 'BA'],
        'Bulgaria' => ['code' => '+359', 'flag' => 'BG'],
        'Croatia' => ['code' => '+385', 'flag' => 'HR'],
        'Cyprus' => ['code' => '+357', 'flag' => 'CY'],
        'Czech Republic' => ['code' => '+420', 'flag' => 'CZ'],
        'Denmark' => ['code' => '+45', 'flag' => 'DK'],
        'Estonia' => ['code' => '+372', 'flag' => 'EE'],
        'Finland' => ['code' => '+358', 'flag' => 'FI'],
        'France' => ['code' => '+33', 'flag' => 'FR'],
        'Georgia' => ['code' => '+995', 'flag' => 'GE'],
        'Germany' => ['code' => '+49', 'flag' => 'DE'],
        'Greece' => ['code' => '+30', 'flag' => 'GR'],
        'Hungary' => ['code' => '+36', 'flag' => 'HU'],
        'Iceland' => ['code' => '+354', 'flag' => 'IS'],
        'Ireland' => ['code' => '+353', 'flag' => 'IE'],
        'Italy' => ['code' => '+39', 'flag' => 'IT'],
        'Kosovo' => ['code' => '+383', 'flag' => 'XK'],
        'Latvia' => ['code' => '+371', 'flag' => 'LV'],
        'Liechtenstein' => ['code' => '+423', 'flag' => 'LI'],
        'Lithuania' => ['code' => '+370', 'flag' => 'LT'],
        'Luxembourg' => ['code' => '+352', 'flag' => 'LU'],
        'Malta' => ['code' => '+356', 'flag' => 'MT'],
        'Moldova' => ['code' => '+373', 'flag' => 'MD'],
        'Monaco' => ['code' => '+377', 'flag' => 'MC'],
        'Montenegro' => ['code' => '+382', 'flag' => 'ME'],
        'Netherlands' => ['code' => '+31', 'flag' => 'NL'],
        'North Macedonia' => ['code' => '+389', 'flag' => 'MK'],
        'Norway' => ['code' => '+47', 'flag' => 'NO'],
        'Poland' => ['code' => '+48', 'flag' => 'PL'],
        'Portugal' => ['code' => '+351', 'flag' => 'PT'],
        'Romania' => ['code' => '+40', 'flag' => 'RO'],
        'Russia' => ['code' => '+7', 'flag' => 'RU'],
        'San Marino' => ['code' => '+378', 'flag' => 'SM'],
        'Serbia' => ['code' => '+381', 'flag' => 'RS'],
        'Slovakia' => ['code' => '+421', 'flag' => 'SK'],
        'Slovenia' => ['code' => '+386', 'flag' => 'SI'],
        'Spain' => ['code' => '+34', 'flag' => 'ES'],
        'Sweden' => ['code' => '+46', 'flag' => 'SE'],
        'Switzerland' => ['code' => '+41', 'flag' => 'CH'],
        'Turkey' => ['code' => '+90', 'flag' => 'TR'],
        'Ukraine' => ['code' => '+380', 'flag' => 'UA'],
        'United Kingdom' => ['code' => '+44', 'flag' => 'GB'],
        'Vatican City' => ['code' => '+39', 'flag' => 'VA'],
        // Americas
        'Antigua and Barbuda' => ['code' => '+1268', 'flag' => 'AG'],
        'Argentina' => ['code' => '+54', 'flag' => 'AR'],
        'Bahamas' => ['code' => '+1242', 'flag' => 'BS'],
        'Barbados' => ['code' => '+1246', 'flag' => 'BB'],
        'Belize' => ['code' => '+501', 'flag' => 'BZ'],
        'Bolivia' => ['code' => '+591', 'flag' => 'BO'],
        'Brazil' => ['code' => '+55', 'flag' => 'BR'],
        'Canada' => ['code' => '+1', 'flag' => 'CA'],
        'Chile' => ['code' => '+56', 'flag' => 'CL'],
        'Colombia' => ['code' => '+57', 'flag' => 'CO'],
        'Costa Rica' => ['code' => '+506', 'flag' => 'CR'],
        'Cuba' => ['code' => '+53', 'flag' => 'CU'],
        'Dominica' => ['code' => '+1767', 'flag' => 'DM'],
        'Dominican Republic' => ['code' => '+1809', 'flag' => 'DO'],
        'Ecuador' => ['code' => '+593', 'flag' => 'EC'],
        'El Salvador' => ['code' => '+503', 'flag' => 'SV'],
        'Grenada' => ['code' => '+1473', 'flag' => 'GD'],
        'Guatemala' => ['code' => '+502', 'flag' => 'GT'],
        'Guyana' => ['code' => '+592', 'flag' => 'GY'],
        'Haiti' => ['code' => '+509', 'flag' => 'HT'],
        'Honduras' => ['code' => '+504', 'flag' => 'HN'],
        'Jamaica' => ['code' => '+1876', 'flag' => 'JM'],
        'Mexico' => ['code' => '+52', 'flag' => 'MX'],
        'Nicaragua' => ['code' => '+505', 'flag' => 'NI'],
        'Panama' => ['code' => '+507', 'flag' => 'PA'],
        'Paraguay' => ['code' => '+595', 'flag' => 'PY'],
        'Peru' => ['code' => '+51', 'flag' => 'PE'],
        'Saint Kitts and Nevis' => ['code' => '+1869', 'flag' => 'KN'],
        'Saint Lucia' => ['code' => '+1758', 'flag' => 'LC'],
        'Saint Vincent and the Grenadines' => ['code' => '+1784', 'flag' => 'VC'],
        'Suriname' => ['code' => '+597', 'flag' => 'SR'],
        'Trinidad and Tobago' => ['code' => '+1868', 'flag' => 'TT'],
        'United States' => ['code' => '+1', 'flag' => 'US'],
        'Uruguay' => ['code' => '+598', 'flag' => 'UY'],
        'Venezuela' => ['code' => '+58', 'flag' => 'VE'],
        // Africa
        'Algeria' => ['code' => '+213', 'flag' => 'DZ'],
        'Angola' => ['code' => '+244', 'flag' => 'AO'],
        'Benin' => ['code' => '+229', 'flag' => 'BJ'],
        'Botswana' => ['code' => '+267', 'flag' => 'BW'],
        'Burkina Faso' => ['code' => '+226', 'flag' => 'BF'],
        'Burundi' => ['code' => '+257', 'flag' => 'BI'],
        'Cabo Verde' => ['code' => '+238', 'flag' => 'CV'],
        'Cameroon' => ['code' => '+237', 'flag' => 'CM'],
        'Central African Republic' => ['code' => '+236', 'flag' => 'CF'],
        'Chad' => ['code' => '+235', 'flag' => 'TD'],
        'Comoros' => ['code' => '+269', 'flag' => 'KM'],
        'DR Congo' => ['code' => '+243', 'flag' => 'CD'],
        'Republic of the Congo' => ['code' => '+242', 'flag' => 'CG'],
        'Djibouti' => ['code' => '+253', 'flag' => 'DJ'],
        'Egypt' => ['code' => '+20', 'flag' => 'EG'],
        'Equatorial Guinea' => ['code' => '+240', 'flag' => 'GQ'],
        'Eritrea' => ['code' => '+291', 'flag' => 'ER'],
        'Eswatini' => ['code' => '+268', 'flag' => 'SZ'],
        'Ethiopia' => ['code' => '+251', 'flag' => 'ET'],
        'Gabon' => ['code' => '+241', 'flag' => 'GA'],
        'Gambia' => ['code' => '+220', 'flag' => 'GM'],
        'Ghana' => ['code' => '+233', 'flag' => 'GH'],
        'Guinea' => ['code' => '+224', 'flag' => 'GN'],
        'Guinea-Bissau' => ['code' => '+245', 'flag' => 'GW'],
        'Ivory Coast' => ['code' => '+225', 'flag' => 'CI'],
        'Kenya' => ['code' => '+254', 'flag' => 'KE'],
        'Lesotho' => ['code' => '+266', 'flag' => 'LS'],
        'Liberia' => ['code' => '+231', 'flag' => 'LR'],
        'Libya' => ['code' => '+218', 'flag' => 'LY'],
        'Madagascar' => ['code' => '+261', 'flag' => 'MG'],
        'Malawi' => ['code' => '+265', 'flag' => 'MW'],
        'Mali' => ['code' => '+223', 'flag' => 'ML'],
        'Mauritania' => ['code' => '+222', 'flag' => 'MR'],
        'Mauritius' => ['code' => '+230', 'flag' => 'MU'],
        'Morocco' => ['code' => '+212', 'flag' => 'MA'],
        'Mozambique' => ['code' => '+258', 'flag' => 'MZ'],
        'Namibia' => ['code' => '+264', 'flag' => 'NA'],
        'Niger' => ['code' => '+227', 'flag' => 'NE'],
        'Nigeria' => ['code' => '+234', 'flag' => 'NG'],
        'Rwanda' => ['code' => '+250', 'flag' => 'RW'],
        'São Tomé and Príncipe' => ['code' => '+239', 'flag' => 'ST'],
        'Senegal' => ['code' => '+221', 'flag' => 'SN'],
        'Seychelles' => ['code' => '+248', 'flag' => 'SC'],
        'Sierra Leone' => ['code' => '+232', 'flag' => 'SL'],
        'Somalia' => ['code' => '+252', 'flag' => 'SO'],
        'South Africa' => ['code' => '+27', 'flag' => 'ZA'],
        'South Sudan' => ['code' => '+211', 'flag' => 'SS'],
        'Sudan' => ['code' => '+249', 'flag' => 'SD'],
        'Tanzania' => ['code' => '+255', 'flag' => 'TZ'],
        'Togo' => ['code' => '+228', 'flag' => 'TG'],
        'Tunisia' => ['code' => '+216', 'flag' => 'TN'],
        'Uganda' => ['code' => '+256', 'flag' => 'UG'],
        'Zambia' => ['code' => '+260', 'flag' => 'ZM'],
        'Zimbabwe' => ['code' => '+263', 'flag' => 'ZW'],
        // Oceania
        'Australia' => ['code' => '+61', 'flag' => 'AU'],
        'Fiji' => ['code' => '+679', 'flag' => 'FJ'],
        'Kiribati' => ['code' => '+686', 'flag' => 'KI'],
        'Marshall Islands' => ['code' => '+692', 'flag' => 'MH'],
        'Micronesia' => ['code' => '+691', 'flag' => 'FM'],
        'Nauru' => ['code' => '+674', 'flag' => 'NR'],
        'New Zealand' => ['code' => '+64', 'flag' => 'NZ'],
        'Palau' => ['code' => '+680', 'flag' => 'PW'],
        'Papua New Guinea' => ['code' => '+675', 'flag' => 'PG'],
        'Samoa' => ['code' => '+685', 'flag' => 'WS'],
        'Solomon Islands' => ['code' => '+677', 'flag' => 'SB'],
        'Tonga' => ['code' => '+676', 'flag' => 'TO'],
        'Tuvalu' => ['code' => '+688', 'flag' => 'TV'],
        'Vanuatu' => ['code' => '+678', 'flag' => 'VU'],
    ];

    return $codes;
}

function sf_get_distinct_calling_codes(): array
{
    $all = sf_get_country_calling_codes();
    $list = [];

    // Philippines always at top
    $list['+63'] = ['code' => '+63', 'flag' => 'PH', 'display' => 'PH +63'];

    // Common tourist/guest origins prioritized
    $priority = ['United States', 'Australia', 'United Kingdom', 'Japan', 'South Korea', 'China', 'Singapore', 'Canada', 'Taiwan', 'Hong Kong', 'Malaysia', 'Germany', 'France', 'Cambodia', 'Thailand', 'Vietnam', 'Indonesia', 'India', 'United Arab Emirates', 'Saudi Arabia', 'Qatar', 'Spain', 'Italy', 'Netherlands', 'Switzerland', 'New Zealand', 'Russia', 'Brazil'];
    foreach ($priority as $cName) {
        if (isset($all[$cName])) {
            $code = $all[$cName]['code'];
            if (!isset($list[$code])) {
                $list[$code] = [
                    'code' => $code,
                    'flag' => $all[$cName]['flag'],
                    'display' => $all[$cName]['flag'] . ' ' . $code
                ];
            }
        }
    }

    // All remaining
    foreach ($all as $cName => $item) {
        $code = $item['code'];
        if (!isset($list[$code])) {
            $list[$code] = [
                'code' => $code,
                'flag' => $item['flag'],
                'display' => $item['flag'] . ' ' . $code
            ];
        }
    }

    return array_values($list);
}

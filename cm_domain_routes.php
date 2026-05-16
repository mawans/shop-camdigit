<?php
/**
 * CamDigit Custom Routes Hook
 * ─────────────────────────────────────────────────────────────────────────────
 * File location:  <whmcs_root>/includes/hooks/cm_domain_routes.php
 *
 * Registers the custom .cm domain order page at:
 *   https://shop-camdigit.cm/index.php?rp=/cm-domain-order
 *
 * WHMCS docs: https://developers.whmcs.com/hooks-reference/routes/
 * ─────────────────────────────────────────────────────────────────────────────
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\View\Menu\Item as MenuItem;

/**
 * Register the /cm-domain-order route.
 * The template file is: templates/<theme>/cm-domain-order.tpl
 */
add_hook('ClientAreaRoutes', 1, function ($vars) {
    return [
        'cm-domain-order' => [
            'methods'     => ['GET', 'POST'],
            'controller'  => function ($vars) {
                return [
                    'templatefile'  => 'cm-domain-order',
                    'breadcrumb'    => [
                        'index.php'  => 'Home',
                        'index.php?rp=/cm-domain-order' => '.CM Domain Order',
                    ],
                    'pagetitle'  => '.CM Domain Registration',
                    'tagline'    => 'Register your .cm domain in minutes',
                    // Pass country list to template
                    'countries'  => get_country_list(),
                ];
            },
        ],
    ];
});

/**
 * Returns ISO-3166-1 country list.
 * We build a minimal list here; WHMCS also exposes $countries in most templates
 * but injecting it explicitly is safer for a custom route.
 */
function get_country_list(): array
{
    // Full list — kept sorted alphabetically by name
    return [
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AO' => 'Angola',
        'AR' => 'Argentina',
        'AU' => 'Australia',
        'AT' => 'Austria',
        'BE' => 'Belgium',
        'BJ' => 'Benin',
        'BO' => 'Bolivia',
        'BR' => 'Brazil',
        'BF' => 'Burkina Faso',
        'BI' => 'Burundi',
        'CM' => 'Cameroon',
        'CA' => 'Canada',
        'CF' => 'Central African Republic',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CO' => 'Colombia',
        'CG' => 'Congo',
        'CD' => 'Congo (DRC)',
        'CI' => "Côte d'Ivoire",
        'CU' => 'Cuba',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DO' => 'Dominican Republic',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'GQ' => 'Equatorial Guinea',
        'ER' => 'Eritrea',
        'ET' => 'Ethiopia',
        'FI' => 'Finland',
        'FR' => 'France',
        'GA' => 'Gabon',
        'GM' => 'Gambia',
        'DE' => 'Germany',
        'GH' => 'Ghana',
        'GR' => 'Greece',
        'GT' => 'Guatemala',
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
        'HT' => 'Haiti',
        'HN' => 'Honduras',
        'HK' => 'Hong Kong',
        'HU' => 'Hungary',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IR' => 'Iran',
        'IQ' => 'Iraq',
        'IE' => 'Ireland',
        'IL' => 'Israel',
        'IT' => 'Italy',
        'JM' => 'Jamaica',
        'JP' => 'Japan',
        'JO' => 'Jordan',
        'KZ' => 'Kazakhstan',
        'KE' => 'Kenya',
        'KW' => 'Kuwait',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libya',
        'MG' => 'Madagascar',
        'MW' => 'Malawi',
        'MY' => 'Malaysia',
        'ML' => 'Mali',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'MX' => 'Mexico',
        'MA' => 'Morocco',
        'MZ' => 'Mozambique',
        'NA' => 'Namibia',
        'NL' => 'Netherlands',
        'NZ' => 'New Zealand',
        'NI' => 'Nicaragua',
        'NE' => 'Niger',
        'NG' => 'Nigeria',
        'NO' => 'Norway',
        'PK' => 'Pakistan',
        'PA' => 'Panama',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'QA' => 'Qatar',
        'RO' => 'Romania',
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'SS' => 'South Sudan',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SD' => 'Sudan',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syria',
        'TW' => 'Taiwan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TG' => 'Togo',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'UY' => 'Uruguay',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];
}

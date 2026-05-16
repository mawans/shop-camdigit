<?php
/**
 * CAMNIC .CM Registrar Module for WHMCS
 * ─────────────────────────────────────────────────────────────────────────────
 * Upload to: <whmcs_root>/modules/registrars/camnic/camnic.php
 *
 * Purpose: gives WHMCS a correct CheckAvailability implementation for .cm
 * domains using a direct TCP connection to whois.nic.cm (ANTIC's WHOIS server).
 *
 * After uploading:
 *   1. Admin → System Settings → Domain Registrars → Activate "CAMNIC"
 *   2. Admin → System Settings → Domain Pricing → Edit .cm row → Registrar: CAMNIC
 *   3. Clear any template/opcode cache if needed
 * ─────────────────────────────────────────────────────────────────────────────
 */

use WHMCS\Domains\DomainLookup\ResultsList;
use WHMCS\Domains\DomainLookup\SearchResult;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

// ── Module metadata ───────────────────────────────────────────────────────────
function camnic_MetaData(): array
{
    return [
        'DisplayName' => 'CAMNIC (.cm Domains)',
        'APIVersion'  => '1.1',
    ];
}

// No configuration fields needed — WHOIS server is hardcoded
function camnic_getConfigArray(): array
{
    return [];
}

// ── Domain availability check ─────────────────────────────────────────────────
/**
 * Called by WHMCS cart.php when searching for .cm domain availability.
 * Uses a direct TCP connection to whois.nic.cm (port 43).
 */
function camnic_CheckAvailability(array $params): ResultsList
{
    $results    = new ResultsList();
    $searchTerm = strtolower(trim((string) ($params['searchTerm'] ?? '')));

    foreach ((array) ($params['tldsToInclude'] ?? []) as $tld) {
        $domain = $searchTerm . $tld;
        $result = new SearchResult($searchTerm, $tld);

        $status = _camnic_whois_check($domain);

        if ($status === 'available') {
            $result->setStatus(SearchResult::STATUS_NOT_REGISTERED);
        } elseif ($status === 'taken') {
            $result->setStatus(SearchResult::STATUS_REGISTERED);
        } else {
            // If the WHOIS server is unreachable or the answer is ambiguous,
            // avoid a false "unavailable" result in the cart.
            $result->setStatus(SearchResult::STATUS_TLD_NOT_SUPPORTED);
        }

        $results->append($result);
    }

    return $results;
}

// ── Internal WHOIS helper ─────────────────────────────────────────────────────
function _camnic_whois_check(string $domain): string
{
    $fp = @fsockopen('whois.nic.cm', 43, $errno, $errstr, 10);
    if (!$fp) {
        error_log("[camnic] whois.nic.cm unreachable for $domain: $errstr ($errno)");
        return 'unknown';
    }

    fwrite($fp, $domain . "\r\n");

    $raw = '';
    stream_set_timeout($fp, 8);
    while (!feof($fp)) {
        $chunk = fread($fp, 4096);
        if ($chunk === false || $chunk === '') break;
        $raw .= $chunk;
    }
    fclose($fp);

    // "The queried object does not exist: No Object Found" → available
    if (preg_match('/no object found/i', $raw)) {
        return 'available';
    }

    // "Registry Domain ID:" only present when domain is registered
    if (preg_match('/Registry Domain ID/i', $raw)) {
        return 'taken';
    }

    error_log("[camnic] Ambiguous WHOIS for $domain: " . substr($raw, 0, 200));
    return 'unknown';
}

// ── Required stub functions ───────────────────────────────────────────────────
// WHMCS requires these to exist even if the registrar handles orders manually.

function camnic_RegisterDomain(array $params): array
{
    return ['error' => 'Registration is handled manually. Please contact CamDigit support.'];
}

function camnic_TransferDomain(array $params): array
{
    return ['error' => 'Transfer is handled manually. Please contact CamDigit support.'];
}

function camnic_RenewDomain(array $params): array
{
    return ['error' => 'Renewal is handled manually. Please contact CamDigit support.'];
}

function camnic_GetNameservers(array $params): array
{
    return [
        'ns1' => 'ns1.camdigit.cm',
        'ns2' => 'ns2.camdigit.cm',
    ];
}

function camnic_SaveNameservers(array $params): array
{
    return ['error' => 'Please update nameservers manually via the CAMNIC portal.'];
}

function camnic_GetRegistrarLock(array $params): string
{
    return 'unlocked';
}

function camnic_SaveRegistrarLock(array $params): array
{
    return [];
}

function camnic_GetContactDetails(array $params): array
{
    return [];
}

function camnic_SaveContactDetails(array $params): array
{
    return [];
}

function camnic_GetDNS(array $params): array
{
    return [];
}

function camnic_SaveDNS(array $params): array
{
    return ['error' => 'DNS management is not supported by this module.'];
}

function camnic_RequestDelete(array $params): array
{
    return ['error' => 'Deletion is handled manually. Please contact CamDigit support.'];
}

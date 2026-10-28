<?php

namespace App\Support;

/**
 * Classifies an autonomous system (from GeoLite2-ASN) as a data center.
 *
 * People browse from access networks (ISPs, mobile carriers, company
 * networks); traffic out of cloud and hosting networks is almost always
 * automated — headless browsers that run the snippet with a genuine browser
 * user agent, which CrawlerDetector cannot recognise.
 */
class DataCenterNetworks
{
    /**
     * Cloud and hosting providers whose network names give no hint of what
     * they are.
     */
    private const ASNS = [
        16509 => 'Amazon Web Services',
        14618 => 'Amazon Web Services',
        15169 => 'Google',
        396982 => 'Google Cloud',
        8075 => 'Microsoft Azure',
        31898 => 'Oracle Cloud',
        36351 => 'IBM Cloud',
        45102 => 'Alibaba Cloud',
        37963 => 'Alibaba Cloud',
        45090 => 'Tencent Cloud',
        132203 => 'Tencent Cloud',
        136907 => 'Huawei Cloud',
        55990 => 'Huawei Cloud',
        55967 => 'Baidu',
        38365 => 'Baidu',
        396986 => 'ByteDance',
        135377 => 'UCloud',
        14061 => 'DigitalOcean',
        63949 => 'Akamai Connected Cloud (Linode)',
        20473 => 'Vultr',
        24940 => 'Hetzner',
        213230 => 'Hetzner',
        212317 => 'Hetzner',
        16276 => 'OVHcloud',
        12876 => 'Scaleway',
        51167 => 'Contabo',
        197540 => 'netcup',
        8560 => 'IONOS',
        6724 => 'Strato',
        20773 => 'Host Europe',
        47583 => 'Hostinger',
        29222 => 'Infomaniak',
        61098 => 'Exoscale',
        60781 => 'Leaseweb',
        28753 => 'Leaseweb',
        16265 => 'Leaseweb',
        202053 => 'UpCloud',
        40509 => 'Fly.io',
        31034 => 'Aruba',
        200350 => 'Yandex Cloud',
        49505 => 'Selectel',
        9123 => 'Timeweb',
        57043 => 'HOSTKEY',
        199524 => 'Gcore',
        7979 => 'Servers.com',
        29802 => 'Hivelocity',
        40676 => 'Psychz',
        8100 => 'QuadraNet',
        36352 => 'ColoCrossing',
        32097 => 'WholeSale Internet',
        27357 => 'Rackspace',
        19994 => 'Rackspace',
        54825 => 'Equinix Metal',
        26496 => 'GoDaddy',
        46606 => 'Unified Layer',
    ];

    /**
     * Cloud and CDN networks that people browse through, so they never count:
     * iCloud Private Relay egresses through Akamai, Cloudflare and Fastly,
     * Cloudflare also runs WARP, and Forcepoint proxies company staff.
     */
    private const PEOPLE = [
        13335 => 'Cloudflare',
        36183 => 'Akamai',
        20940 => 'Akamai',
        54113 => 'Fastly',
        44444 => 'Forcepoint Cloud',
    ];

    /**
     * The long tail of smaller hosters names itself after what it sells;
     * Chinese carriers name their data center networks "IDC".
     */
    private const HOSTING_NAME = '/\b(hosting|hoster|servers?|data ?cent(er|re)s?|colocation|dedicated|idc)\b/i';

    /**
     * "Cloud" and "VPS" also turn up in the names of small wireless, cable
     * and hotspot providers ("Cloud 9 Wireless", "The Cloud Networks"), so
     * they only count when the name does not describe an access network too.
     */
    private const CLOUD_NAME = '/\b(cloud|vps)\b/i';

    private const ACCESS_NAME = '/\b(broadband|wireless|cable|fib(er|re)|isp|telecom\w*|telekom\w*|communications?|mobile|networks?)\b/i';

    public static function contains(int $asn, ?string $organization): bool
    {
        if (isset(self::PEOPLE[$asn])) {
            return false;
        }

        if (isset(self::ASNS[$asn])) {
            return true;
        }

        if ($organization === null) {
            return false;
        }

        return preg_match(self::HOSTING_NAME, $organization) === 1
            || (preg_match(self::CLOUD_NAME, $organization) === 1 && preg_match(self::ACCESS_NAME, $organization) !== 1);
    }
}

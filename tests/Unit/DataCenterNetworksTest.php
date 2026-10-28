<?php

namespace Tests\Unit;

use App\Support\DataCenterNetworks;
use PHPUnit\Framework\TestCase;

class DataCenterNetworksTest extends TestCase
{
    public function test_known_cloud_and_hosting_networks_are_data_centers(): void
    {
        $this->assertTrue(DataCenterNetworks::contains(24940, 'Hetzner Online GmbH'));
        $this->assertTrue(DataCenterNetworks::contains(16509, 'AMAZON-02'));
        $this->assertTrue(DataCenterNetworks::contains(37963, 'Hangzhou Alibaba Advertising Co.,Ltd.'));
        $this->assertTrue(DataCenterNetworks::contains(45090, 'Shenzhen Tencent Computer Systems Company Limited'));
        $this->assertTrue(DataCenterNetworks::contains(14061, null));
    }

    public function test_unlisted_hosters_are_recognised_by_their_network_name(): void
    {
        $this->assertTrue(DataCenterNetworks::contains(64500, 'Example Hosting Ltd'));
        $this->assertTrue(DataCenterNetworks::contains(64501, 'EXAMPLE-DATACENTER'));
        $this->assertTrue(DataCenterNetworks::contains(64502, 'Example Data Center GmbH'));
        $this->assertTrue(DataCenterNetworks::contains(64503, 'Example VPS s.r.o.'));
        $this->assertTrue(DataCenterNetworks::contains(64504, 'Example Cloud Computing'));
        $this->assertTrue(DataCenterNetworks::contains(23724, 'IDC, China Telecommunications Corporation'));
    }

    public function test_access_networks_are_not_data_centers(): void
    {
        $this->assertFalse(DataCenterNetworks::contains(3303, 'Swisscom (Schweiz) AG'));
        $this->assertFalse(DataCenterNetworks::contains(3320, 'Deutsche Telekom AG'));
        $this->assertFalse(DataCenterNetworks::contains(4808, 'China Unicom Beijing Province Network'));
        $this->assertFalse(DataCenterNetworks::contains(64505, null));
    }

    public function test_networks_that_carry_people_are_never_data_centers(): void
    {
        // iCloud Private Relay, Cloudflare WARP and company proxies.
        $this->assertFalse(DataCenterNetworks::contains(13335, 'CLOUDFLARENET'));
        $this->assertFalse(DataCenterNetworks::contains(36183, 'Akamai Technologies, Inc.'));
        $this->assertFalse(DataCenterNetworks::contains(54113, 'FASTLY'));
        $this->assertFalse(DataCenterNetworks::contains(44444, 'Forcepoint Cloud Ltd'));
    }

    public function test_cloud_in_the_name_of_an_access_network_does_not_count(): void
    {
        $this->assertTrue(DataCenterNetworks::contains(23576, 'NAVER Cloud Corp.'));
        $this->assertFalse(DataCenterNetworks::contains(401694, 'Cloud 9 Wireless LLC'));
        $this->assertFalse(DataCenterNetworks::contains(42390, 'The Cloud Networks Germany GmbH'));
        $this->assertFalse(DataCenterNetworks::contains(149022, 'Cloud Net Cable and Telecom Inc.'));
        $this->assertFalse(DataCenterNetworks::contains(135756, 'VPS Broadband And Telecommunications Pvt Ltd'));
    }

    public function test_a_hosting_word_inside_another_word_does_not_count(): void
    {
        $this->assertFalse(DataCenterNetworks::contains(64506, 'Webservice Kabelnetz GmbH'));
        $this->assertFalse(DataCenterNetworks::contains(64507, 'iCloudish Telecom'));
    }
}

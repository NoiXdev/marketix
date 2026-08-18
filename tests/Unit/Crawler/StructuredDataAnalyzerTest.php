<?php

namespace Tests\Unit\Crawler;

use App\Crawler\AnalyzerResult;
use App\Crawler\Analyzers\StructuredDataAnalyzer;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;

class StructuredDataAnalyzerTest extends TestCase
{
    private function runAnalyzer(string $html, string $url = 'https://x.test/page'): AnalyzerResult
    {
        $wrapped = '<html><head></head><body>'.$html.'</body></html>';

        return (new StructuredDataAnalyzer)->analyze(
            new Crawler($wrapped),
            new PageContext($url, 200, 'x.test'),
        );
    }

    public function test_valid_organization_jsonld_produces_one_valid_item(): void
    {
        $html = '<script type="application/ld+json">{"@context":"https://schema.org","@type":"Organization","name":"X"}</script>';

        $result = $this->runAnalyzer($html);

        $this->assertSame([], $result->issues);
        $this->assertSame(['Organization'], $result->data['structured_data']);
        $this->assertCount(1, $result->data['structured_data_items']);
        $this->assertSame(
            ['format' => 'json-ld', 'type' => 'Organization', 'valid' => true, 'missing' => []],
            $result->data['structured_data_items'][0],
        );
    }

    public function test_invalid_json_flags_parse_error(): void
    {
        $html = '<script type="application/ld+json">{not valid json}</script>';

        $result = $this->runAnalyzer($html);

        $item = $result->data['structured_data_items'][0];
        $this->assertSame('json-ld', $item['format']);
        $this->assertNull($item['type']);
        $this->assertFalse($item['valid']);
        $this->assertSame('parse', $item['error']);
        $this->assertContains(IssueCode::StructuredDataParseError, $result->issues);
    }

    public function test_graph_with_two_typed_nodes_produces_two_items(): void
    {
        $html = '<script type="application/ld+json">'
            .'{"@graph":[{"@type":"WebPage","name":"A"},{"@type":"BreadcrumbList","itemListElement":[1,2]}]}'
            .'</script>';

        $result = $this->runAnalyzer($html);

        $items = $result->data['structured_data_items'];
        $this->assertCount(2, $items);
        $this->assertEqualsCanonicalizing(['WebPage', 'BreadcrumbList'], array_column($items, 'type'));
        $this->assertSame([], $result->issues);
    }

    public function test_bare_top_level_array_of_nodes_produces_one_item_per_node(): void
    {
        $html = '<script type="application/ld+json">'
            .'[{"@type":"Organization","name":"X"},{"@type":"WebSite","name":"Y","url":"https://x.test"}]'
            .'</script>';

        $result = $this->runAnalyzer($html);

        $items = $result->data['structured_data_items'];
        $this->assertCount(2, $items);
        $this->assertEqualsCanonicalizing(['Organization', 'WebSite'], array_column($items, 'type'));
        foreach ($items as $item) {
            $this->assertTrue($item['valid']);
            $this->assertSame([], $item['missing']);
        }
        $this->assertSame([], $result->issues);
    }

    public function test_node_with_own_type_and_graph_child_produces_both_items(): void
    {
        $html = '<script type="application/ld+json">'
            .'{"@context":"https://schema.org","@type":"WebPage","name":"Home",'
            .'"@graph":[{"@type":"Organization","name":"X"}]}'
            .'</script>';

        $result = $this->runAnalyzer($html);

        $items = $result->data['structured_data_items'];
        $this->assertCount(2, $items);
        $this->assertEqualsCanonicalizing(['WebPage', 'Organization'], array_column($items, 'type'));
    }

    public function test_product_missing_name_is_invalid(): void
    {
        $html = '<script type="application/ld+json">{"@type":"Product","sku":"123"}</script>';

        $result = $this->runAnalyzer($html);

        $item = $result->data['structured_data_items'][0];
        $this->assertSame('Product', $item['type']);
        $this->assertFalse($item['valid']);
        $this->assertSame(['name'], $item['missing']);
        $this->assertContains(IssueCode::StructuredDataInvalid, $result->issues);
    }

    public function test_node_without_type_flags_missing_type(): void
    {
        $html = '<script type="application/ld+json">{"name":"NoType"}</script>';

        $result = $this->runAnalyzer($html);

        $item = $result->data['structured_data_items'][0];
        $this->assertNull($item['type']);
        $this->assertFalse($item['valid']);
        $this->assertSame('no_type', $item['error']);
        $this->assertContains(IssueCode::StructuredDataMissingType, $result->issues);
    }

    public function test_microdata_product_item(): void
    {
        $html = '<div itemscope itemtype="https://schema.org/Product"><span itemprop="name">Widget</span></div>';

        $result = $this->runAnalyzer($html);

        $item = $result->data['structured_data_items'][0];
        $this->assertSame('microdata', $item['format']);
        $this->assertSame('Product', $item['type']);
        $this->assertTrue($item['valid']);
        $this->assertSame([], $item['missing']);
    }

    public function test_microdata_nested_itemscope_properties_are_not_double_counted(): void
    {
        $html = '<div itemscope itemtype="https://schema.org/Organization">'
            .'<div itemprop="founder" itemscope itemtype="https://schema.org/Person">'
            .'<span itemprop="name">Jane</span>'
            .'</div>'
            .'</div>';

        $result = $this->runAnalyzer($html);

        $items = $result->data['structured_data_items'];
        $org = null;
        $person = null;
        foreach ($items as $item) {
            if ($item['type'] === 'Organization') {
                $org = $item;
            }
            if ($item['type'] === 'Person') {
                $person = $item;
            }
        }

        $this->assertNotNull($org);
        $this->assertFalse($org['valid']);
        $this->assertSame(['name'], $org['missing']);

        $this->assertNotNull($person);
        $this->assertTrue($person['valid']);
    }

    public function test_rdfa_product_item(): void
    {
        $html = '<div typeof="Product"><span property="name">Widget</span></div>';

        $result = $this->runAnalyzer($html);

        $item = $result->data['structured_data_items'][0];
        $this->assertSame('rdfa', $item['format']);
        $this->assertSame('Product', $item['type']);
        $this->assertTrue($item['valid']);
    }

    public function test_rdfa_product_item_with_schema_prefix(): void
    {
        $html = '<div typeof="schema:Product"><span property="name">Widget</span></div>';

        $result = $this->runAnalyzer($html);

        $item = $result->data['structured_data_items'][0];
        $this->assertSame('Product', $item['type']);
        $this->assertTrue($item['valid']);
    }

    public function test_page_with_no_structured_data_flags_missing(): void
    {
        $result = $this->runAnalyzer('<p>nothing here</p>');

        $this->assertContains(IssueCode::MissingStructuredData, $result->issues);
        $this->assertSame([], $result->data['structured_data']);
        $this->assertNull($result->data['structured_data_items']);
    }

    public function test_imageobject_with_only_url_alternative_is_valid(): void
    {
        $html = '<script type="application/ld+json">{"@type":"ImageObject","url":"https://x.test/img.jpg"}</script>';

        $result = $this->runAnalyzer($html);

        $item = $result->data['structured_data_items'][0];
        $this->assertSame('ImageObject', $item['type']);
        $this->assertTrue($item['valid']);
        $this->assertSame([], $item['missing']);
    }
}

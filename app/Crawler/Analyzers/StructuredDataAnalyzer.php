<?php

namespace App\Crawler\Analyzers;

use App\Crawler\AnalyzerResult;
use App\Crawler\IssueCode;
use App\Crawler\PageContext;
use App\Crawler\SchemaRules;
use DOMElement;
use DOMXPath;
use Symfony\Component\DomCrawler\Crawler;

class StructuredDataAnalyzer implements Analyzer
{
    public function analyze(Crawler $dom, PageContext $ctx): AnalyzerResult
    {
        $r = new AnalyzerResult;

        /** @var array<int, array<string, mixed>> $items */
        $items = [];
        $hasParseError = false;
        $hasMissingType = false;
        $hasInvalid = false;

        $dom->filter('script[type="application/ld+json"]')->each(
            function (Crawler $node) use (&$items, &$hasParseError, &$hasMissingType, &$hasInvalid) {
                $decoded = json_decode($node->text(''), true);

                if (! is_array($decoded)) {
                    $items[] = ['format' => 'json-ld', 'type' => null, 'valid' => false, 'missing' => [], 'error' => 'parse'];
                    $hasParseError = true;

                    return;
                }

                $topNodes = array_is_list($decoded) ? $decoded : [$decoded];
                foreach ($topNodes as $topNode) {
                    if (! is_array($topNode)) {
                        continue;
                    }
                    foreach ($this->jsonLdLeafNodes($topNode) as $leaf) {
                        $this->addJsonLdItem($leaf, $items, $hasMissingType, $hasInvalid);
                    }
                }
            }
        );

        $dom->filter('[itemscope]')->each(
            function (Crawler $node) use (&$items, &$hasMissingType, &$hasInvalid) {
                $el = $node->getNode(0);
                if ($el instanceof DOMElement) {
                    $this->addMicrodataItem($el, $items, $hasMissingType, $hasInvalid);
                }
            }
        );

        $dom->filter('[typeof]')->each(
            function (Crawler $node) use (&$items, &$hasMissingType, &$hasInvalid) {
                $el = $node->getNode(0);
                if ($el instanceof DOMElement) {
                    $this->addRdfaItem($el, $items, $hasMissingType, $hasInvalid);
                }
            }
        );

        $types = array_values(array_unique(array_filter(array_map(
            fn (array $item) => $item['type'],
            $items,
        ))));

        $r->add('structured_data', $types);
        $r->add('structured_data_items', $items === [] ? null : $items);

        if ($hasParseError) {
            $r->issue(IssueCode::StructuredDataParseError);
        }
        if ($hasMissingType) {
            $r->issue(IssueCode::StructuredDataMissingType);
        }
        if ($hasInvalid) {
            $r->issue(IssueCode::StructuredDataInvalid);
        }
        if ($items === []) {
            $r->issue(IssueCode::MissingStructuredData);
        }

        return $r;
    }

    /**
     * Expand `@graph` wrappers into their constituent nodes. A wrapper that only
     * groups other nodes via `@graph` is not itself treated as a leaf/typed node.
     *
     * @return array<int, array<string, mixed>>
     */
    private function jsonLdLeafNodes(array $node): array
    {
        if (isset($node['@graph']) && is_array($node['@graph'])) {
            $out = [];
            foreach ($node['@graph'] as $child) {
                if (is_array($child)) {
                    $out = array_merge($out, $this->jsonLdLeafNodes($child));
                }
            }

            // A node carrying @graph is normally a pure container, but if it
            // also declares its own @type it is itself a typed node and must
            // not be dropped in favour of only its graph children.
            if (isset($node['@type'])) {
                $out[] = $node;
            }

            return $out;
        }

        return [$node];
    }

    /** @param array<int, array<string, mixed>> $items */
    private function addJsonLdItem(array $node, array &$items, bool &$hasMissingType, bool &$hasInvalid): void
    {
        $types = [];
        if (isset($node['@type'])) {
            foreach ((array) $node['@type'] as $t) {
                if (is_string($t) && $t !== '') {
                    $types[] = $t;
                }
            }
        }

        if ($types === []) {
            $items[] = ['format' => 'json-ld', 'type' => null, 'valid' => false, 'missing' => [], 'error' => 'no_type'];
            $hasMissingType = true;

            return;
        }

        $props = $this->nonEmptyKeys($node);
        foreach ($types as $type) {
            [$valid, $missing] = $this->validateType($type, $props);
            $items[] = ['format' => 'json-ld', 'type' => $type, 'valid' => $valid, 'missing' => $missing];
            if ($missing !== []) {
                $hasInvalid = true;
            }
        }
    }

    /** @param array<int, array<string, mixed>> $items */
    private function addMicrodataItem(DOMElement $el, array &$items, bool &$hasMissingType, bool &$hasInvalid): void
    {
        $type = $this->microdataType($el);

        if ($type === null) {
            $items[] = ['format' => 'microdata', 'type' => null, 'valid' => false, 'missing' => [], 'error' => 'no_type'];
            $hasMissingType = true;

            return;
        }

        $props = $this->scopedAttrTokens($el, 'itemprop', 'itemscope');
        [$valid, $missing] = $this->validateType($type, $props);
        $items[] = ['format' => 'microdata', 'type' => $type, 'valid' => $valid, 'missing' => $missing];
        if ($missing !== []) {
            $hasInvalid = true;
        }
    }

    /** @param array<int, array<string, mixed>> $items */
    private function addRdfaItem(DOMElement $el, array &$items, bool &$hasMissingType, bool &$hasInvalid): void
    {
        $type = $this->rdfaType($el);

        if ($type === null) {
            $items[] = ['format' => 'rdfa', 'type' => null, 'valid' => false, 'missing' => [], 'error' => 'no_type'];
            $hasMissingType = true;

            return;
        }

        $props = $this->scopedAttrTokens($el, 'property', 'typeof');
        [$valid, $missing] = $this->validateType($type, $props);
        $items[] = ['format' => 'rdfa', 'type' => $type, 'valid' => $valid, 'missing' => $missing];
        if ($missing !== []) {
            $hasInvalid = true;
        }
    }

    private function microdataType(DOMElement $el): ?string
    {
        $attr = trim($el->getAttribute('itemtype'));
        if ($attr === '') {
            return null;
        }

        $first = strtok($attr, " \t\n\r");
        if ($first === false || $first === '') {
            return null;
        }

        $segments = explode('/', rtrim($first, '/'));
        $last = end($segments);

        return $last !== '' ? $last : null;
    }

    private function rdfaType(DOMElement $el): ?string
    {
        $attr = trim($el->getAttribute('typeof'));
        if ($attr === '') {
            return null;
        }

        $first = strtok($attr, " \t\n\r");
        if ($first === false || $first === '') {
            return null;
        }

        $stripped = preg_replace('/^schema:/i', '', $first);

        return $stripped !== '' ? $stripped : null;
    }

    /**
     * Collect the whitespace-separated tokens of `$attr` on descendants of `$el`,
     * excluding descendants nested inside a closer element carrying `$scopeAttr`
     * (i.e. a nested item's own properties don't leak into the outer item).
     *
     * @return string[]
     */
    private function scopedAttrTokens(DOMElement $el, string $attr, string $scopeAttr): array
    {
        $xpath = new DOMXPath($el->ownerDocument);
        $nodes = $xpath->query(".//*[@{$attr}]", $el);

        $tokens = [];
        if ($nodes === false) {
            return $tokens;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $ancestor = $node->parentNode;
            $scoped = true;
            while ($ancestor !== null && $ancestor !== $el) {
                if ($ancestor instanceof DOMElement && $ancestor->hasAttribute($scopeAttr)) {
                    $scoped = false;
                    break;
                }
                $ancestor = $ancestor->parentNode;
            }

            if (! $scoped) {
                continue;
            }

            foreach (preg_split('/\s+/', trim($node->getAttribute($attr))) as $token) {
                if ($token !== '') {
                    $tokens[] = $token;
                }
            }
        }

        return $tokens;
    }

    /** @return string[] */
    private function nonEmptyKeys(array $node): array
    {
        $out = [];
        foreach ($node as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $out[] = $key;
        }

        return $out;
    }

    /**
     * @param  string[]  $presentProps
     * @return array{0: bool, 1: string[]}
     */
    private function validateType(string $type, array $presentProps): array
    {
        $required = SchemaRules::requiredFor($type);
        $missing = [];

        foreach ($required as $token) {
            $satisfied = false;
            foreach (explode('|', $token) as $alt) {
                if (in_array($alt, $presentProps, true)) {
                    $satisfied = true;
                    break;
                }
            }
            if (! $satisfied) {
                $missing[] = $token;
            }
        }

        $valid = $required === [] ? true : $missing === [];

        return [$valid, $missing];
    }
}

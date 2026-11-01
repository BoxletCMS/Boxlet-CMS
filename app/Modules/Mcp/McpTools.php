<?php

namespace App\Modules\Mcp;

use App\Core\Container;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * WHAT AN AI CLIENT MAY DO (PLAN.md O-78; the owner, 2026-10-23: "popis je ok", a design set kept
 * and never applied, "po tvojoj preporuki").
 *
 * Reading: the site, its pages and each page's document, the block types, the pictures, the
 * design and the design set format. Writing, with a key that writes: a new page, a page's
 * document saved as its DRAFT, a picture fetched into the library, a design set kept for the
 * owner to try. Publishing only where the owner allowed it in Settings. Nothing is deleted.
 *
 * The tools a key may use are the ones listed to it (the protocol lets the list follow the
 * credentials), so a model is never offered what it would be refused.
 *
 * A tool that cannot do what it was asked says why in its result (isError), in words a model can
 * correct itself from; the protocol's own errors are McpServer's.
 *
 * @phpstan-import-type Key from McpKeys
 */
final class McpTools
{
    private const EMPTY = ['type' => 'object', 'additionalProperties' => false];
    private const DOCUMENT = 'Sections and blocks as get_page gives them. sections: [{key, id, layout, stack, style}], blocks: '
        . '[{key, id, type, content, layout, options, style, section, column}]. Keep key and id of what exists; a new section has '
        . 'key m1, m2…, a new block n1, n2… and no id. A block names its section by key and its column from 0. Content follows '
        . 'list_block_types. A section or block left out of the document is removed from the draft.';

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * The tools this key may use, in a fixed order.
     *
     * @param Key $key
     * @return list<array<string, mixed>>
     */
    public function definitions(array $key): array
    {
        $read = ['readOnlyHint' => true, 'openWorldHint' => false];
        $write = ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false];
        $tools = [
            self::tool('get_site', 'The site', 'The site: its name, languages (the main one first), design character, counts, what this key may do.', self::EMPTY, $read),
            self::tool('list_pages', 'Pages', 'Every page, or one language\'s: id, language, title, address, published or not, parent, whether it has a draft.', ['type' => 'object', 'properties' => ['locale' => ['type' => 'string', 'description' => 'A language code from get_site']], 'additionalProperties' => false], $read),
            self::tool('get_page', 'One page', 'One page as a document, its draft where it has one (version > 0), else the page as published. Edit this and give it to update_page.', self::object(['id' => ['type' => 'integer']], ['id']), $read),
            self::tool('list_block_types', 'Block types', 'Every block type: its fields (text, textarea, richtext as HTML, media as a picture id, link as {label, url} where url may be page:ID, select with its options, repeater with its item fields), layouts and options; and the section layouts and styles.', self::EMPTY, $read),
            self::tool('list_media', 'Pictures and files', 'The library, newest first: id, kind, file name, size in pixels, the alt text in each language, the picture\'s address.', ['type' => 'object', 'properties' => ['search' => ['type' => 'string'], 'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200]], 'additionalProperties' => false], $read),
            self::tool('get_design', 'The design', 'The design the site is published with, as a boxlet-design-set file; the characters it could start from; and the designs saved in its library, by_ai for one an AI client kept.', self::EMPTY, $read),
            self::tool('get_design_set_schema', 'Design set format', 'The JSON Schema of a boxlet-design-set file: every key a design may hold and every value it may take.', self::EMPTY, $read),
        ];
        if ($key['can_write']) {
            $document = ['sections' => ['type' => 'array', 'items' => ['type' => 'object']], 'blocks' => ['type' => 'array', 'items' => ['type' => 'object']]];
            array_push(
                $tools,
                self::tool('create_page', 'New page', 'A new page, unpublished, its blocks saved as its draft. ' . self::DOCUMENT, self::object(['locale' => ['type' => 'string'], 'title' => ['type' => 'string'], 'slug' => ['type' => 'string', 'description' => 'The address; made from the title when left out'], 'parent_id' => ['type' => 'integer']] + $document, ['locale', 'title']), $write),
                self::tool('update_page', 'Change a page', 'Saves the whole document as the page\'s draft; the published page is unchanged until the owner publishes. Problems a field has are listed in the answer. ' . self::DOCUMENT, self::object(['id' => ['type' => 'integer'], 'version' => ['type' => 'integer', 'description' => 'get_page\'s version, so a change made meanwhile is not written over'], 'title' => ['type' => 'string']] + $document, ['id', 'sections', 'blocks']), $write),
                self::tool('add_media_from_url', 'Add a picture', 'Fetches a picture (JPEG, PNG, WebP, GIF, AVIF) from a public https address into the library, its sizes made as for an upload. The same picture twice is kept once.', self::object(['url' => ['type' => 'string'], 'alt' => ['type' => 'string', 'description' => 'What the picture shows, for people who cannot see it'], 'locale' => ['type' => 'string', 'description' => 'The alt text\'s language; the main one when left out']], ['url']), $write),
                self::tool('create_design_set', 'Keep a design set', 'Checks a boxlet-design-set file as an import is checked and keeps it: as a character when it has a composition, else as a saved design. Never applied to the site: the owner tries it in Appearance; the answer says where. A saved design is kept under its name, so the same name again writes over it rather than adding a second; a character always gets an id of its own, so sending one again adds another. get_design lists both.', self::object(['set' => ['type' => 'object']], ['set']), $write),
            );
            if ($key['can_publish']) {
                $tools[] = self::tool('publish_page', 'Publish a page', 'Publishes the page\'s draft, or the page as it is. Allowed by the owner for this key.', self::object(['id' => ['type' => 'integer']], ['id']), ['readOnlyHint' => false, 'destructiveHint' => true, 'openWorldHint' => false]);
            }
        }

        return $tools;
    }

    /**
     * @param Key $key
     */
    public function offers(string $name, array $key): bool
    {
        return in_array($name, array_column($this->definitions($key), 'name'), true);
    }

    /**
     * The tool's result: its data as JSON text and as structured content, or why it could not.
     *
     * @param array<mixed> $arguments
     * @param Key $key
     * @return array<string, mixed>
     */
    public function call(string $name, array $arguments, array $key): array
    {
        $pages = new McpPages($this->container);
        $site = new McpSite($this->container);
        try {
            $data = match ($name) {
                'get_site' => $site->site($key),
                // A list is given inside an object: a result's structured content is an object in
                // the legacy revisions, and the SDKs drop anything else.
                'list_pages' => ['pages' => $pages->all(self::text($arguments, 'locale'))],
                'get_page' => $pages->one(self::integer($arguments, 'id')),
                'list_block_types' => $pages->blockTypes(),
                'list_media' => ['media' => $site->media(self::text($arguments, 'search'), max(1, min(200, (int) ($arguments['limit'] ?? 50))))],
                'get_design' => $site->design(),
                'get_design_set_schema' => $site->designSchema(),
                'create_page' => $pages->create($arguments),
                'update_page' => $pages->update($arguments),
                'publish_page' => $pages->publish(self::integer($arguments, 'id')),
                'add_media_from_url' => $site->addPicture(self::text($arguments, 'url'), self::text($arguments, 'alt'), self::text($arguments, 'locale')),
                'create_design_set' => $site->keepDesignSet($arguments['set'] ?? null),
                default => throw new RuntimeException('Unknown tool: ' . $name),
            };
        } catch (PDOException $e) {
            // A PDOException is a RuntimeException too, and its words are the database's: never
            // a model's to read. Logged for the owner, as anything else unforeseen.
            error_log('Boxlet MCP ' . $name . ': ' . $e->getMessage());

            return ['content' => [['type' => 'text', 'text' => 'The site could not do this just now.']], 'isError' => true];
        } catch (RuntimeException $e) {
            return ['content' => [['type' => 'text', 'text' => $e->getMessage()]], 'isError' => true];
        } catch (Throwable $e) {
            // Logged for the owner; the model is told only that it failed here.
            error_log('Boxlet MCP ' . $name . ': ' . $e->getMessage());

            return ['content' => [['type' => 'text', 'text' => 'The site could not do this just now.']], 'isError' => true];
        }

        return [
            'content' => [['type' => 'text', 'text' => (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]],
            'structuredContent' => $data,
            'isError' => false,
        ];
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, bool> $annotations
     * @return array<string, mixed>
     */
    private static function tool(string $name, string $title, string $description, array $schema, array $annotations): array
    {
        return ['name' => $name, 'title' => $title, 'description' => $description, 'inputSchema' => $schema, 'annotations' => $annotations];
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param list<string> $required
     * @return array<string, mixed>
     */
    private static function object(array $properties, array $required): array
    {
        return ['type' => 'object', 'properties' => $properties, 'required' => $required];
    }

    /** @param array<mixed> $arguments */
    private static function text(array $arguments, string $name): string
    {
        return is_string($arguments[$name] ?? null) ? trim($arguments[$name]) : '';
    }

    /** @param array<mixed> $arguments */
    private static function integer(array $arguments, string $name): int
    {
        $value = $arguments[$name] ?? null;
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new RuntimeException("{$name} must be a whole number.");
        }

        return (int) $value;
    }
}

<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\ContentAudit;

use Symfony\Component\Yaml\Yaml;

/**
 * The content canon: limits, facts, forbidden phrases, spelling and address
 * rules, read from Resources/Private/Data/Content/canon.yaml.
 *
 * Only reads and normalises. CanonChecker applies it.
 */
final readonly class Canon
{
    public const DEFAULT_PATH = __DIR__ . '/../../Resources/Private/Data/Content/canon.yaml';

    /**
     * @param array<string, array<string, int>> $hardLimits
     * @param array<string, array<string, int>> $softLimits
     * @param array<string, array<string, int|float>> $pageLimits
     * @param list<array{pattern: string, message: string, sites: list<string>}> $forbidden
     * @param list<string> $bannedHard
     * @param list<string> $bannedSoft
     * @param list<string> $leftovers
     * @param array<string, string> $spellingUk
     * @param array<string, array{root: int, language: string, address: string}> $sites
     * @param array<string, string> $address
     * @param array<string, array<string, string>> $glossary
     */
    public function __construct(
        public array $hardLimits,
        public array $softLimits,
        public array $pageLimits,
        public array $forbidden,
        public array $bannedHard,
        public array $bannedSoft,
        public array $leftovers,
        public array $spellingUk,
        public array $sites,
        public array $address,
        public array $glossary,
    ) {}

    public static function fromFile(string $path = self::DEFAULT_PATH): self
    {
        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new \InvalidArgumentException(sprintf('Canon %s is not a YAML map.', $path), 1790000001);
        }

        return self::fromArray($data);
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $limits = self::map($data['limits'] ?? []);

        $forbidden = [];
        foreach (self::list($data['forbidden'] ?? []) as $rule) {
            $rule = self::map($rule);
            $forbidden[] = [
                'pattern' => self::string($rule['pattern'] ?? ''),
                'message' => self::string($rule['message'] ?? ''),
                'sites' => array_map(self::string(...), self::list($rule['sites'] ?? [])),
            ];
        }

        $sites = [];
        foreach (self::map($data['sites'] ?? []) as $key => $site) {
            $site = self::map($site);
            $sites[(string)$key] = [
                'root' => (int)self::scalar($site['root'] ?? 0),
                'language' => self::string($site['language'] ?? 'en'),
                'address' => self::string($site['address'] ?? ''),
            ];
        }

        $glossary = [];
        foreach (self::map($data['glossary'] ?? []) as $language => $terms) {
            $glossary[(string)$language] = self::stringMap($terms);
        }
        $banned = self::map($data['banned'] ?? []);

        return new self(
            hardLimits: self::limitMap($limits['hard'] ?? []),
            softLimits: self::limitMap($limits['soft'] ?? []),
            pageLimits: self::pageLimitMap($limits['page'] ?? []),
            forbidden: $forbidden,
            bannedHard: array_map(self::string(...), self::list($banned['hard'] ?? [])),
            bannedSoft: array_map(self::string(...), self::list($banned['soft'] ?? [])),
            leftovers: array_map(self::string(...), self::list($data['leftovers'] ?? [])),
            spellingUk: self::stringMap($data['spelling_uk'] ?? []),
            sites: $sites,
            address: self::stringMap($data['address'] ?? []),
            glossary: $glossary,
        );
    }

    /**
     * @return array{root: int, language: string, address: string}|null
     */
    public function site(string $key): ?array
    {
        return $this->sites[$key] ?? null;
    }

    public function siteKeyForRoot(int $root): ?string
    {
        foreach ($this->sites as $key => $site) {
            if ($site['root'] === $root) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, int>>
     */
    private static function limitMap(mixed $value): array
    {
        $result = [];
        foreach (self::map($value) as $role => $limits) {
            foreach (self::map($limits) as $name => $limit) {
                $result[(string)$role][(string)$name] = (int)self::scalar($limit);
            }
        }

        return $result;
    }

    /**
     * @return array<string, array<string, int|float>>
     */
    private static function pageLimitMap(mixed $value): array
    {
        $result = [];
        foreach (self::map($value) as $metric => $byLanguage) {
            foreach (self::map($byLanguage) as $language => $limit) {
                $limit = self::scalar($limit);
                $result[(string)$metric][(string)$language] = is_float($limit) ? $limit : (int)$limit;
            }
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private static function stringMap(mixed $value): array
    {
        $result = [];
        foreach (self::map($value) as $key => $item) {
            $result[(string)$key] = self::string($item);
        }

        return $result;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function map(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @return list<mixed>
     */
    private static function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? (string)$value : '';
    }

    private static function scalar(mixed $value): int|float|string|bool
    {
        return is_scalar($value) ? $value : 0;
    }
}

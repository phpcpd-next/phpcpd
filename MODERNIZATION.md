# phpcpd-next — Modernization Log

> The diff-by-diff story of how the archived `sebastianbergmann/phpcpd` was modernised into
> **phpcpd-next**. This is the narrative/rationale log; release notes live in `CHANGELOG.md`.

## Why this exists

Sebastian Bergmann wrote phpcpd in 2009 and maintained it for over a decade alongside PHPUnit,
php-timer, and the rest of the sebastian/* ecosystem. In 2023 he archived the repository with a
[short note](https://github.com/sebastianbergmann/phpcpd): the tool still works but will receive
no further updates, and people who need it are encouraged to fork it.

**This fork exists for two reasons.**

The first is simple: I love the tool. Finding copy-paste in a growing codebase is one of those
tasks that sounds mechanical until the moment it catches a bug you duplicated six months ago and
forgot. phpcpd does that quietly and fast, and it deserves to keep working on modern PHP.

The second reason is that this fork is meant to be a **practical guide to modernising an
unmaintained PHP codebase with today's toolset**. Not a theoretical tutorial — every step
recorded here was taken on real code, in order, with the actual diff and the actual reason.
The things covered, roughly in the order they appear:

| Step | What it shows |
|------|--------------|
| Fork setup — `composer.json`, platform, autoload | Where to start with an archived repo |
| PHP 8.5 as baseline | What becomes available and what breaks |
| Bug fixes before modernisation | Never modernise broken code |
| `readonly class`, constructor promotion, typed constants | PHP 8.0–8.3 idioms applied one at a time |
| `#[\Override]` attribute | Enforcing interface contracts mechanically |
| PHPDoc generics (`list<T>`, `@template`, `@implements`) | Making PHPStan understand your data structures |
| Extracting duplicate code | The tool found a clone in itself — we fixed it |
| PHPStan level 9 → max — zero errors | Working through 64 → 0 at the strictest level |
| PHP-CS-Fixer (PER-CS2.0) | Automated style, not a lint report |
| Rector | What automated modernisation can and can't do |
| PHPUnit latest | Writing tests for code that had none |
| GitHub Actions CI | The gate that makes everything above stick |
| Dependency upgrade — `sebastian/*` to current majors | Unlocking the full modern ecosystem |
| PHP 8.x idiom sweep | Arrow functions, `foreach`, array destructuring, `printf` |
| Named arguments | Making a 13-param constructor call safe against reordering |
| Bug found while testing | Writing tests surfaces bugs that static analysis misses |
| Test suite | Fixture-based integration tests + targeted unit tests |

If you are reading this as a guide: every section below is a numbered change with the exact
`diff` block and a **Why** line. Skip the ones that don't apply to your codebase; the order
matters only where one step unblocks the next.

If you are reading this as a user: the tool works on PHP 8.5, installs via Composer, and scans
PHP code exactly as it did upstream.

— Luciano Federico Pereira

---

Fork of `sebastianbergmann/phpcpd` (archived 2023, last commit on `main`: 7.0-dev, PHP ≥ 8.1).  
This document is the canonical diff record. Every deliberate change from upstream is listed here,
file by file, line by line. New changes must be added to this file **before** the PR is merged.

Upstream ref: `https://github.com/sebastianbergmann/phpcpd` — branch `main`, commit at fork point.

---

## Naming

| | Upstream | phpcpd-next |
|---|---|---|
| Project identity | `sebastian/phpcpd` | `phpcpd-next/phpcpd` |
| Entry point | `phpcpd` | `phpcpd` (unchanged) |
| Version constant | `7.0` in `Application::VERSION` | `0.1.0` |
| Banner | `phpcpd 7.0-dev by Sebastian Bergmann.` | `phpcpd 0.1.0 by Luciano Federico Pereira based on phpcpd 7.0-dev by Sebastian Bergmann.` |

---

## 1. `composer.json`

### PHP constraint

```diff
-    "php": ">=8.1",
+    "php": ">=8.5",
```

### Platform simulation

```diff
-    "platform": { "php": "8.1.0" },
+    "platform": { "php": "8.5.0" },
```

Composer now resolves dependencies assuming PHP 8.5 is installed, so any package
that dropped 8.5 support will fail at `composer install` time rather than at runtime.

### Dependency constraints widened

```diff
-    "phpunit/php-file-iterator": "^4.0",
+    "phpunit/php-file-iterator": "^4.0 || ^5.0",

-    "phpunit/php-timer": "^6.0",
+    "phpunit/php-timer": "^6.0 || ^7.0",
```

Resolved to `php-file-iterator 5.1.1` and `php-timer 7.0.1` on first install.
`sebastian/cli-parser ^2.0` and `sebastian/version ^4.0` were unchanged and resolved fine.

### Removed stale fields

```diff
-    "support": { "issues": "..." },
-    "extra": { "branch-alias": { "dev-main": "7.0-dev" } },
```

`support` points to the archived upstream; removed to avoid confusion.
`branch-alias` is upstream-specific.

---

## 2. `phpcpd` (entry script)

### PHP version guard

```diff
-if (version_compare('8.1.0', PHP_VERSION, '>')) {
+if (version_compare('8.5.0', PHP_VERSION, '>')) {

-            'This version of PHPCPD requires PHP 8.1 (or later).' . PHP_EOL .
+            'This version of PHPCPD requires PHP 8.5 (or later).' . PHP_EOL .
```

### Array literal style (cosmetic)

```diff
-foreach (array(__DIR__ . '/../../autoload.php', __DIR__ . '/vendor/autoload.php') as $file) {
+foreach ([__DIR__ . '/../../autoload.php', __DIR__ . '/vendor/autoload.php'] as $file) {
```

Short array syntax throughout for consistency with the rest of the codebase.

---

## 3. `src/CLI/Application.php`

### Version identity and banner

Four constants replace the single `VERSION` string:

```diff
-    private const VERSION = '7.0';
+    private const VERSION         = '0.1.0';
+    private const UPSTREAM        = '7.0-dev';
+    private const UPSTREAM_AUTHOR = 'Sebastian Bergmann';
+    private const AUTHOR          = 'Luciano Federico Pereira';
```

`printVersion()` updated to produce the new banner:

```diff
-        printf(
-            'phpcpd %s by Sebastian Bergmann.' . PHP_EOL,
-            (new Version(self::VERSION, dirname(__DIR__)))->asString()
-        );
+        printf(
+            'phpcpd %s by %s based on phpcpd %s by %s.' . PHP_EOL,
+            self::VERSION,
+            self::AUTHOR,
+            self::UPSTREAM,
+            self::UPSTREAM_AUTHOR
+        );
```

Output: `phpcpd 0.1.0 by Luciano Federico Pereira based on phpcpd 7.0-dev by Sebastian Bergmann.`

The `sebastian/version` git-tag lookup is no longer used for the banner (version is now a
hardcoded constant), so the `Version` class import is removed from the banner path.
It is still present in `use` imports for potential future use.

### `sebastian/version` v4 API change — `getVersion()` → `asString()`

`sebastian/version` v4 renamed the only public method.

```diff
-            (new Version(self::VERSION, dirname(__DIR__)))->getVersion()
+            (new Version(self::VERSION, dirname(__DIR__)))->asString()
```

**Why:** `getVersion()` no longer exists in v4; calling it throws `Error: Call to undefined method`.
Confirmed by reading `vendor/sebastian/version/src/Version.php`.

---

## 4. `src/Detector/Strategy/SuffixTree/AbstractToken.php`

### Typed properties (PHP 8.2 – 8.5 compatibility)

All five public properties were untyped (`public $name;`).
PHP 8.2 deprecated dynamic properties; typed properties are the clean path forward.

```diff
-    /** @var int */
-    public $tokenCode;
-
-    /** @var int */
-    public $line;
-
-    /** @var string */
-    public $file;
-
-    /** @var string */
-    public $tokenName;
-
-    /** @var string */
-    public $content;
+    public int $tokenCode;
+    public int $line;
+    public string $file;
+    public string $tokenName;
+    public string $content;
```

`@var` docblocks removed because the type declaration is now authoritative.

---

## 5. `src/Detector/Strategy/SuffixTree/Sentinel.php`

### Typed property

```diff
-    /** @var int The hash value used. */
-    private $hash;
+    private int $hash;
```

---

## 6. `src/Detector/Strategy/SuffixTree/CloneInfo.php`

### Typed properties

```diff
-    /** @var int */
-    public $length;
-
-    /** @var int */
-    public $position;
-
-    /** @var AbstractToken */
-    public $token;
-
-    /** @var PairList */
-    public $otherClones;
-
-    /** @var int */
-    private $occurrences;
+    public int $length;
+    public int $position;
+    public AbstractToken $token;
+    public PairList $otherClones;
+    private int $occurrences;
```

---

## 7. `src/Detector/Strategy/SuffixTree/PairList.php`

### Typed properties

```diff
-    /** @var int */
-    private $serialVersionUID = 1;
-
-    /** @var int */
-    private $size = 0;
-
-    /** @var S[] */
-    private $firstElements;
-
-    /** @var T[] */
-    private $secondElements;
+    private int $serialVersionUID = 1;
+    private int $size = 0;
+    private array $firstElements;
+    private array $secondElements;
```

Note: the upstream used `@template S` / `@template T` Psalm generics via docblocks.
PHP has no runtime generics; `array` is the accurate native type.
The Psalm annotations are dropped — a future iteration could add PHPStan generics if needed.

### `mixed` type on previously-untyped parameters and return values

PHP 8.0 introduced `mixed` as an explicit type, making "no type = mixed" explicit.
PHP 8.5 does not require this but it eliminates the implicit-nullable/untyped ambiguity.

```diff
-    public function __construct(int $initialCapacity, $firstType, $secondType)
+    public function __construct(int $initialCapacity, mixed $firstType, mixed $secondType)

-    public function add($first, $second): void
+    public function add(mixed $first, mixed $second): void

-    public function getFirst(int $i)
+    public function getFirst(int $i): mixed

-    public function setFirst(int $i, $value): void
+    public function setFirst(int $i, mixed $value): void

-    public function getSecond(int $i)
+    public function getSecond(int $i): mixed

-    public function setSecond(int $i, $value): void
+    public function setSecond(int $i, mixed $value): void
```

---

## 8. `src/Detector/Strategy/SuffixTree/SuffixTree.php`

### Typed properties (13 properties)

```diff
-    /** @var int */
-    protected $INFTY;
-
-    /** @var AbstractToken[] */
-    protected $word;
-
-    /** @var int */
-    protected $numNodes = 0;
-
-    /** @var int[] */
-    protected $nodeWordBegin;
-
-    /** @var int[] */
-    protected $nodeWordEnd;
-
-    /** @var int[] */
-    protected $suffixLink;
-
-    /** @var SuffixTreeHashTable */
-    protected $nextNode;
-
-    /** @var int[] */
-    protected $nodeChildFirst = [];
-
-    /** @var int[] */
-    protected $nodeChildNext = [];
-
-    /** @var int[] */
-    protected $nodeChildNode = [];
-
-    /** @var int */
-    private $currentNode = 0;
-
-    /** @var int */
-    private $refWordBegin = 0;
-
-    /** @var int */
-    private $explicitNode = 0;
+    protected int $INFTY;
+    protected array $word;
+    protected int $numNodes = 0;
+    protected array $nodeWordBegin;
+    protected array $nodeWordEnd;
+    protected array $suffixLink;
+    protected SuffixTreeHashTable $nextNode;
+    protected array $nodeChildFirst = [];
+    protected array $nodeChildNext  = [];
+    protected array $nodeChildNode  = [];
+    private int $currentNode  = 0;
+    private int $refWordBegin = 0;
+    private int $explicitNode = 0;
```

### Constructor parameter typed

```diff
-    public function __construct($word)
+    public function __construct(array $word)
```

---

## 9. `src/Detector/Strategy/SuffixTree/SuffixTreeHashTable.php`

### Typed properties (8 properties)

```diff
-    /** @var int[] */
-    private $allowedSizes = [...];
-
-    /** @var int */
-    private $tableSize;
-
-    /** @var int[] */
-    private $keyNodes;
-
-    /** @var array<null|AbstractToken> */
-    private $keyChars;
-
-    /** @var int[] */
-    private $resultNodes;
-
-    /** @var int */
-    private $_numStoredNodes = 0;
-
-    /** @var int */
-    private $_numFind = 0;
-
-    /** @var int */
-    private $_numColl = 0;
+    private array $allowedSizes = [...];
+    private int $tableSize;
+    private array $keyNodes;
+    private array $keyChars;
+    private array $resultNodes;
+    private int $_numStoredNodes = 0;
+    private int $_numFind        = 0;
+    private int $_numColl        = 0;
```

---

## 10. `src/Detector/Strategy/SuffixTree/ApproximateCloneDetectingSuffixTree.php`

### Typed properties (7 properties)

```diff
-    /** @var int */
-    protected $minLength = 70;
-
-    /** @var int[] */
-    private $leafCount = [];
-
-    /** @var int */
-    private $INDEX_SPREAD = 10;
-
-    /** @var array<CloneInfo[]> */
-    private $cloneInfos = [];
-
-    /** @var int */
-    private $MAX_LENGTH = 1024;
-
-    /** @var array<int[]> */
-    private $edBuffer = [];
-
-    /** @var int */
-    private $headEquality = 10;
+    protected int $minLength  = 70;
+    private array $leafCount  = [];
+    private int $INDEX_SPREAD = 10;
+    private array $cloneInfos = [];
+    private int $MAX_LENGTH   = 1024;
+    private array $edBuffer   = [];
+    private int $headEquality = 10;
```

Removed class-level docblock (original ConQAT provenance comment preserved inline).

---

## 11. `src/Log/AbstractXmlLogger.php`

### Explicit source encoding in `mb_convert_encoding`

```diff
-            $string = mb_convert_encoding($string, 'UTF-8');
+            $string = mb_convert_encoding($string, 'UTF-8', mb_internal_encoding());
```

**Why:** PHP 8.2 deprecated omitting the source encoding argument when it could be `null`.
Passing `mb_internal_encoding()` makes the intent explicit and suppresses the deprecation notice.

---

## 12. `src/Detector/Strategy/SuffixTreeStrategy.php` — bug fix

### `empty()` on object never fires — null guard replaced

```diff
-        if (empty($this->result)) {
+        if ($this->result === null) {
             throw new MissingResultException('Missing result');
         }
```

**Why:** In PHP, `empty($object)` always returns `false` for any instantiated object, including
a freshly-constructed `CodeCloneMap`. The original guard was permanently dead code — calling
`postProcess()` before `processFile()` would crash with a null-dereference on `$this->result->add()`
instead of throwing the clean `MissingResultException`. Replaced with strict identity check `=== null`.

---

## 13. `src/Log/AbstractXmlLogger.php` — bug fix

### `preg_replace()` null return unguarded

```diff
-        $string = preg_replace(
+        $string = preg_replace(
             '/[^\x09\x0A\x0D\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]/u',
             "\xEF\xBF\xBD",
             $string
-        );
+        ) ?? $string;
```

**Why:** `preg_replace()` returns `string|null` — `null` when the regex engine fails (e.g. PCRE
internal error or stack overflow on pathological input). Passing `null` to `htmlspecialchars()`
throws `TypeError` on PHP 8.1+. The `?? $string` fallback preserves the original string in the
failure case, which is the correct degraded behaviour for an XML escaper.

---

## 14. `src/CodeCloneMap.php` — bug fix

### `averageSize()` division by zero

```diff
-    public function averageSize(): float
-    {
-        return $this->numberOfDuplicatedLines() / $this->count();
-    }
+    public function averageSize(): float
+    {
+        if ($this->count() === 0) {
+            return 0.0;
+        }
+
+        return $this->numberOfDuplicatedLines() / $this->count();
+    }
```

**Why:** `count()` is 0 when no clones are found. `Text::printResult()` guards its call to
`averageSize()` with an early return on `isEmpty()`, so the upstream never crashed in practice.
But the method itself offered no guarantee — any direct caller would get a DivisionByZeroError.
The fix makes the method safe in isolation regardless of call context.

---

## 15. `src/CodeClone.php` — bug fix

### `current()` returns `false` on empty array

```diff
-            $file = current($this->files);
+            $file = array_values($this->files)[0];
```

**Why:** `current()` returns `false` when the array is empty, giving `CodeCloneFile|false`.
Calling `$file->name()` on `false` throws `TypeError` in PHP 8. The constructor always calls
`add()` twice, so `$files` is never empty in the current code path — but the method had no
type-level guarantee. `array_values($this->files)[0]` makes the intent explicit (first element,
after resetting keys) and returns `CodeCloneFile` directly, which PHPStan can verify.

---

## 16. `src/CLI/Arguments.php` — `final readonly class` + constructor property promotion

```diff
-final class Arguments
+final readonly class Arguments
 {
-    private array $directories;
     // ... (13 property declarations removed)
-
-    public function __construct(array $directories, ...)
-    {
-        $this->directories = $directories;
-        // ... (13 assignment lines removed)
-    }
+    public function __construct(
+        private array $directories,
+        private array $suffixes,
+        private array $exclude,
+        private ?string $pmdCpdXmlLogfile,
+        private int $linesThreshold,
+        private int $tokensThreshold,
+        private bool $fuzzy,
+        private bool $verbose,
+        private bool $help,
+        private bool $version,
+        private ?string $algorithm,
+        private int $editDistance,
+        private int $headEquality,
+    ) {}
```

**Why:** Pure value object — 13 properties set once, never mutated. `readonly class` (PHP 8.2) makes immutability a compile-time guarantee. CPP eliminates 27 lines of boilerplate. Accessor methods unchanged; external API identical.

---

## 17. `src/Detector/Strategy/StrategyConfiguration.php` — `final readonly class`

```diff
-final class StrategyConfiguration
+final readonly class StrategyConfiguration
```

**Why:** All 5 props derive from `Arguments` accessors at construction and are never written again. CPP not applied here because prop names differ from the `Arguments` param names (e.g. `$minLines` from `linesThreshold()`). `readonly` at class level still enforces immutability.

---

## 18. `src/CodeCloneFile.php` — `final readonly class` + CPP

```diff
-final class CodeCloneFile
+final readonly class CodeCloneFile
 {
-    private string $id;
-    private string $name;
-    private int $startLine;
-
-    public function __construct(string $name, int $startLine)
-    {
-        $this->name      = $name;
-        $this->startLine = $startLine;
-        $this->id        = $this->name . ':' . $this->startLine;
-    }
+    public string $id;
+
+    public function __construct(
+        public string $name,
+        public int $startLine,
+    ) {
+        $this->id = $this->name . ':' . $this->startLine;
+    }
```

**Why:** Value object. `$id` must be computed in the constructor body (depends on the other two params), so it stays as a declared property. Accessor methods unchanged.

---

## 19. `src/Detector/Strategy/SuffixTree/CloneInfo.php` — `readonly class` + CPP

```diff
-class CloneInfo
+readonly class CloneInfo
 {
-    public int $length;
-    public int $position;
-    public AbstractToken $token;
-    public PairList $otherClones;
-    private int $occurrences;
-
-    public function __construct(int $length, int $position, int $occurrences, AbstractToken $token, PairList $otherClones)
-    {
-        $this->length      = $length;
-        // ...
-    }
+    public function __construct(
+        public int $length,
+        public int $position,
+        private int $occurrences,
+        public AbstractToken $token,
+        public PairList $otherClones,
+    ) {}
```

**Why:** Constructed once per clone in `findClones()`, only read afterward. Public fields accessed directly in suffix-tree traversal; `$occurrences` stays `private` — only `dominates()` reads it on `$this`.

---

## 20. `src/Detector/Detector.php` — CPP + `readonly`

```diff
-    private AbstractStrategy $strategy;
-
-    public function __construct(AbstractStrategy $strategy)
-    {
-        $this->strategy = $strategy;
-    }
+    public function __construct(private readonly AbstractStrategy $strategy) {}
```

**Why:** Single injected dependency, never reassigned. CPP collapses 4 lines to 1.

---

## 21. `src/Log/AbstractXmlLogger.php` — CPP + `readonly` on both properties

```diff
-    protected DOMDocument $document;
-    private string $filename;
-
-    public function __construct(string $filename)
-    {
-        $this->document               = new DOMDocument('1.0', 'UTF-8');
-        $this->document->formatOutput = true;
-        $this->filename               = $filename;
-    }
+    protected readonly DOMDocument $document;
+
+    public function __construct(private readonly string $filename)
+    {
+        $this->document               = new DOMDocument('1.0', 'UTF-8');
+        $this->document->formatOutput = true;
+    }
```

**Why:** `$document` is assigned once and then mutated at the object level only (never reassigned). `readonly` on the property reference is correct. `readonly abstract class` was rejected — it would silently force every concrete subclass to be `readonly` too.

---

## 22. `src/CLI/Application.php` — typed class constants (PHP 8.3)

```diff
-    private const VERSION         = '0.1.0';
-    private const UPSTREAM        = '7.0-dev';
-    private const UPSTREAM_AUTHOR = 'Sebastian Bergmann';
-    private const AUTHOR          = 'Luciano Federico Pereira';
+    private const string VERSION         = '0.1.0';
+    private const string UPSTREAM        = '7.0-dev';
+    private const string UPSTREAM_AUTHOR = 'Sebastian Bergmann';
+    private const string AUTHOR          = 'Luciano Federico Pereira';
```

**Why:** PHP 8.3 typed constants. Without a type, a subclass could override `const AUTHOR = 123` silently. PHPStan can now verify usages.

---

## 23. `src/Detector/Strategy/SuffixTree/SuffixTree.php` — loose `== null` on array

```diff
-        if ($this->nodeChildFirst == null || count($this->nodeChildFirst) < $this->numNodes) {
+        if (count($this->nodeChildFirst) < $this->numNodes) {
```

**Why:** `$nodeChildFirst` is `protected array = []` — never null. `[] == null` is `true` in PHP (loose comparison). Since `count([]) === 0 < $numNodes` (which starts at 1), the `== null` branch is fully subsumed. Removing it eliminates implicit type-widening and makes intent clear.

---

## 24. `src/Detector/Strategy/SuffixTree/PairList.php` — remove `$serialVersionUID`

```diff
-    private int $serialVersionUID = 1;
     private int $size = 0;
```

**Why:** Java serialization artifact — `serialVersionUID` is checked by `ObjectInputStream` during Java deserialization. PHP has no equivalent. The field is never read anywhere in the codebase.

---

## 25. `src/Detector/Strategy/DefaultStrategy.php` — `#[\Override]` + `foreach` refactor

```diff
-use function array_keys;
 use function chr;

+    #[\Override]
     public function processFile(string $file, CodeCloneMap $result): void

-        foreach (array_keys($tokens) as $key) {
-            $token = $tokens[$key];
-
-            if (is_array($token)) {
+        foreach ($tokens as $token) {
+            if (is_array($token)) {
```

**Why:** `#[\Override]` documents and enforces the abstract-method contract with `AbstractStrategy`. The `array_keys()` pattern is a Java holdover — `$key` was only used to retrieve `$tokens[$key]` and was never used otherwise. Direct value iteration removes one function call and one array lookup per token.

---

## 26. `src/Detector/Strategy/SuffixTreeStrategy.php` — `#[\Override]` + `foreach` refactor

```diff
-use function array_keys;
 use function file_get_contents;

+    #[\Override]
     public function processFile(string $file, CodeCloneMap $result): void

-        foreach (array_keys($tokens) as $key) {
-            $token = $tokens[$key];
-
-            if (is_array($token) && !isset($this->tokensIgnoreList[$token[0]])) {
+        foreach ($tokens as $token) {
+            if (is_array($token) && !isset($this->tokensIgnoreList[$token[0]])) {

     /**
      * @throws MissingResultException
      */
+    #[\Override]
     public function postProcess(): void
```

**Why:** Same as §25. `postProcess()` also gets `#[\Override]` — `AbstractStrategy` provides a no-op default; if the base signature changes, PHP catches it at class load time.

---

## 27–30. `#[\Override]` sweep — PMD, CodeCloneMapIterator, CodeCloneMap, Token, Sentinel

**PMD.php** — `processClones()` implements the abstract method in `AbstractXmlLogger`.

**CodeCloneMapIterator.php** — all 5 `Iterator` methods: `rewind()`, `valid()`, `key()`, `current()`, `next()`.

**CodeCloneMap.php** — `count()` (`Countable`) and `getIterator()` (`IteratorAggregate`).

**Token.php** and **Sentinel.php** — `__toString()`, `hashCode()`, `equals()` are all abstract in `AbstractToken`.

```diff
+    #[\Override]
     public function processClones(...) / rewind() / valid() / ... / __toString() / hashCode() / equals()
```

**Why:** `#[\Override]` (PHP 8.3) turns silent regressions into fatal errors. If a parent renames or removes an abstract/interface method, any `#[\Override]`-marked override breaks loudly at class load time instead of silently becoming a dead method.

---

## 31. `src/Detector/Strategy/DefaultStrategy.php` — extract `recordCloneIfValid()`

```diff
-            } else {
-                if ($found) {
-                    $fileA        = $this->hashes[$firstHash][0];
-                    $firstLineA   = $this->hashes[$firstHash][1];
-                    $lastToken    = ($tokenNr - 1) + $this->config->minTokens() - 1;
-                    $lastLine     = $currentTokenPositions[$lastToken];
-                    $lastRealLine = $currentTokenRealPositions[$lastToken];
-                    $numLines     = $lastLine + 1 - $firstLine;
-                    $realNumLines = $lastRealLine + 1 - $firstRealLine;
-
-                    if ($numLines >= $this->config->minLines() &&
-                        ($fileA !== $file || $firstLineA !== $firstRealLine)) {
-                        $result->add(new CodeClone(...));
-                    }
-
-                    $found     = false;
-                    $firstLine = 0;
-                }
-
-                $this->hashes[$hash] = [$file, $realLine];
-            }
-
-            $tokenNr++;
-        }
-
-        if ($found) {
-            $fileA        = $this->hashes[$firstHash][0];
-            // ... identical 17-line block ...
-        }
+            } else {
+                if ($found) {
+                    $this->recordCloneIfValid(
+                        $result, $file, $firstHash, $tokenNr,
+                        $firstLine, $firstRealLine, $firstToken,
+                        $currentTokenPositions, $currentTokenRealPositions
+                    );
+                    $found     = false;
+                    $firstLine = 0;
+                }
+                $this->hashes[$hash] = [$file, $realLine];
+            }
+            $tokenNr++;
+        }
+
+        if ($found) {
+            $this->recordCloneIfValid(
+                $result, $file, $firstHash, $tokenNr,
+                $firstLine, $firstRealLine, $firstToken,
+                $currentTokenPositions, $currentTokenRealPositions
+            );
+        }
+    }
+
+    private function recordCloneIfValid(
+        CodeCloneMap $result,
+        string $file,
+        string $firstHash,
+        int $tokenNr,
+        int $firstLine,
+        int $firstRealLine,
+        int $firstToken,
+        array $currentTokenPositions,
+        array $currentTokenRealPositions,
+    ): void {
+        // ... shared flush logic ...
+    }
```

**Why:** The Rabin-Karp algorithm has two flush points for the same "emit clone" logic: one inside the loop (when a non-match breaks a matching run) and one after the loop (when a matching run reaches end-of-file with no trailing non-match to trigger the inner flush). Upstream left the 17-line block duplicated verbatim. Extracting `recordCloneIfValid()` makes the two-flush-point structure explicit while eliminating the duplication. Verification: running `phpcpd` on its own `src/` directory reports **"No code clones found."** after this change.

---

---

## 32. PHPStan level 9 → max — zero errors pass

Goal: reach PHPStan `--level=max` (level 10, the highest) with zero errors across the entire `src/` tree.
Work started at level 9 and was completed all the way to `max` in a single pass.
This section records every change made to satisfy the strictest analysis level.

### 32a. `phpstan.neon` — new config file

**New file** (did not exist upstream), later updated to `max`:

```neon
parameters:
    level: max
    paths:
        - src/
```

**Why:** `max` is an alias for the highest numeric level PHPStan supports (currently 10).
Using `max` means the CI gate always tracks the ceiling as new levels are added, without
requiring a manual bump. The `stubFiles` entry present at level 9 was removed once
`sebastian/cli-parser` was upgraded to v3 (which ships its own full PHPDoc — see §35).

---

### 32b. `phpstan-stubs.php` — PHPStan stub for `sebastian/cli-parser` *(superseded by §35)*

**New file** (did not exist upstream), later emptied when the dependency was upgraded.

The stub was necessary while `sebastian/cli-parser ^2.0` was pinned: that release shipped no
PHPDoc on `Parser::parse()`, so PHPStan level 9 saw its return value as bare `array`. The stub
declared the correct return-type shape:

```php
// @return array{0: list<array{0: string, 1: string|false}>, 1: list<non-empty-string>}
```

When the dep was upgraded to `^3.0` (§35), v3 ships its own full PHPDoc with the updated
signature (`?non-empty-string` instead of `string|false`). The stub was emptied — keeping it
would mask the real vendor types.

---

### 32c. `src/CLI/Arguments.php` — `list<non-empty-string>` annotations

```diff
-     * @param list<string> $directories
-     * @param list<string> $suffixes
-     * @param list<string> $exclude
+     * @param list<non-empty-string> $directories
+     * @param list<non-empty-string> $suffixes
+     * @param list<non-empty-string> $exclude

-     /** @return list<string> */
+     /** @return list<non-empty-string> */
      public function directories(): array { ... }
-     /** @return list<string> */
+     /** @return list<non-empty-string> */
      public function suffixes(): array { ... }
-     /** @return list<string> */
+     /** @return list<non-empty-string> */
      public function exclude(): array { ... }
```

**Why:** `Facade::getFilesAsArray()` (from `phpunit/php-file-iterator`) requires
`list<non-empty-string>|non-empty-string`. Directories/suffixes/excludes always come from
CLI arguments (never empty strings), so `non-empty-string` is the truthful annotation and
satisfies the downstream call.

---

### 32d. `src/CLI/ArgumentsBuilder.php` — type guards on option values

The CliParser stub declares option values as `string|false` (options without `=` return `false`
for their value). Three switch cases now guard before appending:

```diff
  case '--suffix':
-     $suffixes[] = $option[1];
+     if ($option[1] !== false && $option[1] !== '') {
+         $suffixes[] = $option[1];
+     }
      break;
  case '--exclude':
-     $exclude[] = $option[1];
+     if ($option[1] !== false && $option[1] !== '') {
+         $exclude[] = $option[1];
+     }
      break;
  case '--log-pmd':
-     $pmdCpdXmlLogfile = $option[1];
+     $pmdCpdXmlLogfile = $option[1] !== false ? $option[1] : null;
      break;
```

**Why:** PHPStan narrows `$option[1]` to `non-empty-string` after the double guard
`!== false && !== ''`, satisfying `list<non-empty-string>` on `Arguments`. The `--log-pmd`
case uses the ternary to produce `?string` (the field type on `Arguments`).

---

### 32e. `src/Detector/Strategy/SuffixTree/PairList.php` — `@template` generics

This is the largest structural change in the PHPStan pass. `PairList` stores heterogeneous
pairs at runtime (int/int in ACST, mixed pairs elsewhere). Without generics, every `getFirst()`
/ `getSecond()` / `extractFirstList()` return is `mixed`, causing binaryOp and offsetAccess
errors at all call sites.

**Changes:**

```diff
+/**
+ * @template TFirst
+ * @template TSecond
+ */
 class PairList

-    /** @var array<int, mixed> */
+    /** @var array<int, TFirst> */
     private array $firstElements;
-    /** @var array<int, mixed> */
+    /** @var array<int, TSecond> */
     private array $secondElements;

-    public function __construct(int $initialCapacity)
-    {
-        $this->firstElements  = array_fill(0, $initialCapacity, null);
-        $this->secondElements = array_fill(0, $initialCapacity, null);
-    }
+    public function __construct(int $initialCapacity)
+    {
+        unset($initialCapacity);      // capacity hint unused; PHP arrays are dynamic
+        $this->firstElements  = [];
+        $this->secondElements = [];
+    }

+    /**
+     * @param TFirst $first
+     * @param TSecond $second
+     */
     public function add(mixed $first, mixed $second): void

+    /** @param PairList<TFirst, TSecond> $other */
     public function addAll(self $other): void

-    /** @return list<mixed> */
+    /** @return TFirst */
     public function getFirst(int $i): mixed

+    /**
+     * @param TFirst $value
+     */
     public function setFirst(int $i, mixed $value): void

-    /** @return list<mixed> */
+    /** @return TSecond */
     public function getSecond(int $i): mixed

+    /**
+     * @param TSecond $value
+     */
     public function setSecond(int $i, mixed $value): void

-    /** @return list<mixed> */
+    /** @return list<TFirst> */
     public function extractFirstList(): array

-    /** @return list<mixed> */
+    /** @return list<TSecond> */
     public function extractSecondList(): array
```

**Why (array_fill removal):** `array_fill(0, N, null)` assigns `null` to an array typed
`array<int, TFirst>`, which PHPStan rejects (null is not TFirst for all T). PHP arrays grow
on assignment, so pre-filling was a Java-ism with no PHP benefit.

**Why (generics):** without template params every usage site is `mixed` and arithmetic /
index operations are errors at level 9.

---

### 32f. `src/Detector/Strategy/SuffixTree/CloneInfo.php` — `PairList<int, int>` annotation

```diff
  readonly class CloneInfo
  {
+     /**
+      * @param PairList<int, int> $otherClones
+      */
      public function __construct(
          public int $length,
          public int $position,
          private int $occurrences,
          public AbstractToken $token,
          public PairList $otherClones,
      ) {}
```

**Why:** `CloneInfo` always holds a `PairList<int, int>` (start positions × clone lengths).
The annotation propagates to the promoted property, so PHPStan tracks the type through
`$cloneInfo->otherClones->extractFirstList()` and `getFirst()` / `getSecond()` without
requiring inline casts at every call site in `SuffixTreeStrategy`.

---

### 32g. `src/Detector/Strategy/SuffixTree/ApproximateCloneDetectingSuffixTree.php` — generic annotations

Two additions to propagate the `PairList<int, int>` type to the analysis:

```diff
+     /** @var PairList<int, int> $otherClones */
      $otherClones = new PairList(16);

+     /** @param PairList<int, int> $clonePositions */
      private function findRemainingClones(
          PairList $clonePositions,
```

**Why:** `new PairList(16)` has no constructor arguments that carry the generic type, so
PHPStan cannot infer `TFirst`/`TSecond`; an inline `@var` at the instantiation site is the
standard PHPStan practice for this pattern. `findRemainingClones` calls `$clonePositions->add(int, int)`,
which is only type-safe when the callee is annotated.

---

---

## 33. Toolchain — CS-Fixer, Rector, PHPUnit, CI

### New files

| File | Purpose |
|------|---------|
| `.php-cs-fixer.dist.php` | PHP-CS-Fixer config; `@PER-CS2.0` + risky fixers (`declare_strict_types`, `strict_param`, `native_function_invocation`, …) |
| `rector.php` | Rector config; `php85` set + `CODE_QUALITY` + `TYPE_DECLARATION` (skips `ReadonlyClassRector` — already done manually) |
| `phpunit.xml` | PHPUnit 11/12 config; `tests/` suite, random execution order, fail-on-notice |
| `.editorconfig` | 4-space PHP indent, LF endings, UTF-8 — editor-level agreement before any tool runs |
| `.github/workflows/ci.yml` | GitHub Actions: PHP 8.5, `composer audit`, CS-Fixer dry-run, PHPStan, PHPUnit |
| `tests/.gitkeep` | Placeholder so `tests/` is tracked by git before the test suite is written |

### `composer.json` — new dev deps + scripts

```diff
  "require-dev": {
+     "friendsofphp/php-cs-fixer": "^3.64",
      "phpstan/phpstan": "^2.2",
+     "phpunit/phpunit": "^11.0 || ^12.0",
+     "rector/rector": "^2.0"
- }
+ },
+ "scripts": {
+     "lint":     "php-cs-fixer fix --dry-run --diff",
+     "lint:fix": "php-cs-fixer fix",
+     "analyse":  "phpstan analyse --memory-limit=1G",
+     "test":     "phpunit",
+     "check":    ["@lint", "@analyse", "@test"]
+ }
```

**Why one style tool:** PHP-CS-Fixer autofixes; PHP_CodeSniffer only reports. Running both
creates conflicts. PER-CS2.0 is the modern successor to PSR-12 — it covers readonly, match,
named args, and fibers that PSR-12 predates.

**Why PHPUnit here:** linting confirms shape; tests confirm behaviour. Without them, the other
tools give false confidence on a ported codebase.

**CI gate order:** audit → lint → analyse → test. Audit is cheapest, so it goes first; if a
vulnerable package is locked, no point running the rest. PHPStan runs before test since
type errors are faster to diagnose than runtime assertion failures.

**Note:** PHPStan `max` (level 10) is the current gate — reached in §35 as part of the
dependency upgrade to `sebastian/cli-parser ^3.0`. The upgrade and max-level pass happened
before the test suite was written, not after.

---

---

## 34. Versioning

### Scheme

**Semantic Versioning** (`MAJOR.MINOR.PATCH`). No fork-branding suffix in the version number —
the fork identity is already expressed in the package name (`phpcpd-next/phpcpd`), the banner, and
this document.

```
MAJOR  bumped when a change breaks backwards compatibility with upstream's CLI interface
MINOR  bumped when new detection capability or output format is added
PATCH  bumped for bug fixes and toolchain-only changes
```

Pre-release labels (`-rc.1`, `-alpha.1`) are allowed before a major jump.

### Single source of truth

The version lives in exactly one place:

```php
// src/CLI/Application.php
private const string VERSION = '0.1.0';
```

Composer does **not** carry a `"version"` key — the version is derived from the git tag at
install time, which is the standard Composer-package practice.

### Release process

```bash
# 1. Bump the constant and get the checklist printed:
composer release 0.2.0        # wraps bin/release.sh

# 2. Fill in CHANGELOG.md ([Unreleased] → [0.2.0])

# 3. Commit + tag:
git add src/CLI/Application.php CHANGELOG.md
git commit -m "release: v0.2.0"
git tag -s v0.2.0 -m "v0.2.0"
git push origin main v0.2.0
```

`bin/release.sh` validates SemVer shape, checks the working tree is clean, and does the
`sed` substitution — it does not commit or tag (those steps stay manual so the
CHANGELOG entry is always reviewed before the tag lands).

### New files in this section

| File | Purpose |
|------|---------|
| `CHANGELOG.md` | User-facing release notes (Keep a Changelog 1.1.0 format) |
| `bin/release.sh` | Version-bump helper; run via `composer release <version>` |

### `composer.json` diff

```diff
  "scripts": {
      ...
+     "release": "bash bin/release.sh"
  }
```

---

## 35. Dependency upgrade — `sebastian/*` to current majors + PHPUnit latest

### Context

Sebastian Bergmann authored phpcpd, PHPUnit, and all the `sebastian/*` support libraries as a
unified ecosystem. When he archived phpcpd in 2023 the production deps were pinned to the major
versions current at that time. PHPUnit's own releases since then bumped `sebastian/cli-parser`
and `sebastian/version` to new majors, creating a conflict that blocked installing PHPUnit as a
dev dependency.

### `composer.json` constraint widening

```diff
-    "sebastian/cli-parser": "^2.0",
+    "sebastian/cli-parser": "^2.0 || ^3.0 || ^4.0 || ^5.0",

-    "sebastian/version": "^4.0",
+    "sebastian/version": "^4.0 || ^5.0",

-    "phpunit/php-file-iterator": "^4.0 || ^5.0",
+    "phpunit/php-file-iterator": "^4.0 || ^5.0 || ^6.0",

-    "phpunit/phpunit": "^11.0 || ^12.0",
+    "phpunit/phpunit": "^11.0 || ^12.0 || ^13.0",
```

**Why widened rather than pinned to a single new major:** the codebase must remain installable
alongside other packages in consumer projects that may still be on any of these major lines.
Composer picks the newest satisfying version; the lock file records the actual resolved versions.

### Resolved lock file (after `composer update --with-all-dependencies`)

| Package | Was | Now |
|---------|-----|-----|
| `sebastian/cli-parser` | `2.0.1` | `3.0.2` |
| `sebastian/version` | `4.0.1` | `5.0.2` |
| `phpunit/phpunit` | *(not installed)* | `11.5.55` |
| `rector/rector` | *(not installed)* | `2.5.2` |
| `friendsofphp/php-cs-fixer` | *(not installed)* | `3.95.11` |

### `sebastian/cli-parser` v2 → v3 — API change

v3 changed the return type of `Parser::parse()`:

```diff
-  @return array{0: list<array{0: string, 1: string|false}>, 1: list<non-empty-string>}
+  @return array{0: list<array{0: non-empty-string, 1: ?non-empty-string}>, 1: list<non-empty-string>}
```

Option values are now `?non-empty-string` (null for flags) instead of `string|false`.
Three call sites in `ArgumentsBuilder.php` updated:

```diff
  case '--suffix':
-     if ($option[1] !== false && $option[1] !== '') {
+     if ($option[1] !== null) {

  case '--exclude':
-     if ($option[1] !== false && $option[1] !== '') {
+     if ($option[1] !== null) {

  case '--log-pmd':
-     $pmdCpdXmlLogfile = $option[1] !== false ? $option[1] : null;
+     $pmdCpdXmlLogfile = $option[1];
```

The empty-string guard on `--suffix` and `--exclude` is also dropped: `non-empty-string`
guarantees the value is never empty, so the check was redundant.

### `phpstan-stubs.php` — emptied

v3 of `sebastian/cli-parser` ships its own full PHPDoc. The stub that patched the missing
annotations in v2 was emptied to avoid masking the real vendor types. See §32b.

### PHPStan `max` — reached as part of this upgrade

With the stub gone, PHPStan reads the real v3 annotations and confirms zero errors at `max`
without any further code changes. `phpstan.neon` updated from `level: 9` to `level: max`.

---

## 36. Code quality sweep — PHP 8.x idioms applied

A targeted pass over the source after the toolchain was fully wired, applying modern PHP idioms
that Rector did not catch because they involve semantic understanding rather than mechanical
pattern matching.

### 36a. `src/CodeClone.php` — arrow function

```diff
-    array_map(
-        static function (string $line) use ($indent) {
-            return $indent . $line;
-        },
+    array_map(
+        static fn (string $line) => $indent . $line,
```

Single-expression closure with one captured variable — exactly the arrow-function use case.

---

### 36b. `src/Detector/Detector.php` — `empty()` on string

```diff
-    if (empty($file)) {
+    if ($file === '') {
```

**Why:** `empty($x)` returns `true` for `''`, `'0'`, `0`, `null`, `[]`, and `false`. The
parameter is typed `string`, so only the empty-string case can occur — `=== ''` makes the
intent exact and avoids the implicit type-coercion behaviour of `empty`.

---

### 36c. `src/Detector/Strategy/SuffixTree/PairList.php` — multiple cleanups

**Drop unused capacity parameter**

```diff
-    public function __construct(int $initialCapacity)
-    {
-        if ($initialCapacity < 1) {
-            $initialCapacity = 1;
-        }
-        unset($initialCapacity);
+    public function __construct()
+    {
```

`$initialCapacity` was a Java-ism (pre-sizing arrays is meaningless in PHP — arrays grow
dynamically). The guard and `unset` were both vestigial. The single call site `new PairList(16)`
updated to `new PairList()`.

**Extract `extractList()` private helper**

`extractFirstList()` and `extractSecondList()` were identical loops over different array fields.
Extracted to a private `extractList(array $elements): array` with a `@template T` annotation,
reducing duplication to a single line per public method.

**Array-destructure swap in `swapEntries()`**

```diff
-    $tmp1 = $this->getFirst($i);
-    $tmp2 = $this->getSecond($i);
-    $this->setFirst($i, $this->getFirst($j));
-    $this->setSecond($i, $this->getSecond($j));
-    $this->setFirst($j, $tmp1);
-    $this->setSecond($j, $tmp2);
+    [$this->firstElements[$i], $this->firstElements[$j]]   = [$this->firstElements[$j], $this->firstElements[$i]];
+    [$this->secondElements[$i], $this->secondElements[$j]] = [$this->secondElements[$j], $this->secondElements[$i]];
```

PHP 7.1 array destructuring eliminates the temp variables and 4 method calls.
Bounds checks are now done once at the top of `swapEntries()` before the destructure.

---

### 36d. `src/Detector/Strategy/SuffixTreeStrategy.php` — `for`+index → `foreach`

```diff
-    $others = $cloneInfo->otherClones->extractFirstList();
-    for ($j = 0; $j < count($others); $j++) {
-        $otherStart = $others[$j];
+    foreach ($cloneInfo->otherClones->extractFirstList() as $otherStart) {
```

The `for` loop was indexing into `$others` only to retrieve each value — `foreach` is the
correct construct. Removes the temporary array variable and the `count` import.

---

### 36e. `src/Detector/Strategy/SuffixTree/ApproximateCloneDetectingSuffixTree.php` — several

**Cache `count($this->word)`**

`count($this->word)` appeared in two consecutive loop bounds. The word array is not modified
during the loops. Cached in `$wordCount` before the first loop, used in both.

**Drop temporary `$existingClones` variable**

```diff
-    $existingClones = $this->cloneInfos[$index] ?? null;
-    if (!empty($existingClones)) {
-        foreach ($existingClones as $ci) {
+    if (!empty($this->cloneInfos[$index])) {
+        foreach ($this->cloneInfos[$index] as $ci) {
```

The temp variable was only used to check emptiness and immediately iterate — inline both.

**`usort` closure → arrow function**

```diff
-    usort($values, static function (CloneInfo $a, CloneInfo $b): int {
-        return $b->length - $a->length;
-    });
+    usort($values, static fn (CloneInfo $a, CloneInfo $b): int => $b->length - $a->length);
```

**`print ... . $x . ...` → `printf`**

```diff
-    print 'Encountered buffer shortage: ' . $leafStart . ' ' . $leafLength . "\n";
+    printf("Encountered buffer shortage: %d %d\n", $leafStart, $leafLength);
```

`printf` with typed format specifiers (`%d`) is more explicit about the expected types and
eliminates three concatenation operations.

---

### 36f. CS-Fixer config — import grouping

```diff
-    'ordered_imports' => ['sort_algorithm' => 'alpha'],
-    'global_namespace_import' => ['import_classes' => false, 'import_functions' => true, 'import_constants' => false],
+    'ordered_imports' => ['sort_algorithm' => 'alpha', 'imports_order' => ['function', 'const', 'class']],
+    'global_namespace_import' => ['import_classes' => false, 'import_functions' => true, 'import_constants' => true],
```

**Why:** several files had `use function`, `use class`, and `use const` blocks interleaved
rather than grouped. Adding `imports_order` enforces the canonical PHP convention (functions
first, then constants, then classes, each group sorted alphabetically). `import_constants: true`
makes global constants consistent with global functions — both imported explicitly rather than
accessed with a backslash prefix.

CS-Fixer re-ran and fixed 11 files.

---

The following files were copied without any modification:

- `src/CLI/Arguments.php`
- `src/CLI/ArgumentsBuilder.php`
- `src/CodeClone.php`
- `src/CodeCloneFile.php`
- `src/CodeCloneMap.php`
- `src/CodeCloneMapIterator.php`
- `src/Detector/Detector.php`
- `src/Detector/Strategy/AbstractStrategy.php`
- `src/Detector/Strategy/DefaultStrategy.php`
- `src/Detector/Strategy/StrategyConfiguration.php`
- `src/Detector/Strategy/SuffixTree/Token.php`
- `src/Detector/Strategy/SuffixTreeStrategy.php`
- `src/Exceptions/Exception.php`
- `src/Exceptions/ArgumentsBuilderException.php`
- `src/Exceptions/InvalidStrategyException.php`
- `src/Exceptions/MissingResultException.php`
- `src/Exceptions/OutOfBoundsException.php`
- `src/Log/PMD.php`
- `src/Log/Text.php`

---

## 37. `src/CLI/ArgumentsBuilder.php` — named arguments at `new Arguments(...)` call site

```diff
-        return new Arguments(
-            $directories,
-            $suffixes,
-            $exclude,
-            $pmdCpdXmlLogfile,
-            $linesThreshold,
-            $tokensThreshold,
-            $fuzzy,
-            $verbose,
-            $help,
-            $version,
-            $algorithm,
-            $editDistance,
-            $headEquality,
-        );
+        return new Arguments(
+            directories:      $directories,
+            suffixes:         $suffixes,
+            exclude:          $exclude,
+            pmdCpdXmlLogfile: $pmdCpdXmlLogfile,
+            linesThreshold:   $linesThreshold,
+            tokensThreshold:  $tokensThreshold,
+            fuzzy:            $fuzzy,
+            verbose:          $verbose,
+            help:             $help,
+            version:          $version,
+            algorithm:        $algorithm,
+            editDistance:     $editDistance,
+            headEquality:     $headEquality,
+        );
```

**Why:** `Arguments` is a `final readonly class` with 13 promoted constructor parameters.
Positional arguments at the call site create a silent-bug trap: swapping two `bool` params
(e.g. `$fuzzy` and `$verbose`) produces no type error and no runtime crash — PHP accepts it
silently. Named arguments make each assignment self-documenting and order-independent, so a
future parameter reorder can never silently corrupt a call site.

**Why not a builder or DTO factory:** the only call site is `ArgumentsBuilder::build()`, which
already has descriptive local variables. A builder class would add indirection with no gain.
Named arguments give the same safety and readability at zero cost.

---

## 38. `src/Detector/Strategy/SuffixTreeStrategy.php` — bug fix: empty file list throws

**Discovered while writing the test suite.**

```diff
-    /**
-     * @throws MissingResultException
-     */
     #[\Override]
     public function postProcess(): void
     {
         if ($this->result === null) {
-            throw new MissingResultException('Missing result');
+            return;
         }
```

**Why:** `Detector::copyPasteDetection()` unconditionally calls `postProcess()` after iterating
the file list. When the list is empty, `processFile()` is never called, so `$this->result`
stays `null`. The original code threw `MissingResultException` — but an empty scan is a valid
call path, not a programmer error. The caller (`Detector`) creates its own fresh `CodeCloneMap`
and returns it regardless; the early return leaves that map empty, which is correct.

The `MissingResultException` import was also removed as it became unused.

**Why the original throw existed:** `postProcess()` was intended to catch "called before any
`processFile()`" misuse. That intent is now covered by the test
`empty_file_list_produces_empty_map` — if someone breaks the `Detector` call order, the test
will fail rather than relying on a runtime exception.

---

## 39. Test suite — 38 tests, 63 assertions

### Structure

```
tests/
  fixtures/
    with_clones/
      Alpha.php    — function with a 21-line body
      Beta.php     — different function name, identical body
    no_clones/
      Unique.php   — unrelated code, no duplication
  ArgumentsBuilderTest.php   — 17 tests: all CLI flags, defaults, error cases
  DetectorTest.php           — 10 tests: both strategies via DataProvider, fixture files
  CodeCloneMapTest.php       — 11 tests: empty map, edge cases, regression guards
```

### `composer.json` — `autoload-dev` added

```diff
+ "autoload-dev": {
+     "psr-4": {
+         "SebastianBergmann\\PHPCPD\\Tests\\": "tests/"
+     }
+ },
```

PSR-4 autoloading for the `SebastianBergmann\PHPCPD\Tests\` namespace so test classes are
found without a manual `require`.

---

### `ArgumentsBuilderTest` — all CLI surface tested

Every flag, short alias, multi-value accumulation, default value, and error case:

| Test | What it verifies |
|------|-----------------|
| `defaults_are_set_when_only_directory_is_given` | All 13 defaults simultaneously |
| `suffix_flag_appends_to_default` | `--suffix .php5` → `['.php', '.php5']` |
| `suffix_flag_can_be_given_multiple_times` | Multiple `--suffix` accumulate |
| `exclude_flag_is_collected` | Multiple `--exclude` accumulate |
| `min_lines_flag_overrides_default` | `--min-lines 10` → `linesThreshold: 10` |
| `min_tokens_flag_overrides_default` | `--min-tokens 100` → `tokensThreshold: 100` |
| `fuzzy_flag_enables_fuzzy_mode` | `--fuzzy` → `fuzzy: true` |
| `verbose_flag_enables_verbose_mode` | `--verbose` → `verbose: true` |
| `log_pmd_flag_sets_output_path` | `--log-pmd /tmp/r.xml` → `pmdCpdXmlLogfile` set |
| `algorithm_flag_overrides_default` | `--algorithm suffixtree` → stored |
| `edit_distance_flag_overrides_default` | `--edit-distance 3` stored |
| `head_equality_flag_overrides_default` | `--head-equality 5` stored |
| `help_flag_does_not_require_a_directory` | `--help` alone is valid |
| `version_flag_does_not_require_a_directory` | `--version` alone is valid |
| `short_h_flag_sets_help` | `-h` → `help: true` |
| `short_v_flag_sets_version` | `-v` → `version: true` |
| `missing_directory_throws` | No args → `ArgumentsBuilderException` |
| `unknown_flag_throws` | Bad flag → `ArgumentsBuilderException` |

---

### `DetectorTest` — fixture-based, both strategies

`#[DataProvider]` runs each test against both `DefaultStrategy` and `SuffixTreeStrategy`.
Tests call `Detector::copyPasteDetection()` directly — no subprocess, no filesystem mocking.

**Finding from writing these tests:** the two strategies filter clones differently.

- **DefaultStrategy** (Rabin-Karp): uses `--min-lines` as a post-detection line-count filter.
  A clone below the line threshold is detected and then discarded.
- **SuffixTreeStrategy**: uses `--min-tokens` as the primary detection gate inside
  `ApproximateCloneDetectingSuffixTree::findClones()`. Line count is not independently checked.

This means `--min-lines` suppresses clones in one strategy but not the other — a real
behavioural difference between the two algorithms. The tests make this explicit with separate
assertions per strategy for the threshold cases, rather than assuming they behave identically.

---

### `CodeCloneMapTest` — edge cases and regressions

**Finding from writing these tests:** `CodeClone::id()` is `md5($this->lines())`, where
`lines()` reads the actual file off disk. Fake filenames like `'a.php'` all produce `md5('')`
when the file is missing — so two clones with different fake names get the same ID and are
merged by `CodeCloneMap::add()`. Fixed by using real fixture files in any test that needs
distinct clone IDs.

Key coverage:

| Test | What it guards |
|------|---------------|
| `average_size_on_empty_map_returns_zero_not_division_error` | §14 regression: `averageSize()` on empty map now returns `0.0` |
| `duplicate_clone_id_merges_rather_than_double_counts` | `add()` deduplication logic |
| `largest_size_tracks_the_biggest_clone` | `largestSize()` updates correctly |
| `percentage_is_100_when_no_total_lines_tracked` | Edge: zero `numberOfLines` → 100% (upstream behaviour) |
| `percentage_reflects_duplicated_fraction` | Normal case: 10 / 100 lines = 10.00% |

---

## 40. Fork identity: namespace, autoloading, and dependency independence

After the test suite landed, the fork took on its own identity and shed its runtime
dependencies. These are larger structural changes than the earlier idiom sweeps.

### 40a. Namespace → `LucianoPereira\PhpcpdNext`

The code namespace moved from `SebastianBergmann\PHPCPD` to `LucianoPereira\PhpcpdNext`
(in two scoped steps: vendor segment first, then package segment). The replace was scoped to
the `\PHPCPD` prefix so the external `SebastianBergmann\{Version,CliParser,FileIterator,Timer}`
imports were never touched. **Copyright attribution is unchanged** — file headers and `LICENSE`
still credit Sebastian Bergmann (BSD-3 requires it); only the code namespace reflects the new
maintainer. The CLI binary's bootstrap (`phpcpd` line 44) had to be renamed too — it lives
outside `src/` and the first sweep missed it, producing a fatal until fixed.

### 40b. Autoloading: `classmap` → PSR-4

```diff
 "autoload": {
-    "classmap": ["src/"]
+    "psr-4": {
+        "LucianoPereira\\PhpcpdNext\\": ["src/", "src/CLI/", "src/Exceptions/"]
+    }
 },
```

**Why:** classmap has no on-demand fallback, so every new class needed `composer dump-autoload`
(this bit during development — runtime "class not found" while PHPStan stayed green). PSR-4
resolves on demand. The **multi-path** mapping is required because `CLI/*` and `Exceptions/*`
keep the root namespace despite living in subdirectories (the rest use sub-namespaces matching
their directories — which is the reason upstream chose classmap in the first place).

### 40c. Dependency independence — all four sebastian/phpunit deps replaced

The tool shipped with four runtime dependencies, all from the PHPUnit/sebastian ecosystem. Each
was removed or replaced with owned code, **decoupling the tool from the PHPUnit release train**
(the source of the §35 version-constraint pain — `cli-parser ^2→^5`, `file-iterator ^4→^6`).

| Was | Now | Improvement, not just ownership |
|-----|-----|--------------------------------|
| `sebastian/version` | deleted | dead import — `Application::VERSION` is a constant; never used |
| `sebastian/cli-parser` | `CLI/{OptionDefinition,OptionParser,Options}` | one declarative spec drives parsing **and** `--help` (so they cannot drift — it fixed a live bug where `--verbose` was parsed but undocumented) **and** value validation (`--algorithm` rejects unknown values at parse time) |
| `phpunit/php-timer` | `Util/{Timer,ResourceUsageFormatter}` | `hrtime()` monotonic clock + **domain throughput**: `Time: 0.006s, Memory: 6.00 MB — 35 files (6322 files/s)` |
| `phpunit/php-file-iterator` | `Util/FileFinder` | **prunes excluded directories during traversal** (never descends into `vendor/`) + **glob excludes** (`*.blade.php`), substring excludes kept for compatibility |

**Result: zero composer-package runtime dependencies.** Production `require` is now `php >=8.5`
plus two genuinely-used extensions: `ext-dom` (PMD XML output via `DOMDocument`) and
`ext-mbstring` (UTF-8 conversion in `AbstractXmlLogger`). The four packages remain only as
*test-time* transitives of `phpunit/phpunit` (dev), which is fine.

*One caught mistake worth recording:* `ext-mbstring` was briefly removed on a faulty grep
(`mb_[a-z]+\(` cannot match `mb_convert_encoding` — `[a-z]+` excludes the underscore). A looser
re-grep found the real usage in `AbstractXmlLogger`, so it was re-added. The tool *ran* regardless
because mbstring is installed locally — but the composer contract would have been wrong.

### 40d. Tests

`OptionsTest` (help-can't-drift, data-provider over every option), `FileFinderTest`
(suffix / substring-exclude / glob-exclude / directory-pruning / dedup), `ResourceUsageFormatterTest`
(time, memory, throughput, minute formatting) — plus new `ArgumentsBuilderTest` cases for
validation, `=value`, and missing-value. Suite: **87 tests, 156 assertions**, PHPStan max, CS clean.

---

## 41. Output formats — a `Logger` contract, JSON + SARIF, and an XML cleanup

### 41a. Shared `Logger` interface

The report writers had no common contract (`PMD extends AbstractXmlLogger`, others ad hoc).
`Log\Logger` (`process(CodeCloneMap): void`) now unifies the file-output formats, all projecting
the **same format-neutral `CodeCloneMap`** to their own serialisation — fan-out from the model,
never transcoding one format into another (model→XML and model→SARIF are lossless projections;
SARIF→XML would be a lossy schema mapping).

### 41b. XML cleanup — `createTextNode` over hand-rolled escaping

```diff
-$this->document->createElement('codefragment', $this->escapeForXml($clone->lines()))
+$codefragment = $duplication->appendChild($this->document->createElement('codefragment'));
+$codefragment->appendChild($this->document->createTextNode($this->sanitizeForXml($clone->lines())));
```

`AbstractXmlLogger` carried ~40 lines reinventing what `DOMDocument` does: a 25-line hand-rolled
UTF-8 byte-scanner (→ `mb_check_encoding`) and manual `htmlspecialchars` escaping that only worked
via a subtle escape-then-DOM-reparse round-trip. `createTextNode` escapes once, natively. Only the
illegal-XML-character strip (NUL etc., invalid in XML 1.0 even escaped) was kept. **Output is
identical and verified well-formed** — this is a clarity/robustness cleanup, not a bug fix.

### 41c. JSON and SARIF

- `Log\Json` — modern, script-friendly: tool/version/summary/clones with the `gapped` flag.
- `Log\Sarif` — **SARIF 2.1.0**, ingested natively by GitHub Code Scanning. Gapped (Type-3 /
  inconsistent) clones map to `level: warning`, exact clones to `note`, so the bug-bearing clones
  (the R4 signal) surface as higher-severity PR annotations.
- `--log-json` / `--log-sarif` added to the declarative `Options` spec — so they were
  auto-documented in `--help` and auto-covered by `OptionsTest` with no extra wiring.
- `Version::NUMBER` extracted as the single source for the tool version (used by both new loggers
  and `Application`).

Suite after this section: **94 tests, 173 assertions**, PHPStan max, CS clean.

---

## 42. Incremental result cache (R5) — faster CI re-runs

`src/Cache/CloneCache.php` persists each run's `CodeCloneMap` to
`{cache-dir}/{configFingerprint}.json`. The fingerprint is a sha256 of the algorithm and every
threshold, so different configurations coexist in one cache directory without collision. A run is a
**hit** only when *every* scanned file's sha256 matches the stored manifest; any changed, added, or
removed file is a miss and forces a full re-scan.

```diff
+ $cache  = $arguments->cacheDir() !== null
+     ? new CloneCache($arguments->cacheDir(), CloneCache::configFingerprint($arguments)) : null;
+ $clones = $cache?->get($files);
+ if ($clones === null) { $clones = (new Detector($strategy))->copyPasteDetection($files); $cache?->put($files, $clones); }
```

**Why a new `CodeCloneMap::setNumberOfDuplicatedLines()`:** `add()` accumulates the duplicated-line
stat as `lines × (files − 1)` per call, which is correct for the 2-file clones the strategies emit
but is awkward to reconstruct when a 3+-occurrence clone is restored as a single object. The cache
stores the exact stat and restores it directly, so a warm run reports identical numbers to the cold
run — verified end-to-end (`Found 1 code clones (1 inconsistent) with 34 duplicated lines`, warm,
at `Time: 0.001s`).

CLI: `--cache` (default `.phpcpd-cache/`) and `--cache-dir <path>`. Built to mount with
`actions/cache` keyed on `hashFiles('**/*.php')`. Tests: `tests/CloneCacheTest.php` (7).

---

## 43. Per-file incremental index (Hummel) — re-scan only what changed

§42's cache is **coarse**: one changed file invalidates the entire run. This section adds the
finer-grained alternative described as future work there — Hummel et al.'s per-file index (*Index-Based
Code Clone Detection*, ICSM 2010). Instead of caching the *result*, it caches each file's
*tokenization* and re-tokenizes only the files that actually changed.

**The seam.** `DefaultStrategy::processFile()` did two things: tokenize (`token_get_all` + build the
5-byte-per-token signature + line tables) and scan (slide the min-tokens window, merge runs against a
running hash table). These are split into two public methods with the body unchanged:

```php
public function processFile(string $file, CodeCloneMap $result): void
{
    $buffer = file_get_contents($file);
    if ($buffer === false) { return; }
    $this->scan($file, $this->tokenize($buffer), $result);   // tokenize is pure; scan is stateful
}
```

`tokenize()` returns a `FileTokens` value object (`numberOfLines`, binary `signature`, two line
tables) — exactly the per-file work that dominates a scan, and the unit the index persists
(signature base64-encoded). The refactor is behaviour-preserving: every pre-existing
`DefaultStrategy` test stayed green.

**The index.** `src/Cache/IncrementalIndex.php` stores `{file → {hash, FileTokens}}` at
`{dir}/{fingerprint}.idx.json` (`.idx.json`, so it never collides with §42's `.json`). Per run it:
loads the index; for each file, reuses the stored `FileTokens` if its sha256 still matches, else
re-tokenizes; feeds **every** file's tokens through `scan()` in the same order a full pass uses; then
persists the refreshed index. It returns an `IndexResult` carrying the clones plus a `reused`/`scanned`
split (the incrementality metric).

**Why this is provably correct, not just faster.** Detection is replayed from the *same* chunk hashes
through the *same* merge — only the tokenization of unchanged files is skipped. So the incremental map
is byte-for-byte identical to a non-incremental Rabin–Karp run. `IncrementalIndexTest` pins this as its
headline property (`index_detection_equals_a_full_rabin_karp_pass`) and re-asserts it after a change,
an addition, and a removal — incrementality never changes the answer, only the work.

CLI: `--incremental` (Rabin–Karp only; reuses the cache directory). Application prints
`(incremental index: N reused, M scanned)`; requested with another algorithm it prints a one-line
notice and falls back to §42. Verified end-to-end: cold `0 reused, 3 scanned` → warm `3 reused, 0
scanned` → edit one file `2 reused, 1 scanned`, each result matching a plain run. Tests:
`tests/IncrementalIndexTest.php` (7) + `FileTokens`/`IndexResult` coverage.

**First-cut limits (logged):** the index still reads every file to hash it (mtime short-circuit is
future work) and serializes per-file token tables as JSON (a binary/compressed format would shrink the
index on large corpora). Both are size/IO optimizations, not correctness gaps.

---

## Not changed (intentionally deferred)

| What | Why deferred |
|------|-------------|
| `Arguments.php` constructor — 13 params in one line | ✅ Done — named arguments at call site, see §37 |
| `SuffixTree` / `ApproximateCloneDetectingSuffixTree` use `==` null on arrays | PHP loose-comparison `[] == null` is `true` — behaviour is intentional, not a bug |
| PHPStan level 9 clean pass | ✅ Done — see §32 |
| PHPStan `max` (level 10) | ✅ Done — see §35 |
| Dependency upgrade to current sebastian/* majors + PHPUnit latest | ✅ Done — see §35 |
| Code quality sweep — PHP 8.x idioms | ✅ Done — see §36 |
| `DefaultStrategy` duplicate block (the clone PHPCPD finds in itself) | ✅ Done — extracted `recordCloneIfValid()` in §31; `phpcpd src/` reports zero clones |
| `SuffixTreeStrategy::postProcess()` throws on empty file list | ✅ Done — fixed in §38, discovered by the test suite |
| Test suite — writing actual tests | ✅ Done — see §39; 38 tests, 63 assertions, all green |

---

## Runtime dependencies

**None** (no composer packages). After §40c, the production `require` is:

| Requirement | Why |
|-------------|-----|
| `php: >=8.5` | language baseline |
| `ext-dom` | PMD-CPD XML output (`DOMDocument` in `AbstractXmlLogger`) |
| `ext-mbstring` | UTF-8 conversion in `AbstractXmlLogger` |

The four sebastian/phpunit packages that used to be required (`php-file-iterator`, `php-timer`,
`cli-parser`, `version`) were replaced with owned code or deleted (§40c). They remain installed
only as transitive *dev* dependencies of `phpunit/phpunit`.

Dev dependencies (not present upstream):

| Package | phpcpd-next req | Locked version |
|---------|-----------|----------------|
| `friendsofphp/php-cs-fixer` | `^3.64` | `3.95.11` |
| `phpstan/phpstan` | `^2.2` | current |
| `phpunit/phpunit` | `^11.0 \|\| ^12.0 \|\| ^13.0` | `11.5.55` |
| `rector/rector` | `^2.0` | `2.5.2` |

---

## How to add a change entry

1. Make the code change.
2. Add an entry in this file under a new numbered section (or append to an existing section if the file already has one).
3. Use diff blocks for every changed line — no paraphrasing.
4. State **why** the change was made (deprecation, API break, correctness, style).
5. If the change is intentionally deferred, add it to the "Not changed" table instead.

<?php namespace Uralmedias\Linker;


use Uralmedias\Linker\Selector;
use Uralmedias\Linker\Injector;
use Uralmedias\Linker\Accessor;
use DOMDocument, DOMXPath, Closure;


/**
 * Основной класс библиотеки, который позволяет манипулировать
 * фрагментами шаблонов.
 */
class Fragment
{

    public static bool $lazyImport = TRUE;
    public static bool $lazyParsing = TRUE;
    public static bool $lazyLoading = TRUE;
    public static bool $asumeStatic = TRUE;
    public static bool $cacheStrings = TRUE;
    public static bool $lazyExecution = TRUE;
    public static bool $asumeIdempotence = TRUE;

    private static $cache = [];

    private DOMDocument $document;
    private DOMXPath $xpath;
    private Closure $loader;
    private bool $loaded;


    private function __construct(bool $lazy, callable $loader)
    {
        $this->loader = $loader;
        $this->loaded = FALSE;
        if (!$lazy) {
            $this->fetch();
        }
    }


    /**
     * Превращает экземпляр в текст.
     */
    public function __toString(): string
    {
        $this->query();
        return html_entity_decode($this->document->saveHTML(), ENT_HTML5);
    }


    /**
     * Правильно клонирует экземпляр.
     */
    public function __clone ()
    {
        if ($this->loaded) {
            $this->document = clone $this->document;
            $this->xpath = new DOMXPath($this->document);
        }
    }


    /**
     * Инициализирует экземпляр при использовании ленивой инициализации.
     */
    public function fetch (bool $reset = FALSE): bool
    {
        if (!$this->loaded or $reset) {

            $this->document = ($this->loader)();
            $this->xpath = new DOMXPath($this->document);
            $this->loaded = TRUE;
            return TRUE;
        }

        return FALSE;
    }


    /**
     * Создаёт экземпляр из коллекции DOMNode.
     */
    public static function fromNodes (iterable $nodes): self
    {
        return new static (static::$lazyImport, function () use ($nodes) {

            $doc = new DOMDocument("1.0", "utf-8");
            foreach ($nodes as $n) {
                $doc->appendChild($doc->importNode($n, TRUE));
            }

            return $doc;
        });
    }


    /**
     * Создаёт экземпляр из текстовых данных.
     */
    public static function fromString (string $contents): self
    {
        return new static (static::$lazyParsing, function () use ($contents) {

            $createDoc = function () use ($contents) {

                $doc = new DOMDocument("1.0", "utf-8");
                $doc->loadHTML($contents);
                return $doc;
            };

            if (static::$cacheStrings) {

                $cacheKey = 'S'.crc32($contents);

                if (!array_key_exists($cacheKey, self::$cache)) {
                    self::$cache[$cacheKey] = $createDoc();
                }
                return clone self::$cache[$cacheKey];
            }

            return $createDoc();
        });
    }


    /**
     * Создаёт экземпляр из текстового файла.
     */
    public static function fromFile (string $filename): self
    {
        return new static (static::$lazyLoading, function () use ($filename) {

            $createDoc = function () use ($filename) {

                $doc = new DOMDocument("1.0", "utf-8");
                $doc->loadHTMLFile($filename);
                return $doc;
            };

            if (static::$asumeStatic) {

                $cacheKey = 'F'.$filename;
                if (!array_key_exists($cacheKey, self::$cache)) {
                    self::$cache[$cacheKey] = $createDoc();
                }
                return  clone self::$cache[$cacheKey];
            }

            return $createDoc();
        });
    }


    /**
     * Создаёт экземпляр из выводимого функцией текста.
     */
    public static function fromBuffer (callable $process): self
    {
        return new static (static::$lazyExecution, function () use ($process) {

            $createDoc = function () use ($process) {

                ob_start();
                call_user_func($process);
                $doc = new DOMDocument("1.0", "utf-8");
                $doc->loadHTML(ob_get_contents());
                ob_end_clean();
                return $doc;
            };

            if (static::$asumeIdempotence) {

                $cacheKey = 'B'.spl_object_hash((object) $process);
                if (!array_key_exists($cacheKey, self::$cache)) {
                    self::$cache[$cacheKey] = $createDoc();
                }
                return clone self::$cache[$cacheKey];
            }

            return $createDoc();
        });
    }


    /**
     * Минимизирует, убирая лишние данные.
     */
    public function minimize (bool $comments = FALSE, bool $scripts = FALSE)
    {
        $q = $scripts ?
            '//text()[not(parent::pre)]':
            '//text()[not(parent::script) and not(parent::style) and not(parent::pre)]';

        $nodes = $this->query($q);
        foreach ($nodes as $n) {
            $n->textContent = preg_replace('/\s+/', ' ', $n->textContent);
        }

        if ($comments) {
            $nodes = $this->query('//comment()');
            foreach ($nodes as $n) {
                $n->parentNode->removeChild($n);
            }
        }
    }


    /**
     * Создаёт новый экземпляр, вырезая часть существующего.
     */
    public function cut (string ...$selectors): self
    {
        $nodes = $this->query(...$selectors);
        foreach ($nodes as $n) {
            if ($parent = $n->parentNode) {
                $parent->removeChild($n);
            }
        }

        return static::fromNodes($nodes);
    }


    /**
     * Создаёт экземпляр, копируя часть существующего.
     */
    public function copy (string ...$selectors): self
    {
        return static::fromNodes($this->query(...$selectors));
    }


    public function move (string ...$selectors): Injector
    {
        return $this->put($this->cut(...$selectors));
    }


    /**
     * Расширяет текущий экземпляр контентом другого.
     */
    public function put (self ...$fragments): Injector
    {
        $this->fetch();

        $nodes = [];
        foreach ($fragments as $f) {
            $f->query();
            foreach ($f->document->childNodes as $n) {
                $nodes[] = $n;
            }
        }

        return new Injector ($this->xpath, ...$nodes);
    }


    /**
     * Втавляет текстовый узел.
     */
    public function write (string ...$strings): Injector
    {
        $this->fetch();

        $nodes = [];
        foreach ($strings as $s) {
            $nodes[] = $this->document->createTextNode($s);
        }

        return new Injector ($this->xpath, ...$nodes);
    }


    /**
     * Вставляет коментарий.
     */
    public function annotate (string ...$comments): Injector
    {
        $this->fetch();

        $nodes = [];
        foreach ($comments as $c) {
            $nodes[] = $this->document->createComment($c);
        }

        return new Injector ($this->xpath, ...$nodes);
    }


    /**
     * Позволяет читать и изменять атрибуты элементов внутри экземпляра.
     */
    public function nodes (string ...$selectors): Accessor
    {
        return new Accessor (...$this->query(...$selectors));
    }


    /**
     * Возвращает список ссылок на ресурсы из экземляра.
     */
    public function assets (array $updates = [], bool $assumeRE = FALSE): array
    {
        $walkAssets = function (string $xpath) use ($updates, $assumeRE) {

            $result = [];
            $nodes = $this->query($xpath);
            $search = array_keys($updates);
            $replacement = array_values($updates);

            foreach ($nodes as $n) {

                $n->value = $assumeRE ?
                    preg_replace ($search, $replacement, $n->value):
                    str_replace ($search, $replacement, $n->value);

                $result[] = $n->value;
            }

            return $result;
        };

        return array_unique(array_merge(
            $walkAssets('//@*[name() = "src"]'),
            $walkAssets('//@*[name() = "href"]'),
            $walkAssets('//@*[name() = "xlink:href"]')
        ));
    }


    private function query (string ...$selectors): array
    {
        $this->fetch();

        $nodes = [];
        foreach ($selectors as $s) {
            $list = $this->xpath->query(Selector::query($s));
            foreach ($list as $n) {
                $nodes[] = $n;
            }
        }

        return $nodes;
    }

}
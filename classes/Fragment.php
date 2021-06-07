<?php namespace Uralmedias\Linker;


use Uralmedias\Linker\Selector;
use Uralmedias\Linker\Injector;
use Uralmedias\Linker\Accessor;
use DOMDocument, DOMXPath;


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
    public static bool $lazyExecution = TRUE;
    public static bool $asumeIdempotence = TRUE;

    private static $cache = [];

    private DOMDocument $document;
    private DOMXPath $xpath;
    private $initializer = NULL;


    private function __construct(bool $lazy, callable $initializer)
    {
        $this->initializer = $initializer;
        if (!$lazy) {
            $this->touch();
        }
    }


    public function __toString(): string
    {
        $this->touch();
        return html_entity_decode($this->document->saveHTML(), ENT_HTML5);
    }


    /**
     * Загружает и парсит данные.
     */
    public function touch(): void
    {
        if ($this->initializer) {
            $doc = ($this->initializer)();
            $this->initializer = NULL;
            $this->document = $doc;
            $this->xpath = new DOMXPath($doc);
        }
    }


    /**
     * Создаёт новый фрагмент, наполняя его из любого итерируемого
     * объекта, который предоставляет узлы DOM в качестве элементов.
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
     * Создаёт новый фрагмент разбирая входную строку.
     */
    public static function fromString (string $contents): self
    {
        $fragment = new static (static::$lazyParsing, function () use ($contents) {

            $doc = new DOMDocument("1.0", "utf-8");
            $doc->loadHTML($contents);

            return $doc;
        });

        return $frament;
    }


    /**
     * Создаёт новый фрагмент разбирая загруженный файл.
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
     * Читает и разбирает стандартный вывод внутри функции.
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
     * Убирает лишние пробельные символы из текстовых узлов.
     *
     * Если $comments == TRUE, то удаляет комментарии. Если $scripts == TRUE,
     * то работает внутри тегов <script> и <style> и может привести к поломкам.
     */
    public function minimize (bool $comments = FALSE, bool $scripts = FALSE)
    {
        $this->touch();

        $q = $scripts ?
            '//text()[not(parent::pre)]':
            '//text()[not(parent::script) and not(parent::style) and not(parent::pre)]';

        $list = $this->xpath->query($q);
        foreach ($list as $l) {
            $l->textContent = preg_replace('/\s+/', ' ', $l->textContent);
        }

        if ($comments) {
            $list = $this->xpath->query('//comment()');
            foreach ($list as $l) {
                $l->parentNode->removeChild($l);
            }
        }
    }


    /**
     * Перемещает выбранные узлы в новый фрагмент.
     *
     * **По факту - перемещает копии узлов, а оригиналы удаляет -
     * будьте аккуратнее с полученными ранее ссылками**
     */
    public function cut (...$selectors): self
    {
        $this->touch();

        $lists = [];
        $nodes = [];

        foreach ($selectors as $s) {
            $lists[] = $this->xpath->query(Selector::fromValue($s));
        }
        foreach ($lists as $l) {
            foreach ($l as $n) {
                $nodes[] = $n;
                if ($parent = $n->parentNode) {
                    $parent->removeChild($n);
                }
            }
        }
        return static::fromNodes($nodes);
    }


    /**
     * Дублирует выбранные селектором узлы в новый фрагмент
     */
    public function copy (mixed ...$selectors): self
    {
        $this->touch();

        $nodes = [];
        foreach ($selectors as $s) {
            $list = $this->xpath->query(Selector::fromValue($s));
            foreach ($list as $n) {
                $nodes[] = $n;
            }
        }

        return static::fromNodes($nodes);
    }


    /**
     * Вставляет (копирует) узлы одного фрагмента внутрь другого.
     */
    public function put (self ...$fragments): Injector
    {
        $this->touch();

        $nodes = [];
        foreach ($fragments as $f) {
            $f->touch();
            foreach ($f->document->childNodes as $n) {
                $nodes[] = $n;
            }
        }

        return new Injector ($this->xpath, ...$nodes);
    }


    /**
     * Втавляет текстовый узел внутрь фрагмента.
     */
    public function write (string ...$strings): Injector
    {
        $this->touch();

        $nodes = [];
        foreach ($strings as $s) {
            if ($str = $this->document->createTextNode($s)) {
                $nodes[] = $str;
            }
        }

        return new Injector ($this->xpath, ...$nodes);
    }


    /**
     * Вставляет коментарий внутрь фрагмента.
     */
    public function annotate (string ...$comments): Injector
    {
        $this->touch();

        $nodes = [];
        foreach ($comments as $c) {
            if ($com = $this->document->createComment($c)) {
                $nodes[] = $com;
            }
        }

        return new Injector ($this->xpath, ...$nodes);
    }


    /**
     * Получает доступ к параметров выбранных селектором узлов.
     */
    public function nodes (...$selectors): Accessor
    {
        $this->touch();

        $nodes = [];
        foreach ($selectors as $s) {
            $list = $this->xpath->query(Selector::fromValue($s));
            foreach ($list as $n) {
                $nodes[] = $n;
            }
        }

        return new Accessor (...$nodes);
    }


    public function assets (array $updates = [], bool $assumeRE = FALSE): array
    {
        $this->touch();

        $walkAssets = function (string $xpath) use ($updates, $assumeRE) {

            $result = [];
            $search = array_keys($updates);
            $replacement = array_values($updates);
            $list = $this->xpath->query($xpath);

            foreach ($list as $l) {

                $l->value = $assumeRE ?
                    preg_replace ($search, $replacement, $l->value):
                    str_replace ($search, $replacement, $l->value);

                $result[] = $l->value;
            }

            return $result;
        };

        return array_unique(array_merge(
            $walkAssets('//@*[name() = "src"]'),
            $walkAssets('//@*[name() = "href"]'),
            $walkAssets('//@*[name() = "xlink:href"]')
        ));
    }

}
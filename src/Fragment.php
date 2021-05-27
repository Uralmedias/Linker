<?php namespace Uralmedias\Linker;


use Uralmedias\Linker\Settings;
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

    static private $fileCache = [];
    static private $bufferCache = [];

    private DOMDocument $document;
    private DOMXPath $xpath;
    private $initProc = NULL;


    private function __construct() {}


    public function __toString(): string
    {
        $this->touch();
        return html_entity_decode($this->document->saveHTML(), ENT_HTML5);
    }


    /**
     * Выполняет процедуры загрузки содержимого, если они не были
     * выполнены до этого. Если при создании фрагмента указать
     * ленивую загрузку, то его содержимое будет формироваться
     * не в момент определения переменной, а по первому требованию.
     * С одной стороны - это упрощает оптимизацию при работе со
     * статическим содержимым, с другой - усложняет отладку при
     * работе с динамическим.
     */
    public function touch(): void
    {
        if (is_callable($this->initProc)) {
            ($this->initProc)();
        }
        $this->initProc = NULL;
    }


    /**
     * Создаёт новый фрагмент, наполняя его из любого итерируемого
     * объекта, который предоставляет узлы DOM в качестве элементов.
     */
    static public function fromNodes (iterable $nodes, bool $lazyImport = NULL): self
    {
        $lazyImport = $lazyImport === NULL ? Settings::$lazyImport : $lazyImport;

        $fragment = new static;
        $fragment->initProc = function ($nodes) {

            $doc = new DOMDocument("1.0", "utf-8");
            foreach ($nodes as $i) {
                $doc->appendChild($doc->importNode($i, true));
            }

            $fragment = new static;
            $fragment->document = $doc;
            $fragment->xpath = new DOMXPath($doc);
        };

        if (!$lazyImport) {
            $fragment->touch();
        }

        return $fragment;
    }


    /**
     * Создаёт новый фрагмент разбирая входную строку.
     */
    static public function fromString (string $contents, bool $lazyParsing = NULL): self
    {
        $lazyParsing = $lazyParsing === NULL ? Settings::$lazyParsing : $lazyParsing;

        $fragment = new static;
        $fragment->initProc = function () use ($fragment, $contents) {

            $doc = new DOMDocument("1.0", "utf-8");
            $doc->loadHTML($contents);

            $fragment = new static;
            $fragment->document = $doc;
            $fragment->xpath = new DOMXPath($doc);
        };

        if (!$lazyParsing) {
            $fragment->touch();
        }

        return $fragment;
    }


    /**
     * Создаёт новый фрагмент разбирая загруженный файл.
     *
     * Значение аргументов, установленных в NULL будет, взято из Settings. Призначении TRUE
     * у $asumeStatic файл будет загружен единожды за запрос - это оптимально для статики,
     * но опасно для динамического контента. При значении TRUE у $lazyLoading будет применяться
     * отложенная загрузка, что тоже не очень хорошо для динамического контента.
     */
    static public function fromFile (string $filename, bool $asumeStatic = NULL, bool $lazyLoading = NULL): self
    {
        $lazyLoading = $lazyLoading === NULL ? Settings::$lazyLoading : $lazyLoading;
        $asumeStatic = $asumeStatic === NULL ? Settings::$asumeStatic : $asumeStatic;

        $createDoc = function () use ($filename) {
            $doc = new DOMDocument("1.0", "utf-8");
            $doc->loadHTMLFile($filename);
            return $doc;
        };

        $fragment = new static;
        $fragment->initProc = function () use ($fragment, $filename, $asumeStatic, $createDoc) {

            if ($asumeStatic) {

                if (!array_key_exists($filename, self::$fileCache)) {
                    self::$fileCache[$filename] = $createDoc();
                }
                $fragment->document = clone self::$fileCache[$filename];

            } else {

                $fragment->document = $createDoc();
            }

            $fragment->xpath = new DOMXPath($fragment->document);
        };

        if (!$lazyLoading) {
            $fragment->touch();
        }

        return $fragment;
    }


    /**
     * Читает и разбирает стандартный вывод внутри функции.
     *
     * Значение аргументов, установленных в NULL будет, взято из Settings. Призначении TRUE
     * у $asumeIdempotence будет предполагаться, что функция идемпотентна и её вывод будет
     * закеширован на время выполнения запроса. При значении TRUE у $lazyExecution выполнение
     * будет отложено до первого запроса к данным.
     */
    static public function fromBuffer (callable $process, bool $asumeIdempotence = NULL, bool $lazyExecution = NULL): self
    {
        $lazyExecution = $lazyExecution === NULL ? Settings::$lazyExecution : $lazyExecution;
        $asumeIdempotence = $asumeIdempotence === NULL ? Settings::$asumeIdempotence : $asumeIdempotence;

        $createDoc = function () use ($process) {
            ob_start();
            call_user_func($process);
            $buffer = ob_get_contents();
            ob_end_clean();
            $doc = new DOMDocument("1.0", "utf-8");
            $doc->loadHTML($buffer);
            return $doc;
        };

        $fragment = new static;
        $fragment->initProc = function () use ($fragment, $process, $asumeIdempotence, $createDoc) {

            if ($asumeIdempotence) {

                $hash = spl_object_hash($process);
                if (!array_key_exists($hash, self::$bufferCache)) {
                    self::$bufferCache[$hash] = $createDoc();
                }
                $fragment->document = clone self::$fileCache[$hash];

            } else {

                $fragment->document = $createDoc();
            }

            $fragment->xpath = new DOMXPath($fragment->document);
        };

        if (!$lazyExecution) {
            $fragment->touch();
        }

        return $fragment;
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

}

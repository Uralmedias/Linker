# Uralmedias Linker

Набор классов для программирования обработки веб-контента. Использовать можно для:

* Редизайн и оптимизация старых сайтов
* Интеграция верстки, шаблонизация
* Тестирование и мониторинг веба
* Создание грабберов, парсеров
* Может быть, для чего-нибудь еще...

Идея для названия пришла не сразу и позаимствована у компилируемых языков. Эта библиотека решает сходные задачи, по крайней мере, для этого и создавалась. Чем больше машинного труда используется в работе, тем меньше ~~часов уйдёт на работу~~ устанет специалист. Ну а кроме шуток это - ещё и отличный способ повысить качество продукта и продуктивность в целом, ведь машины не устают и не ошибаются.

- Почему не шаблонизаторы? - потому что не особо помогают сберечь силы при многочисленных правках.
- Почему не XSLT? - потому что слишком замороченный.
- Почему не Document Object Model? - библиотека использует PHP DOM, но его интерфейсы не особо годятся для перечисленных целей. Смысл ведь в том, чтобы упростить код, а не наоборот.

## Установка
Для работы библиотеки нужно установить `composer`, затем с его помощью установить зависимости:
```bash
# Для использования:
composer i --no-dev
# Для разработки:
composer i
```
Для использования библиотеки во внешнем проекте необходимо добавить классы в автозагрузку. Поскольку сейчас это - закрытая версия для разработчиков, нужно будет настроить вручную. Для этого надо подключить к проекту файл `./linker/vendors/autoload.php`.

При разработке не забывайте запускать тесты перед комитом:
```php
./vendor/bin/phpunit --testdox tests
```
Кстати, тесты тоже сами себя не напишут.

## API
### Фасады и декораторы

#### Layout
`Uralmedias\Linker\Layout` используется для загрузки разметки из разных источников. Работает основной точкой входа в библиотеку внешних пользователей. В случае использования `DOMNode` и `DOMDocument` в качестве источника данных, исходные объекты клонируются для большей безопасности. Для использования `DOMDocument` по ссылке можно инстанциировать класс `Uralmedias\Linker\Layout\LayoutFragment` напрямую.

```php
// Из набора узлов DOM:
public static function fromNodes (DOMNode ...$nodes): LayoutFragment;

// Из документа DOM:
public static function fromDocument (DOMDocument $document): LayoutFragment

// Из произвольной строки:
public static function fromHTML (string $contents): LayoutFragment;

// Из локального файла или URL:
public static function fromFile (string $filename): LayoutFragment;

// Из буфера вывода:
public static function fromOutput (callable $process): LayoutFragment;
```

#### Select
`Uralmedias\Linker\Select` - это интерпретатор различных способов указания узлов внутри разметки. Поддерживаются селекторы CSS, XPath, целочисленные индексы. По умолчанию определяет наиболее подходящий тип в следующем порядке: индекс, CSS, XPath. Классы внутри библиотеки автоматически используют этот фасад, поэтому его явное подключение может потребоваться только в редких случаях, когда нужно явно указать тип селектора.

```php
// Автоматически определеить тип селектора.
public static function auto ($pattern): string;

// Целочисленный индекс. Положительный начинается с 0
// и указывает позицию с начала, отрицательный начинается
// с -1 и указывает позицию с конца.
public static function at (int $index): string;

// Селектор CSS (будет преобразован в XPath).
public static function css (string $css): string;
```

### Классы для работы с разметкой

#### LayoutFragment
`LayoutFragment` хранит образец структуры разметки и позволяет обращаться к нему. Инстанциируется фасадом `Layout`.

```php
// Удаляет лишнюю информацию, лишние пробельные символы.
// $comments = TRUE - удалять коментарии,
// $script = TRUE - заходить внутрь тэгов script и style
public function minimize (bool $comments = FALSE, bool $scripts = FALSE): void;

// Вырезает новый фрагмент из текущего
public function cut (...$selectors): self;

// Копирует часть текущего фрагмента в новый
public function copy (...$selectors): self;

// Перемещает узлы в пределах текущего фрагмента
public function move (...$selectors): NodeRelocator;

// Вставляет другие фрагменты в текущий
public function put (self ...$fragments): NodeRelocator;

// Вставляет текстовый узел
public function write (string ...$strings): NodeRelocator;

// Вставляет коментарий
public function annotate (string ...$comments): NodeRelocator;

// Получает доступ к свойствам узлов фрагмента
public function nodes (...$selectors): NodeAggregator;

// Извлекает ссылки на ресурсы
public function assets (array $updates = [], bool $assumeRE = FALSE): array;

// Инвертирует последовательность узлов фрагмента
public function reverse (...$selectors): NodeAggregator;

// Расставляет узлы фрагмента в случайном порядке
public function randomize (...$selectors): NodeAggregator;
```

#### NodeRelocator
`NodeRelocator` инстанциируется экземпляром `LayoutFragment` для завершения изменения структуры. Позволяет выбирать место размещения новых узлов. Не рекомендуется длительное хранение экземпляра, так как он обращается к источнику и приёмнику по ссылке, а ссылки на некоторые объекты DOM могут со временем портится.

```php
// Аргумент $selectors в каждом случае определяет селекторы целевых узлов
// Возвращаемое значение - это объект NodeAggregator, ссылающийся на
// только что вставленные узлы.

// Перед каждой целью
public function before (...$selectors): NodeAggregator;

// После каждой цели
public function after (...$selectors): NodeAggregator;

// В начало каждой цели (первый потомок)
public function up (...$selectors): NodeAggregator;

// В конец каждой цели (последний потомок)
public function down (...$selectors): NodeAggregator;

// Внутрь целей (заменить текущее содержимое)
public function into (...$selectors): NodeAggregator;

// Вместо целей (заменить выбранные узлы)
public function to (...$selectors): NodeAggregator;
```

#### NodeAggregator
`NodeAggregator` позволяет получать или изменять имя, значение или атрибуты конкретных узлов. Инстанциируется экземплярами `LayoutFragment` и `NodeRelocator`. Так же, как и `NodeRelocator` владеет только ссылкой на управляемый им объект, поэтому не рекомендуется длительное хранение экземпляров.

```php
// Названия узлов
// Для элемента это — тэг, для атрибута это — токен перед "=", узлы других типов
// не могут иметь имя, в этом случае значение не устанавливается, а возвращается
// пустая строка.
public function name (string $update = NULL): string;

// Значения узлов
// Для элемента, коментария или текстового узла это — его текст, для атрибута - это
// выражение после "=". Для задания нового значения нужно передать аргумент.
public function value (string $update = NULL): string;

// Inline-стили элементов
// Возвращает массив, ключами которого служат имена атрибутов стиля, а значениями — их
// значения. Заполненный аргумент заменяет текущее значение. Парсер очень неаккуратный,
// нужно пользоваться осторожно.
public function styles (array $updates = NULL): array;

// Классы элементов
// Возвращает массив CSS-классов. Чтобы изменить значения, необходимо передать первым
// аргументом массив, при этом поведение вариируется:
// - По умолчанию: ключи соответствуют удаляемым классам, значения - добавляемым;
// - $doReplacing = TRUE: ключи соответствуют шаблонам поиска, значения - подстановкам;
// - $assumeRE = TRUE: то, что и предыдущий, но шаблоны поиска - регулярные выражения.
// При использовании регулярных выражений можно использовать групировки и подстановки.
public function classes (array $updates = NULL, bool $doReplacing = FALSE, bool $assumeRE = FALSE): array;

// Атрибуты элементов
// Доступ к атрибутам элементов. Возвращает атрибуты в виде массива ключ => значение,
// принимает новое значение в таком же виде. При отсустствии ключа в аргументе, значение
// атрибута остаётся не тронутым. Если значение === NULL, атрибут удаляется, кроме
// случаев, когда $nullValues = TRUE. В этом случае устанавливается NULL.
public function attributes (array $updates = NULL, bool $nullValues = FALSE): array;
```

## Пример
Код этого и других примеров можно найти в директории `examples`.

```php
// Актуальное место, где находится /linker/vendor/autoload.php, но
// если проект уже использует composer и библиотека подключена через
// него, то следующая строка становится не нужной. Это надо, чтобы
// классы библиотеки стали доступными загрузчику классов.
require_once __DIR__ .'/../../../linker/vendor/autoload.php';

// Главный класс библиотеки, который загружает
// фрагменты верстки и позволяет работать с ними
use Uralmedias\Linker\Layout;


// Загружаем верстку из файла. Также верстка может быть загружена
// из сети, из строки, из стандартного вывода подпрограммы,
// а еще - из ранее созданных узлов DOM. (см. комментарии в коде)
$index = Layout::fromFile('markup.html');

// Вырезаем блоки с классом .w3-row.w3-padding-64 и изапоминаеи
// первый из них в переменную $row - он пригодится нам, чтобы
// формировать новые элементы.
$row = $index->cut('.w3-row.w3-padding-64')->cut(1);

// Так же вырезем (удаляем) остальные блоки, которые нам не нужны
$index->cut('.w3-row');


// Наполняем вырезанный блок рыбой
// Здесь использованы два варианта установки текста,
// второй позволяет дополнительно читать текст из узлов.
$row->write('Hello world!')->into('h1');
$row->nodes('.w3-twothird p')->value('С другой стороны укрепление и развитие структуры играет
важную роль в формировании системы обучения кадров, соответствует
насущным потребностям. Задача организации, в особенности же новая
модель организационной деятельности в значительной степени обуславливает
создание модели развития. С другой стороны укрепление и развитие
структуры влечет за собой процесс внедрения и модернизации направлений
прогрессивного развития. Задача организации, в особенности же реализация
намеченных плановых заданий требуют от нас анализа позиций, занимаемых
участниками в отношении поставленных задач. Задача организации,
в особенности же консультация с широким активом представляет собой
интересный эксперимент проверки дальнейших направлений развития.');

// Далее переменная $row может быть использована повторно,
// а можно на этом ограничиться
// Заполняем блоками основной фрагмент
$index->pull($row)->up('.w3-main');

// Удаляем лишние пробельные символы - не обязательно,
// но позволяет облегчить страницу, иногда - на 20-25%.
$index->minimize(TRUE,TRUE);

// Вывод содержимого фрагмента
echo $index;
```

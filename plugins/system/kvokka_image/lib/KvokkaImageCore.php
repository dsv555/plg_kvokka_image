<?php

abstract class KvokkaImageCore
{
    abstract public function resizeImage();

    protected $height; // Высота
    protected $width; // Ширена

    protected $quality; // Качество изображения

    protected $scale; // Напровление маштобирования
    protected $crop; // Обрезать по размерам
    protected $loadDefault; // Возрощать изображение по умолчанию если произошла ошибка при маштобировани

    protected $wt; // Добавлять водяной знак
    protected $wtHeight; // Высота водяного знака
    protected $wtWidth; // Ширена водяного знака
    protected $wtScale; // Напровление маштобирования водяного знака
    protected $wtRigth; // Отступ с права
    protected $wtBottom; // Отступ с низу
    protected $wtCenter; // Выровнять по центру, отменяет $wtBottom и $wtRight
    protected $wtOpacity; // Прозрачность водяного знака

    protected $srcWatermark; // Водянои занк
    protected $srcDefault; // Изображение по умолчанию

    protected $originalUrl; // URL к исходному фаилу

    protected $cachePathToFile; // Путь к кэшированому изображению
    protected $originalPathToFIle; // Путь к исходному фаилу

    protected $cacheUrl; // URL к кэшированому изображению
    protected $cacheName; // Имя кэшированого изображения
    protected $cachePath; // Путь к директории кэша

    protected $params; // Параметры плагина

    /**
     * @param string $src - url изображения
     * @param mixed $options - параметры масштабирования
     */
    public function __construct($src, $params, $options = '')
    {
        if (!is_array($options)) {
            parse_str(str_replace(' ', '', $options), $settings);
        } else {
            $settings = $options;
        }

        $this->originalUrl = $src;
        $this->params = $params;

        $this->height = isset($settings['h']) ? (int) $settings['h'] : 0;
        $this->width = isset($settings['w']) ? (int) $settings['w'] : 0;
        $this->quality = isset($settings['q']) ? (int) $settings['q'] : (int) $this->getParam('quality');
        $this->cachePath = isset($settings['path']) ? (string) $settings['path'] : JPATH_CACHE . '/plg_kvokka_image/';
        $this->scale = isset($settings['scale']) ? $settings['scale'] : 'w';
        $this->crop = isset($settings['crop']) ? (bool) $settings['crop'] : false;
        $this->loadDefault = isset($settings['def']) ? (bool) $settings['def'] : true;
        $this->srcDefault = isset($settings['defsrc']) ? $settings['defsrc'] : $this->getParam('default');

        $this->wt = isset($settings['wt']) ? (bool) $settings['wt'] : 0;
        $this->wtHeight = isset($settings['wth']) ? (int) $settings['wth'] : 0;
        $this->wtWidth = isset($settings['wtw']) ? (int) $settings['wtw'] : 0;
        $this->wtScale = isset($settings['wts']) ? $settings['wts'] : 'w';
        $this->wtRigth = isset($settings['wtr']) ? (int) $settings['wtr'] : 10;
        $this->wtBottom = isset($settings['wtb']) ? (int) $settings['wtb'] : 10;
        $this->wtCenter = isset($settings['wtc']) ? (bool) $settings['wtc'] : false;
        $this->wtOpacity = isset($settings['wto']) ? (int) $settings['wto'] : 65;
        $this->srcWatermark = isset($settings['wtsrc']) ? $settings['wtsrc'] : $this->getParam('watermark');

        if ($this->quality > 100) {
            $this->quality = 100;
        } elseif ($this->quality <= 0) {
            $this->quality = 1;
        }

        $this->prepare();
    }

    /**
     *  Возрощает измененое изображение из кэша
     *  или создает при необходимости и кэширует
     *
     * @return string
     */
    public function resize()
    {
        if (JFile::exists($this->cachePathToFile)) {
            $dateCache = filemtime($this->cachePathToFile);
            $dateOrigenal = filemtime($this->originalPathToFIle);
            if ($dateCache > $dateOrigenal) {
                return $this->cacheUrl;
            }
        }

        // Изменяет размер изображения
        $outerSrc = $this->resizeImage();

        // Если не удалось создать изображние
        if (!$outerSrc && !empty($this->loadDefault) && !empty($this->srcDefault)) {

            $this->wt = false;
            $this->loadDefault = false;
            $this->originalUrl = $this->srcDefault;

            $this->prepare();

            return $this->resize();
        }

        return $outerSrc;
    }

    /**
     * Параметры плагина
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getParam($name, $default = false)
    {
        if (isset($this->params[$name]) && !empty($this->params[$name])) {
            return $this->params[$name];
        }
        return $default;
    }

    /**
     * Загружает изображение из внешнего источника и возрощает url для него
     *
     * @param string $url
     * @return string
     */
    protected function loadImage($url)
    {
        $isLoad = false;
        $fileName = 'outer_' . md5($url);
        $pathToFile = '';

        // Проверка загружено ли изображение
        foreach (array('.jpg', '.png', '.gif') as $format) {
            $pathToFile = $this->cachePath . $fileName . $format;
            if (JFile::exists($pathToFile)) {
                $isLoad = true;
                break;
            }
        }

        // Если изображение не загружено
        if (!$isLoad) {

            $ch = curl_init($url);

            // Параметры запросса
            curl_setopt_array($ch, [
                CURLOPT_TIMEOUT => 60,
                CURLOPT_FOLLOWLOCATION => 1,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_NOPROGRESS => 0,
                CURLOPT_BUFFERSIZE => 1024,
                CURLOPT_PROGRESSFUNCTION => function ($ch, $dwnldSize, $dwnld, $upldSize, $upld) {
                    // Когда будет скачано больше 5 Мбайт, cURL прервёт работу
                    if ($dwnld > 1024 * 1024 * 5) {
                        return -1;
                    }
                },
            ]);

            $raw = curl_exec($ch);
            $info = curl_getinfo($ch);
            $error = curl_errno($ch);

            curl_close($ch);

            if ($error === CURLE_OPERATION_TIMEDOUT) {
                return false;
            }

            if ($error === CURLE_ABORTED_BY_CALLBACK) {
                return false;
            }

            if ($info['http_code'] !== 200) {
                return false;
            }

            $file = finfo_open(FILEINFO_MIME_TYPE);
            $mime = (string) finfo_buffer($file, $raw);
            finfo_close($file);

            if (strpos($mime, 'image') === false) {
                return false;
            }

            $image = getimagesizefromstring($raw);
            $ext = image_type_to_extension($image[2]);
            $format = str_replace('jpeg', 'jpg', $ext);
            $pathToFile = $this->cachePath . $fileName . $format;

            // Загрузка изображения
            if (!file_put_contents($pathToFile, $raw)) {
                return false;
            }
        }

        $url = str_replace(JPATH_ROOT, '', $pathToFile);
        $url = str_replace('\\', '/', $url);

        return $url;
    }

    /**
     * Назначение путе, загрузка изображения, формирование имен фаилов
     *
     * @return void
     */
    protected function prepare()
    {
        // Создание директории для кэширования
        if (!JFolder::exists($this->cachePath)) {
            JFolder::create($this->cachePath);
        }

        // Преоброзования пути
        if (preg_match('/' . addcslashes(JUri::base(), '/') . '/', $this->originalUrl)) {
            $this->originalUrl = str_replace(JUri::base(), '', $this->originalUrl);
        }

        // Загрузка изобрежения
        if (preg_match('#^http[s]*://#', $this->originalUrl)) {
            $this->originalUrl = $this->loadImage($this->originalUrl);
        }

        // Абсолютный путь к исходному изображению
        $this->originalPathToFIle = str_replace('//', '/', JPATH_ROOT . '/' . $this->originalUrl);

        $r = serialize($this);
        // Имя закэшированого изображения
        $this->cacheName = md5(serialize($this)) . '.' . JFile::getExt($this->originalPathToFIle);

        // Путь к закэшированому изображению
        $this->cachePathToFile = $this->cachePath . $this->cacheName;

        // Url к закэшированому изображению
        $this->cacheUrl = str_replace(JPATH_ROOT, '', $this->cachePathToFile);
        $this->cacheUrl = str_replace('\\', '/', $this->cacheUrl);
    }
}

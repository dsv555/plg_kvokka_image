<?php

jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.file');
jimport('joomla.image.image');

class KvokkaImage
{
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

    /**
     * @param string $src - url изображения
     * @param mixed $options - параметры масштабирования
     */
    public function __construct($src = '', $options = '')
    {
        if (!is_array($options)) {
            parse_str(str_replace(' ', '', $options), $settings);
        } else {
            $settings = $options;
        }

        $this->originalUrl = $src;

        $this->height = isset($settings['h']) ? (int) $settings['h'] : 1;
        $this->width = isset($settings['w']) ? (int) $settings['w'] : 1;
        $this->quality = isset($settings['q']) ? (int) $settings['q'] : (int) $this->param('quality', 90);
        $this->cachePath = isset($settings['path']) ? (string) $settings['path'] : $this->param('cache-path');
        $this->scale = isset($settings['scale']) ? $settings['scale'] : 'w';
        $this->crop = isset($settings['crop']) ? (bool) $settings['crop'] : false;
        $this->loadDefault = isset($settings['def']) ? (bool) $settings['def'] : true;
        $this->srcDefault = isset($settings['defsrc']) ? $settings['defsrc'] : $this->param('default');

        $this->wt = isset($settings['wt']) ? (bool) $settings['wt'] : 0;
        $this->wtHeight = isset($settings['wth']) ? (int) $settings['wth'] : 0;
        $this->wtWidth = isset($settings['wtw']) ? (int) $settings['wtw'] : 0;
        $this->wtScale = isset($settings['wts']) ? $settings['wts'] : 'h';
        $this->wtRigth = isset($settings['wtr']) ? (int) $settings['wtr'] : 10;
        $this->wtBottom = isset($settings['wtb']) ? (int) $settings['wtb'] : 10;
        $this->wtCenter = isset($settings['wtc']) ? (bool) $settings['wtc'] : false;
        $this->wtOpacity = isset($settings['wto']) ? (int) $settings['wto'] : 65;
        $this->srcWatermark = isset($settings['wtsrc']) ? $settings['wtsrc'] : $this->param('watermark');
    }

    /**
     * Статический метод запуска
     *
     * @param string $src - url изображения
     * @param array $options - параметры масштабирования
     * @return string
     */
    public static function resize($src, $options)
    {
        $image = new self($src, $options);
        $image->prepare();

        return $image->getCaheImage();
    }

    /**
     * Назначение путе, загрузка изображения, формирование имен фаилов
     *
     * @return void
     */
    public function prepare()
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
            $this->originalUrl = $this->loadOuterImage($this->originalUrl);
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

    /**
     *  Возрощает измененое изображение из кэша
     *  или создает при необходимости и кэширует
     *
     * @return string
     */
    public function getCaheImage()
    {
        if ($this->width == 0 && $this->height == 0) {
            return false;
        }

        if (JFile::exists($this->cachePathToFile)) {
            $dateCache = filemtime($this->cachePathToFile);
            $dateOrigenal = filemtime($this->originalPathToFIle);
            if ($dateCache > $dateOrigenal) {
                return $this->cacheUrl;
            }
        }

        // Возрощает путь к кэшированому изображению
        $outerSrc = $this->resizeImage();

        // Если не удалось создать изображние
        if (!$outerSrc && !empty($this->loadDefault) && !empty($this->srcDefault)) {

            $this->wt = false;
            $this->loadDefault = false;
            $this->originalUrl = $this->srcDefault;

            $this->prepare();

            return $this->getCaheImage();
        }

        return $outerSrc;
    }

    /**
     * Изменяет размеры изображения
     *
     * @return void
     */
    public function resizeImage()
    {
        // Проверка существования фаила
        if (!JFile::exists($this->originalPathToFIle)) {
            return false;
        }

        // Проверка типа
        $type = exif_imagetype($this->originalPathToFIle);
        if (!in_array($type, array(IMAGETYPE_GIF, IMAGETYPE_PNG, IMAGETYPE_JPEG))) {
            return false;
        }

        // Загрузка исходного изображения
        $image = new JImage($this->originalPathToFIle);

        if (!$this->crop) {

            if ($this->scale == 'h') {
                $this->width = 10000;
            }
            if ($this->scale == 'w') {
                $this->height = 1;
            }

            $image->resize($this->width, $this->height, false, $this->getScale($this->scale));

        } else if ($this->crop) {

            $image->cropResize($this->width, $this->height, false);

        }

        if ($this->wt && $this->srcWatermark) {

            $wtPath = str_replace('//', '/', JPATH_ROOT . '/' . $this->srcWatermark);

            if (!JFile::exists($wtPath)) {
                return false;
            }

            $wt = new JImage($wtPath);

            $this->wtHeight = !empty($this->wtHeight) ? $this->wtHeight : $this->width / 2;
            $this->wtWidth = !empty($this->wtWidth) ? $this->wtWidth : $this->width / 2;

            $wt->resize($this->wtWidth, $this->wtHeight, false, $this->getScale($this->wtScale));

            if ($this->wtCenter) {
                $this->wtBottom = $image->getHeight() / 2 - $wt->getHeight() / 2;
                $this->wtRigth = $image->getWidth() / 2 - $wt->getWidth() / 2;
            }

            $image->watermark($wt, $this->wtOpacity, $this->wtBottom, $this->wtRigth);
        }

        $image->toFile($this->cachePathToFile, $type, array('quality' => $this->quality));

        return $this->cacheUrl;
    }

    /**
     * Загружает изображение из внешнего источника и возрощает url для него
     *
     * @param string $url
     * @return string
     */
    private function loadOuterImage($url)
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
     * Метод маштобирования
     *
     * @param string $name
     * @return int
     */
    private function getScale($name)
    {
        switch ($name) {
            case 'w':
                $scale = JImage::SCALE_OUTSIDE;
                break;
            case 'h':
                $scale = JImage::SCALE_INSIDE;
                break;
            case 'full':
            default:
                $scale = JImage::SCALE_FILL;
                break;
        }

        return $scale;
    }

    /**
     * Параметры плагина
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    private function param($name, $default = false)
    {
        $plugin = JPluginHelper::getPlugin('system', 'kvokka_image');

        if (isset($plugin->params) && !empty($plugin->params)) {
            $params = json_decode($plugin->params);

            switch ($name) {

                case 'default':
                    return !empty($params->default_img) ? $params->default_img : '/plugins/system/kvokka_image/media/noimage.png';
                    break;

                case 'watermark':
                    return !empty($params->watermark_img) ? $params->watermark_img : $default;
                    break;

                case 'quality':
                    return !empty($params->quality) ? $params->quality : $default;
                    break;
            }
        }

        return $default;
    }
}

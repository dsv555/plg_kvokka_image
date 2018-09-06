<?php

// No direct access
defined('_JEXEC') or die;

jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.file');
jimport('joomla.image.image');

class KvokkaImage
{
    public $option; // Срока параметров
    public $originalPathToFIle; // Путь к исходному фаилу
    public $originalUrl; // URL к исходному фаилу
    public $cacheName; // Имя кэшированого изображения
    public $cachePath; // Путь к директории кэша
    public $cachePathToFile; // Путь к кэшированому изображению
    public $cacheUrl; // URL к кэшированому изображению
    public $height; // Высота
    public $width; // Ширена
    public $scale; // Матод моштобирования
    public $crop; // Обрезать по размерам
    public $default; // Возрощать изображение по умолчанию
    public $wt; // Добавлять водяной знак
    public $wtHeight; // Высота водяного знака
    public $wtWidth; // Ширена водяного знака
    public $wtScale; // Маштабирование водяного знака
    public $wtRigth; // Отступ с права
    public $wtBottom; // Отступ с низу
    public $wtCenter; // Выровнять по центру, отменяет $wtBottom и $wtRight
    public $wtOpacity; // Прозрачность водяного знака

    public function __construct($src, $options)
    {
        $this->originalUrl = $src;
        $this->option = str_replace(' ', '', $options);

        parse_str($this->option, $settings);

        $this->height = isset($settings['h']) ? (int) $settings['h'] : 1;
        $this->width = isset($settings['w']) ? (int) $settings['w'] : 1;
        $this->scale = isset($settings['scale']) ? $settings['scale'] : 'w';
        $this->crop = isset($settings['crop']) ? (bool) $settings['crop'] : false;
        $this->default = isset($settings['def']) ? (bool) $settings['def'] : true;
        $this->wt = isset($settings['wt']) ? (bool) $settings['wt'] : 0;
        $this->wtHeight = isset($settings['wth']) ? (int) $settings['wth'] : 0;
        $this->wtWidth = isset($settings['wtw']) ? (int) $settings['wtw'] : 0;
        $this->wtScale = isset($settings['wts']) ? $settings['wts'] : 'h';
        $this->wtRigth = isset($settings['wtr']) ? (int) $settings['wtr'] : 10;
        $this->wtBottom = isset($settings['wtb']) ? (int) $settings['wtb'] : 10;
        $this->wtCenter = isset($settings['wtc']) ? (bool) $settings['wtc'] : false;
        $this->wtOpacity = isset($settings['wto']) ? (int) $settings['wto'] : 60;
    }

    /**
     * Статический метод запуска
     *
     * @param string $src
     * @param string $options
     * @return string
     */
    public static function resize($src, $options)
    {
        $image = new self($src, $options);
        $image->load();

        return $image->getImage();
    }

    /**
     * Назначение путе, загрузка изображения, формирование имен фаилов
     *
     * @return void
     */
    public function load($cPath = '/plg_kvokka_image/')
    {
        // Путь к директори закэшированых изображении
        $this->cachePath = JPATH_CACHE . $cPath;

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
            $this->originalUrl = $this->loadOuterImg($this->originalUrl);
        }

        // Физически путь к исходному изображению
        $this->originalPathToFIle = str_replace('//', '/', JPATH_ROOT . '/' . $this->originalUrl);

        // Имя закэшированого изображения
        $this->cacheName = md5(urlencode($this->originalUrl) . $this->option) . '.' . JFile::getExt($this->originalPathToFIle);

        // Путь к закэшированому изображению
        $this->cachePathToFile = $this->cachePath . $this->cacheName;

        // Url к закэшированому изображению
        $this->cacheUrl = str_replace(JPATH_ROOT, '', $this->cachePathToFile);
        $this->cacheUrl = str_replace("\\", '/', $this->cacheUrl);
    }

    /**
     *  Возрощает измененое изображение из кэша
     *  или создает при необходимости и кэширует
     *
     * @return string
     */
    public function getImage()
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
        if (!$outerSrc && !empty($this->default)) {

            $this->wt = false;
            $this->default = false;
            $this->originalUrl = $this->param('default');

            $this->load();

            return $this->resizeImage();
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
    public function param($name, $default = false)
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
            }
        }

        return $default;
    }

    /**
     * Загружает изображение из внешнего источника и возрощает url для него
     *
     * @param string $imgScr
     * @return string
     */
    protected function loadOuterImg($url)
    {
        $isLoad = false;
        $fileName = 'outer_' . md5(urlencode($url));
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
        $url = str_replace("\\", '/', $url);

        return $url;
    }

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

        if ($this->wt && $this->param('watermark')) {

            $watermarkPath = str_replace('//', '/', JPATH_ROOT . '/' . $this->param('watermark'));
            if (!JFile::exists($watermarkPath)) {
                return false;
            }

            $wt = new JImage($watermarkPath);

            $this->wtHeight = !empty($this->wtHeight) ? $this->wtHeight : $this->width / 2;
            $this->wtWidth = !empty($this->wtWidth) ? $this->wtWidth : $this->width / 2;

            $wt->resize($this->wtWidth, $this->wtHeight, false, $this->getScale($this->wtScale));

            if ($this->wtCenter) {
                $this->wtBottom = $image->getHeight() / 2 - $wt->getHeight() / 2;
                $this->wtRigth = $image->getWidth() / 2 - $wt->getWidth() / 2;
            }

            $image->watermark($wt, $this->wtOpacity, $this->wtBottom, $this->wtRigth);
        }

        $image->toFile($this->cachePathToFile, $type);

        return $this->cacheUrl;
    }

    protected function getScale($name)
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
}

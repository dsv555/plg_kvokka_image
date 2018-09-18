<?php

abstract class KvokkaImageBase
{
    protected $originalUrl; // URL к исходному фаилу
    protected $cachePath; // Путь к директории кэша
    protected $height; // Высота
    protected $width; // Ширена

    /**
     *  Возрощает измененое изображение из кэша
     *  или создает при необходимости
     *
     * @return string
     */
    abstract public function getCaheImage();

    /**
     * Задет директорию для кэширования
     *
     * @param string $path
     * @return void
     */
    public function setCachePath($path)
    {
        $this->cachePath = $path;
    }

    /**
     * Размеры изображения
     *
     * @param int $width
     * @param int $height
     * @return void
     */
    public function setSize($width, $height)
    {
        $this->width = $width;
        $this->height = $height;
    }
}

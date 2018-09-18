<?php

abstract class KvokkaImageBase
{
    private $originalPathToFIle; // Путь к исходному фаилу
    private $originalUrl; // URL к исходному фаилу
	
    private $cachePathToFile; // Путь к кэшированому изображению
	private $cacheUrl; // URL к кэшированому изображению
	private $cacheName; // Имя кэшированого изображения
    private $cachePath; // Путь к директории кэша
 
    public $height; // Высота
    public $width; // Ширена

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
     *  Возрощает измененое изображение из кэша
     *  или создает при необходимости и кэширует
     *
     * @return string
     */
    abstract public function getCaheImage();

    /**
     * Изменяет размеры изображения
     *
     * @return void
     */
    abstract public function resizeImage();
}

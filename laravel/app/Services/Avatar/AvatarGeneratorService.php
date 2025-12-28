<?php

namespace App\Services\Avatar;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Exception;

class AvatarGeneratorService
{
    protected int $size = 256;
    protected string $format = 'webp'; // Предпочтительный формат, но может быть PNG если WebP недоступен

    /**
     * Генерация аватара из первых букв имени и фамилии
     * 
     * @param string $firstName Имя
     * @param string $lastName Фамилия
     * @return string Путь к сохраненному файлу
     */
    public function generate(string $firstName, string $lastName): string
    {
        $firstLetter = mb_strtoupper(mb_substr($firstName, 0, 1, 'UTF-8'), 'UTF-8');
        $lastLetter = mb_strtoupper(mb_substr($lastName, 0, 1, 'UTF-8'), 'UTF-8');
        $text = $lastLetter . $firstLetter;

        // Генерируем цвет фона
        $bgColorHex = $this->generateColorFromString($firstName . $lastName);

        // Создаем менеджер изображений
        $manager = new ImageManager(new Driver());
        
        // Создаем новое изображение
        $image = $manager->create($this->size, $this->size);
        
        // Заполняем фон цветом (используем HEX строку)
        $image->fill($bgColorHex);
        
        // Добавляем текст
        $fontSize = 80;
        $fontPath = $this->getSystemFont();
        
        if ($fontPath && file_exists($fontPath)) {
            // Используем TTF шрифт
            $image->text($text, $this->size / 2, $this->size / 2, function ($font) use ($fontSize, $fontPath) {
                $font->file($fontPath);
                $font->size($fontSize);
                $font->color('#ffffff');
                $font->align('center');
                $font->valign('middle');
            });
        } else {
            // Fallback - используем встроенный шрифт
            $image->text($text, $this->size / 2, $this->size / 2, function ($font) use ($fontSize) {
                $font->size($fontSize);
                $font->color('#ffffff');
                $font->align('center');
                $font->valign('middle');
            });
        }
        
        // Определяем формат и расширение файла
        $useWebp = function_exists('imagewebp');
        $extension = $useWebp ? 'webp' : 'png';
        $filename = 'avatars/' . uniqid() . '.' . $extension;
        $path = storage_path('app/public/' . $filename);
        
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        // Сохраняем в формате WebP или PNG (fallback)
        try {
            if ($useWebp) {
                $image->toWebp(90)->save($path);
            } else {
                throw new Exception('WebP not available');
            }
        } catch (Exception $e) {
            // Fallback на PNG если WebP недоступен
            $extension = 'png';
            $filename = 'avatars/' . uniqid() . '.' . $extension;
            $path = storage_path('app/public/' . $filename);
            $image->toPng()->save($path);
        }
        
        // Устанавливаем права доступа к файлу
        if (file_exists($path)) {
            chmod($path, 0644);
        }
        
        return $filename;
    }

    /**
     * Генерация цвета на основе строки (для уникальности)
     */
    protected function generateColorFromString(string $string): string
    {
        // Используем хеш строки для генерации цвета
        $hash = md5($string);
        
        // Берем первые 6 символов хеша для цвета
        $r = hexdec(substr($hash, 0, 2));
        $g = hexdec(substr($hash, 2, 2));
        $b = hexdec(substr($hash, 4, 2));
        
        // Делаем цвет более насыщенным и приятным
        $r = min(255, $r + 50);
        $g = min(255, $g + 50);
        $b = min(255, $b + 50);
        
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Конвертация HEX в RGB
     */
    protected function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Получить системный шрифт
     */
    protected function getSystemFont(): ?string
    {
        $fonts = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/System/Library/Fonts/Helvetica.ttc',
            storage_path('fonts/arial.ttf'),
        ];

        foreach ($fonts as $font) {
            if (file_exists($font)) {
                return $font;
            }
        }

        return null;
    }
}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Photo extends Model
{
    use HasUuids;

    protected $fillable = [
        'event_id',
        'price',
        'has_faces',
        'original_path',
        'custom_path',
        'face_vec',
        'face_encodings',
        'face_bboxes',
        'numbers',
        'exif_data',
        'created_at_exif',
        'date_exif',
        'status',
        'original_name',
        'custom_name',
        's3_custom_url',
        's3_original_url',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'has_faces' => 'boolean',
            'face_vec' => 'array',
            'face_encodings' => 'array',
            'face_bboxes' => 'array',
            'numbers' => 'array',
            'exif_data' => 'array',
            'date_exif' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function faceSearches(): HasMany
    {
        return $this->hasMany(FaceSearch::class);
    }

    /**
     * Получить URL для отображения фотографии
     * Приоритет: 1) S3 URL, 2) custom_path, 3) original_path
     */
    public function getDisplayUrl(): ?string
    {
        // Приоритет 1: S3 URL
        if ($this->s3_custom_url) {
            return $this->s3_custom_url;
        }

        // Приоритет 2: custom_path
        if ($this->custom_path) {
            return $this->normalizeStoragePath($this->custom_path);
        }

        // Приоритет 3: original_path
        if ($this->original_path) {
            return $this->normalizeStoragePath($this->original_path);
        }

        return null;
    }

    /**
     * Получить fallback URL (локальный файл) если S3 URL недоступен
     */
    public function getFallbackUrl(): ?string
    {
        // Если есть S3 URL, возвращаем локальный custom_path или original_path как fallback
        if ($this->s3_custom_url) {
            if ($this->custom_path) {
                return $this->normalizeStoragePath($this->custom_path);
            }
            if ($this->original_path) {
                return $this->normalizeStoragePath($this->original_path);
            }
        }
        
        // Если нет S3 URL, возвращаем null (getDisplayUrl уже вернет локальный путь)
        return null;
    }

    /**
     * Получить цену с комиссией для пользователя
     */
    public function getPriceWithCommission(): float
    {
        $commissionPercent = \App\Models\Setting::get('percent_for_sales', 20);
        // Если у фотографии есть цена, используем её, иначе загружаем событие и используем его цену
        if ($this->price) {
            $basePrice = $this->price;
        } else {
            // Загружаем событие если оно не загружено
            if (!$this->relationLoaded('event')) {
                $this->load('event');
            }
            $basePrice = $this->event ? $this->event->price : 0;
        }
        $priceWithCommission = $basePrice * (1 + ($commissionPercent / 100));
        
        // ИСПРАВЛЕНИЕ ОШИБКИ 4: Добавлено логирование расчета цены (только при подозрительных значениях)
        // Логируем только если цена с комиссией больше базовой более чем на 50% (возможная ошибка)
        if ($priceWithCommission > $basePrice * 1.5 && $basePrice > 0) {
            \Log::warning("Photo::getPriceWithCommission: Suspicious price calculation", [
                'photo_id' => $this->id,
                'base_price' => $basePrice,
                'commission_percent' => $commissionPercent,
                'price_with_commission' => $priceWithCommission,
                'calculation_formula' => "{$basePrice} * (1 + ({$commissionPercent} / 100)) = {$priceWithCommission}",
                'photo_price' => $this->price,
                'event_price' => $this->event ? $this->event->price : null
            ]);
        }
        
        return $priceWithCommission;
    }

    /**
     * Нормализовать путь к файлу в storage
     * Убирает абсолютные пути и возвращает правильный URL через Storage::url()
     */
    protected function normalizeStoragePath(string $path): string
    {
        // Убираем абсолютные пути Docker контейнера
        if (str_starts_with($path, '/var/www/html/storage/app/public/')) {
            $path = str_replace('/var/www/html/storage/app/public/', '', $path);
        }
        
        // Убираем /storage/app/public/
        if (str_starts_with($path, '/storage/app/public/')) {
            $path = str_replace('/storage/app/public/', '', $path);
        }
        
        // Убираем storage/app/public/
        if (str_starts_with($path, 'storage/app/public/')) {
            $path = str_replace('storage/app/public/', '', $path);
        }

        return Storage::url($path);
    }
}

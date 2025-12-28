<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasUuids;

    protected $fillable = [
        'author_id',
        'title',
        'slug',
        'city',
        'date',
        'cover_path',
        'price',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'price' => 'decimal:2',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function celeryTasks(): HasMany
    {
        return $this->hasMany(CeleryTask::class);
    }

    /**
     * Генерация slug из названия
     */
    public static function generateSlug(string $title): string
    {
        $slug = \Illuminate\Support\Str::slug($title, '-', 'ru');
        
        // Проверяем уникальность
        $count = 1;
        $originalSlug = $slug;
        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }
        
        return $slug;
    }

    /**
     * Получить маршрут для события (используем slug вместо id)
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
    
    /**
     * Получить значение для маршрута
     */
    public function getRouteKey()
    {
        return $this->slug ?? $this->getKey();
    }

    /**
     * Получить отформатированную дату на русском языке
     * Формат: "18 Января 2026г."
     */
    public function getFormattedDate(): string
    {
        if (!$this->date) {
            return 'Дата не указана';
        }
        
        $months = [
            1 => 'Января', 2 => 'Февраля', 3 => 'Марта', 4 => 'Апреля',
            5 => 'Мая', 6 => 'Июня', 7 => 'Июля', 8 => 'Августа',
            9 => 'Сентября', 10 => 'Октября', 11 => 'Ноября', 12 => 'Декабря'
        ];
        
        $day = $this->date->format('d');
        $month = $months[(int)$this->date->format('n')];
        $year = $this->date->format('Y');
        
        return "{$day} {$month} {$year}г.";
    }
}

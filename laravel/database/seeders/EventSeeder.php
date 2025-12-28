<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Получаем фотографов
        $photographers = User::where('group', 'photo')->get();
        
        if ($photographers->isEmpty()) {
            $this->command->warn('Нет фотографов в базе. Сначала запустите UserSeeder.');
            return;
        }

        $cities = ['Москва', 'Санкт-Петербург', 'Казань', 'Новосибирск', 'Екатеринбург'];
        
        $eventTitles = [
            'Чемпионат России по легкой атлетике',
            'Московский марафон 2025',
            'Кубок России по футболу',
            'Всероссийские соревнования по плаванию',
            'Турнир по баскетболу "Кубок Победы"',
            'Чемпионат по волейболу',
            'Спортивный фестиваль "Здоровье нации"',
            'Турнир по теннису',
            'Соревнования по гимнастике',
            'Кросс нации 2025',
        ];

        $descriptions = [
            'Крупнейшие соревнования по легкой атлетике с участием лучших спортсменов страны.',
            'Ежегодный марафон по улицам столицы с участием тысяч бегунов.',
            'Футбольный турнир с участием ведущих команд России.',
            'Соревнования по плаванию в различных дисциплинах.',
            'Баскетбольный турнир памяти героев Великой Отечественной войны.',
            'Волейбольный чемпионат с участием сильнейших команд.',
            'Масштабный спортивный фестиваль для всей семьи.',
            'Теннисный турнир с участием профессиональных спортсменов.',
            'Соревнования по художественной и спортивной гимнастике.',
            'Всероссийский день бега с участием миллионов людей.',
        ];

        for ($i = 0; $i < 10; $i++) {
            $photographer = $photographers->random();
            $city = $cities[array_rand($cities)];
            $title = $eventTitles[$i];
            $description = $descriptions[$i];
            
            // Генерируем случайную дату в прошлом или будущем
            $daysOffset = rand(-60, 60);
            $date = Carbon::now()->addDays($daysOffset);
            
            $event = Event::create([
                'author_id' => $photographer->id,
                'title' => $title,
                'slug' => Event::generateSlug($title),
                'city' => $city,
                'date' => $date,
                'cover_path' => null, // Обложка будет добавлена позже
                'price' => rand(50, 500),
                'status' => 'published',
                'description' => $description,
            ]);
        }
    }
}

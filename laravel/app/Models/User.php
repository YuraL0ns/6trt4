<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'second_name',
        'login', // Логин для входа
        'email',
        'phone',
        'password',
        'avatar',
        'city',
        'gender',
        'group',
        'balance',
        'status',
        'hash_login', // для фотографов
        'description', // для фотографов
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'balance' => 'decimal:2',
        ];
    }

    /**
     * Проверка, является ли пользователь администратором
     */
    public function isAdmin(): bool
    {
        return $this->group === 'admin';
    }

    /**
     * Проверка, является ли пользователь фотографом
     */
    public function isPhotographer(): bool
    {
        return $this->group === 'photo';
    }

    /**
     * Проверка, является ли пользователь администратором или фотографом
     */
    public function isAdminOrPhotographer(): bool
    {
        return $this->isAdmin() || $this->isPhotographer();
    }

    /**
     * Проверка, является ли пользователь обычным пользователем
     */
    public function isUser(): bool
    {
        return $this->group === 'user';
    }

    /**
     * Проверка, заблокирован ли пользователь
     */
    public function isBlocked(): bool
    {
        return $this->status === 'blocked' || $this->group === 'blocked';
    }

    /**
     * Получить полное имя (Фамилия Имя Отчество)
     */
    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->last_name ?? '',
            $this->first_name ?? '',
            $this->second_name ?? ''
        ], function($part) {
            return !empty(trim($part));
        });
        
        return !empty($parts) ? trim(implode(' ', $parts)) : '';
    }

    // Связи
    public function events(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Event::class, 'author_id');
    }

    public function carts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function withdrawals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Withdrawal::class, 'photographer_id');
    }

    public function groupChangeRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GroupChangeRequest::class);
    }

    public function supportTickets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SupportTicket::class, 'user_id');
    }

    public function supportMessages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SupportMessage::class);
    }

    public function photographerMessages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PhotographerMessage::class, 'photographer_id');
    }

    public function userMessages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PhotographerMessage::class, 'user_id');
    }

    public function notifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Notification::class);
    }
}

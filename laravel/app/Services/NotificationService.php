<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /**
     * Создать уведомление для пользователя
     */
    public static function create(
        string $userId,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
        ]);
    }

    /**
     * Уведомление о создании заявки на вывод средств (для фотографа)
     */
    public static function withdrawalCreated(string $photographerId, float $amount): void
    {
        self::create(
            $photographerId,
            'withdrawal_created',
            'Заявка на вывод средств создана',
            "Ваша заявка на вывод {$amount} ₽ создана и ожидает рассмотрения администратором.",
            route('photo.withdrawals.index')
        );
    }

    /**
     * Уведомление об одобрении заявки на вывод (для фотографа)
     */
    public static function withdrawalApproved(string $photographerId, float $amount, float $finalAmount): void
    {
        self::create(
            $photographerId,
            'withdrawal_approved',
            'Заявка на вывод средств одобрена',
            "Ваша заявка на вывод {$amount} ₽ одобрена. К выплате: {$finalAmount} ₽.",
            route('photo.withdrawals.index')
        );
    }

    /**
     * Уведомление об отклонении заявки на вывод (для фотографа)
     */
    public static function withdrawalRejected(string $photographerId, float $amount): void
    {
        self::create(
            $photographerId,
            'withdrawal_rejected',
            'Заявка на вывод средств отклонена',
            "Ваша заявка на вывод {$amount} ₽ была отклонена. Средства возвращены на баланс.",
            route('photo.withdrawals.index')
        );
    }

    /**
     * Уведомление о новой заявке на вывод средств (для администратора)
     */
    public static function newWithdrawalRequest(string $adminId, string $photographerName, float $amount): void
    {
        self::create(
            $adminId,
            'new_withdrawal_request',
            'Новая заявка на вывод средств',
            "Фотограф {$photographerName} подал заявку на вывод {$amount} ₽.",
            route('admin.withdrawals.index')
        );
    }

    /**
     * Уведомление о создании заявки на смену группы (для администратора)
     */
    public static function groupChangeRequestCreated(string $adminId, string $userName): void
    {
        self::create(
            $adminId,
            'group_change_request',
            'Новая заявка на смену группы',
            "Пользователь {$userName} подал заявку на смену группы с пользователя на фотографа.",
            route('admin.group-requests.index')
        );
    }

    /**
     * Уведомление об одобрении заявки на смену группы (для пользователя)
     */
    public static function groupChangeApproved(string $userId): void
    {
        self::create(
            $userId,
            'group_change_approved',
            'Заявка на смену группы одобрена',
            "Ваша заявка на смену группы одобрена! Теперь вы можете создавать события и загружать фотографии.",
            route('photo.events.index')
        );
    }

    /**
     * Уведомление об отклонении заявки на смену группы (для пользователя)
     */
    public static function groupChangeRejected(string $userId): void
    {
        self::create(
            $userId,
            'group_change_rejected',
            'Заявка на смену группы отклонена',
            "К сожалению, ваша заявка на смену группы была отклонена. Вы можете подать новую заявку позже.",
            route('profile.settings.photo_me')
        );
    }

    /**
     * Уведомление о новом ответе в техподдержке (для пользователя)
     */
    public static function supportMessageReceived(string $userId, string $ticketId): void
    {
        self::create(
            $userId,
            'support_message',
            'Новый ответ в техподдержке',
            "Вам пришел новый ответ в обращении техподдержки.",
            route('support.show', $ticketId)
        );
    }

    /**
     * Уведомление о новом обращении в техподдержке (для администратора)
     */
    public static function newSupportTicket(string $adminId, string $userName, string $ticketId): void
    {
        self::create(
            $adminId,
            'new_support_ticket',
            'Новое обращение в техподдержке',
            "Пользователь {$userName} создал новое обращение в техподдержке.",
            route('admin.support.show', $ticketId)
        );
    }

    /**
     * Уведомление о новом ответе в техподдержке (для администратора)
     */
    public static function supportMessageForAdmin(string $adminId, string $userName, string $ticketId): void
    {
        self::create(
            $adminId,
            'support_message_admin',
            'Новый ответ в техподдержке',
            "Пользователь {$userName} ответил в обращении техподдержки.",
            route('admin.support.show', $ticketId)
        );
    }
}


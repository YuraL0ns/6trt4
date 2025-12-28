@extends('layouts.app')

@section('title', 'Пользователи - Hunter-Photo.Ru')
@section('page-title', 'Управление пользователями')

@section('content')
    @if(session('success'))
        <x-alert type="success" class="mb-6">
            {{ session('success') }}
        </x-alert>
    @endif

    @if(isset($users) && $users->count() > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-800">
                <thead class="bg-[#1e1e1e]">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">ФИО</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">Телефон</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">Группа</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">Статус</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">Действия</th>
                    </tr>
                </thead>
                <tbody class="bg-[#121212] divide-y divide-gray-800">
                    @foreach($users as $user)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-white">
                                {{ $user->full_name ?: 'Не указано' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-white">{{ $user->email ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-white">{{ $user->phone ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <select 
                                    onchange="changeGroup('{{ $user->id }}', this.value)"
                                    class="bg-[#1e1e1e] border border-gray-700 rounded px-2 py-1 text-white text-sm"
                                >
                                    <option value="user" {{ $user->group === 'user' ? 'selected' : '' }}>Пользователь</option>
                                    <option value="photo" {{ $user->group === 'photo' ? 'selected' : '' }}>Фотограф</option>
                                    <option value="admin" {{ $user->group === 'admin' ? 'selected' : '' }}>Администратор</option>
                                    <option value="blocked" {{ $user->group === 'blocked' ? 'selected' : '' }}>Заблокирован</option>
                                </select>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <x-badge variant="{{ $user->status === 'active' ? 'success' : 'error' }}">
                                    {{ $user->status === 'active' ? 'Активен' : 'Заблокирован' }}
                                </x-badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex space-x-2">
                                    <button 
                                        onclick="openEditModal('{{ $user->id }}')"
                                        class="px-3 py-1 bg-[#a78bfa] hover:bg-[#8b5cf6] text-white rounded text-xs transition-colors"
                                    >
                                        Редактировать
                                    </button>
                                    <button 
                                        onclick="openPasswordModal('{{ $user->id }}')"
                                        class="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-white rounded text-xs transition-colors"
                                    >
                                        Сменить пароль
                                    </button>
                                    <button 
                                        onclick="toggleBlock('{{ $user->id }}')"
                                        class="px-3 py-1 {{ $user->status === 'active' ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700' }} text-white rounded text-xs transition-colors"
                                    >
                                        {{ $user->status === 'active' ? 'Заблокировать' : 'Разблокировать' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $users->links() }}
        </div>
    @else
        <div class="text-center py-12">
            <p class="text-gray-400">Нет пользователей</p>
        </div>
    @endif

    <!-- Модальное окно редактирования пользователя -->
    <x-modal id="edit-user-modal" title="Редактировать пользователя" size="lg">
        <form id="edit-user-form" method="POST">
            @csrf
            @method('PUT')
            <x-input label="Имя" name="first_name" id="edit-first-name" required />
            <x-input label="Фамилия" name="last_name" id="edit-last-name" required />
            <x-input label="Отчество" name="second_name" id="edit-second-name" />
            <x-input label="Email" name="email" type="email" id="edit-email" required />
            <x-input label="Телефон" name="phone" id="edit-phone" />
            <x-input label="Город" name="city" id="edit-city" />
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-300 mb-2">Пол</label>
                <select name="gender" id="edit-gender" class="w-full px-4 py-2 bg-[#121212] border border-gray-700 rounded-lg text-white">
                    <option value="">Не указан</option>
                    <option value="male">Мужской</option>
                    <option value="female">Женский</option>
                </select>
            </div>
            <div class="flex space-x-3">
                <x-button type="submit">Сохранить</x-button>
                <x-button variant="outline" type="button" onclick="closeModal('edit-user-modal')">Отмена</x-button>
            </div>
        </form>
    </x-modal>

    <!-- Модальное окно смены пароля -->
    <x-modal id="password-modal" title="Сменить пароль" size="md">
        <form id="password-form" method="POST">
            @csrf
            <x-input label="Новый пароль" name="password" type="password" id="new-password" required />
            <x-input label="Подтверждение пароля" name="password_confirmation" type="password" id="password-confirmation" required />
            <div class="flex space-x-3 mt-4">
                <x-button type="submit">Изменить пароль</x-button>
                <x-button variant="outline" type="button" onclick="closeModal('password-modal')">Отмена</x-button>
            </div>
        </form>
    </x-modal>
@endsection

@push('scripts')
<script>
function changeGroup(userId, group) {
    if (!confirm('Вы уверены, что хотите изменить группу пользователя?')) {
        return;
    }
    
    fetch(`/admin/users/${userId}/change-group`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ group: group })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success || response.ok) {
            location.reload();
        } else {
            alert('Ошибка при изменении группы');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Ошибка при изменении группы');
    });
}

function toggleBlock(userId) {
    if (!confirm('Вы уверены, что хотите изменить статус пользователя?')) {
        return;
    }
    
    fetch(`/admin/users/${userId}/toggle-block`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (response.ok) {
            location.reload();
        } else {
            alert('Ошибка при изменении статуса');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Ошибка при изменении статуса');
    });
}

function openEditModal(userId) {
    // Загружаем данные пользователя
    fetch(`/admin/users/${userId}`, {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        // Заполняем форму данными пользователя
        document.getElementById('edit-first-name').value = data.first_name || '';
        document.getElementById('edit-last-name').value = data.last_name || '';
        document.getElementById('edit-second-name').value = data.second_name || '';
        document.getElementById('edit-email').value = data.email || '';
        document.getElementById('edit-phone').value = data.phone || '';
        document.getElementById('edit-city').value = data.city || '';
        document.getElementById('edit-gender').value = data.gender || '';
        
        // Устанавливаем action формы
        document.getElementById('edit-user-form').action = `/admin/users/${userId}`;
        document.getElementById('edit-user-modal').classList.remove('hidden');
    })
    .catch(error => {
        console.error('Error loading user data:', error);
        alert('Ошибка при загрузке данных пользователя');
    });
}

function openPasswordModal(userId) {
    document.getElementById('password-form').action = `/admin/users/${userId}/change-password`;
    document.getElementById('password-modal').classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}
</script>
@endpush

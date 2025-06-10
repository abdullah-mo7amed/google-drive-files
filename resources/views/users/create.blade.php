<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
      {{ __('Create New User') }}
    </h2>
  </x-slot>

  <div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
      <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
          <form method="POST" action="{{ route('users.store') }}" class="space-y-6">
            @csrf

            <div>
              <x-input-label for="name" :value="__('Name')" />
              <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus />
              <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div>
              <x-input-label for="email" :value="__('Email')" />
              <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required />
              <x-input-error class="mt-2" :messages="$errors->get('email')" />
            </div>

            <div>
              <x-input-label for="password" :value="__('Password')" />
              <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required />
              <x-input-error class="mt-2" :messages="$errors->get('password')" />
            </div>

            <div>
              <x-input-label for="api_url" :value="__('API URL')" />
              <x-text-input id="api_url" name="api_url" type="url" class="mt-1 block w-full" :value="old('api_url')" required />
              <x-input-error class="mt-2" :messages="$errors->get('api_url')" />
            </div>

            <div class="flex items-center gap-4">
              <x-primary-button>{{ __('Create User') }}</x-primary-button>
              <a href="{{ route('users.index') }}" class="text-gray-600 hover:text-gray-900">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</x-app-layout>
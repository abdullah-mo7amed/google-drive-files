<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
      {{ __('Google Drive Upload') }}
    </h2>
  </x-slot>

  <div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
      <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
          @if(session('success'))
          <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline">{{ session('success') }}</span>
          </div>
          @endif

          @if(session('error'))
          <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline">{{ session('error') }}</span>
          </div>
          @endif

          <form method="POST" action="{{ route('google-drive.upload') }}" class="space-y-6">
            @csrf
            <div>
              <x-input-label for="webinar_id" :value="__('Webinar ID')" />
              <x-text-input id="webinar_id" name="webinar_id" type="number" class="mt-1 block w-full" :value="old('webinar_id')" required />
              <p class="mt-1 text-sm text-gray-500">Insert the Webinar ID</p>
              <x-input-error class="mt-2" :messages="$errors->get('webinar_id')" />
            </div>

            <div>
              <x-input-label for="folder_id" :value="__('Google Drive Folder ID')" />
              <x-text-input id="folder_id" name="folder_id" type="text" class="mt-1 block w-full" :value="old('folder_id')" required />
              <p class="mt-1 text-sm text-gray-500">Insert the Google Drive Folder ID</p>
              <x-input-error class="mt-2" :messages="$errors->get('folder_id')" />
            </div>

            @if(auth()->user()->is_admin)
            <div>
              <x-input-label for="api_url" :value="__('API URL')" />
              <x-text-input id="api_url" name="api_url" type="url" class="mt-1 block w-full" :value="old('api_url')" />
              <p class="mt-1 text-sm text-gray-500">Insert the API URL if you are an admin</p>
              <x-input-error class="mt-2" :messages="$errors->get('api_url')" />
            </div>
            @endif

            <div class="flex items-center gap-4">
              <x-primary-button>{{ __('Upload Files') }}</x-primary-button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</x-app-layout>
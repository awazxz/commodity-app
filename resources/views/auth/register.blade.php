{{--
    CATATAN: File ini menggunakan x-guest-layout.
    Agar dark mode bekerja di halaman register, tambahkan dark mode classes
    ke resources/views/layouts/guest.blade.php (atau komponen Breeze kamu).

    Tambahkan script ini di <head> guest layout:
    <script>
        (function() {
            var t = localStorage.getItem('theme') || 'light';
            document.documentElement.classList.toggle('dark', t === 'dark');
        })();
    </script>

    Dan ubah <body> menjadi:
    <body class="font-sans text-gray-900 dark:text-gray-100 antialiased bg-gray-100 dark:bg-gray-900">
--}}

<x-guest-layout>
    <style>
        html.dark .bg-white { background-color: #1e2433 !important; }
        html.dark body { background-color: #111827 !important; }
        html.dark input {
            background-color: #374151 !important;
            border-color: #4b5563 !important;
            color: #f3f4f6 !important;
        }
        html.dark input::placeholder { color: #9ca3af !important; }
        html.dark label { color: #d1d5db !important; }
        html.dark .text-gray-600 { color: #9ca3af !important; }
        html.dark .text-gray-700 { color: #d1d5db !important; }
        html.dark a.text-gray-600 { color: #9ca3af !important; }
        html.dark a.text-gray-600:hover { color: #f3f4f6 !important; }
        html.dark .border-gray-300 { border-color: #4b5563 !important; }
        html.dark .bg-gray-200 { background-color: #374151 !important; }
        html.dark .focus\:ring-indigo-500:focus { --tw-ring-color: rgba(99,102,241,0.5) !important; }
    </style>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" class="dark:text-gray-300" />
            <x-text-input id="name"
                class="block mt-1 w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400"
                type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" class="dark:text-gray-300" />
            <x-text-input id="email"
                class="block mt-1 w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400"
                type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" class="dark:text-gray-300" />
            <x-text-input id="password"
                class="block mt-1 w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400"
                type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" class="dark:text-gray-300" />
            <x-text-input id="password_confirmation"
                class="block mt-1 w-full dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400"
                type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ml-4 dark:bg-blue-700 dark:hover:bg-blue-600">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
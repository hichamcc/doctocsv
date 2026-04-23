<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-white antialiased">

<div class="flex min-h-screen">

    {{-- Left branding panel --}}
    <div class="hidden lg:flex lg:w-1/2 flex-col justify-between border-r border-gray-100 bg-gray-50 p-12">
        <a href="{{ route('home') }}" class="flex items-center gap-3">
            <span class="flex size-9 items-center justify-center rounded-lg bg-gray-900">
                <x-app-logo-icon class="size-5 fill-current text-white" />
            </span>
            <span class="text-lg font-semibold text-gray-900">{{ config('app.name') }}</span>
        </a>

        <div class="space-y-8">
            <div class="space-y-3">
                <h1 class="text-4xl font-bold leading-tight text-gray-900">
                    Turn any document<br>into structured data.
                </h1>
                <p class="text-lg text-gray-500">
                    Upload PDF, DOCX, or XLSX files and let AI extract clean, downloadable CSV in seconds.
                </p>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div class="space-y-1 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="text-xl font-bold text-gray-900">PDF</div>
                    <div class="text-sm text-gray-400">Native support</div>
                </div>
                <div class="space-y-1 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="text-xl font-bold text-gray-900">DOCX</div>
                    <div class="text-sm text-gray-400">Word documents</div>
                </div>
                <div class="space-y-1 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="text-xl font-bold text-gray-900">XLSX</div>
                    <div class="text-sm text-gray-400">Spreadsheets</div>
                </div>
            </div>
        </div>

        <p class="text-sm text-gray-400">© {{ date('Y') }} {{ config('app.name') }}</p>
    </div>

    {{-- Right login panel --}}
    <div class="flex w-full flex-col items-center justify-center px-6 py-12 lg:w-1/2">
        <div class="w-full max-w-sm space-y-8">

            {{-- Mobile logo --}}
            <div class="flex justify-center lg:hidden">
                <a href="{{ route('home') }}" class="flex items-center gap-2">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-gray-900">
                        <x-app-logo-icon class="size-5 fill-current text-white" />
                    </span>
                    <span class="font-semibold text-gray-900">{{ config('app.name') }}</span>
                </a>
            </div>

            <div>
                <h2 class="text-2xl font-bold text-gray-900">Welcome back</h2>
                <p class="mt-1 text-sm text-gray-500">Sign in to your account to continue</p>
            </div>

            <x-auth-session-status :status="session('status')" />

            <x-form method="post" :action="route('login.store')" class="space-y-5">
                <x-field>
                    <x-label>{{ __('Email address') }}</x-label>
                    <x-input
                        type="email"
                        name="email"
                        required
                        autofocus
                        autocomplete="email"
                        :value="old('email')"
                    />
                    <x-error for="email" />
                </x-field>

                <x-field>
                    <div class="flex items-center justify-between">
                        <x-label>{{ __('Password') }}</x-label>
                        @if (Route::has('password.request'))
                            <x-link class="text-xs" href="{{ route('password.request') }}">
                                {{ __('Forgot password?') }}
                            </x-link>
                        @endif
                    </div>
                    <x-input
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                    />
                    <x-error for="password" />
                </x-field>

                <x-checkbox name="remember" :label="__('Remember me for 30 days')" />

                <x-button variant="primary" class="w-full justify-center">
                    {{ __('Sign in') }}
                </x-button>
            </x-form>

            @if (Route::has('register'))
                <p class="text-center text-sm text-gray-500">
                    {{ __("Don't have an account?") }}
                    <x-link href="{{ route('register') }}">{{ __('Sign up') }}</x-link>
                </p>
            @endif

        </div>
    </div>

</div>

</body>
</html>

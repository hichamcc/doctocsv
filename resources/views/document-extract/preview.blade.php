<x-layouts.app :title="__('Preview')">
    <div class="flex flex-col gap-6">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <x-heading>{{ __('Extracted Data') }}</x-heading>
                <x-subheading>{{ trans_choice(':count record extracted|:count records extracted', count($records), ['count' => count($records)]) }}</x-subheading>
            </div>
            <div class="flex items-center gap-3">
                <x-button href="{{ route('document-extract.create') }}">
                    {{ __('Upload New') }}
                </x-button>
                <x-form method="post" action="{{ route('document-extract.download') }}">
                    <x-button type="submit" variant="primary">
                        {{ __('Download CSV') }}
                    </x-button>
                </x-form>
            </div>
        </div>

        {{-- Data table --}}
        <x-section>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800">
                            @foreach ($headers as $header)
                                <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    {{ str_replace('_', ' ', $header) }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($records as $record)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                @foreach ($headers as $header)
                                    <td class="max-w-xs truncate px-4 py-3 text-gray-700 dark:text-gray-300" title="{{ $record[$header] ?? '' }}">
                                        {{ $record[$header] ?? '' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-section>

        {{-- Send to Google Sheet --}}
        <x-section>
            @error('sheet')
                <div class="px-6 py-4 text-sm text-red-700 bg-red-50 border-b border-red-200 dark:bg-red-950 dark:text-red-400 dark:border-red-800">
                    {{ $message }}
                </div>
            @enderror

            @if (session('sheet_success'))
                <div class="px-6 py-4 text-sm text-green-700 bg-green-50 border-b border-green-200 dark:bg-green-950 dark:text-green-400 dark:border-green-800">
                    {{ session('sheet_success') }}
                </div>
            @endif

            <x-form
                method="post"
                action="{{ route('document-extract.send-to-sheet') }}"
                x-data="{ loading: false }"
                @submit="loading = true"
            >
                <div class="space-y-4 p-6">
                    <div>
                        <x-heading class="text-base">{{ __('Send to Google Sheet') }}</x-heading>
                        <x-subheading>{{ __('Appends data to today\'s tab in the current week\'s spreadsheet.') }}</x-subheading>
                    </div>

                    @error('client_name')
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    <x-field>
                        <x-label>{{ __('Client Name') }}</x-label>
                        <x-input
                            type="text"
                            name="client_name"
                            placeholder="e.g. Acme Corp"
                            :value="old('client_name')"
                        />
                    </x-field>
                </div>

                <div class="flex justify-end border-t border-gray-200 px-6 py-4 dark:border-gray-700">
                    <x-button type="submit" variant="primary">
                        <span x-show="!loading">{{ __('Send to Google Sheet') }}</span>
                        <span x-show="loading" x-cloak class="flex items-center gap-2">
                            <svg class="size-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ __('Sending…') }}
                        </span>
                    </x-button>
                </div>
            </x-form>
        </x-section>

    </div>
</x-layouts.app>

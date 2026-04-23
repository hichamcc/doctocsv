<x-layouts.app :title="__('Document to CSV')">
    <div class="mx-auto max-w-2xl">
        <x-heading>{{ __('Document to CSV') }}</x-heading>
        <x-subheading>{{ __('Upload PDF, DOCX, or XLSX files. Claude will extract the structured data and return a CSV download.') }}</x-subheading>

        @if ($errors->any())
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-400">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-section class="mt-6">
            <x-form method="post" action="{{ route('document-extract.store') }}" upload
                x-data="{ loading: false }"
                @submit="loading = true"
            >
                <div class="space-y-6 p-6">
                    <x-field>
                        <x-label>{{ __('Files') }}</x-label>
                        <div
                            x-data="fileUpload()"
                            class="mt-1"
                        >
                            <label
                                for="documents"
                                class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 px-6 py-10 transition hover:border-gray-400 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:hover:border-gray-500 dark:hover:bg-gray-700"
                                :class="dragging ? 'border-blue-400 bg-blue-50 dark:border-blue-500 dark:bg-blue-950' : ''"
                                @dragover.prevent="dragging = true"
                                @dragleave.prevent="dragging = false"
                                @drop.prevent="onDrop($event)"
                            >
                                <svg class="mb-3 size-10 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                </svg>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-semibold text-blue-600 dark:text-blue-400">{{ __('Click to upload') }}</span>
                                    {{ __('or drag and drop') }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">{{ __('PDF, DOCX, XLSX — up to 20 MB each, max 10 files') }}</p>
                                <input
                                    id="documents"
                                    name="documents[]"
                                    type="file"
                                    multiple
                                    accept=".pdf,.docx,.xlsx"
                                    class="sr-only"
                                    @change="onFileChange($event)"
                                >
                            </label>

                            <template x-if="files.length > 0">
                                <ul class="mt-4 space-y-2">
                                    <template x-for="(file, index) in files" :key="index">
                                        <li class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm dark:border-gray-700 dark:bg-gray-900">
                                            <div class="flex items-center gap-3 truncate">
                                                <svg class="size-5 shrink-0 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                </svg>
                                                <span class="truncate text-gray-700 dark:text-gray-300" x-text="file.name"></span>
                                                <span class="shrink-0 text-gray-400" x-text="formatSize(file.size)"></span>
                                            </div>
                                            <button type="button" @click="remove(index)" class="ml-3 shrink-0 text-gray-400 hover:text-red-500">
                                                <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </li>
                                    </template>
                                </ul>
                            </template>
                        </div>
                        <x-error for="documents" />
                        <x-error for="documents.0" />
                    </x-field>
                </div>

                    <x-field>
                        <x-label>{{ __('Additional Instructions') }} <span class="text-gray-400 font-normal">({{ __('optional') }})</span></x-label>
                        <x-textarea
                        class="p-2"
                            name="instructions"
                            rows="3"
                            placeholder="{{ __('e.g. Translate all headers to English, format dates as YYYY-MM-DD, merge first and last name into full_name…') }}"
                        >{{ old('instructions') }}</x-textarea>
                    </x-field>

                <div class="flex items-center justify-end border-t border-gray-200 px-6 py-4 dark:border-gray-700">
                    <x-button
                        type="submit"
                        variant="primary"
                    >
                        <span x-show="!loading">{{ __('Extract & Download CSV') }}</span>
                        <span x-show="loading" x-cloak class="flex items-center gap-2">
                            <svg class="size-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ __('Extracting…') }}
                        </span>
                    </x-button>
                </div>
            </x-form>
        </x-section>
    </div>

    <script>
        function fileUpload() {
            return {
                files: [],
                dragging: false,

                onFileChange(event) {
                    this.addFiles(Array.from(event.target.files));
                },

                onDrop(event) {
                    this.dragging = false;
                    this.addFiles(Array.from(event.dataTransfer.files));
                },

                addFiles(incoming) {
                    const allowed = ['application/pdf',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
                    incoming
                        .filter(f => allowed.includes(f.type) || /\.(pdf|docx|xlsx)$/i.test(f.name))
                        .forEach(f => {
                            if (!this.files.find(e => e.name === f.name && e.size === f.size)) {
                                this.files.push(f);
                            }
                        });
                    this.syncInput();
                },

                remove(index) {
                    this.files.splice(index, 1);
                    this.syncInput();
                },

                syncInput() {
                    const dt = new DataTransfer();
                    this.files.forEach(f => dt.items.add(f));
                    document.getElementById('documents').files = dt.files;
                },

                formatSize(bytes) {
                    if (bytes < 1024) return bytes + ' B';
                    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
                    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
                },
            };
        }
    </script>
</x-layouts.app>

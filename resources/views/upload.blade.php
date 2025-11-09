
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- add met csrf token --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>YoPrint CSV Upload</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        .fixed-notification {
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.3s ease-in-out;
        }

        .fixed-notification.translate-x-0 {
            transform: translateX(0);
            opacity: 1;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8" x-data="uploadComponent()">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">YoPrint CSV Upload</h1>
            <p class="text-gray-600">Upload and process CSV files in the background</p>
        </div>

        <!-- Upload Form -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form @submit.prevent="uploadFile" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Select CSV File
                    </label>
                    <input
                        type="file"
                        @change="file = $event.target.files[0]"
                        accept=".csv,.txt"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required
                    >
                </div>

                <button
                    type="submit"
                    :disabled="uploading"
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                >
                    <span x-text="uploading ? 'Uploading...' : 'Upload CSV File'"></span>
                    <svg x-show="uploading" class="animate-spin -mr-1 ml-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </button>
            </form>
        </div>

        <!-- Upload History -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Upload History</h2>

            <div class="space-y-4">
                <template x-for="upload in uploads" :key="upload.id">
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <h3 class="font-medium text-gray-800" x-text="upload.filename"></h3>
                                <p class="text-sm text-gray-500" x-text="upload.created_at"></p>
                            </div>
                            <span
                                class="px-2 py-1 text-xs rounded-full"
                                :class="{
                                    'bg-yellow-100 text-yellow-800': upload.status === 'pending',
                                    'bg-blue-100 text-blue-800': upload.status === 'processing',
                                    'bg-green-100 text-green-800': upload.status === 'completed',
                                    'bg-red-100 text-red-800': upload.status === 'failed'
                                }"
                                x-text="upload.status"
                            ></span>
                        </div>

                        <div x-show="upload.status === 'processing'" class="mt-2">
                            <div class="flex justify-between text-sm text-gray-600 mb-1">
                                <span>Processing...</span>
                                <span x-text="`${upload.processed_rows}/${upload.total_rows}`"></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div
                                    class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                                    :style="`width: ${upload.progress}%`"
                                ></div>
                            </div>
                        </div>

                        <div x-show="upload.status === 'failed'" class="mt-2">
                            <p class="text-sm text-red-600" x-text="upload.error_message"></p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <script>
    function uploadComponent() {
        return {
            file: null,
            uploading: false,
            uploads: [],

            init() {
                this.fetchStatus();
                // Poll every 3 seconds for updates
                setInterval(() => this.fetchStatus(), 3000);
            },

            async uploadFile() {
                if (!this.file) {
                    this.showNotification('Please select a CSV file first.', 'error');
                    return;
                }

                this.uploading = true;
                const formData = new FormData();
                formData.append('csv_file', this.file);

                try {
                    const response = await fetch('/upload', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: formData
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || 'Upload failed');
                    }

                    if (data.success) {
                        this.showNotification(data.message, 'success');
                    } else {
                        this.showNotification(data.message, 'error');
                    }

                    // Reset form
                    this.file = null;
                    document.querySelector('input[type="file"]').value = '';

                    // Refresh status
                    this.fetchStatus();

                } catch (error) {
                    console.error('Upload failed:', error);
                    this.showNotification(error.message || 'Upload failed. Please try again.', 'error');
                } finally {
                    this.uploading = false;
                }
            },

            async fetchStatus() {
                try {
                    const response = await fetch('/upload/status');

                    if (!response.ok) {
                        throw new Error('Failed to fetch status');
                    }

                    const data = await response.json();

                    // Handle both resource collection and direct array
                    if (data.uploads && data.uploads.data) {
                        this.uploads = data.uploads.data;
                    } else if (data.uploads) {
                        this.uploads = data.uploads;
                    } else {
                        this.uploads = [];
                    }

                } catch (error) {
                    console.error('Failed to fetch status:', error);
                    // Don't show error for status polling to avoid spamming users
                }
            },

            showNotification(message, type = 'info') {
                // Remove any existing notifications
                const existingNotifications = document.querySelectorAll('.fixed-notification');
                existingNotifications.forEach(notification => notification.remove());

                const colors = {
                    success: 'bg-green-50 border-green-200 text-green-800',
                    error: 'bg-red-50 border-red-200 text-red-800',
                    info: 'bg-blue-50 border-blue-200 text-blue-800',
                    warning: 'bg-yellow-50 border-yellow-200 text-yellow-800'
                };

                const icons = {
                    success: `
                        <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                    `,
                    error: `
                        <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                    `,
                    info: `
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                    `
                };

                const notification = document.createElement('div');
                notification.className = `fixed-notification fixed top-4 right-4 z-50 max-w-sm w-full rounded-lg shadow-lg border ${colors[type]} transform transition-all duration-300 ease-in-out`;
                notification.innerHTML = `
                    <div class="p-4">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                ${icons[type]}
                            </div>
                            <div class="ml-3 w-0 flex-1 pt-0.5">
                                <p class="text-sm font-medium">${message}</p>
                            </div>
                            <div class="ml-4 flex-shrink-0 flex">
                                <button class="rounded-md inline-flex text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" onclick="this.parentElement.parentElement.parentElement.parentElement.remove()">
                                    <span class="sr-only">Close</span>
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                `;

                document.body.appendChild(notification);

                // Animate in
                requestAnimationFrame(() => {
                    notification.classList.remove('transform');
                    notification.classList.add('translate-x-0', 'opacity-100');
                });

                // Auto-remove after 5 seconds
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.classList.add('transform', 'translate-x-full', 'opacity-0');
                        setTimeout(() => {
                            if (notification.parentElement) {
                                notification.remove();
                            }
                        }, 300);
                    }
                }, 5000);
            },

            // Helper method to get status color
            getStatusColor(status) {
                const colors = {
                    'pending': 'bg-yellow-100 text-yellow-800',
                    'processing': 'bg-blue-100 text-blue-800',
                    'completed': 'bg-green-100 text-green-800',
                    'failed': 'bg-red-100 text-red-800'
                };
                return colors[status] || 'bg-gray-100 text-gray-800';
            }
        }
    }
    </script>
</body>
</html>

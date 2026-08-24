<template>
    <div class="w-full">
        <div class="bg-white shadow-sm border border-gray-200 overflow-hidden">
            <!-- Header -->
            <div class="px-6 py-5 border-b border-gray-100">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-blue-50 p-2">
                            <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Messages</h2>
                            <p class="text-sm text-gray-500">View and manage public inquiries</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">
                            0 messages
                        </span>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Bar -->
            <div class="px-6 py-3 border-b border-gray-100 bg-gray-50/50">
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="relative flex-1">
                        <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            type="text"
                            placeholder="Search messages..."
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 pl-9 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-all duration-200"
                        />
                    </div>
                    <div class="flex gap-2">
                        <select class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-all duration-200">
                            <option value="all">All Messages</option>
                            <option value="unread">Unread</option>
                            <option value="read">Read</option>
                            <option value="archived">Archived</option>
                        </select>
                        <button class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-all duration-200">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                            </svg>
                            Sort
                        </button>
                    </div>
                </div>
            </div>

            <!-- Messages List -->
            <div class="divide-y divide-gray-100">
                <!-- Empty State -->
                <div class="flex flex-col items-center justify-center py-16 px-6">
                    <div class="rounded-full bg-gray-100 p-4 mb-4">
                        <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No messages yet</h3>
                    <p class="text-sm text-gray-500 text-center max-w-md mb-6">
                        Messages submitted through the Ask MEO platform will appear here. 
                        You'll be able to review, respond, and manage all public inquiries.
                    </p>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2 text-sm text-gray-500">
                            <svg class="h-4 w-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Real-time notifications
                        </div>
                        <div class="h-4 w-px bg-gray-300"></div>
                        <div class="flex items-center gap-2 text-sm text-gray-500">
                            <svg class="h-4 w-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Email alerts
                        </div>
                    </div>
                </div>

                <!-- Message Item Example (commented out for now) -->
                <!-- 
                <div class="px-6 py-4 hover:bg-gray-50 transition-colors cursor-pointer">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0">
                            <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                <span class="text-sm font-medium text-blue-600">JD</span>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-1">
                                <div class="flex items-center gap-2">
                                    <h4 class="text-sm font-semibold text-gray-900">John Doe</h4>
                                    <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">New</span>
                                </div>
                                <span class="text-xs text-gray-500">2 hours ago</span>
                            </div>
                            <p class="text-sm text-gray-600 truncate">Subject: Inquiry about project timeline</p>
                            <p class="text-xs text-gray-500 truncate mt-0.5">Hello, I would like to know more about the upcoming project...</p>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="text-xs text-gray-400">john.doe@email.com</span>
                                <span class="text-xs text-gray-300">•</span>
                                <span class="text-xs text-gray-400">+63 912 345 6789</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Reply">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                </svg>
                            </button>
                            <button class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Archive">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                -->
            </div>

            <!-- Footer with Stats -->
            <div class="px-6 py-3 border-t border-gray-100 bg-gray-50/50">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <span class="text-xs text-gray-500">Auto-refreshes every 5 minutes</span>
                        <button class="text-xs text-blue-600 hover:text-blue-700 font-medium">
                            Refresh now
                        </button>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-400">Ask MEO Inbox</span>
                        <div class="h-1.5 w-1.5 rounded-full bg-green-500"></div>
                        <span class="text-xs text-gray-400">Active</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
// Component logic will go here when messages functionality is implemented
</script>

<style scoped>
/* Custom scrollbar for message list */
.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: transparent;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #e5e7eb;
    border-radius: 2px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #d1d5db;
}
</style>
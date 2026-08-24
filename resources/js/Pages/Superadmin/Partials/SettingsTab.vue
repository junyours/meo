<template>
    <div class="w-full">
        <div class="bg-white border border-gray-200 shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">System Settings</h2>
            <p class="text-gray-600">Configure MEO portal and system preferences.</p>
            
            <div class="mt-6 space-y-6">
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="font-semibold text-gray-900 mb-3">General Settings</h3>
                    <label class="flex items-center gap-3">
                        <input type="checkbox" class="w-4 h-4 rounded border-gray-300" checked />
                        <span class="text-sm text-gray-700">Enable public project viewing</span>
                    </label>
                </div>

                <div class="border-t border-gray-200 pt-6">
                    <h3 class="font-semibold text-gray-900 mb-3">Email Settings</h3>
                    <label class="flex items-center gap-3">
                        <input type="checkbox" class="w-4 h-4 rounded border-gray-300" checked />
                        <span class="text-sm text-gray-700">Send email notifications for new messages</span>
                    </label>
                </div>

                <div class="border-t border-gray-200 pt-6">
                    <button class="px-6 py-2 bg-emerald-600 text-white hover:bg-emerald-700 transition-colors font-medium">
                        Save Settings
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

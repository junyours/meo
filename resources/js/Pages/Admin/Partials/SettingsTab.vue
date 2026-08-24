<template>
    <div class="w-full">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">System Settings</h2>
            <p class="text-gray-600">Configure MEO portal and admin preferences.</p>
            <div class="mt-6 space-y-6">
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="font-semibold text-gray-900 mb-3">General Settings</h3>
                    <label class="flex items-center gap-3">
                        <input type="checkbox" class="w-4 h-4 rounded" checked />
                        <span class="text-sm text-gray-700">Enable team message notifications</span>
                    </label>
                </div>
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="font-semibold text-gray-900 mb-3">Project Settings</h3>
                    <label class="flex items-center gap-3">
                        <input type="checkbox" class="w-4 h-4 rounded" checked />
                        <span class="text-sm text-gray-700">Enable project status alerts</span>
                    </label>
                </div>
                <div class="border-t border-gray-200 pt-6">
                    <button class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        Save Settings
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

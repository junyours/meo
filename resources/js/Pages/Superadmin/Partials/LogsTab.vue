<template>
    <div class="w-full">
        <div class="bg-white border border-gray-200 shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Activity Logs</h2>
            <p class="text-gray-600">Track system activity and user actions.</p>
            
            <div class="mt-6">
                <div class="space-y-3">
                    <div class="p-4 border border-gray-200">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-semibold text-gray-900">System initialized</p>
                                <p class="text-sm text-gray-600">Database migrations completed</p>
                            </div>
                            <span class="text-xs text-gray-500">Just now</span>
                        </div>
                    </div>
                    
                    <div class="p-4 border border-gray-200">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-semibold text-gray-900">Test users created</p>
                                <p class="text-sm text-gray-600">Superadmin, admin, and staff accounts seeded</p>
                            </div>
                            <span class="text-xs text-gray-500">Just now</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<template>
    <div class="w-full space-y-4">
        <!-- Gmail-Style Inbox Container -->
        <div class="bg-white shadow-sm border border-gray-200 overflow-hidden rounded-xl">
            <!-- Top App Bar & Header -->
            <div class="px-5 py-3.5 border-b border-gray-200 bg-gradient-to-r from-white via-slate-50 to-red-50/20">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                    <!-- Title & Inbox Counters -->
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-red-600 p-2 text-white shadow-sm">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-base font-bold text-gray-900 tracking-tight">Ask MEO Inquiries Inbox</h2>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-700 border border-red-200">
                                    {{ activeInquiriesCount }} Active
                                </span>
                                <span v-if="countByStatus('pending') > 0" class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300 animate-pulse">
                                    {{ countByStatus('pending') }} Waiting
                                </span>
                            </div>
                            <p class="text-[11px] text-gray-500">Public concern reports, citizen inquiries &amp; site inspection requests</p>
                        </div>
                    </div>

                    <!-- Right Controls: Live Sync Badge & Refresh -->
                    <div class="flex items-center gap-2">
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 border border-emerald-200 text-[11px] font-bold text-emerald-800 shadow-2xs">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                            </span>
                            <span>Live Sync</span>
                        </div>
                        <button 
                            @click="fetchInquiries"
                            :disabled="isLoading"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition shadow-2xs"
                            title="Refresh Inbox"
                        >
                            <svg class="h-3.5 w-3.5" :class="{ 'animate-spin': isLoading || isSyncing }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span>Refresh</span>
                        </button>
                    </div>
                </div>

                <!-- Gmail-Style Filter Tabs -->
                <div class="flex items-center gap-1 overflow-x-auto mt-3 pt-2 border-t border-gray-100">
                    <button 
                        @click="statusFilter = 'all'; currentPage = 1;"
                        class="px-3 py-1.5 rounded-md text-xs font-semibold transition-all shrink-0 flex items-center gap-1.5 border"
                        :class="statusFilter === 'all' ? 'bg-red-50 text-red-700 border-red-200 font-bold shadow-2xs' : 'border-transparent text-gray-600 hover:bg-gray-100'"
                    >
                        <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                        All Inquiries
                        <span class="px-1.5 py-0.2 rounded text-[10px]" :class="statusFilter === 'all' ? 'bg-red-200 text-red-800' : 'bg-gray-200 text-gray-700'">
                            {{ activeInquiriesCount }}
                        </span>
                    </button>

                    <button 
                        @click="statusFilter = 'pending'; currentPage = 1;"
                        class="px-3 py-1.5 rounded-md text-xs font-semibold transition-all shrink-0 flex items-center gap-1.5 border"
                        :class="statusFilter === 'pending' ? 'bg-amber-50 text-amber-900 border-amber-300 font-bold shadow-2xs' : 'border-transparent text-gray-600 hover:bg-gray-100'"
                    >
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        Waiting for Review
                        <span class="px-1.5 py-0.2 rounded text-[10px] font-bold" :class="countByStatus('pending') > 0 ? 'bg-amber-200 text-amber-900' : 'bg-gray-200 text-gray-700'">
                            {{ countByStatus('pending') }}
                        </span>
                    </button>

                    <button 
                        @click="statusFilter = 'accepted'; currentPage = 1;"
                        class="px-3 py-1.5 rounded-md text-xs font-semibold transition-all shrink-0 flex items-center gap-1.5 border"
                        :class="statusFilter === 'accepted' ? 'bg-emerald-50 text-emerald-900 border-emerald-300 font-bold shadow-2xs' : 'border-transparent text-gray-600 hover:bg-gray-100'"
                    >
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        Accepted / In Progress
                        <span class="px-1.5 py-0.2 rounded text-[10px] font-bold" :class="statusFilter === 'accepted' ? 'bg-emerald-200 text-emerald-900' : 'bg-gray-200 text-gray-700'">
                            {{ countByStatus('accepted') }}
                        </span>
                    </button>

                    <button 
                        @click="statusFilter = 'resolved'; currentPage = 1;"
                        class="px-3 py-1.5 rounded-md text-xs font-semibold transition-all shrink-0 flex items-center gap-1.5 border"
                        :class="statusFilter === 'resolved' ? 'bg-blue-50 text-blue-900 border-blue-300 font-bold shadow-2xs' : 'border-transparent text-gray-600 hover:bg-gray-100'"
                    >
                        <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                        Resolved
                        <span class="px-1.5 py-0.2 rounded text-[10px] font-bold" :class="statusFilter === 'resolved' ? 'bg-blue-200 text-blue-900' : 'bg-gray-200 text-gray-700'">
                            {{ countByStatus('resolved') }}
                        </span>
                    </button>

                    <button 
                        @click="statusFilter = 'declined'; currentPage = 1;"
                        class="px-3 py-1.5 rounded-md text-xs font-semibold transition-all shrink-0 flex items-center gap-1.5 border"
                        :class="statusFilter === 'declined' ? 'bg-slate-100 text-slate-800 border-slate-300 font-bold shadow-2xs' : 'border-transparent text-gray-600 hover:bg-gray-100'"
                    >
                        <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                        Declined / Closed
                        <span class="px-1.5 py-0.2 rounded text-[10px]" :class="statusFilter === 'declined' ? 'bg-slate-200 text-slate-900' : 'bg-gray-200 text-gray-700'">
                            {{ countByStatus('declined') }}
                        </span>
                    </button>
                </div>

                <!-- Search & Filters Toolbar -->
                <div class="mt-2.5 flex flex-col md:flex-row gap-2 items-center justify-between">
                    <div class="relative flex-1 w-full">
                        <svg class="absolute left-3 top-2.5 h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search by token, citizen, contact, location, subject, message, or officer..."
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-1.5 pl-9 pr-8 text-xs focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500/30 shadow-2xs"
                        />
                        <button 
                            v-if="searchQuery" 
                            @click="searchQuery = ''"
                            class="absolute right-2.5 top-2 text-gray-400 hover:text-gray-600 text-xs"
                        >
                            ✕
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                        <!-- Location Filter -->
                        <select 
                            v-model="locationFilter" 
                            class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500/30 shadow-2xs text-gray-700"
                        >
                            <option value="all">All Locations ({{ uniqueLocations.length }})</option>
                            <option v-for="loc in uniqueLocations" :key="loc" :value="loc">{{ loc }}</option>
                        </select>

                        <!-- Sort Order -->
                        <select 
                            v-model="sortBy" 
                            class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500/30 shadow-2xs text-gray-700 font-medium"
                        >
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="location">Location (A-Z)</option>
                        </select>

                        <!-- Items Per Page -->
                        <select 
                            v-model="pageSize" 
                            class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500/30 shadow-2xs text-gray-700"
                        >
                            <option :value="15">15 / page</option>
                            <option :value="25">25 / page</option>
                            <option :value="50">50 / page</option>
                            <option :value="100">100 / page</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="isLoading && inquiries.length === 0" class="py-16 text-center text-gray-500 text-xs">
                <svg class="h-6 w-6 text-red-600 animate-spin mx-auto mb-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
                Loading public inquiries inbox...
            </div>

            <!-- Empty State -->
            <div v-else-if="filteredInquiries.length === 0" class="flex flex-col items-center justify-center py-16 px-4 bg-white">
                <div class="rounded-lg bg-gray-100 p-3 mb-2 text-gray-400">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-gray-900 mb-0.5">No inquiries found in this view</h3>
                <p class="text-xs text-gray-500 text-center max-w-sm">
                    {{ statusFilter === 'all' ? 'All active public inquiries are currently cleared or moved to resolved.' : 'Try adjusting your search query, location selection, or status tab.' }}
                </p>
            </div>

            <!-- ==================== GMAIL-STYLE INBOX LIST ==================== -->
            <div v-else class="divide-y divide-gray-100 bg-white">
                <div 
                    v-for="item in paginatedInquiries" 
                    :key="item.id"
                    @click="openDetailsModal(item)"
                    class="group relative flex flex-col md:flex-row md:items-center justify-between gap-2.5 px-4 py-2.5 hover:bg-slate-50 cursor-pointer transition-colors border-l-4"
                    :class="{
                        'border-l-amber-500 bg-amber-50/20': item.status === 'pending',
                        'border-l-emerald-500': item.status === 'accepted',
                        'border-l-blue-500': item.status === 'resolved',
                        'border-l-gray-300': item.status === 'declined'
                    }"
                >
                    <!-- Left: Status Indicator, Token & Citizen Name -->
                    <div class="flex items-center gap-2.5 min-w-0 md:w-60 lg:w-68 shrink-0">
                        <!-- Status Dot -->
                        <span 
                            class="w-2 h-2 rounded-full shrink-0" 
                            :class="{
                                'bg-amber-500 ring-2 ring-amber-100 animate-pulse': item.status === 'pending',
                                'bg-emerald-500': item.status === 'accepted',
                                'bg-blue-500': item.status === 'resolved',
                                'bg-gray-400': item.status === 'declined'
                            }"
                        ></span>

                        <!-- Reference Token -->
                        <span class="font-mono text-[11px] font-bold text-gray-700 bg-gray-100 px-1.5 py-0.5 rounded border border-gray-300 shrink-0">
                            {{ item.tracking_token }}
                        </span>

                        <!-- Sender Name & Phone -->
                        <div class="min-w-0 truncate">
                            <span class="text-xs font-bold text-gray-900 block truncate group-hover:text-red-700 transition-colors">
                                {{ item.fullname }}
                            </span>
                            <span class="text-[10px] text-gray-500 font-mono block truncate">
                                {{ item.phone }}
                            </span>
                        </div>
                    </div>

                    <!-- Center: Location, Subject & Message Snippet -->
                    <div class="flex-1 min-w-0 flex flex-col sm:flex-row sm:items-center gap-1.5 sm:gap-2 text-xs">
                        <!-- Location Badge -->
                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-red-50 text-red-700 border border-red-200 shrink-0 max-w-max">
                            <svg class="w-3 h-3 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span class="truncate">{{ item.location }}</span>
                        </span>

                        <!-- Subject & Content Preview (Gmail Bold Subject - Regular Message Style) -->
                        <div class="min-w-0 truncate text-gray-600">
                            <strong class="text-gray-900 font-bold" v-if="item.subject">{{ item.subject }} — </strong>
                            <span class="text-gray-600">{{ item.message }}</span>
                        </div>

                        <!-- Photo Attachment Indicator -->
                        <div 
                            v-if="(item.photo_urls && item.photo_urls.length > 0) || item.photo_url"
                            @click.stop="openLightbox(item.photo_urls?.[0] || item.photo_url, item.photo_urls?.length ? item.photo_urls : [item.photo_url])"
                            class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-bold border border-slate-300 shrink-0 cursor-pointer shadow-2xs"
                            title="View Photos in Lightbox"
                        >
                            <svg class="w-3 h-3 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                            {{ item.photo_urls?.length || 1 }} Photo{{ (item.photo_urls?.length || 1) > 1 ? 's' : '' }}
                        </div>
                    </div>

                    <!-- Right: Status Badge / Officer Info & Date & Actions -->
                    <div class="flex items-center justify-between sm:justify-end gap-2.5 shrink-0 pt-1 sm:pt-0 border-t sm:border-t-0 border-gray-100">
                        <!-- Status / Handler Badge -->
                        <div class="text-right whitespace-nowrap">
                            <span 
                                class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border block w-max ml-auto"
                                :class="statusClasses[item.status] || 'bg-gray-100 text-gray-700'"
                            >
                                {{ item.status === 'pending' ? 'Waiting' : item.status }}
                            </span>
                            
                            <!-- Handler Officer Snippet -->
                            <div v-if="item.resolved_by_user" class="text-[10px] text-blue-700 font-semibold mt-0.5">
                                Resolved by {{ item.resolved_by_user.name }}
                            </div>
                            <div v-else-if="item.accepted_by_user" class="text-[10px] text-emerald-700 font-semibold mt-0.5">
                                Handled by {{ item.accepted_by_user.name }}
                            </div>
                            <div v-else-if="item.updated_by_user" class="text-[10px] text-gray-500 mt-0.5">
                                Updated by {{ item.updated_by_user.name }}
                            </div>
                        </div>

                        <!-- Date Time -->
                        <span class="text-[11px] text-gray-500 font-medium whitespace-nowrap min-w-[65px] text-right">
                            {{ item.created_at_relative || item.created_at }}
                        </span>

                        <!-- Action Buttons (Accept / Resolve / Remarks / Delete) -->
                        <div class="flex items-center gap-1 pl-1" @click.stop>
                            <!-- Accept Concern: ONLY SHOW IF PENDING -->
                            <button 
                                v-if="item.status === 'pending'"
                                @click="updateStatus(item, 'accepted')"
                                class="p-1 bg-emerald-50 hover:bg-emerald-600 text-emerald-700 hover:text-white rounded transition border border-emerald-300 shadow-2xs"
                                title="Accept this public concern"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </button>

                            <!-- Mark Resolved: ONLY SHOW IF ACCEPTED -->
                            <button 
                                v-if="item.status === 'accepted'"
                                @click="updateStatus(item, 'resolved')"
                                class="p-1 bg-blue-50 hover:bg-blue-600 text-blue-700 hover:text-white rounded transition border border-blue-300 shadow-2xs"
                                title="Mark as resolved"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </button>

                            <!-- Add / Edit Remarks -->
                            <button 
                                @click="promptEditNote(item)"
                                class="p-1 bg-gray-50 hover:bg-gray-200 text-gray-600 rounded transition border border-gray-200"
                                title="Add/Edit Internal Remarks"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>

                            <!-- View Details Button -->
                            <button 
                                @click="openDetailsModal(item)"
                                class="p-1 bg-slate-50 hover:bg-slate-200 text-slate-700 rounded transition border border-slate-200"
                                title="View Full Details"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>

                            <!-- Delete Inquiry -->
                            <button 
                                @click="deleteInquiry(item)"
                                class="p-1 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition"
                                title="Delete Record"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gmail-Style Pagination Footer -->
            <div class="px-5 py-3 border-t border-gray-200 bg-gray-50 flex flex-col sm:flex-row items-center justify-between gap-2.5 text-xs text-gray-600">
                <div class="flex items-center gap-2">
                    <span>
                        Showing <strong class="text-gray-900">{{ startIndex + 1 }}</strong> to <strong class="text-gray-900">{{ endIndex }}</strong> of <strong class="text-gray-900">{{ filteredInquiries.length }}</strong> inquiries
                    </span>
                </div>

                <!-- Page Navigation -->
                <div class="flex items-center gap-1" v-if="totalPages > 1">
                    <button 
                        @click="currentPage--"
                        :disabled="currentPage <= 1"
                        class="px-2.5 py-1 rounded border border-gray-300 bg-white font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-40 transition shadow-2xs text-xs"
                    >
                        &larr; Prev
                    </button>

                    <div class="flex items-center gap-1 px-1">
                        <button 
                            v-for="p in totalPages" 
                            :key="p"
                            @click="currentPage = p"
                            class="w-6 h-6 rounded text-xs font-bold transition flex items-center justify-center"
                            :class="currentPage === p ? 'bg-red-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'"
                        >
                            {{ p }}
                        </button>
                    </div>

                    <button 
                        @click="currentPage++"
                        :disabled="currentPage >= totalPages"
                        class="px-2.5 py-1 rounded border border-gray-300 bg-white font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-40 transition shadow-2xs text-xs"
                    >
                        Next &rarr;
                    </button>
                </div>
            </div>
        </div>

        <!-- ==================== CRISP, COMPACT & READABLE DETAILS MODAL ==================== -->
        <div 
            v-if="selectedInquiry" 
            class="fixed inset-0 z-50 bg-black/60 backdrop-blur-2xs flex items-center justify-center p-3 sm:p-4 overflow-y-auto"
            @click.self="closeDetailsModal"
        >
            <!-- Compact Container without oversized radius -->
            <div class="relative w-full max-w-2xl bg-white rounded-lg shadow-xl border border-gray-300 overflow-hidden my-6 transform transition-all text-xs">
                <!-- Modal Header -->
                <div class="px-5 py-3 border-b border-gray-200 bg-slate-50 flex items-start justify-between gap-3">
                    <div class="space-y-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs font-bold text-gray-800 bg-white px-2 py-0.5 rounded border border-gray-300">
                                {{ selectedInquiry.tracking_token }}
                            </span>
                            <span 
                                class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border"
                                :class="statusClasses[selectedInquiry.status] || 'bg-gray-100 text-gray-700 border-gray-200'"
                            >
                                {{ selectedInquiry.status === 'pending' ? 'Waiting for Review' : (selectedInquiry.status === 'accepted' ? 'Accepted / In Progress' : (selectedInquiry.status === 'resolved' ? 'Resolved' : selectedInquiry.status)) }}
                            </span>
                            <span class="text-[11px] text-gray-400">
                                {{ selectedInquiry.created_at_relative || selectedInquiry.created_at }}
                            </span>
                        </div>
                        <h3 class="text-sm font-bold text-gray-900 truncate">
                            {{ selectedInquiry.subject || 'Public Concern Inquiry' }}
                        </h3>
                    </div>

                    <!-- Close Button -->
                    <button 
                        @click="closeDetailsModal" 
                        class="p-1 text-gray-400 hover:text-gray-700 hover:bg-gray-200 rounded transition"
                        title="Close details"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                    <!-- Handling Officer Info Box (If Accepted or Resolved) -->
                    <div v-if="selectedInquiry.accepted_by_user || selectedInquiry.resolved_by_user" class="rounded p-3 border" :class="selectedInquiry.status === 'resolved' ? 'bg-blue-50 border-blue-200 text-blue-950' : 'bg-emerald-50 border-emerald-200 text-emerald-950'">
                        <div class="flex items-center gap-1.5 font-bold text-xs mb-1">
                            <svg class="w-3.5 h-3.5" :class="selectedInquiry.status === 'resolved' ? 'text-blue-600' : 'text-emerald-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Officer Assignment:</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                            <span v-if="selectedInquiry.resolved_by_user">
                                <strong>Resolved by:</strong> {{ selectedInquiry.resolved_by_user.name }} ({{ selectedInquiry.resolved_by_user.role?.toUpperCase() }})
                                <span v-if="selectedInquiry.resolved_at" class="text-blue-700 font-normal"> on {{ selectedInquiry.resolved_at }}</span>
                            </span>
                            <span v-if="selectedInquiry.accepted_by_user">
                                <strong>Accepted by:</strong> {{ selectedInquiry.accepted_by_user.name }} ({{ selectedInquiry.accepted_by_user.role?.toUpperCase() }})
                                <span v-if="selectedInquiry.accepted_at" class="text-emerald-700 font-normal"> on {{ selectedInquiry.accepted_at }}</span>
                            </span>
                        </div>
                    </div>

                    <!-- Citizen Contact Information Card -->
                    <div class="bg-slate-50 p-3.5 rounded border border-gray-200">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs">
                            <div>
                                <span class="text-gray-500 block text-[10px] uppercase font-semibold">Citizen Name:</span>
                                <span class="font-bold text-gray-900 block mt-0.5">{{ selectedInquiry.fullname }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 block text-[10px] uppercase font-semibold">Phone:</span>
                                <span class="font-bold text-blue-700 font-mono block mt-0.5">{{ selectedInquiry.phone }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 block text-[10px] uppercase font-semibold">Email:</span>
                                <span class="text-gray-700 block mt-0.5 truncate">{{ selectedInquiry.email || 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 block text-[10px] uppercase font-semibold">Location:</span>
                                <span class="font-bold text-red-700 block mt-0.5 truncate">{{ selectedInquiry.location }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Concern Message Content -->
                    <div class="bg-white p-4 rounded border border-gray-200 space-y-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 block">
                            Citizen Concern Statement:
                        </span>
                        <p class="text-xs text-gray-800 whitespace-pre-line leading-relaxed">
                            {{ selectedInquiry.message }}
                        </p>
                    </div>

                    <!-- Attached Site Photos (Up to 5 Photos) -->
                    <div v-if="(selectedInquiry.photo_urls && selectedInquiry.photo_urls.length > 0) || selectedInquiry.photo_url">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500">
                                Attached Photos ({{ (selectedInquiry.photo_urls?.length) || 1 }}):
                            </span>
                            <span class="text-[10px] text-gray-400">Click to view high-res</span>
                        </div>

                        <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
                            <div 
                                v-for="(imgUrl, pIdx) in (selectedInquiry.photo_urls?.length ? selectedInquiry.photo_urls : [selectedInquiry.photo_url])" 
                                :key="pIdx"
                                @click="openLightbox(imgUrl, selectedInquiry.photo_urls?.length ? selectedInquiry.photo_urls : [selectedInquiry.photo_url])"
                                class="relative group rounded overflow-hidden border border-gray-300 bg-black/5 h-20 cursor-pointer"
                            >
                                <img :src="imgUrl" alt="Site Photo" class="w-full h-full object-cover group-hover:scale-105 transition duration-150" />
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-[10px] font-bold transition">
                                    View
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- MEO Internal Remarks / Resolution Action Report -->
                    <div class="rounded p-3 border" :class="selectedInquiry.admin_notes ? 'bg-emerald-50 border-emerald-200' : 'bg-gray-50 border-gray-200'">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-[10px] uppercase tracking-wider block" :class="selectedInquiry.admin_notes ? 'text-emerald-900' : 'text-gray-600'">
                                Engineering Remarks &amp; Action Notes:
                            </span>
                            <button 
                                @click="promptEditNote(selectedInquiry)"
                                class="text-[10px] font-bold text-red-600 hover:text-red-800 underline"
                            >
                                {{ selectedInquiry.admin_notes ? 'Edit Remarks' : '+ Add Remarks' }}
                            </button>
                        </div>
                        <p v-if="selectedInquiry.admin_notes" class="text-xs text-emerald-950 leading-relaxed whitespace-pre-line bg-white p-2.5 rounded border border-emerald-200">
                            {{ selectedInquiry.admin_notes }}
                        </p>
                        <p v-else class="text-[11px] text-gray-400 italic">
                            No internal remarks entered yet.
                        </p>
                    </div>
                </div>

                <!-- Modal Footer Actions -->
                <div class="px-5 py-3 border-t border-gray-200 bg-gray-50 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <!-- Delete Inquiry Button -->
                        <button 
                            @click="deleteInquiry(selectedInquiry); closeDetailsModal();"
                            class="px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:text-red-800 hover:bg-red-50 rounded transition flex items-center gap-1"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Delete
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <!-- Close Button -->
                        <button 
                            @click="closeDetailsModal"
                            class="px-3.5 py-1.5 border border-gray-300 hover:bg-gray-100 text-gray-700 text-xs font-semibold rounded transition"
                        >
                            Close
                        </button>

                        <!-- Accept Concern: ONLY IF PENDING (DISAPPEARS IF ACCEPTED OR RESOLVED) -->
                        <button 
                            v-if="selectedInquiry.status === 'pending'"
                            @click="updateStatus(selectedInquiry, 'accepted')"
                            class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded transition shadow-2xs flex items-center gap-1.5"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Accept Concern
                        </button>

                        <!-- Mark Resolved Button: ONLY IF ACCEPTED (DISAPPEARS IF RESOLVED) -->
                        <button 
                            v-if="selectedInquiry.status === 'accepted'"
                            @click="updateStatus(selectedInquiry, 'resolved')"
                            class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded transition shadow-2xs flex items-center gap-1.5"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Mark Resolved
                        </button>

                        <!-- If Already Resolved: Show Resolved Badge -->
                        <span 
                            v-if="selectedInquiry.status === 'resolved'"
                            class="px-3 py-1.5 bg-blue-50 text-blue-800 border border-blue-300 text-xs font-bold rounded flex items-center gap-1"
                        >
                            <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Resolved
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== LIGHTBOX GALLERY MODAL ==================== -->
        <div 
            v-if="activeLightboxImage" 
            class="fixed inset-0 z-50 bg-black/90 backdrop-blur-md flex items-center justify-center p-4"
            @click.self="closeLightbox"
        >
            <div class="relative max-w-4xl max-h-[90vh] flex flex-col items-center">
                <!-- Close Button -->
                <button 
                    @click="closeLightbox" 
                    class="absolute -top-10 right-0 p-1.5 text-white/80 hover:text-white bg-white/10 rounded transition"
                    title="Close"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>

                <!-- Navigation Previous / Next -->
                <button 
                    v-if="lightboxGallery.length > 1"
                    @click="prevLightboxImage"
                    class="absolute left-2 top-1/2 -translate-y-1/2 p-2 text-white bg-black/60 hover:bg-black/80 rounded transition"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button 
                    v-if="lightboxGallery.length > 1"
                    @click="nextLightboxImage"
                    class="absolute right-2 top-1/2 -translate-y-1/2 p-2 text-white bg-black/60 hover:bg-black/80 rounded transition"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>

                <img :src="activeLightboxImage" alt="Inspection Attachment" class="max-w-full max-h-[78vh] object-contain rounded shadow-2xl" />

                <!-- Bottom Image Bar -->
                <div class="mt-2.5 flex items-center justify-between w-full text-xs text-slate-300">
                    <span>Photo {{ currentLightboxIndex + 1 }} of {{ lightboxGallery.length }}</span>
                    <a :href="activeLightboxImage" target="_blank" class="hover:text-white underline">
                        Open full-resolution image in new tab &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    initialInquiries: {
        type: Array,
        default: () => [],
    },
});

const inquiries = ref([...(props.initialInquiries || [])]);
const isLoading = ref(false);
const isSyncing = ref(false);
let pollTimer = null;
const searchQuery = ref('');
const statusFilter = ref('all');
const locationFilter = ref('all');
const sortBy = ref('newest');

// Pagination state
const currentPage = ref(1);
const pageSize = ref(15);

// Details Modal state
const selectedInquiry = ref(null);

const openDetailsModal = (item) => {
    selectedInquiry.value = item;
};

const closeDetailsModal = () => {
    selectedInquiry.value = null;
};

// Lightbox state
const activeLightboxImage = ref(null);
const lightboxGallery = ref([]);
const currentLightboxIndex = ref(0);

const openLightbox = (url, gallery = []) => {
    lightboxGallery.value = gallery.length ? gallery : [url];
    currentLightboxIndex.value = lightboxGallery.value.indexOf(url) >= 0 ? lightboxGallery.value.indexOf(url) : 0;
    activeLightboxImage.value = url;
};

const closeLightbox = () => {
    activeLightboxImage.value = null;
    lightboxGallery.value = [];
};

const nextLightboxImage = () => {
    if (lightboxGallery.value.length === 0) return;
    currentLightboxIndex.value = (currentLightboxIndex.value + 1) % lightboxGallery.value.length;
    activeLightboxImage.value = lightboxGallery.value[currentLightboxIndex.value];
};

const prevLightboxImage = () => {
    if (lightboxGallery.value.length === 0) return;
    currentLightboxIndex.value = (currentLightboxIndex.value - 1 + lightboxGallery.value.length) % lightboxGallery.value.length;
    activeLightboxImage.value = lightboxGallery.value[currentLightboxIndex.value];
};

watch(() => props.initialInquiries, (newVal) => {
    if (newVal && Array.isArray(newVal)) {
        inquiries.value = [...newVal];
    }
}, { deep: true });

const statusClasses = {
    pending: 'bg-amber-50 text-amber-800 border-amber-300',
    accepted: 'bg-emerald-50 text-emerald-800 border-emerald-300',
    resolved: 'bg-blue-50 text-blue-800 border-blue-300',
    declined: 'bg-slate-100 text-slate-600 border-slate-300',
};

const getInquiriesUrl = () => {
    try {
        if (typeof route === 'function' && route().has && route().has('inquiries.index')) {
            return route('inquiries.index');
        }
    } catch (e) {}
    return '/inquiries';
};

const getStatusUrl = (id) => {
    try {
        if (typeof route === 'function' && route().has && route().has('inquiries.status')) {
            return route('inquiries.status', id);
        }
    } catch (e) {}
    return `/inquiries/${id}/status`;
};

const getDestroyUrl = (id) => {
    try {
        if (typeof route === 'function' && route().has && route().has('inquiries.destroy')) {
            return route('inquiries.destroy', id);
        }
    } catch (e) {}
    return `/inquiries/${id}`;
};

const fetchInquiries = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get(getInquiriesUrl());
        inquiries.value = response.data || [];
        if (selectedInquiry.value) {
            const updated = inquiries.value.find(i => i.id === selectedInquiry.value.id);
            if (updated) selectedInquiry.value = updated;
        }
    } catch (err) {
        console.error('Failed to fetch inquiries:', err);
    } finally {
        isLoading.value = false;
    }
};

const silentSyncInquiries = async () => {
    if (typeof document !== 'undefined' && document.hidden) return;
    if (isLoading.value) return;

    try {
        isSyncing.value = true;
        const response = await axios.get(getInquiriesUrl());
        const data = response.data || [];
        if (Array.isArray(data)) {
            inquiries.value = data;
            if (selectedInquiry.value) {
                const updated = data.find(i => i.id === selectedInquiry.value.id);
                if (updated) selectedInquiry.value = updated;
            }
        }
    } catch (err) {
        // Silent error handling for background polling
    } finally {
        isSyncing.value = false;
    }
};

const countByStatus = (st) => {
    return inquiries.value.filter(i => i.status === st).length;
};

// Active inquiries excludes resolved & declined from All Inquiries view
const activeInquiriesCount = computed(() => {
    return inquiries.value.filter(i => i.status !== 'resolved' && i.status !== 'declined').length;
});

const uniqueLocations = computed(() => {
    const set = new Set();
    inquiries.value.forEach(i => {
        if (i.location) set.add(i.location.trim());
    });
    return Array.from(set).sort();
});

const filteredInquiries = computed(() => {
    let result = inquiries.value.filter(item => {
        // When 'all' is selected: REMOVE RESOLVED & DECLINED (only active inbox)
        const matchesStatus = statusFilter.value === 'all' 
            ? (item.status !== 'resolved' && item.status !== 'declined') 
            : item.status === statusFilter.value;

        const matchesLocation = locationFilter.value === 'all' || item.location === locationFilter.value;

        const q = searchQuery.value.trim().toLowerCase();
        if (!q) return matchesStatus && matchesLocation;

        const matchesQuery = 
            (item.fullname && item.fullname.toLowerCase().includes(q)) ||
            (item.tracking_token && item.tracking_token.toLowerCase().includes(q)) ||
            (item.location && item.location.toLowerCase().includes(q)) ||
            (item.phone && item.phone.toLowerCase().includes(q)) ||
            (item.email && item.email.toLowerCase().includes(q)) ||
            (item.subject && item.subject.toLowerCase().includes(q)) ||
            (item.admin_notes && item.admin_notes.toLowerCase().includes(q)) ||
            (item.accepted_by_user && item.accepted_by_user.name.toLowerCase().includes(q)) ||
            (item.resolved_by_user && item.resolved_by_user.name.toLowerCase().includes(q)) ||
            (item.updated_by_user && item.updated_by_user.name.toLowerCase().includes(q)) ||
            (item.message && item.message.toLowerCase().includes(q));

        return matchesStatus && matchesLocation && matchesQuery;
    });

    // Sorting
    if (sortBy.value === 'oldest') {
        result.sort((a, b) => a.id - b.id);
    } else if (sortBy.value === 'location') {
        result.sort((a, b) => (a.location || '').localeCompare(b.location || ''));
    } else {
        result.sort((a, b) => b.id - a.id);
    }

    return result;
});

const totalPages = computed(() => {
    return Math.ceil(filteredInquiries.value.length / pageSize.value) || 1;
});

const startIndex = computed(() => (currentPage.value - 1) * pageSize.value);
const endIndex = computed(() => Math.min(startIndex.value + pageSize.value, filteredInquiries.value.length));

const paginatedInquiries = computed(() => {
    return filteredInquiries.value.slice(startIndex.value, endIndex.value);
});

const updateStatus = async (inquiry, newStatus, notes = null) => {
    try {
        const payload = { status: newStatus };
        if (notes !== null) payload.admin_notes = notes;

        const response = await axios.patch(getStatusUrl(inquiry.id), payload);

        if (response.data && response.data.success) {
            const updatedInquiry = response.data.inquiry;
            const idx = inquiries.value.findIndex(i => i.id === inquiry.id);
            if (idx !== -1) {
                inquiries.value[idx] = updatedInquiry;
            }
            if (selectedInquiry.value && selectedInquiry.value.id === inquiry.id) {
                selectedInquiry.value = updatedInquiry;
            }
        }
    } catch (err) {
        console.error('Failed to update status:', err);
        alert('Failed to update status.');
    }
};

const promptEditNote = async (inquiry) => {
    const note = prompt('Enter internal remarks / response for this concern:', inquiry.admin_notes || '');
    if (note === null) return;
    await updateStatus(inquiry, inquiry.status, note);
};

const deleteInquiry = async (inquiry) => {
    if (!confirm(`Are you sure you want to delete concern inquiry #${inquiry.tracking_token}?`)) return;

    try {
        await axios.delete(getDestroyUrl(inquiry.id));
        inquiries.value = inquiries.value.filter(i => i.id !== inquiry.id);
        if (selectedInquiry.value && selectedInquiry.value.id === inquiry.id) {
            selectedInquiry.value = null;
        }
    } catch (err) {
        console.error('Failed to delete inquiry:', err);
        alert('Failed to delete inquiry.');
    }
};

onMounted(() => {
    fetchInquiries();
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(silentSyncInquiries, 4000);
});

onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
});
</script>
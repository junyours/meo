<script setup>
import axios from 'axios'
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'

const reminders = ref([])
const activeTab = ref('active')
const showModal = ref(false)
const editingId = ref(null)
const saving = ref(false)
const togglingId = ref(null)
const error = ref('')
const successMessage = ref('')
const searchQuery = ref('')
const selectedCategory = ref('all')
const viewLayout = ref('list') // 'list' | 'grid'

const categories = ['Meeting', 'Training', 'Inspection', 'Deadline', 'Field Visit', 'Other']
const audienceOptions = [
  { value: 'personal', label: 'Personal Only' },
  { value: 'everyone', label: 'Everyone' }
]

const initialFormState = {
  title: '',
  category: 'Meeting',
  description: '',
  starts_at: '',
  ends_at: '',
  location: '',
  audience: 'personal'
}

const form = reactive({ ...initialFormState })
const formErrors = reactive({
  title: '',
  starts_at: '',
  ends_at: ''
})

const sortedReminders = computed(() =>
  [...reminders.value].sort((a, b) => new Date(a.startsAt) - new Date(b.startsAt))
)

const activeReminders = computed(() =>
  sortedReminders.value.filter(item => !item.isDone)
)

const doneReminders = computed(() =>
  [...reminders.value]
    .filter(item => item.isDone)
    .sort((a, b) => new Date(b.completedAt || b.startsAt) - new Date(a.completedAt || a.startsAt))
)

// Counts
const todayRemindersCount = computed(() => {
  const todayStr = new Date().toDateString()
  return activeReminders.value.filter(item => {
    if (!item.startsAt) return false
    return new Date(item.startsAt).toDateString() === todayStr
  }).length
})

const visibleReminders = computed(() => {
  const baseList = activeTab.value === 'done' ? doneReminders.value : activeReminders.value

  return baseList.filter(item => {
    // Search Filter
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase()
      const matchTitle = item.title?.toLowerCase().includes(q)
      const matchLoc = item.location?.toLowerCase().includes(q)
      const matchDesc = item.description?.toLowerCase().includes(q)
      const matchCat = item.category?.toLowerCase().includes(q)
      if (!matchTitle && !matchLoc && !matchDesc && !matchCat) return false
    }

    // Category Filter
    if (selectedCategory.value !== 'all') {
      if (item.category !== selectedCategory.value) return false
    }

    return true
  })
})

const emptyMessage = computed(() => {
  if (searchQuery.value || selectedCategory.value !== 'all') {
    return {
      title: 'No matching schedules found',
      body: 'Try adjusting your search query or category filter.'
    }
  }

  if (activeTab.value === 'done') {
    return {
      title: 'No completed schedules yet',
      body: 'Completed schedules will appear here once marked done.'
    }
  }

  return {
    title: 'No active schedules found',
    body: 'Create a meeting, inspection, deadline, or reminder to stay organized.'
  }
})

// Date helpers
const parseDateParts = (value) => {
  if (!value) return null
  const d = new Date(value)
  if (isNaN(d.getTime())) return null

  return {
    month: d.toLocaleString('en-US', { month: 'short' }).toUpperCase(),
    day: d.getDate(),
    year: d.getFullYear(),
    time: d.toLocaleString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }),
    weekday: d.toLocaleString('en-US', { weekday: 'short' }),
    raw: d
  }
}

const formatDateTimeRange = (startsAt, endsAt) => {
  const start = parseDateParts(startsAt)
  if (!start) return ''

  if (!endsAt) {
    return `${start.weekday}, ${start.month} ${start.day}, ${start.year} • ${start.time}`
  }

  const end = parseDateParts(endsAt)
  if (!end) {
    return `${start.weekday}, ${start.month} ${start.day}, ${start.year} • ${start.time}`
  }

  // Same day
  if (start.raw.toDateString() === end.raw.toDateString()) {
    return `${start.weekday}, ${start.month} ${start.day}, ${start.year} • ${start.time} – ${end.time}`
  }

  // Multi-day
  return `${start.month} ${start.day}, ${start.time} – ${end.month} ${end.day}, ${end.time}`
}

const formatSimpleDate = (value) => {
  if (!value) return ''
  return new Date(value).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit'
  })
}

// Relative timing status
const getTimingStatus = (startsAt) => {
  if (!startsAt) return null
  const now = new Date()
  const target = new Date(startsAt)

  const isToday = target.toDateString() === now.toDateString()
  if (isToday) {
    return { label: 'Today', class: 'bg-amber-50 text-amber-700 border-amber-200' }
  }

  if (target < now) {
    return { label: 'Past Due', class: 'bg-rose-50 text-rose-700 border-rose-200' }
  }

  // Tomorrow
  const tomorrow = new Date(now)
  tomorrow.setDate(tomorrow.getDate() + 1)
  if (target.toDateString() === tomorrow.toDateString()) {
    return { label: 'Tomorrow', class: 'bg-blue-50 text-blue-700 border-blue-200' }
  }

  return null
}

const categoryStyles = (category) => {
  const styles = {
    Meeting: {
      badge: 'bg-sky-50 text-sky-700 border-sky-200',
      dot: 'bg-sky-500',
      accent: 'border-l-sky-500'
    },
    Training: {
      badge: 'bg-purple-50 text-purple-700 border-purple-200',
      dot: 'bg-purple-500',
      accent: 'border-l-purple-500'
    },
    Inspection: {
      badge: 'bg-amber-50 text-amber-700 border-amber-200',
      dot: 'bg-amber-500',
      accent: 'border-l-amber-500'
    },
    Deadline: {
      badge: 'bg-rose-50 text-rose-700 border-rose-200',
      dot: 'bg-rose-500',
      accent: 'border-l-rose-500'
    },
    'Field Visit': {
      badge: 'bg-emerald-50 text-emerald-700 border-emerald-200',
      dot: 'bg-emerald-500',
      accent: 'border-l-emerald-500'
    },
    Other: {
      badge: 'bg-slate-100 text-slate-700 border-slate-200',
      dot: 'bg-slate-400',
      accent: 'border-l-slate-400'
    }
  }

  return styles[category] || styles.Other
}

const validateForm = () => {
  let isValid = true
  formErrors.title = ''
  formErrors.starts_at = ''
  formErrors.ends_at = ''

  if (!form.title.trim()) {
    formErrors.title = 'Title is required'
    isValid = false
  }

  if (!form.starts_at) {
    formErrors.starts_at = 'Start date and time is required'
    isValid = false
  }

  if (form.ends_at && form.starts_at && new Date(form.ends_at) <= new Date(form.starts_at)) {
    formErrors.ends_at = 'End date must be after start date'
    isValid = false
  }

  return isValid
}

const loadReminders = async () => {
  try {
    const response = await axios.get('/reminders')
    reminders.value = response.data
    error.value = ''
  } catch (err) {
    error.value = 'Unable to load schedules. Please try again later.'
    console.error('Failed to load reminders:', err)
  }
}

const resetForm = () => {
  Object.assign(form, initialFormState)
  Object.keys(formErrors).forEach(key => {
    formErrors[key] = ''
  })
}

const openCreateModal = () => {
  editingId.value = null
  resetForm()
  error.value = ''
  successMessage.value = ''
  showModal.value = true
}

const openEditModal = (item) => {
  editingId.value = item.id
  Object.assign(form, {
    title: item.title,
    category: item.category,
    description: item.description || '',
    starts_at: item.startsAt || '',
    ends_at: item.endsAt || '',
    location: item.location || '',
    audience: item.audience
  })
  error.value = ''
  successMessage.value = ''
  showModal.value = true
}

const saveReminder = async () => {
  if (!validateForm()) return

  saving.value = true
  error.value = ''
  successMessage.value = ''

  try {
    const payload = {
      title: form.title.trim(),
      category: form.category,
      description: form.description.trim() || null,
      starts_at: form.starts_at,
      ends_at: form.ends_at || null,
      location: form.location.trim() || null,
      audience: form.audience
    }

    let response
    if (editingId.value) {
      response = await axios.put(`/reminders/${editingId.value}`, payload)
      reminders.value = reminders.value.map(item =>
        item.id === editingId.value ? response.data : item
      )
      successMessage.value = 'Schedule updated successfully'
    } else {
      response = await axios.post('/reminders', payload)
      reminders.value = [...reminders.value, response.data]
      activeTab.value = 'active'
      successMessage.value = 'Schedule created successfully'
    }

    showModal.value = false
    setTimeout(() => {
      successMessage.value = ''
    }, 3000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to save schedule. Please try again.'
    console.error('Failed to save reminder:', err)
  } finally {
    saving.value = false
  }
}

const toggleDone = async (item) => {
  if (!item.canManage) return

  togglingId.value = item.id
  error.value = ''

  try {
    const response = await axios.patch(`/reminders/${item.id}/complete`, {
      is_done: !item.isDone
    })
    reminders.value = reminders.value.map(reminder =>
      reminder.id === item.id ? response.data : reminder
    )
    successMessage.value = response.data.isDone ? 'Schedule marked as completed' : 'Schedule restored to active'
    setTimeout(() => {
      successMessage.value = ''
    }, 3000)
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to update schedule status.'
    console.error('Failed to toggle reminder status:', err)
  } finally {
    togglingId.value = null
  }
}

const deleteReminder = async (item) => {
  const confirmed = confirm(`Are you sure you want to delete "${item.title}"? This action cannot be undone.`)

  if (!confirmed) return

  try {
    await axios.delete(`/reminders/${item.id}`)
    reminders.value = reminders.value.filter(reminder => reminder.id !== item.id)
    successMessage.value = 'Schedule deleted successfully'
    setTimeout(() => {
      successMessage.value = ''
    }, 3000)
  } catch (err) {
    error.value = 'Unable to delete schedule. Please try again.'
    console.error('Failed to delete reminder:', err)
  }
}

const handleKeydown = (event) => {
  if (event.key === 'Escape' && showModal.value) {
    showModal.value = false
  }
}

onMounted(() => {
  loadReminders()
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
  document.body.style.overflow = ''
})

watch(showModal, (isOpen) => {
  if (isOpen) {
    document.addEventListener('keydown', handleKeydown)
    document.body.style.overflow = 'hidden'
    return
  }

  document.removeEventListener('keydown', handleKeydown)
  document.body.style.overflow = ''
})
</script>

<template>
  <div class="space-y-3 font-sans text-slate-800">
    <!-- Header Card (Square Border, Compact & Aesthetic) -->
    <div class="bg-white border border-slate-200 p-4 sm:p-5 shadow-xs">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="h-9 w-9 bg-slate-900 text-white border border-slate-900 flex items-center justify-center shrink-0">
            <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h2 class="text-sm sm:text-base font-bold text-slate-900 tracking-tight">Reminders & Schedules</h2>
              <span class="px-2 py-0.5 text-[10px] font-bold bg-red-50 text-red-700 border border-red-200">
                {{ activeReminders.length }} Active
              </span>
            </div>
            <p class="text-xs text-slate-500 mt-0.5">Track meetings, site inspections, project milestones, and office agendas.</p>
          </div>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-auto">
          <button
            @click="openCreateModal"
            type="button"
            class="inline-flex items-center gap-1.5 bg-red-700 hover:bg-red-800 text-white px-3 py-1.5 text-xs font-semibold shadow-xs transition cursor-pointer"
          >
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
            <span>Add Schedule</span>
          </button>
        </div>
      </div>

      <!-- Quick Metrics Row -->
      <div class="mt-3.5 pt-3 flex flex-wrap items-center gap-4 sm:gap-8 border-t border-slate-200 text-xs">
        <div class="flex items-baseline gap-1.5">
          <span class="text-base font-bold text-slate-900">{{ reminders.length }}</span>
          <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Total</span>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-base font-bold text-red-600">{{ activeReminders.length }}</span>
          <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Active</span>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-base font-bold text-amber-600">{{ todayRemindersCount }}</span>
          <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Today</span>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-base font-bold text-emerald-600">{{ doneReminders.length }}</span>
          <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Completed</span>
        </div>
      </div>
    </div>

    <!-- Alert Notifications -->
    <transition name="fade">
      <div v-if="successMessage" class="flex items-center justify-between border border-emerald-200 bg-emerald-50 px-3.5 py-2 text-xs font-semibold text-emerald-800">
        <div class="flex items-center gap-2">
          <svg class="h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <span>{{ successMessage }}</span>
        </div>
        <button @click="successMessage = ''" class="text-emerald-500 hover:text-emerald-700">
          <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
      </div>
    </transition>

    <transition name="fade">
      <div v-if="error" class="flex items-center justify-between border border-rose-200 bg-rose-50 px-3.5 py-2 text-xs font-semibold text-rose-800">
        <div class="flex items-center gap-2">
          <svg class="h-4 w-4 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>{{ error }}</span>
        </div>
        <button @click="error = ''" class="text-rose-500 hover:text-rose-700">
          <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
      </div>
    </transition>

    <!-- Toolbar: Search, Filters & Tab Control -->
    <div class="bg-white border border-slate-200 p-3 shadow-xs space-y-3">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <!-- Status Tabs: Active vs Completed -->
        <div class="flex items-center gap-1 bg-slate-100 p-1 border border-slate-200">
          <button
            @click="activeTab = 'active'"
            type="button"
            :class="[
              'px-3 py-1 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer',
              activeTab === 'active' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'
            ]"
          >
            <span class="h-1.5 w-1.5 bg-red-600"></span>
            <span>Active</span>
            <span class="text-[10px] px-1.5 py-0.2 bg-slate-100 text-slate-600 font-semibold border border-slate-200">{{ activeReminders.length }}</span>
          </button>
          <button
            @click="activeTab = 'done'"
            type="button"
            :class="[
              'px-3 py-1 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer',
              activeTab === 'done' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'
            ]"
          >
            <span class="h-1.5 w-1.5 bg-emerald-500"></span>
            <span>Completed</span>
            <span class="text-[10px] px-1.5 py-0.2 bg-slate-100 text-slate-600 font-semibold border border-slate-200">{{ doneReminders.length }}</span>
          </button>
        </div>

        <!-- Search Bar & View Mode Toggle -->
        <div class="flex items-center gap-2 flex-1 md:max-w-md justify-end">
          <div class="relative flex-1">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
              <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </span>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search schedules by title, location, or notes..."
              class="w-full pl-8.5 pr-3 py-1.5 bg-slate-50 border border-slate-300 text-xs text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-red-600 focus:ring-1 focus:ring-red-600 outline-none transition"
            />
            <button
              v-if="searchQuery"
              @click="searchQuery = ''"
              class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600"
            >
              <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- Layout Mode Toggle -->
          <div class="flex items-center bg-slate-100 p-0.5 border border-slate-200">
            <button
              @click="viewLayout = 'list'"
              type="button"
              :class="viewLayout === 'list' ? 'bg-white text-red-700 shadow-xs' : 'text-slate-400 hover:text-slate-700'"
              class="p-1.5 transition cursor-pointer"
              title="Compact List"
            >
              <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <button
              @click="viewLayout = 'grid'"
              type="button"
              :class="viewLayout === 'grid' ? 'bg-white text-red-700 shadow-xs' : 'text-slate-400 hover:text-slate-700'"
              class="p-1.5 transition cursor-pointer"
              title="Grid Cards"
            >
              <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Category Filter Pills -->
      <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pt-1">
        <button
          @click="selectedCategory = 'all'"
          type="button"
          :class="[
            'px-2.5 py-1 text-[11px] font-bold transition whitespace-nowrap cursor-pointer border',
            selectedCategory === 'all'
              ? 'bg-slate-900 text-white border-slate-900 shadow-xs'
              : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200'
          ]"
        >
          All Schedules
        </button>
        <button
          v-for="cat in categories"
          :key="cat"
          @click="selectedCategory = cat"
          type="button"
          :class="[
            'inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold transition whitespace-nowrap cursor-pointer border',
            selectedCategory === cat
              ? 'bg-red-50 text-red-700 border-red-300 font-bold shadow-xs'
              : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'
          ]"
        >
          <span class="h-1.5 w-1.5" :class="categoryStyles(cat).dot"></span>
          <span>{{ cat }}</span>
        </button>
      </div>
    </div>

    <!-- ============================================== -->
    <!-- COMPACT LIST VIEW (Sharp, High-Density Rows)   -->
    <!-- ============================================== -->
    <div v-if="viewLayout === 'list' && visibleReminders.length" class="bg-white border border-slate-200 shadow-2xs divide-y divide-slate-100 overflow-hidden">
      <div
        v-for="item in visibleReminders"
        :key="item.id"
        :class="[
          'p-3 sm:p-3.5 hover:bg-slate-50/80 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-l-4 group',
          item.isDone ? 'border-l-emerald-500 bg-slate-50/40 opacity-85' : categoryStyles(item.category).accent
        ]"
      >
        <!-- Left: Checkbox + Date Block + Info -->
        <div class="flex items-start sm:items-center gap-3 min-w-0 flex-1">
          <!-- Checkbox -->
          <button
            v-if="item.canManage"
            type="button"
            @click="toggleDone(item)"
            :disabled="togglingId === item.id"
            :title="item.isDone ? 'Mark as active' : 'Mark as done'"
            :class="[
              'h-5 w-5 shrink-0 flex items-center justify-center border transition cursor-pointer',
              item.isDone
                ? 'border-emerald-600 bg-emerald-600 text-white'
                : 'border-slate-300 bg-white hover:border-emerald-500 text-transparent hover:text-emerald-600'
            ]"
          >
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
            </svg>
          </button>
          <div v-else class="h-5 w-5 shrink-0"></div>

          <!-- Date Square -->
          <div v-if="parseDateParts(item.startsAt)" class="hidden sm:flex flex-col items-center justify-center h-10 w-10 shrink-0 bg-slate-50 border border-slate-200 text-center">
            <span class="text-[8px] font-bold uppercase text-red-600 leading-none">{{ parseDateParts(item.startsAt).month }}</span>
            <span class="text-xs font-extrabold text-slate-800 leading-tight">{{ parseDateParts(item.startsAt).day }}</span>
          </div>

          <!-- Info Details -->
          <div class="min-w-0 flex-1 space-y-1">
            <div class="flex items-center gap-2 flex-wrap">
              <!-- Category Badge -->
              <span :class="['inline-flex items-center gap-1 px-1.5 py-0.2 text-[10px] font-bold uppercase tracking-wider border', categoryStyles(item.category).badge]">
                <span class="h-1 w-1" :class="categoryStyles(item.category).dot"></span>
                {{ item.category }}
              </span>

              <!-- Timing Status -->
              <span
                v-if="!item.isDone && getTimingStatus(item.startsAt)"
                :class="['px-1.5 py-0.2 text-[10px] font-bold uppercase tracking-wider border', getTimingStatus(item.startsAt).class]"
              >
                {{ getTimingStatus(item.startsAt).label }}
              </span>

              <!-- Audience -->
              <span class="px-1.5 py-0.2 text-[10px] font-medium bg-slate-100 text-slate-600 border border-slate-200">
                {{ item.audience === 'everyone' ? 'Everyone' : 'Personal' }}
              </span>

              <!-- Completed Tag -->
              <span v-if="item.isDone" class="px-1.5 py-0.2 text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                ✓ Completed
              </span>

              <span class="text-[11px] text-slate-500 font-medium flex items-center gap-1">
                <svg class="h-3 w-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ formatDateTimeRange(item.startsAt, item.endsAt) }}
              </span>
            </div>

            <!-- Title -->
            <h4 :class="['text-xs sm:text-sm font-bold truncate transition', item.isDone ? 'text-slate-400 line-through' : 'text-slate-900 group-hover:text-red-700']">
              {{ item.title }}
            </h4>

            <!-- Location / Agenda Snippet -->
            <div class="flex items-center gap-3 text-xs text-slate-500 flex-wrap">
              <span v-if="item.location" class="flex items-center gap-1 text-slate-600 truncate max-w-xs">
                <svg class="h-3 w-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                {{ item.location }}
              </span>
              <span v-if="item.description" class="text-slate-400 truncate max-w-sm">
                • {{ item.description }}
              </span>
            </div>
          </div>
        </div>

        <!-- Right: Actions -->
        <div v-if="item.canManage" class="flex items-center gap-1 self-end sm:self-center shrink-0">
          <button
            type="button"
            @click="openEditModal(item)"
            class="p-1 text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition cursor-pointer"
            title="Edit Schedule"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
          </button>

          <button
            type="button"
            @click="deleteReminder(item)"
            class="p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer"
            title="Delete Schedule"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- ============================================== -->
    <!-- GRID VIEW (Sharp Cards)                        -->
    <!-- ============================================== -->
    <div v-else-if="viewLayout === 'grid' && visibleReminders.length" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
      <div
        v-for="item in visibleReminders"
        :key="item.id"
        :class="[
          'group bg-white border p-4 shadow-2xs hover:shadow-xs transition-all flex flex-col justify-between relative border-l-4',
          item.isDone ? 'border-slate-200 border-l-emerald-500 bg-slate-50/40 opacity-85' : `border-slate-200 ${categoryStyles(item.category).accent}`
        ]"
      >
        <div>
          <!-- Header info -->
          <div class="flex items-center justify-between gap-2 mb-2">
            <span :class="['inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider border', categoryStyles(item.category).badge]">
              <span class="h-1.5 w-1.5" :class="categoryStyles(item.category).dot"></span>
              {{ item.category }}
            </span>

            <div class="flex items-center gap-1">
              <span
                v-if="!item.isDone && getTimingStatus(item.startsAt)"
                :class="['px-1.5 py-0.2 text-[10px] font-bold uppercase tracking-wider border', getTimingStatus(item.startsAt).class]"
              >
                {{ getTimingStatus(item.startsAt).label }}
              </span>

              <span class="px-1.5 py-0.2 text-[10px] font-medium bg-slate-100 text-slate-600 border border-slate-200">
                {{ item.audience === 'everyone' ? 'Everyone' : 'Personal' }}
              </span>
            </div>
          </div>

          <!-- Title -->
          <h3 :class="['text-xs sm:text-sm font-bold transition line-clamp-1', item.isDone ? 'text-slate-400 line-through' : 'text-slate-900 group-hover:text-red-700']">
            {{ item.title }}
          </h3>

          <!-- Date/Time Range -->
          <p class="text-xs text-slate-600 font-medium mt-1 flex items-center gap-1">
            <svg class="h-3 w-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ formatDateTimeRange(item.startsAt, item.endsAt) }}
          </p>

          <!-- Location -->
          <p v-if="item.location" class="text-xs text-slate-500 mt-1 flex items-center gap-1 truncate">
            <svg class="h-3 w-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
            {{ item.location }}
          </p>

          <!-- Description -->
          <p v-if="item.description" class="mt-2 text-xs text-slate-600 leading-relaxed line-clamp-2 bg-slate-50 p-2 border border-slate-100">
            {{ item.description }}
          </p>
        </div>

        <!-- Footer Actions -->
        <div class="mt-3 pt-2 border-t border-slate-100 flex items-center justify-between">
          <button
            v-if="item.canManage"
            type="button"
            @click="toggleDone(item)"
            :disabled="togglingId === item.id"
            :class="[
              'inline-flex items-center gap-1 text-[11px] font-semibold transition cursor-pointer',
              item.isDone ? 'text-emerald-700 hover:text-emerald-800' : 'text-slate-600 hover:text-emerald-600'
            ]"
          >
            <span class="h-3.5 w-3.5 border flex items-center justify-center" :class="item.isDone ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-300 bg-white'">
              <svg v-if="item.isDone" class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </span>
            <span>{{ item.isDone ? 'Completed' : 'Mark Done' }}</span>
          </button>
          <span v-else></span>

          <div v-if="item.canManage" class="flex items-center gap-1">
            <button
              type="button"
              @click="openEditModal(item)"
              class="p-1 text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition cursor-pointer"
              title="Edit"
            >
              <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </button>
            <button
              type="button"
              @click="deleteReminder(item)"
              class="p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer"
              title="Delete"
            >
              <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ============================================== -->
    <!-- EMPTY STATE                                    -->
    <!-- ============================================== -->
    <div v-else class="bg-white border border-dashed border-slate-300 p-8 text-center space-y-3">
      <div class="h-9 w-9 mx-auto bg-slate-100 text-slate-400 flex items-center justify-center border border-slate-200">
        <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
      </div>
      <div>
        <h3 class="text-xs sm:text-sm font-bold text-slate-800">{{ emptyMessage.title }}</h3>
        <p class="text-xs text-slate-500 mt-0.5">{{ emptyMessage.body }}</p>
      </div>
      <button
        v-if="activeTab === 'active' && !searchQuery && selectedCategory === 'all'"
        @click="openCreateModal"
        type="button"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-700 hover:bg-red-800 text-white text-xs font-semibold shadow-xs transition cursor-pointer"
      >
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        <span>Add Schedule</span>
      </button>
      <button
        v-else-if="searchQuery || selectedCategory !== 'all'"
        @click="searchQuery = ''; selectedCategory = 'all'"
        type="button"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 transition cursor-pointer"
      >
        Clear Filters
      </button>
    </div>

    <!-- ============================================== -->
    <!-- MODAL: CREATE / EDIT SCHEDULE FORM             -->
    <!-- ============================================== -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-3 sm:p-4 backdrop-blur-xs animate-in fade-in duration-150"
        @click.self="showModal = false"
        role="dialog"
        aria-modal="true"
      >
        <div class="my-auto flex w-full max-w-lg flex-col overflow-hidden bg-white shadow-2xl border border-slate-300 animate-in zoom-in-95 duration-150">
          
          <!-- Modal Header -->
          <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-slate-50 px-5 py-3.5">
            <div>
              <h3 class="text-sm sm:text-base font-bold text-slate-900">
                {{ editingId ? 'Edit Schedule' : 'Create New Schedule' }}
              </h3>
              <p class="text-xs text-slate-500">Fill in the schedule and calendar details below.</p>
            </div>
            <button
              type="button"
              @click="showModal = false"
              class="p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition"
            >
              <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Form -->
          <form @submit.prevent="saveReminder" class="space-y-3.5 p-5 overflow-y-auto max-h-[calc(85vh-120px)]">
            
            <!-- Title -->
            <div class="space-y-1">
              <label for="title" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                Title <span class="text-red-500">*</span>
              </label>
              <input
                id="title"
                v-model="form.title"
                type="text"
                required
                placeholder="e.g. Weekly Infrastructure Progress Meeting"
                class="w-full border border-slate-300 px-3 py-2 text-xs sm:text-sm text-slate-900 shadow-2xs outline-none focus:border-red-600 focus:ring-1 focus:ring-red-600 transition"
              />
              <p v-if="formErrors.title" class="text-[11px] text-red-600 font-medium">{{ formErrors.title }}</p>
            </div>

            <!-- Category & Visibility -->
            <div class="grid gap-3 sm:grid-cols-2">
              <div class="space-y-1">
                <label for="category" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Category</label>
                <div class="relative">
                  <select
                    id="category"
                    v-model="form.category"
                    class="w-full border border-slate-300 px-3 py-2 text-xs sm:text-sm text-slate-900 shadow-2xs outline-none focus:border-red-600 focus:ring-1 focus:ring-red-600 transition appearance-none bg-white cursor-pointer"
                  >
                    <option v-for="category in categories" :key="category" :value="category">
                      {{ category }}
                    </option>
                  </select>
                  <svg class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </div>
              </div>

              <div class="space-y-1">
                <label for="audience" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Visibility</label>
                <div class="relative">
                  <select
                    id="audience"
                    v-model="form.audience"
                    class="w-full border border-slate-300 px-3 py-2 text-xs sm:text-sm text-slate-900 shadow-2xs outline-none focus:border-red-600 focus:ring-1 focus:ring-red-600 transition appearance-none bg-white cursor-pointer"
                  >
                    <option v-for="option in audienceOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </option>
                  </select>
                  <svg class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </div>
              </div>
            </div>

            <!-- Date & Times -->
            <div class="grid gap-3 sm:grid-cols-2">
              <div class="space-y-1">
                <label for="starts_at" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                  Starts At <span class="text-red-500">*</span>
                </label>
                <input
                  id="starts_at"
                  v-model="form.starts_at"
                  type="datetime-local"
                  required
                  class="w-full border border-slate-300 px-3 py-2 text-xs sm:text-sm text-slate-900 shadow-2xs outline-none focus:border-red-600 focus:ring-1 focus:ring-red-600 transition"
                />
                <p v-if="formErrors.starts_at" class="text-[11px] text-red-600 font-medium">{{ formErrors.starts_at }}</p>
              </div>

              <div class="space-y-1">
                <label for="ends_at" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                  Ends At <span class="text-slate-400 font-normal lowercase">(optional)</span>
                </label>
                <input
                  id="ends_at"
                  v-model="form.ends_at"
                  type="datetime-local"
                  class="w-full border border-slate-300 px-3 py-2 text-xs sm:text-sm text-slate-900 shadow-2xs outline-none focus:border-red-600 focus:ring-1 focus:ring-red-600 transition"
                />
                <p v-if="formErrors.ends_at" class="text-[11px] text-red-600 font-medium">{{ formErrors.ends_at }}</p>
              </div>
            </div>

            <!-- Location -->
            <div class="space-y-1">
              <label for="location" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                Location <span class="text-slate-400 font-normal lowercase">(optional)</span>
              </label>
              <input
                id="location"
                v-model="form.location"
                type="text"
                placeholder="e.g., Mayor's Conference Room / Barangay Site"
                class="w-full border border-slate-300 px-3 py-2 text-xs sm:text-sm text-slate-900 shadow-2xs outline-none focus:border-red-600 focus:ring-1 focus:ring-red-600 transition"
              />
            </div>

            <!-- Description -->
            <div class="space-y-1">
              <label for="description" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                Agenda / Notes <span class="text-slate-400 font-normal lowercase">(optional)</span>
              </label>
              <textarea
                id="description"
                v-model="form.description"
                rows="3"
                placeholder="Add meeting agenda, attendees, materials needed, or notes..."
                class="w-full resize-none border border-slate-300 p-2.5 text-xs sm:text-sm text-slate-900 shadow-2xs outline-none focus:border-red-600 focus:ring-1 focus:ring-red-600 leading-relaxed transition"
              ></textarea>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-3">
              <button
                type="button"
                @click="showModal = false"
                class="px-3.5 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 transition cursor-pointer"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="saving"
                class="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-bold text-white bg-red-700 hover:bg-red-800 shadow-xs transition disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
              >
                <svg
                  v-if="saving"
                  class="h-3.5 w-3.5 animate-spin"
                  fill="none"
                  viewBox="0 0 24 24"
                >
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ saving ? 'Saving...' : editingId ? 'Update Schedule' : 'Create Schedule' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.line-clamp-1 {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>

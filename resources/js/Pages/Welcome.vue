<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';

const pageProps = defineProps({
    canLogin: { type: Boolean },
    canRegister: { type: Boolean },
    welcomeContent: { type: Object, default: null },
    projects: { type: Array, default: () => [] },
    announcements: { type: Array, default: () => [] },
});

const projects = ref(pageProps.projects);
const announcements = ref(pageProps.announcements);

// Hero content with fallbacks
const heroTitle = computed(() => pageProps.welcomeContent?.hero_title || 'Public Infrastructure\nTransparency Portal');
const heroDescription = computed(() => pageProps.welcomeContent?.hero_description || 'Track all municipal engineering projects in real-time. Every peso. Every milestone. Every deadline — open to all citizens.');
const slideshowImages = computed(() => pageProps.welcomeContent?.slideshow_images || []);
const achievementImages = computed(() => pageProps.welcomeContent?.achievement_images || []);
const activeAchievementCategory = ref('all');
const activeAchievementYear = ref('all');
const activeAchievementModal = ref(null);
const galleryViewMode = ref('mosaic'); // 'mosaic' | 'grid'

const achievementCategoryTabs = [
    { key: 'all', label: 'All Exhibits' },
    { key: 'completed_project', label: 'Completed Works' },
    { key: 'turnover', label: 'Turnovers & Inaugurations' },
    { key: 'achievement', label: 'Awards & Honors' },
    { key: 'milestone', label: 'Key Milestones' },
];

const availableAchievementYears = computed(() => {
    const years = new Set();
    (achievementImages.value || []).forEach(img => {
        if (img.year) {
            years.add(String(img.year).trim());
        }
    });
    return Array.from(years).sort((a, b) => b.localeCompare(a, undefined, { numeric: true }));
});

const getCategoryCount = (catKey) => {
    if (!achievementImages.value) return 0;
    const base = activeAchievementYear.value === 'all'
        ? achievementImages.value
        : achievementImages.value.filter(img => String(img.year || '').trim() === String(activeAchievementYear.value).trim());
    if (catKey === 'all') return base.length;
    return base.filter(img => img.category === catKey).length;
};

const filteredAchievementsList = computed(() => {
    if (!achievementImages.value) return [];
    return achievementImages.value.filter(img => {
        const matchesCat = activeAchievementCategory.value === 'all' || img.category === activeAchievementCategory.value;
        const matchesYear = activeAchievementYear.value === 'all' || String(img.year || '').trim() === String(activeAchievementYear.value).trim();
        return matchesCat && matchesYear;
    });
});

// Achievement Gallery Pagination (Limit to 10 per page)
const currentAchPage = ref(1);
const achPerPage = ref(10);

const totalAchPages = computed(() => {
    return Math.ceil(filteredAchievementsList.value.length / achPerPage.value) || 1;
});

const paginatedAchievementsList = computed(() => {
    const start = (currentAchPage.value - 1) * achPerPage.value;
    return filteredAchievementsList.value.slice(start, start + achPerPage.value);
});

const visibleAchPages = computed(() => {
    const total = totalAchPages.value;
    const current = currentAchPage.value;
    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }
    if (current <= 4) {
        return [1, 2, 3, 4, 5, '...', total];
    }
    if (current >= total - 3) {
        return [1, '...', total - 4, total - 3, total - 2, total - 1, total];
    }
    return [1, '...', current - 1, current, current + 1, '...', total];
});

const goToAchPage = (page) => {
    if (typeof page === 'number' && page >= 1 && page <= totalAchPages.value) {
        currentAchPage.value = page;
        const el = document.getElementById('achievements');
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
};

const nextAchPage = () => {
    if (currentAchPage.value < totalAchPages.value) {
        goToAchPage(currentAchPage.value + 1);
    }
};

const prevAchPage = () => {
    if (currentAchPage.value > 1) {
        goToAchPage(currentAchPage.value - 1);
    }
};

watch([activeAchievementCategory, activeAchievementYear], () => {
    currentAchPage.value = 1;
});

const currentAchievementIndex = computed(() => {
    if (!activeAchievementModal.value) return -1;
    return filteredAchievementsList.value.findIndex(item => item.id === activeAchievementModal.value.id);
});

const openAchievementModal = (item) => {
    activeAchievementModal.value = item;
};

const nextAchievementModal = () => {
    const list = filteredAchievementsList.value;
    if (!list || list.length === 0) return;
    const currentIdx = currentAchievementIndex.value;
    const nextIdx = (currentIdx + 1) % list.length;
    activeAchievementModal.value = list[nextIdx];
};

const prevAchievementModal = () => {
    const list = filteredAchievementsList.value;
    if (!list || list.length === 0) return;
    const currentIdx = currentAchievementIndex.value;
    const prevIdx = (currentIdx - 1 + list.length) % list.length;
    activeAchievementModal.value = list[prevIdx];
};

const handleAchievementKeydown = (e) => {
    if (!activeAchievementModal.value) return;
    if (e.key === 'ArrowRight') {
        nextAchievementModal();
    } else if (e.key === 'ArrowLeft') {
        prevAchievementModal();
    } else if (e.key === 'Escape') {
        activeAchievementModal.value = null;
    }
};

// Slideshow state
const currentSlide = ref(0);

const nextSlide = () => {
    if (slideshowImages.value.length > 0) {
        currentSlide.value = (currentSlide.value + 1) % slideshowImages.value.length;
    }
};

const prevSlide = () => {
    if (slideshowImages.value.length > 0) {
        currentSlide.value = (currentSlide.value - 1 + slideshowImages.value.length) % slideshowImages.value.length;
    }
};

// Auto-advance slideshow & keyboard listeners
let slideInterval;
onMounted(() => {
    if (slideshowImages.value.length > 1) {
        slideInterval = setInterval(nextSlide, 5000);
    }
    window.addEventListener('keydown', handleAchievementKeydown);
});

onUnmounted(() => {
    if (slideInterval) {
        clearInterval(slideInterval);
    }
    window.removeEventListener('keydown', handleAchievementKeydown);
});

const searchQuery = ref('');
const sortBy = ref('none');
const sortDir = ref('asc');
const activeFilter = ref('all');
const selectedFundCategory = ref('all');
const selectedYear = ref('all');
const expandedRow = ref(null);
const selectedProject = ref(null);

const filters = ['all', 'ongoing', 'completed', 'delayed', 'not_started', 'suspended'];

const statusConfig = {
    ongoing: { label: 'Ongoing', color: '#b91c1c', bg: '#fef2f2', border: '#fecaca', dot: '#ef4444' },
    completed: { label: 'Completed', color: '#15803d', bg: '#f0fdf4', border: '#bbf7d0', dot: '#22c55e' },
    delayed: { label: 'Delayed', color: '#b45309', bg: '#fffbeb', border: '#fde68a', dot: '#f59e0b' },
    not_started: { label: 'Not Started', color: '#475569', bg: '#f1f5f9', border: '#cbd5e1', dot: '#94a3b8' },
    suspended: { label: 'Suspended', color: '#7c3aed', bg: '#f5f3ff', border: '#ddd6fe', dot: '#8b5cf6' },
};

const categoryConfig = {
    advisory: { label: 'Advisory', color: '#b91c1c', bg: '#fef2f2' },
    update: { label: 'Update', color: '#2563eb', bg: '#eff6ff' },
    notice: { label: 'Notice', color: '#b45309', bg: '#fffbeb' },
    operations: { label: 'Operations', color: '#2563eb', bg: '#eff6ff' },
    schedule: { label: 'Schedule', color: '#7c3aed', bg: '#f5f3ff' },
    announcement: { label: 'Announcement', color: '#0f766e', bg: '#f0fdfa' },
    reminder: { label: 'Reminder', color: '#b45309', bg: '#fffbeb' },
};

const milestoneList = [
    { key: 'hazardAssessment', name: 'Hazard Assessment & Site Feasibility', office: 'MEO / MDRRMO', desc: 'Geotechnical, flood, and safety hazard site evaluation.' },
    { key: 'powDed', name: 'Program of Works (POW) & DED', office: 'MEO', desc: 'Detailed engineering designs, bill of materials, and cost estimates.' },
    { key: 'supplementalBudget', name: 'Budget Appropriation / Supplemental', office: 'End User', desc: 'Municipal budget ordinance and funding allocation certification.' },
    { key: 'alobs', name: 'Allotment & Obligation Slip (ALOBS)', office: 'End User', desc: 'Local accounting allotment obligation and funds earmarked.' },
    { key: 'eccCnc', name: 'Environmental Clearance (ECC / CNC)', office: 'MENRO', desc: 'DENR environmental compliance certificate or CNC non-coverage.' },
    { key: 'technicalDocsToBac', name: 'BAC Technical Endorsement', office: 'MEO', desc: 'Submission of complete technical bidding documents to BAC.' },
    { key: 'bidding', name: 'Competitive Public Bidding', office: 'BAC', desc: 'Competitive procurement bidding under Republic Act 9184.' },
    { key: 'contractNtp', name: 'Contract Award & Notice to Proceed', office: 'GSO', desc: 'Final contract execution, performance bond, and issuance of NTP.' },
];

const milestoneStatusMap = {
    green: { label: 'Done', color: '#166534', bg: '#dcfce7', border: '#86efac', icon: 'check', desc: 'Completed & Verified' },
    yellow: { label: 'In Progress', color: '#854d0e', bg: '#fef9c3', border: '#fde047', icon: 'clock', desc: 'Ongoing Activity' },
    red: { label: 'Pending', color: '#991b1b', bg: '#fee2e2', border: '#fca5a5', icon: 'alert', desc: 'Pending / Not Started' },
    na: { label: 'N/A', color: '#475569', bg: '#f1f5f9', border: '#e2e8f0', icon: 'minus', desc: 'Not Applicable' },
};

// Transparency KPIs
const totalProjectsCount = computed(() => projects.value.length);
const totalInvestmentSum = computed(() => {
    return projects.value.reduce((acc, p) => acc + (Number(p.budget) || 0), 0);
});
const ongoingProjectsCount = computed(() => projects.value.filter(p => p.status === 'ongoing').length);
const completedProjectsCount = computed(() => projects.value.filter(p => p.status === 'completed').length);
const delayedProjectsCount = computed(() => projects.value.filter(p => p.status === 'delayed').length);
const avgAccomplishment = computed(() => {
    if (!projects.value.length) return 0;
    const total = projects.value.reduce((acc, p) => acc + (Number(p.progress) || 0), 0);
    return Math.round(total / projects.value.length);
});

// Dynamic filter options
const availableYears = computed(() => {
    const years = new Set();
    projects.value.forEach(p => {
        if (p.year) years.add(p.year);
    });
    return Array.from(years).sort((a, b) => b - a);
});

const availableFundCategories = computed(() => {
    const cats = new Set();
    projects.value.forEach(p => {
        if (p.fundCategory && p.fundCategory !== 'N/A') cats.add(p.fundCategory);
    });
    return Array.from(cats).sort();
});

const hasActiveFilters = computed(() => {
    return searchQuery.value.trim() !== '' || activeFilter.value !== 'all' || selectedFundCategory.value !== 'all' || selectedYear.value !== 'all' || sortBy.value !== 'none';
});

const resetFilters = () => {
    searchQuery.value = '';
    activeFilter.value = 'all';
    selectedFundCategory.value = 'all';
    selectedYear.value = 'all';
    sortBy.value = 'none';
    sortDir.value = 'asc';
};

const displayedProjects = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    const filtered = projects.value.filter((project) => {
        const matchesStatusFilter = activeFilter.value === 'all' || project.status === activeFilter.value;
        const matchesFund = selectedFundCategory.value === 'all' || project.fundCategory === selectedFundCategory.value;
        const matchesYear = selectedYear.value === 'all' || String(project.year) === String(selectedYear.value);
        
        const searchable = [
            project.title,
            project.location,
            project.contractor,
            project.status,
            project.sourceOfFund,
            project.fundCategory,
            project.year ? `Year ${project.year}` : '',
            project.description,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();
            
        return matchesStatusFilter && matchesFund && matchesYear && (!query || searchable.includes(query));
    });

    if (sortBy.value === 'none') return filtered;

    return [...filtered].sort((a, b) => {
        let left = a[sortBy.value];
        let right = b[sortBy.value];
        if (sortBy.value === 'endDate') {
            left = Date.parse(left) || 0;
            right = Date.parse(right) || 0;
        } else if (sortBy.value === 'progress' || sortBy.value === 'budget') {
            left = Number(left) || 0;
            right = Number(right) || 0;
        } else {
            left = String(left ?? '').toLowerCase();
            right = String(right ?? '').toLowerCase();
        }
        const result = left < right ? -1 : left > right ? 1 : 0;
        return sortDir.value === 'asc' ? result : -result;
    });
});

const filteredTotalBudget = computed(() => {
    return displayedProjects.value.reduce((acc, p) => acc + (Number(p.budget) || 0), 0);
});

// Projects Directory Pagination (Limit to 10 per page)
const currentPage = ref(1);
const perPage = ref(10);

const totalPages = computed(() => {
    return Math.ceil(displayedProjects.value.length / perPage.value) || 1;
});

const paginatedProjects = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    return displayedProjects.value.slice(start, start + perPage.value);
});

const visiblePages = computed(() => {
    const total = totalPages.value;
    const current = currentPage.value;
    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }
    if (current <= 4) {
        return [1, 2, 3, 4, 5, '...', total];
    }
    if (current >= total - 3) {
        return [1, '...', total - 4, total - 3, total - 2, total - 1, total];
    }
    return [1, '...', current - 1, current, current + 1, '...', total];
});

const goToPage = (page) => {
    if (typeof page === 'number' && page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
        const el = document.getElementById('projects');
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
};

const nextPage = () => {
    if (currentPage.value < totalPages.value) {
        goToPage(currentPage.value + 1);
    }
};

const prevPage = () => {
    if (currentPage.value > 1) {
        goToPage(currentPage.value - 1);
    }
};

watch([searchQuery, activeFilter, selectedFundCategory, selectedYear, sortBy, sortDir], () => {
    currentPage.value = 1;
});

const toggleRow = (id) => {
    expandedRow.value = expandedRow.value === id ? null : id;
};

const formatBudget = (value) => {
    if (value === null || value === undefined || value === '') return 'N/A';
    const number = Number(value);
    return Number.isFinite(number)
        ? new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(number)
        : String(value);
};

const formatCompactCurrency = (value) => {
    const num = Number(value) || 0;
    if (num >= 1_000_000_000) {
        return '₱' + (num / 1_000_000_000).toFixed(2) + ' B';
    }
    if (num >= 1_000_000) {
        return '₱' + (num / 1_000_000).toFixed(2) + ' M';
    }
    if (num >= 1_000) {
        return '₱' + (num / 1_000).toFixed(1) + ' K';
    }
    return formatBudget(num);
};

const printProjectTransparency = () => {
    window.print();
};

const formatAnnouncementDate = (value) => {
    if (!value) return '';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};

// Add mobile menu state
const isMobileMenuOpen = ref(false);
const isMobile = ref(false);

// Check if mobile on mount and resize
// Scroll & Reveal States
const isNavScrolled = ref(false);
const showBackToTop = ref(false);
const scrollProgress = ref(0);
const revealedElements = ref(new Set());

const checkMobile = () => {
    isMobile.value = window.innerWidth <= 768;
};

const handleScroll = () => {
    const scrollTop = window.scrollY || document.documentElement.scrollTop;
    const scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
    scrollProgress.value = scrollHeight > 0 ? (scrollTop / scrollHeight) * 100 : 0;
    showBackToTop.value = scrollTop > 350;
    isNavScrolled.value = scrollTop > 20;
};

// Smooth scroll to section with fixed header offset compensation
const scrollToSection = (elementId) => {
    const element = document.getElementById(elementId);
    if (element) {
        const topOffset = 100;
        const elementPosition = element.getBoundingClientRect().top + window.pageYOffset;
        window.scrollTo({ 
            top: elementPosition - topOffset,
            behavior: 'smooth'
        });
    }
};

const scrollToTop = () => {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
};

const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
        if (entry.isIntersecting) {
            revealedElements.value.add(entry.target.id || entry.target.getAttribute('data-reveal'));
            entry.target.classList.add('revealed');
            revealObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

const observeRevealElements = () => {
    document.querySelectorAll('[data-reveal]').forEach((el) => {
        revealObserver.observe(el);
    });
};

onMounted(() => {
    checkMobile();
    window.addEventListener('resize', checkMobile);
    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();
    
    // Add smooth scrolling to all anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            scrollToSection(targetId);
        });
    });

    observeRevealElements();
});

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile);
    window.removeEventListener('scroll', handleScroll);
    revealObserver.disconnect();
});

// Public data comes from the server; never display fallback/demo records.
</script>

<template>
    <Head title="Municipal Engineering Office" />

    <div class="meo-root">
        <!-- TOP BAR - Now sticky and always visible -->
        <div class="top-bar">
            <span class="top-bar-mobile">LGU OPOL - MUNICIPAL ENGINEERING OFFICE</span>
            <span>Republic of the Philippines — Local Government Unit of Opol</span>
        </div>

        <!-- NAV -->
        <nav class="meo-nav" :class="{ 'scrolled-nav': isNavScrolled }">
            <div class="nav-inner">
                <div class="nav-brand">
                    <div class="nav-logo">
                        <img src="/image/meo_logo2.png" alt="MEO logo" />
                    </div>
                    <div>
                        <div class="nav-title">Municipal Engineering Office</div>
                        <div class="nav-sub">Infrastructure &amp; Public Works</div>
                    </div>
                </div>
                
                <!-- Desktop Navigation -->
                <div class="nav-links">
                    <a href="#announcements" class="nav-link" @click.prevent="scrollToSection('announcements')">Bulletin</a>
                    <a href="#projects" class="nav-link" @click.prevent="scrollToSection('projects')">Projects</a>
                    <a v-if="achievementImages && achievementImages.length > 0" href="#achievements" class="nav-link" @click.prevent="scrollToSection('achievements')">Achievements</a>
                    <Link :href="route('ask.meo')" class="nav-link">Ask MEO</Link>
                    <template v-if="canLogin">
                        <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="nav-link">Dashboard</Link>
                        <Link v-else :href="route('login')" class="nav-link">Login</Link>
                    </template>
                </div>

                <!-- Mobile Menu Button -->
                <button 
                    class="mobile-menu-btn" 
                    @click="isMobileMenuOpen = !isMobileMenuOpen"
                    aria-label="Toggle menu"
                    :aria-expanded="isMobileMenuOpen"
                >
                    <span :class="{ open: isMobileMenuOpen }"></span>
                    <span :class="{ open: isMobileMenuOpen }"></span>
                    <span :class="{ open: isMobileMenuOpen }"></span>
                </button>
            </div>

            <!-- Scroll Progress Indicator Bar -->
            <div class="scroll-progress-track">
                <div class="scroll-progress-bar" :style="{ width: `${scrollProgress}%` }"></div>
            </div>

            <!-- Mobile Navigation -->
            <div class="mobile-nav" :class="{ open: isMobileMenuOpen }">
                <a href="#announcements" class="mobile-nav-link" @click.prevent="scrollToSection('announcements'); isMobileMenuOpen = false">Bulletin</a>
                <a href="#projects" class="mobile-nav-link" @click.prevent="scrollToSection('projects'); isMobileMenuOpen = false">Projects</a>
                <a v-if="achievementImages && achievementImages.length > 0" href="#achievements" class="mobile-nav-link" @click.prevent="scrollToSection('achievements'); isMobileMenuOpen = false">Achievements</a>
                <Link :href="route('ask.meo')" class="mobile-nav-link" @click="isMobileMenuOpen = false">Ask MEO</Link>
                <template v-if="canLogin">
                    <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="mobile-nav-link" @click="isMobileMenuOpen = false">Dashboard</Link>
                    <Link v-else :href="route('login')" class="mobile-nav-link" @click="isMobileMenuOpen = false">Login</Link>
                </template>
            </div>
        </nav>

        <!-- HERO -->
        <section class="meo-hero" data-reveal>
            <!-- Background Slideshow -->
            <div class="hero-background-slideshow" v-if="slideshowImages.length > 0">
                <div 
                    v-for="(image, index) in slideshowImages" 
                    :key="image.id" 
                    class="hero-bg-slide"
                    :class="{ active: index === currentSlide }"
                >
                    <img :src="image.url" :alt="`Slide ${index + 1}`">
                </div>
                <div class="hero-overlay"></div>
            </div>
            <div class="hero-background-fallback" v-else>
                <img :src="heroImage" alt="Infrastructure Projects">
                <div class="hero-overlay"></div>
            </div>

            <div class="hero-inner">
                <div class="hero-content">
                    <div class="hero-tag">
                        <span class="pulse-dot"></span>
                        Live Project Monitoring
                    </div>
                    <h1 class="hero-title" v-html="heroTitle"></h1>
                    <p class="hero-desc">{{ heroDescription }}</p>
                    <div class="hero-actions">
                        <a href="#announcements" class="hero-cta" @click.prevent="scrollToSection('announcements')">
                            View Bulletin
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </a>
                        <a href="#projects" class="hero-secondary" @click.prevent="scrollToSection('projects')">View Projects</a>
                    </div>
                </div>
                <div class="hero-logo">
                    <img src="/image/meo_logo2.png" alt="MEO Logo">
                    <img src="/image/Opol-logo.png" alt="Opol Logo">
                </div>
            </div>
        </section>

        <!-- ANNOUNCEMENTS / BULLETIN SECTION -->
        <section class="meo-announcements" id="announcements" data-reveal>
            <div class="announcements-inner">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">Bulletin & Announcements</h2>
                        <p class="section-sub">Latest updates, advisories, and public notices from the MEO</p>
                    </div>
                </div>

                <div v-if="announcements && announcements.length > 0" class="announcements-grid">
                    <div
                        v-for="announcement in announcements"
                        :key="announcement.id"
                        class="announcement-card"
                    >
                        <div class="announcement-header">
                            <span
                                class="announcement-category"
                                :style="{ color: categoryConfig[announcement.category]?.color || '#2563eb', background: categoryConfig[announcement.category]?.bg || '#eff6ff' }"
                            >
                                <span class="category-dot" :style="{ background: categoryConfig[announcement.category]?.color || '#2563eb' }"></span>
                                {{ categoryConfig[announcement.category]?.label || announcement.category }}
                            </span>
                            <span v-if="announcement.isNew" class="new-badge">NEW</span>
                            <span class="announcement-date">{{ formatAnnouncementDate(announcement.date) }}</span>
                        </div>
                        <h3 class="announcement-title">{{ announcement.title }}</h3>
                        <p class="announcement-content">{{ announcement.content }}</p>
                    </div>
                </div>

                <!-- Empty Bulletin State -->
                <div v-else class="bulletin-empty-state">
                    <div class="bulletin-empty-icon">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                    </div>
                    <h3 class="bulletin-empty-title">No Updates Today</h3>
                    <p class="bulletin-empty-desc">There are no new public notices or advisories posted today. Please check back later for official updates from the Municipal Engineering Office.</p>
                </div>
            </div>
        </section>

        <!-- PROJECT SECTION -->
        <section class="meo-projects" id="projects" data-reveal>
            <div class="projects-inner">
                <div class="section-head">
                    <div>
                        <div class="section-badge">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            Public Infrastructure Transparency Portal
                        </div>
                        <h2 class="section-title">Infrastructure Projects Directory</h2>
                        <p class="section-sub">Public record of all municipal engineering works, appropriations, fund sources, timelines, and implementation status across the Municipality of Opol.</p>
                    </div>
                    <div class="projects-meta-pills">
                        <span class="meta-pill meta-pill-count">
                            <strong>{{ displayedProjects.length }}</strong> {{ displayedProjects.length === 1 ? 'Project' : 'Projects' }} Listed
                        </span>
                    </div>
                </div>

                <!-- Search, Filter, and Controls Row -->
                <div class="controls-row">
                    <div class="search-wrap">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input v-model="searchQuery" type="text" placeholder="Search by project name, barangay, contractor, or funding source..." class="search-input" />
                        <button v-if="searchQuery" class="search-clear-btn" @click="searchQuery = ''" title="Clear search">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>

                    <div class="filter-dropdowns">
                        <!-- Year Filter -->
                        <div class="select-group" v-if="availableYears.length > 0">
                            <label class="select-label">Year:</label>
                            <select v-model="selectedYear" class="styled-select">
                                <option value="all">All Years</option>
                                <option v-for="yr in availableYears" :key="yr" :value="yr">FY {{ yr }}</option>
                            </select>
                        </div>

                        <!-- Fund Source Filter -->
                        <div class="select-group" v-if="availableFundCategories.length > 0">
                            <label class="select-label">Fund:</label>
                            <select v-model="selectedFundCategory" class="styled-select">
                                <option value="all">All Funds</option>
                                <option v-for="fc in availableFundCategories" :key="fc" :value="fc">{{ fc }}</option>
                            </select>
                        </div>

                        <!-- Sort Filter -->
                        <div class="sort-wrap">
                            <label class="sort-label">Sort:</label>
                            <select v-model="sortBy" class="sort-select">
                                <option value="none">Default Order</option>
                                <option value="progress">Accomplishment (%)</option>
                                <option value="budget">Project Cost / Budget</option>
                                <option value="endDate">Target Date</option>
                                <option value="title">Project Name</option>
                            </select>
                            <button class="sort-btn" @click="sortDir = sortDir === 'asc' ? 'desc' : 'asc'" :title="sortDir === 'asc' ? 'Ascending' : 'Descending'">
                                <span>{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Status Filter Bar -->
                <div class="filter-bar-wrap">
                    <div class="filter-bar">
                        <button v-for="f in filters" :key="f" class="filter-btn" :class="{ active: activeFilter === f }" @click="activeFilter = f">
                            <span v-if="f !== 'all'" class="filter-dot" :style="{ background: statusConfig[f]?.dot }"></span>
                            {{ f === 'all' ? 'All Projects' : statusConfig[f]?.label }}
                            <span class="filter-count">{{ f === 'all' ? projects.length : projects.filter(p => p.status === f).length }}</span>
                        </button>
                    </div>

                    <button v-if="hasActiveFilters" class="clear-filters-btn" @click="resetFilters">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        Reset Filters
                    </button>
                </div>

                <!-- Desktop Table View -->
                <div class="table-shell">
                    <table class="project-table">
                        <thead>
                            <tr>
                                <th style="width: 32%;">Project Title &amp; Classification</th>
                                <th style="width: 16%;">Location</th>
                                <th style="width: 14%;">Approved Budget</th>
                                <th style="width: 16%;">Contractor / Agency</th>
                                <th style="width: 10%;">Status</th>
                                <th style="width: 12%;">Accomplishment</th>
                                <th style="width: 5%;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="p in paginatedProjects" :key="p.id">
                                <tr class="p-row" @click="selectedProject = p">
                                    <td class="td-title">
                                        <div class="p-title-wrap">
                                            <div class="p-name">{{ p.title }}</div>
                                            <div class="p-tags-row">
                                                <span class="tag-chip tag-year" v-if="p.year">FY {{ p.year }}</span>
                                                <span class="tag-chip tag-fund" v-if="p.fundCategory">{{ p.fundCategory }}</span>
                                                <span class="tag-chip tag-duration" v-if="p.duration">{{ p.duration }} Days</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="td-loc">
                                        <div class="loc-wrapper">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                            <span>{{ p.location }}</span>
                                        </div>
                                    </td>
                                    <td class="td-budget">
                                        <span class="budget-val">{{ formatBudget(p.budget) }}</span>
                                        <span class="budget-source-sub" v-if="p.sourceOfFund">{{ p.sourceOfFund }}</span>
                                    </td>
                                    <td class="td-contractor">
                                        <div class="contractor-wrapper">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                                            <span class="contractor-name" :title="p.contractor">{{ p.contractor }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge" :style="{ color: statusConfig[p.status]?.color || '#475569', background: statusConfig[p.status]?.bg || '#f1f5f9', border: `1px solid ${statusConfig[p.status]?.border || '#cbd5e1'}` }">
                                            <span class="bdot" :style="{ background: statusConfig[p.status]?.dot || '#94a3b8' }"></span>
                                            {{ statusConfig[p.status]?.label || p.status }}
                                        </span>
                                    </td>
                                    <td class="td-progress">
                                        <div class="progress-inline">
                                            <span class="progress-percent" :style="{ color: p.progress >= 100 ? '#15803d' : '#0f172a' }">{{ p.progress }}%</span>
                                            <div class="progress-bar-mini">
                                                <div class="progress-bar-fill" :style="{ width: p.progress + '%', background: p.progress >= 100 ? '#22c55e' : (p.status === 'delayed' ? '#f59e0b' : '#b91c1c') }"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="td-chevron">
                                        <button class="details-btn" @click.stop="selectedProject = p" title="View Full Transparency Record">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="displayedProjects.length === 0">
                                <td colspan="7" class="empty-row">
                                    <div class="empty-state">
                                        <div class="empty-icon-wrap">
                                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        </div>
                                        <h4>No infrastructure projects match your criteria</h4>
                                        <p>Try modifying your search keywords, clear active status filters, or select "All Years".</p>
                                        <button class="reset-search-btn" @click="resetFilters">Clear All Search &amp; Filters</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Projects Directory Pagination Bar -->
                <div v-if="displayedProjects.length > 0" class="projects-pagination">
                    <div class="pagination-info">
                        Showing <strong>{{ (currentPage - 1) * perPage + 1 }}</strong> to
                        <strong>{{ Math.min(currentPage * perPage, displayedProjects.length) }}</strong> of
                        <strong>{{ displayedProjects.length }}</strong> infrastructure projects
                    </div>

                    <div v-if="totalPages > 1" class="pagination-controls">
                        <button
                            class="pg-btn pg-nav-btn"
                            :disabled="currentPage === 1"
                            @click="prevPage"
                            title="Previous Page"
                        >
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                            <span>Prev</span>
                        </button>

                        <div class="pg-numbers">
                            <template v-for="(pg, idx) in visiblePages" :key="idx">
                                <span v-if="pg === '...'" class="pg-ellipsis">…</span>
                                <button
                                    v-else
                                    class="pg-btn pg-num-btn"
                                    :class="{ active: currentPage === pg }"
                                    @click="goToPage(pg)"
                                >
                                    {{ pg }}
                                </button>
                            </template>
                        </div>

                        <button
                            class="pg-btn pg-nav-btn"
                            :disabled="currentPage === totalPages"
                            @click="nextPage"
                            title="Next Page"
                        >
                            <span>Next</span>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- COMPILATION OF COMPLETED PROJECTS & ACHIEVEMENTS -->
        <section v-if="achievementImages && achievementImages.length > 0" class="meo-achievements" id="achievements" data-reveal>
            <div class="achievements-inner">
                <!-- Section Header with Visual Controls -->
                <div class="ach-gallery-header">
                    <div class="ach-header-left">
                        <div class="section-badge section-badge-emerald">
                            <span class="ach-live-pulse"></span>
                            Photographic Exhibition • LGU Opol
                        </div>
                        <h2 class="section-title">Compilation of Completed Projects &amp; Achievements</h2>
                        <p class="section-sub">
                            An inspiring visual archive documenting finished municipal infrastructures, ribbon cutting turnovers, and engineering milestones delivered for the people of Opol.
                        </p>
                    </div>

                    <!-- Right Header Controls: Counter, Year Filter & View Mode Switcher -->
                    <div class="ach-header-controls">
                        <div class="ach-counter-badge">
                            <span class="ach-counter-num">{{ filteredAchievementsList.length }}</span>
                            <span class="ach-counter-text">{{ filteredAchievementsList.length === 1 ? 'Exhibit' : 'Exhibits' }}</span>
                        </div>

                        <!-- Year Filter Dropdown -->
                        <div v-if="availableAchievementYears.length > 0" class="ach-year-filter-wrapper">
                            <div class="ach-year-select-box">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ach-year-icon">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                <select 
                                    v-model="activeAchievementYear" 
                                    class="ach-year-select"
                                    aria-label="Filter exhibits by year"
                                >
                                    <option value="all">All Years</option>
                                    <option v-for="yr in availableAchievementYears" :key="yr" :value="yr">
                                        Year {{ yr }}
                                    </option>
                                </select>
                                <svg class="ach-select-arrow" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </div>
                        </div>

                        <div class="ach-view-switcher">
                            <button
                                class="ach-view-btn"
                                :class="{ active: galleryViewMode === 'mosaic' }"
                                @click="galleryViewMode = 'mosaic'"
                                title="Mosaic Showcase View"
                            >
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
                                <span>Mosaic</span>
                            </button>
                            <button
                                class="ach-view-btn"
                                :class="{ active: galleryViewMode === 'grid' }"
                                @click="galleryViewMode = 'grid'"
                                title="Uniform Grid View"
                            >
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
                                <span>Grid</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Category Filters Pill Bar -->
                <div class="achievements-filter-bar">
                    <button
                        v-for="cat in achievementCategoryTabs"
                        :key="cat.key"
                        class="ach-filter-pill"
                        :class="{ active: activeAchievementCategory === cat.key }"
                        @click="activeAchievementCategory = cat.key"
                    >
                        <span class="ach-pill-icon">
                            <svg v-if="cat.key === 'all'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
                            <svg v-else-if="cat.key === 'completed_project'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M3 7v14M21 7v14M6 11h3M6 15h3M15 11h3M15 15h3M9 3h6v4H9z"/><polyline points="10 12 12 14 16 10"/></svg>
                            <svg v-else-if="cat.key === 'turnover'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 11.5a4.5 4.5 0 1 0-4.5 4.5c.3 0 .6-.03.88-.1L17 19.5l3-3-1.5-1.5 1.5-1.5-2.6-2.6c.07-.28.1-.58.1-.9z"/></svg>
                            <svg v-else-if="cat.key === 'achievement'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6M18 9h1.5a2.5 2.5 0 0 0 0-5H18M4 22h16M10 14.66V17c0 .55-.45 1-1 1H8c-.55 0-1 .45-1 1v1c0 .55.45 1 1 1h8c.55 0 1-.45 1-1v-1c0-.55-.45-1-1-1h-1c-.55 0-1-.45-1-1v-2.34M18 4H6v7a6 6 0 0 0 12 0V4z"/></svg>
                            <svg v-else-if="cat.key === 'milestone'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                        </span>
                        <span class="ach-pill-label">{{ cat.label }}</span>
                        <span class="ach-pill-count">{{ getCategoryCount(cat.key) }}</span>
                    </button>
                </div>

                <!-- Gallery Exhibition Grid / Mosaic with TransitionGroup -->
                <TransitionGroup
                    name="gallery-card"
                    tag="div"
                    class="achievements-gallery-stage"
                    :class="`view-${galleryViewMode}`"
                >
                    <div
                        v-for="(item, idx) in paginatedAchievementsList"
                        :key="item.id"
                        class="gallery-item-card"
                        :class="[
                            `cat-${item.category || 'completed_project'}`,
                            { 'is-hero-card': galleryViewMode === 'mosaic' && idx === 0 }
                        ]"
                        @click="openAchievementModal(item)"
                    >
                        <div class="g-img-container">
                            <img :src="item.url" :alt="item.title || 'Completed Project'" loading="lazy" class="g-photo" />
                            
                            <!-- Ambient Gradient Overlay -->
                            <div class="g-photo-overlay"></div>

                            <!-- Floating Badges Top Left & Right -->
                            <div class="g-top-bar">
                                <span
                                    class="g-category-pill"
                                    :class="{
                                        'g-tag-gold': item.category === 'achievement',
                                        'g-tag-blue': item.category === 'turnover',
                                        'g-tag-purple': item.category === 'milestone',
                                        'g-tag-emerald': !item.category || item.category === 'completed_project'
                                    }"
                                >
                                    <svg v-if="item.category === 'achievement'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6M18 9h1.5a2.5 2.5 0 0 0 0-5H18M4 22h16M10 14.66V17c0 .55-.45 1-1 1H8c-.55 0-1 .45-1 1v1c0 .55.45 1 1 1h8c.55 0 1-.45 1-1v-1c0-.55-.45-1-1-1h-1c-.55 0-1-.45-1-1v-2.34M18 4H6v7a6 6 0 0 0 12 0V4z"/></svg>
                                    <svg v-else-if="item.category === 'turnover'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 11.5a4.5 4.5 0 1 0-4.5 4.5c.3 0 .6-.03.88-.1L17 19.5l3-3-1.5-1.5 1.5-1.5-2.6-2.6c.07-.28.1-.58.1-.9z"/></svg>
                                    <svg v-else-if="item.category === 'milestone'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                                    <svg v-else width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M3 7v14M21 7v14M6 11h3M6 15h3M15 11h3M15 15h3M9 3h6v4H9z"/><polyline points="10 12 12 14 16 10"/></svg>

                                    {{
                                        item.category === 'achievement'
                                            ? 'Award & Achievement'
                                            : item.category === 'turnover'
                                            ? 'Turnover / Inauguration'
                                            : item.category === 'milestone'
                                            ? 'Major Milestone'
                                            : 'Completed Project'
                                    }}
                                </span>

                                <span v-if="item.year" class="g-year-badge">
                                    {{ item.year }}
                                </span>
                            </div>

                            <!-- Floating Center Zoom Icon Action on Hover -->
                            <div class="g-zoom-trigger">
                                <div class="g-zoom-circle">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                                </div>
                            </div>

                            <!-- Bottom Glass Content Drawer -->
                            <div class="g-caption-drawer">
                                <div v-if="item.location" class="g-location-chip">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                    <span>{{ item.location }}</span>
                                </div>

                                <h3 class="g-card-title">{{ item.title || 'Completed Engineering Project' }}</h3>
                                
                                <p v-if="item.caption" class="g-card-caption">
                                    {{ item.caption }}
                                </p>

                                <div class="g-card-footer">
                                    <span>Click to explore exhibit</span>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </TransitionGroup>

                <!-- Achievements Gallery Pagination Bar -->
                <div v-if="filteredAchievementsList.length > 0" class="ach-pagination">
                    <div class="ach-pagination-info">
                        Showing <strong>{{ (currentAchPage - 1) * achPerPage + 1 }}</strong> to
                        <strong>{{ Math.min(currentAchPage * achPerPage, filteredAchievementsList.length) }}</strong> of
                        <strong>{{ filteredAchievementsList.length }}</strong> photographic exhibits
                    </div>

                    <div v-if="totalAchPages > 1" class="ach-pagination-controls">
                        <button
                            class="ach-pg-btn ach-pg-nav"
                            :disabled="currentAchPage === 1"
                            @click="prevAchPage"
                            title="Previous Page"
                        >
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                            <span>Prev</span>
                        </button>

                        <div class="ach-pg-numbers">
                            <template v-for="(pg, idx) in visibleAchPages" :key="idx">
                                <span v-if="pg === '...'" class="ach-pg-ellipsis">…</span>
                                <button
                                    v-else
                                    class="ach-pg-btn ach-pg-num"
                                    :class="{ active: currentAchPage === pg }"
                                    @click="goToAchPage(pg)"
                                >
                                    {{ pg }}
                                </button>
                            </template>
                        </div>

                        <button
                            class="ach-pg-btn ach-pg-nav"
                            :disabled="currentAchPage === totalAchPages"
                            @click="nextAchPage"
                            title="Next Page"
                        >
                            <span>Next</span>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Empty State for Filters -->
                <div v-if="filteredAchievementsList.length === 0" class="ach-empty-gallery">
                    <div class="ach-empty-icon">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    </div>
                    <h4>No photographs found matching these filters</h4>
                    <p>Select another category/year or view all municipal exhibits.</p>
                    <button class="ach-reset-filter-btn" @click="activeAchievementCategory = 'all'; activeAchievementYear = 'all'">
                        Reset Filters &amp; View All
                    </button>
                </div>
            </div>
        </section>

        <!-- FOOTER -->
        <footer class="meo-footer">
            <div class="footer-inner">
                <div class="footer-left">
                    <div class="footer-logo">
                        <img src="/image/meo_logo2.png" alt="MEO Logo">
                    </div>
                    <div>
                        <div class="footer-name">Municipal Engineering Office</div>
                        <div class="footer-sub">Republic of the Philippines</div>
                    </div>
                </div>
                <div class="footer-center">
                    <p>Municipal Hall of Opol, Misamis Oriental, Engineering Department</p>
                    <p>For public inquiries and project concerns: Mon–Fri, 8:00 AM – 5:00 PM</p>
                </div>
                <div class="footer-right">
                    <span>© 2026 Municipal Engineering Office of Opol. Open Data Portal.</span>
                </div>
            </div>
        </footer>

        <!-- COMPREHENSIVE PUBLIC INFRASTRUCTURE TRANSPARENCY MODAL -->
        <Transition name="modal">
            <div v-if="selectedProject" class="modal-backdrop" @click.self="selectedProject = null">
                <div class="modal-card transparency-modal-card">
                    <!-- Modal Header -->
                    <div class="modal-header">
                        <div class="modal-title-group">
                            <div class="modal-agency-sub">
                                <span class="seal-dot"></span>
                                Republic of the Philippines • Local Government Unit of Opol • Municipal Engineering Office
                            </div>
                            <h3 class="modal-title">{{ selectedProject.title }}</h3>
                            <div class="modal-header-tags">
                                <span class="badge" :style="{ color: statusConfig[selectedProject.status]?.color, background: statusConfig[selectedProject.status]?.bg, border: `1px solid ${statusConfig[selectedProject.status]?.border}` }">
                                    <span class="bdot" :style="{ background: statusConfig[selectedProject.status]?.dot }"></span>
                                    {{ statusConfig[selectedProject.status]?.label }}
                                </span>
                                <span class="modal-tag-chip">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                    {{ selectedProject.location }}
                                </span>
                                <span class="modal-tag-chip" v-if="selectedProject.year">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                    FY {{ selectedProject.year }}
                                </span>
                                <span class="modal-tag-chip" v-if="selectedProject.fundCategory">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                    {{ selectedProject.fundCategory }}
                                </span>
                            </div>
                        </div>
                        <button class="modal-close" @click="selectedProject = null" aria-label="Close transparency record">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="modal-body">
                        <!-- 1. Physical Accomplishment Hero Gauge -->
                        <div class="disclosure-section hero-accomplishment-card">
                            <div class="accomplishment-header-row">
                                <div>
                                    <span class="disc-subheading">Physical Accomplishment Rate</span>
                                    <h4 class="disc-progress-num">{{ selectedProject.progress }}%</h4>
                                </div>
                                <div class="accomplishment-status-pill" :class="selectedProject.progress >= 100 ? 'status-delivered' : (selectedProject.status === 'delayed' ? 'status-delayed' : 'status-progressing')">
                                    <svg v-if="selectedProject.progress >= 100" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                    <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    <span>{{ selectedProject.progress >= 100 ? 'Fully Delivered & Completed' : (selectedProject.status === 'delayed' ? 'Behind Target Schedule' : 'Work On Track') }}</span>
                                </div>
                            </div>
                            <div class="disc-progress-track">
                                <div class="disc-progress-fill" :style="{ width: selectedProject.progress + '%', background: selectedProject.progress >= 100 ? '#16a34a' : (selectedProject.status === 'delayed' ? '#d97706' : 'linear-gradient(90deg, #b91c1c, #ef4444)') }"></div>
                            </div>
                            <div class="disc-progress-meta">
                                <span>Start: <strong>{{ selectedProject.startDate }}</strong></span>
                                <span>Target Completion: <strong>{{ selectedProject.endDate }}</strong></span>
                                <span v-if="selectedProject.actualCompletionDate">Actual Completion: <strong class="text-green">{{ selectedProject.actualCompletionDate }}</strong></span>
                                <span v-else-if="selectedProject.revisedCompletionDate">Revised Target: <strong class="text-amber">{{ selectedProject.revisedCompletionDate }}</strong></span>
                            </div>
                        </div>

                        <!-- 2. Project Execution & Timeline Grid -->
                        <div class="disclosure-section">
                            <h4 class="disc-title">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                Implementation &amp; Accountability
                            </h4>
                            <div class="modal-grid">
                                <div class="modal-info-card">
                                    <div class="modal-info-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                                    </div>
                                    <div class="modal-info-content">
                                        <span class="modal-info-label">Contractor / Implementing Entity</span>
                                        <span class="modal-info-val highlight-val">{{ selectedProject.contractor }}</span>
                                    </div>
                                </div>

                                <div class="modal-info-card">
                                    <div class="modal-info-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    </div>
                                    <div class="modal-info-content">
                                        <span class="modal-info-label">Contract Duration</span>
                                        <span class="modal-info-val">{{ selectedProject.duration ? `${selectedProject.duration} Calendar Days` : 'N/A' }}</span>
                                    </div>
                                </div>

                                <div class="modal-info-card">
                                    <div class="modal-info-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                    </div>
                                    <div class="modal-info-content">
                                        <span class="modal-info-label">Commencement Date</span>
                                        <span class="modal-info-val">{{ selectedProject.startDate }}</span>
                                    </div>
                                </div>

                                <div class="modal-info-card">
                                    <div class="modal-info-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 10"/></svg>
                                    </div>
                                    <div class="modal-info-content">
                                        <span class="modal-info-label">Target Completion Date</span>
                                        <span class="modal-info-val">{{ selectedProject.endDate }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Extensions & Suspensions Disclosure -->
                            <div class="adjustments-banner" v-if="selectedProject.timeExtension > 0 || selectedProject.daysSuspensionOrder > 0">
                                <div class="adj-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                </div>
                                <div class="adj-text">
                                    <span class="adj-title">Schedule Adjustments &amp; Suspension Disclosures</span>
                                    <div class="adj-details">
                                        <span v-if="selectedProject.timeExtension > 0">Time Extension Granted: <strong>{{ selectedProject.timeExtension }} Days</strong></span>
                                        <span v-if="selectedProject.daysSuspensionOrder > 0">Suspension Order Logged: <strong>{{ selectedProject.daysSuspensionOrder }} Days</strong></span>
                                        <span v-if="selectedProject.revisedCompletionDate">Revised Target Date: <strong>{{ selectedProject.revisedCompletionDate }}</strong></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Financial Transparency Breakdown -->
                        <div class="disclosure-section">
                            <h4 class="disc-title">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                Financial Transparency &amp; Appropriations
                            </h4>
                            <div class="financial-cards-grid">
                                <div class="fin-card fin-card-highlight">
                                    <span class="fin-label">Total Approved Project Budget (ABC)</span>
                                    <span class="fin-amount">{{ formatBudget(selectedProject.totalCost || selectedProject.budget) }}</span>
                                    <span class="fin-sub">Full municipal budget allocation</span>
                                </div>

                                <div class="fin-card" v-if="selectedProject.originalCost">
                                    <span class="fin-label">Original Contract Cost</span>
                                    <span class="fin-amount">{{ formatBudget(selectedProject.originalCost) }}</span>
                                    <span class="fin-sub">Initial bid / award value</span>
                                </div>

                                <div class="fin-card" v-if="selectedProject.revisedCost">
                                    <span class="fin-label">Revised Contract Cost</span>
                                    <span class="fin-amount" style="color: #b45309;">{{ formatBudget(selectedProject.revisedCost) }}</span>
                                    <span class="fin-sub">Adjusted following change orders</span>
                                </div>

                                <div class="fin-card">
                                    <span class="fin-label">Funding Source</span>
                                    <span class="fin-amount-text">{{ selectedProject.sourceOfFund || 'LGU General Fund' }}</span>
                                    <span class="fin-sub">Classification: {{ selectedProject.fundCategory || 'Local Government' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 4. Project Scope & Public Benefits -->
                        <div class="disclosure-section" v-if="selectedProject.description">
                            <h4 class="disc-title">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                Scope of Work &amp; Project Description
                            </h4>
                            <div class="project-desc-box">
                                <p>{{ selectedProject.description }}</p>
                            </div>
                        </div>

                        <!-- 5. Pre-Engineering & Procurement Checklist (8 Milestones) -->
                        <div class="disclosure-section" v-if="selectedProject.technical_preparations">
                            <div class="milestones-header">
                                <div>
                                    <h4 class="disc-title" style="margin-bottom: 2px;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                                        Pre-Engineering &amp; Procurement Checklist
                                    </h4>
                                    <p class="disc-sub">Mandatory government pre-construction compliance tracking by designated office / entity.</p>
                                </div>
                            </div>

                            <div class="milestones-grid">
                                <div 
                                    v-for="ms in milestoneList" 
                                    :key="ms.key" 
                                    class="milestone-card"
                                    :class="`ms-status-${selectedProject.technical_preparations[ms.key]?.status || 'na'}`"
                                >
                                    <div class="ms-card-top">
                                        <span class="ms-code">{{ ms.office }}</span>
                                        <span 
                                            class="ms-status-badge"
                                            :style="{ 
                                                color: milestoneStatusMap[selectedProject.technical_preparations[ms.key]?.status || 'na']?.color,
                                                background: milestoneStatusMap[selectedProject.technical_preparations[ms.key]?.status || 'na']?.bg,
                                                border: `1px solid ${milestoneStatusMap[selectedProject.technical_preparations[ms.key]?.status || 'na']?.border}`
                                            }"
                                        >
                                            <svg v-if="selectedProject.technical_preparations[ms.key]?.status === 'green'" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                            <svg v-else-if="selectedProject.technical_preparations[ms.key]?.status === 'yellow'" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                            <svg v-else-if="selectedProject.technical_preparations[ms.key]?.status === 'red'" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                            <span v-else>—</span>
                                            {{ milestoneStatusMap[selectedProject.technical_preparations[ms.key]?.status || 'na']?.label }}
                                        </span>
                                    </div>
                                    <h5 class="ms-name">{{ ms.name }}</h5>
                                    <p class="ms-desc">{{ ms.desc }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- 6. Engineering Notes & Inspection Remarks -->
                        <div class="disclosure-section" v-if="selectedProject.remarks && selectedProject.remarks.length > 0">
                            <h4 class="disc-title">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                Site Inspection &amp; Monitoring Remarks
                            </h4>
                            <div class="remarks-list">
                                <div v-for="(rmk, idx) in selectedProject.remarks" :key="idx" class="remark-item">
                                    <div class="remark-bullet"></div>
                                    <div class="remark-text">{{ rmk }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- 7. Citizen Feedback & Public Oversight CTA -->
                        <div class="disclosure-section citizen-oversight-card">
                            <div class="oversight-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                            </div>
                            <div class="oversight-body">
                                <h5>Citizen Oversight &amp; Inquiries</h5>
                                <p>Do you have inquiries, community feedback, or site observations regarding this project? The Municipal Engineering Office welcomes citizen participation.</p>
                            </div>
                            <div class="oversight-actions">
                                <Link :href="route('ask.meo')" class="btn-ask-meo">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                    Ask MEO / Send Inquiry
                                </Link>
                                <button class="btn-print-record" @click="printProjectTransparency">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                    Print Transparency Sheet
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="modal-footer-bar">
                        <span class="modal-footer-note">Transparency record generated from official LGU Opol MEO Engineering Database.</span>
                        <button class="modal-btn-close" @click="selectedProject = null">Close Disclosure</button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- ACHIEVEMENT / COMPLETED PROJECT FULLSCREEN EXHIBITION LIGHTBOX MODAL -->
        <Transition name="modal">
            <div v-if="activeAchievementModal" class="modal-backdrop ach-exhibit-backdrop" @click.self="activeAchievementModal = null">
                <div class="ach-cinema-modal">
                    <!-- Lightbox Top Navigation Bar -->
                    <div class="ach-cinema-header">
                        <div class="ach-cinema-agency">
                            <span class="seal-dot" style="background: #10b981;"></span>
                            <span class="ach-agency-name">LGU Opol • Municipal Engineering Photographic Archive</span>
                        </div>

                        <div class="ach-cinema-top-actions">
                            <span class="ach-cinema-counter">
                                Exhibit {{ currentAchievementIndex + 1 }} of {{ filteredAchievementsList.length }}
                            </span>
                            <button class="ach-cinema-close-btn" @click="activeAchievementModal = null" title="Close Exhibition (Esc)">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Lightbox Visual Stage with Prev / Next Navigation -->
                    <div class="ach-cinema-body">
                        <!-- Left Slide Button -->
                        <button
                            v-if="filteredAchievementsList.length > 1"
                            class="ach-nav-arrow ach-nav-prev"
                            @click="prevAchievementModal"
                            title="Previous Exhibit (Left Arrow)"
                        >
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>

                        <!-- Main Photo Screen with Smooth Transition -->
                        <div class="ach-cinema-stage">
                            <Transition name="cinema-slide" mode="out-in">
                                <img
                                    :key="activeAchievementModal.id"
                                    :src="activeAchievementModal.url"
                                    :alt="activeAchievementModal.title"
                                    class="ach-cinema-img"
                                />
                            </Transition>
                        </div>

                        <!-- Right Slide Button -->
                        <button
                            v-if="filteredAchievementsList.length > 1"
                            class="ach-nav-arrow ach-nav-next"
                            @click="nextAchievementModal"
                            title="Next Exhibit (Right Arrow)"
                        >
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    </div>

                    <!-- Bottom Story & Disclosure Card -->
                    <div class="ach-cinema-info">
                        <div class="ach-info-main">
                            <div class="ach-info-tags">
                                <span
                                    class="ach-tag"
                                    :class="{
                                        'ach-tag-gold': activeAchievementModal.category === 'achievement',
                                        'ach-tag-blue': activeAchievementModal.category === 'turnover',
                                        'ach-tag-purple': activeAchievementModal.category === 'milestone',
                                        'ach-tag-green': !activeAchievementModal.category || activeAchievementModal.category === 'completed_project'
                                    }"
                                >
                                    <svg v-if="activeAchievementModal.category === 'achievement'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6M18 9h1.5a2.5 2.5 0 0 0 0-5H18M4 22h16M10 14.66V17c0 .55-.45 1-1 1H8c-.55 0-1 .45-1 1v1c0 .55.45 1 1 1h8c.55 0 1-.45 1-1v-1c0-.55-.45-1-1-1h-1c-.55 0-1-.45-1-1v-2.34M18 4H6v7a6 6 0 0 0 12 0V4z"/></svg>
                                    <svg v-else-if="activeAchievementModal.category === 'turnover'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 11.5a4.5 4.5 0 1 0-4.5 4.5c.3 0 .6-.03.88-.1L17 19.5l3-3-1.5-1.5 1.5-1.5-2.6-2.6c.07-.28.1-.58.1-.9z"/></svg>
                                    <svg v-else-if="activeAchievementModal.category === 'milestone'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                                    <svg v-else width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M3 7v14M21 7v14M6 11h3M6 15h3M15 11h3M15 15h3M9 3h6v4H9z"/><polyline points="10 12 12 14 16 10"/></svg>

                                    {{
                                        activeAchievementModal.category === 'achievement'
                                            ? 'Award & Achievement'
                                            : activeAchievementModal.category === 'turnover'
                                            ? 'Turnover / Inauguration'
                                            : activeAchievementModal.category === 'milestone'
                                            ? 'Major Milestone'
                                            : 'Completed Project'
                                    }}
                                </span>

                                <span v-if="activeAchievementModal.year" class="modal-tag-chip">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                    {{ activeAchievementModal.year }}
                                </span>

                                <span v-if="activeAchievementModal.location" class="modal-tag-chip">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                    {{ activeAchievementModal.location }}
                                </span>
                            </div>

                            <h3 class="ach-info-title">{{ activeAchievementModal.title || 'Municipal Infrastructure Project' }}</h3>
                            <p v-if="activeAchievementModal.caption" class="ach-info-caption">{{ activeAchievementModal.caption }}</p>
                        </div>

                        <div class="ach-info-footer">
                            <span class="ach-hint-text">Use ◀ / ▶ arrow keys to navigate • Esc to close</span>
                            <button class="ach-btn-cinema-close" @click="activeAchievementModal = null">Close Exhibit</button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Floating Back to Top Button -->
        <Transition name="fade-pop">
            <button
                v-if="showBackToTop"
                class="floating-back-to-top"
                @click="scrollToTop"
                title="Scroll back to top"
                aria-label="Scroll back to top"
            >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>
            </button>
        </Transition>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

.meo-root {
    --steel:    #111827;
    --concrete: #f1f5f9;
    --iron:     #334155;
    --amber:    #dc2626;
    --gold:     #d97706;
    --border:   #e2e8f0;
    --surface:  #ffffff;
    --text:     #0f172a;
    --muted:    #64748b;
    --light:    #f8fafc;
    font-family: 'Poppins', sans-serif;
    background: var(--concrete);
    color: var(--text);
    min-height: 100vh;
    line-height: 1.5;
    overflow-x: hidden;
}

html {
    scroll-behavior: smooth;
    scroll-padding-top: 105px;
}

/* SECTION SCROLL MARGINS */
.meo-announcements,
.meo-projects,
.meo-achievements {
    scroll-margin-top: 105px;
}

/* SMOOTH SCROLL REVEAL */
[data-reveal] {
    opacity: 0;
    transform: translateY(32px);
    transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1), transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
    will-change: opacity, transform;
}

[data-reveal].revealed {
    opacity: 1;
    transform: translateY(0);
}

/* SCROLL PROGRESS INDICATOR */
.scroll-progress-track {
    position: absolute;
    bottom: -1px;
    left: 0;
    width: 100%;
    height: 3px;
    background: rgba(226, 232, 240, 0.4);
    overflow: hidden;
    pointer-events: none;
    z-index: 10;
}

.scroll-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #10b981 0%, #059669 45%, #d97706 80%, #ef4444 100%);
    box-shadow: 0 0 8px rgba(16, 185, 129, 0.5);
    transition: width 0.1s linear;
}

/* FLOATING BACK TO TOP BUTTON */
.floating-back-to-top {
    position: fixed;
    bottom: 28px;
    right: 28px;
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: #0f172a;
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 10px 25px -4px rgba(15, 23, 42, 0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 1100;
    backdrop-filter: blur(8px);
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.floating-back-to-top:hover {
    background: #10b981;
    border-color: #34d399;
    transform: translateY(-4px) scale(1.06);
    box-shadow: 0 14px 28px -4px rgba(16, 185, 129, 0.45);
}

.floating-back-to-top:active {
    transform: translateY(0) scale(0.95);
}

.fade-pop-enter-active,
.fade-pop-leave-active {
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.fade-pop-enter-from,
.fade-pop-leave-to {
    opacity: 0;
    transform: translateY(16px) scale(0.8);
}

/* TOP BAR */
.top-bar {
    background: var(--steel);
    color: rgba(255,255,255,0.5);
    font-size: 11px;
    font-weight: 400;
    padding: 7px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    letter-spacing: 0.02em;
    position: fixed; /* Changed from sticky to fixed */
    top: 0;
    left: 0;
    right: 0;
    z-index: 102; /* Increased z-index to stay above nav */
    height: 30px; /* Fixed height for consistent spacing */
}
.top-bar-mobile { display: none; }

/* NAV */
.meo-nav {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    position: fixed; /* Changed from sticky to fixed */
    top: 30px; /* Position below top bar */
    left: 0;
    right: 0;
    z-index: 101;
    box-shadow: 0 1px 0 var(--border);
    transition: box-shadow 0.3s ease; /* Smooth transition for shadow */
}
.nav-inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 24px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.nav-brand { display: flex; align-items: center; gap: 12px; min-width: 0; }
.nav-logo {
    width: 40px; height: 40px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.nav-logo img { width: 100%; height: 100%; object-fit: contain; display: block; }
.nav-title { font-size: 14px; font-weight: 600; color: var(--steel); letter-spacing: -0.01em; white-space: nowrap; }
.nav-sub { font-size: 11px; color: var(--muted); font-weight: 400; }
.nav-links { display: flex; gap: 4px; }
.nav-link {
    font-size: 13px; font-weight: 500;
    color: var(--muted); text-decoration: none;
    padding: 6px 14px; border-radius: 8px;
    transition: all 0.15s ease; /* Added ease for smooth hover */
    white-space: nowrap;
    cursor: pointer;
}
.nav-link:hover { color: var(--steel); background: var(--light); }

/* Mobile Menu Button */
.mobile-menu-btn {
    display: none;
    flex-direction: column;
    gap: 5px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    z-index: 101;
}
.mobile-menu-btn span {
    display: block;
    width: 24px;
    height: 2px;
    background: var(--steel);
    transition: all 0.3s ease; /* Smooth animation */
}
.mobile-menu-btn span.open:nth-child(1) {
    transform: rotate(45deg) translate(5px, 5px);
}
.mobile-menu-btn span.open:nth-child(2) {
    opacity: 0;
}
.mobile-menu-btn span.open:nth-child(3) {
    transform: rotate(-45deg) translate(5px, -5px);
}

/* Mobile Navigation */
.mobile-nav {
    display: none;
    flex-direction: column;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 8px 24px;
    gap: 4px;
    transform: translateY(-100%);
    transition: transform 0.3s ease, opacity 0.3s ease; /* Smooth transition */
    position: absolute;
    width: 100%;
    top: 64px;
    left: 0;
    z-index: 99;
    opacity: 0;
}
.mobile-nav.open {
    transform: translateY(0);
    opacity: 1;
}
.mobile-nav-link {
    font-size: 14px;
    font-weight: 500;
    color: var(--muted);
    text-decoration: none;
    padding: 12px 16px;
    border-radius: 8px;
    transition: all 0.15s ease; /* Smooth hover */
    cursor: pointer;
}
.mobile-nav-link:hover {
    color: var(--steel);
    background: var(--light);
}

/* HERO */
.meo-hero {
    position: relative;
    min-height: 600px;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    overflow: hidden;
    margin-top: 94px; /* 30px top bar + 64px nav = 94px total offset */
}
.hero-background-slideshow,
.hero-background-fallback {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1;
}
.hero-bg-slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    transition: opacity 0.8s ease-in-out; /* Smooth fade transition */
}
.hero-bg-slide.active {
    opacity: 1;
}
.hero-bg-slide img,
.hero-background-fallback img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    filter: blur(4px) brightness(0.6);
    transform: scale(1.1);
    transition: transform 0.8s ease; /* Smooth scale transition */
}
.hero-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.4);
    z-index: 2;
}
.hero-inner {
    position: relative;
    z-index: 3;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 600px;
}
.hero-content {
    max-width: 700px;
    text-align: left;
    padding: 80px 0 60px;
}
.hero-logo {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 24px;
    padding: 80px 0 60px;
}
.hero-logo img {
    width: 170px;
    height: 170px;
    object-fit: contain;
    transition: transform 0.3s ease; /* Smooth hover effect */
}
.hero-logo img:hover {
    transform: scale(1.05);
}
.hero-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #fff;
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.3);
    padding: 5px 12px;
    border-radius: 20px;
    margin-bottom: 20px;
    backdrop-filter: blur(10px);
}
.pulse-dot {
    width: 7px; height: 7px;
    background: var(--amber);
    border-radius: 50%;
    flex-shrink: 0;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.7); }
}
.hero-title {
    font-size: clamp(30px, 3.5vw, 46px);
    font-weight: 700;
    line-height: 1.12;
    color: #fff;
    margin-bottom: 16px;
    letter-spacing: -0.025em;
}
.hero-accent { color: var(--amber); }
.hero-desc {
    font-size: 15px;
    color: rgba(255, 255, 255, 0.9);
    font-weight: 400;
    line-height: 1.7;
    max-width: 600px;
    margin-bottom: 32px;
}
.hero-actions { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-top: 12px; }
.hero-cta {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    color: #fff;
    background: var(--amber);
    padding: 11px 22px;
    border-radius: 10px;
    text-decoration: none;
    transition: all 0.3s ease; /* Smooth transition */
    letter-spacing: -0.01em;
    cursor: pointer;
}
.hero-cta:hover { background: var(--amber2); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(244,67,54,0.24); }
.hero-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 11px 22px;
    border-radius: 10px;
    background: transparent;
    border: 1px solid var(--amber);
    color: var(--amber);
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease; /* Smooth transition */
    cursor: pointer;
}
.hero-secondary:hover { background: rgba(224,123,0,0.06); color: var(--steel); transform: translateY(-1px); }

/* Stats */
.hero-stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.stat-card {
    background: var(--light);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 20px;
    transition: box-shadow 0.3s ease, transform 0.3s ease; /* Smooth transitions */
}
.stat-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.07); transform: translateY(-2px); }
.stat-icon {
    width: 38px; height: 38px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 14px;
}
.stat-num { font-size: 28px; font-weight: 700; color: var(--steel); line-height: 1; margin-bottom: 4px; letter-spacing: -0.03em; }
.stat-label { font-size: 12px; font-weight: 500; color: var(--muted); }

/* ANNOUNCEMENTS */
.meo-announcements {
    padding: 56px 0 40px;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    scroll-margin-top: 110px; /* Offset for fixed header when scrolling to section */
}
.announcements-inner { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
.announcements-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 16px;
}
.announcement-card {
    background: var(--light);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    transition: box-shadow 0.3s ease, transform 0.3s ease; /* Smooth transitions */
}
.announcement-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.07); transform: translateY(-2px); }
.announcement-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}
.announcement-category {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 3px 8px;
    border-radius: 12px;
}
.category-dot { width: 5px; height: 5px; border-radius: 50%; flex-shrink: 0; }
.new-badge {
    font-size: 9px;
    font-weight: 700;
    background: #d32f2f;
    color: #fff;
    padding: 2px 6px;
    border-radius: 4px;
    letter-spacing: 0.05em;
    animation: pulse 2s infinite;
}
.announcement-date {
    font-size: 11px;
    color: var(--muted);
    margin-left: auto;
}
.announcement-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--steel);
    margin-bottom: 8px;
    line-height: 1.3;
}
.announcement-content {
    font-size: 13px;
    color: var(--muted);
    line-height: 1.6;
}

/* BULLETIN EMPTY STATE */
.bulletin-empty-state {
    background: var(--surface);
    border: 1px dashed #cbd5e1;
    border-radius: 16px;
    padding: 44px 24px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.bulletin-empty-icon {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: #f1f5f9;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
}
.bulletin-empty-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--steel);
    margin-bottom: 6px;
}
.bulletin-empty-desc {
    font-size: 13px;
    color: var(--muted);
    max-width: 480px;
    margin: 0;
    line-height: 1.6;
}

/* SECTION HEAD */
.section-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 16px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.section-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 600;
    color: var(--amber);
    background: #fef2f2;
    border: 1px solid #fecaca;
    padding: 4px 10px;
    border-radius: 20px;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.section-title { font-size: clamp(20px, 2.5vw, 24px); font-weight: 700; color: var(--steel); letter-spacing: -0.02em; margin-bottom: 4px; }
.section-sub { font-size: 13px; color: var(--muted); max-width: 760px; line-height: 1.6; }

/* PROJECTS META PILLS */
.projects-meta-pills {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.meta-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12.5px;
    font-weight: 500;
}
.meta-pill strong {
    font-weight: 700;
}
.meta-pill-count {
    background: #f1f5f9;
    color: var(--steel);
    border: 1px solid #e2e8f0;
}
.meta-pill-count strong {
    color: var(--amber);
}
.meta-pill-total {
    background: #eff6ff;
    color: #1e40af;
    border: 1px solid #bfdbfe;
}
.meta-pill-total strong {
    color: #1d4ed8;
}

/* CONTROLS ROW */
.controls-row {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

/* SEARCH & SORT */
.search-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 8px 14px;
    color: var(--muted);
    transition: all 0.2s ease;
    flex: 1;
    min-width: 260px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.search-wrap:focus-within {
    border-color: var(--amber);
    box-shadow: 0 0 0 3px rgba(185, 28, 28, 0.08);
}
.search-input {
    font-family: 'Poppins', sans-serif;
    font-size: 13px;
    border: none;
    outline: none;
    background: transparent;
    color: var(--text);
    width: 100%;
}
.search-input::placeholder { color: #94a3b8; }
.search-clear-btn {
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 2px;
    display: flex;
    align-items: center;
    border-radius: 4px;
}
.search-clear-btn:hover { color: var(--text); }

.filter-dropdowns {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}
.select-group {
    display: flex;
    align-items: center;
    gap: 6px;
}
.select-label {
    font-size: 12px;
    color: var(--muted);
    font-weight: 500;
    white-space: nowrap;
}
.styled-select,
.sort-select { 
    padding: 7px 10px; 
    border-radius: 8px; 
    border: 1px solid var(--border); 
    background: var(--surface);
    font-family: 'Poppins', sans-serif;
    font-size: 12.5px;
    color: var(--steel);
    cursor: pointer;
    transition: all 0.2s ease;
    outline: none;
}
.styled-select:focus,
.sort-select:focus {
    border-color: var(--amber);
    box-shadow: 0 0 0 3px rgba(185, 28, 28, 0.08);
}

.sort-wrap { display: flex; gap: 6px; align-items: center; }
.sort-label { font-size: 12px; color: var(--muted); font-weight: 500; white-space: nowrap; }
.sort-btn { 
    padding: 7px 10px; 
    border-radius: 8px; 
    border: 1px solid var(--border); 
    background: var(--surface); 
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--steel);
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}
.sort-btn:hover {
    background: var(--light);
    border-color: var(--amber);
    color: var(--amber);
}

/* FILTER BAR WRAP */
.filter-bar-wrap {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.filter-bar {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.filter-btn {
    font-family: 'Poppins', sans-serif;
    font-size: 12px;
    font-weight: 500;
    padding: 6px 13px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--muted);
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}
.filter-btn:hover { border-color: var(--steel); color: var(--steel); background: #f8fafc; }
.filter-btn.active { background: var(--steel); border-color: var(--steel); color: #fff; }
.filter-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.filter-count { font-size: 10px; font-weight: 600; background: rgba(0,0,0,0.06); border-radius: 10px; padding: 1px 6px; }
.filter-btn.active .filter-count { background: rgba(255,255,255,0.22); }

.clear-filters-btn {
    font-family: 'Poppins', sans-serif;
    font-size: 11.5px;
    font-weight: 500;
    color: #dc2626;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    padding: 6px 12px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    transition: all 0.2s ease;
}
.clear-filters-btn:hover {
    background: #fee2e2;
    color: #b91c1c;
}

/* TABLE */
.table-shell {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    display: block;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.project-table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 900px; }
.project-table thead tr { background: #f8fafc; border-bottom: 1px solid var(--border); }
.project-table th {
    padding: 13px 18px;
    text-align: left;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--muted);
    white-space: nowrap;
}
.p-row {
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    transition: all 0.2s ease;
}
.p-row:last-child { border-bottom: none; }
.p-row:hover { background: #f8fafc; }
.p-row:hover .details-btn {
    border-color: var(--amber);
    color: #fff;
    background: var(--amber);
}
.project-table td { padding: 14px 18px; vertical-align: middle; }

.p-title-wrap {
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.p-name {
    font-weight: 600;
    color: var(--text);
    font-size: 13.5px;
    line-height: 1.35;
}
.p-tags-row {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
    align-items: center;
}
.tag-chip {
    font-size: 10px;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 5px;
    letter-spacing: 0.02em;
}
.tag-year {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
}
.tag-fund {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}
.tag-duration {
    background: #fdf4ff;
    color: #86198f;
    border: 1px solid #f5d0fe;
}

.loc-wrapper {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--muted);
    font-size: 12.5px;
}
.loc-wrapper svg { color: #64748b; flex-shrink: 0; }

.td-budget {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.budget-val {
    font-weight: 600;
    color: var(--steel);
    font-size: 13px;
}
.budget-source-sub {
    font-size: 10.5px;
    color: var(--muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 140px;
}

.contractor-wrapper {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #334155;
    font-size: 12.5px;
}
.contractor-wrapper svg { color: #94a3b8; flex-shrink: 0; }
.contractor-name {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 150px;
    font-weight: 500;
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
    white-space: nowrap;
}
.bdot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }

.progress-inline {
    display: flex;
    align-items: center;
    gap: 8px;
}
.progress-percent {
    font-weight: 700;
    font-size: 12.5px;
    min-width: 36px;
}
.progress-bar-mini {
    height: 6px;
    width: 64px;
    background: #e2e8f0;
    border-radius: 3px;
    overflow: hidden;
}
.progress-bar-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.5s ease;
}

.details-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--muted);
    cursor: pointer;
    transition: all 0.2s ease;
}

/* EMPTY STATE */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px 24px;
    text-align: center;
    gap: 10px;
}
.empty-icon-wrap {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #f1f5f9;
    color: #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 4px;
}
.empty-state h4 {
    font-size: 15px;
    font-weight: 600;
    color: var(--steel);
    margin: 0;
}
.empty-state p {
    color: var(--muted);
    font-size: 13px;
    max-width: 440px;
    margin: 0 0 10px;
}
.reset-search-btn {
    font-family: 'Poppins', sans-serif;
    font-size: 12px;
    font-weight: 500;
    background: var(--steel);
    color: #fff;
    border: none;
    padding: 7px 16px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}
.reset-search-btn:hover {
    background: #000;
}

/* PROJECTS DIRECTORY PAGINATION */
.projects-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    margin-top: 16px;
    background: #ffffff;
    border: 1px solid var(--border);
    border-radius: 14px;
    gap: 16px;
    flex-wrap: wrap;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
}

.pagination-info {
    font-size: 12.5px;
    color: #64748b;
}

.pagination-info strong {
    color: #0f172a;
    font-weight: 700;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 6px;
}

.pg-numbers {
    display: flex;
    align-items: center;
    gap: 4px;
}

.pg-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 34px;
    min-width: 34px;
    padding: 0 10px;
    border-radius: 8px;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    user-select: none;
}

.pg-btn:hover:not(:disabled) {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #0f172a;
    transform: translateY(-1px);
}

.pg-btn:active:not(:disabled) {
    transform: translateY(0);
}

.pg-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    background: #f8fafc;
    border-color: #e2e8f0;
}

.pg-nav-btn {
    gap: 4px;
    padding: 0 12px;
}

.pg-num-btn.active {
    background: #0f172a;
    border-color: #0f172a;
    color: #ffffff;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.2);
}

.pg-ellipsis {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 34px;
    font-size: 14px;
    font-weight: 700;
    color: #94a3b8;
}

@media (max-width: 640px) {
    .projects-pagination {
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 12px;
        padding: 14px;
    }
    .pagination-controls {
        flex-wrap: wrap;
        justify-content: center;
    }
    .pg-btn {
        height: 32px;
        min-width: 32px;
        font-size: 11px;
    }
}

/* COMPREHENSIVE TRANSPARENCY MODAL */
.modal-backdrop {
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.65);
    z-index: 1200;
    padding: 16px;
    backdrop-filter: blur(5px);
}
.transparency-modal-card { 
    width: 100%;
    max-width: 820px; 
    background: var(--surface); 
    border-radius: 18px; 
    position: relative; 
    box-shadow: 0 25px 60px -15px rgba(0,0,0,0.35);
    max-height: 92vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 22px 24px 18px;
    border-bottom: 1px solid var(--border);
    background: #fafbfc;
}
.modal-title-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    min-width: 0;
    padding-right: 12px;
}
.modal-agency-sub {
    font-size: 10.5px;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    display: flex;
    align-items: center;
    gap: 6px;
}
.seal-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--amber);
}
.modal-title {
    font-size: 17px;
    font-weight: 700;
    color: var(--steel);
    line-height: 1.3;
}
.modal-header-tags {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    align-items: center;
    margin-top: 4px;
}
.modal-tag-chip {
    font-size: 11px;
    font-weight: 500;
    color: #475569;
    background: #f1f5f9;
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.modal-close {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: none;
    background: #f1f5f9;
    color: var(--muted);
    cursor: pointer;
    transition: all 0.2s ease;
    flex-shrink: 0;
}
.modal-close:hover {
    background: #e2e8f0;
    color: var(--steel);
}

.modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 22px;
}

.disclosure-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.disc-title {
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--steel);
    display: flex;
    align-items: center;
    gap: 7px;
}
.disc-title svg { color: var(--amber); }
.disc-sub {
    font-size: 12px;
    color: var(--muted);
    margin: 0;
}

/* HERO ACCOMPLISHMENT CARD */
.hero-accomplishment-card {
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 18px 20px;
}
.accomplishment-header-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 12px;
}
.disc-subheading {
    font-size: 11px;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.disc-progress-num {
    font-size: 32px;
    font-weight: 800;
    color: var(--steel);
    line-height: 1;
    margin-top: 2px;
}
.accomplishment-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    padding: 6px 12px;
    border-radius: 20px;
}
.status-delivered { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.status-progressing { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.status-delayed { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

.disc-progress-track {
    height: 10px;
    background: #cbd5e1;
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 10px;
}
.disc-progress-fill {
    height: 100%;
    border-radius: 6px;
    transition: width 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.disc-progress-meta {
    display: flex;
    justify-content: space-between;
    font-size: 11.5px;
    color: #64748b;
    flex-wrap: wrap;
    gap: 8px;
}
.text-green { color: #16a34a; }
.text-amber { color: #d97706; }

/* MODAL GRID & CARDS */
.modal-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}
.modal-info-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    background: #f8fafc;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
}
.modal-info-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    color: #64748b;
    flex-shrink: 0;
}
.modal-info-content {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
    flex: 1;
}
.modal-info-label {
    font-size: 10px;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.modal-info-val {
    font-size: 13px;
    font-weight: 600;
    color: var(--steel);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.highlight-val { color: var(--amber); }

/* ADJUSTMENTS BANNER */
.adjustments-banner {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 10px;
    padding: 12px 16px;
}
.adj-icon {
    color: #d97706;
    flex-shrink: 0;
    margin-top: 1px;
}
.adj-text {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.adj-title {
    font-size: 12px;
    font-weight: 700;
    color: #92400e;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.adj-details {
    display: flex;
    gap: 14px;
    font-size: 12px;
    color: #78350f;
    flex-wrap: wrap;
}

/* FINANCIAL CARDS */
.financial-cards-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}
.fin-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.fin-card-highlight {
    background: #fef2f2;
    border-color: #fecaca;
}
.fin-label {
    font-size: 10.5px;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.fin-amount {
    font-size: 18px;
    font-weight: 800;
    color: var(--steel);
}
.fin-card-highlight .fin-amount {
    color: var(--amber);
}
.fin-amount-text {
    font-size: 14px;
    font-weight: 700;
    color: var(--steel);
}
.fin-sub {
    font-size: 11px;
    color: var(--muted);
}

/* PROJECT DESCRIPTION */
.project-desc-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px 16px;
    font-size: 13px;
    color: #334155;
    line-height: 1.65;
}

/* MILESTONES GRID (8 STEPS) */
.milestones-header {
    margin-bottom: 4px;
}
.milestones-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
}
.milestone-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    transition: all 0.2s ease;
}
.milestone-card:hover {
    background: #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    border-color: #cbd5e1;
}
.ms-card-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2px;
}
.ms-code {
    font-size: 10px;
    font-weight: 700;
    color: #1e40af;
    background: #eff6ff;
    padding: 2px 7px;
    border-radius: 4px;
    border: 1px solid #bfdbfe;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.ms-status-badge {
    font-size: 9.5px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    text-transform: uppercase;
}
.ms-name {
    font-size: 11.5px;
    font-weight: 600;
    color: var(--steel);
    line-height: 1.3;
}
.ms-desc {
    font-size: 10px;
    color: var(--muted);
    line-height: 1.4;
}

/* REMARKS LIST */
.remarks-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.remark-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 14px;
}
.remark-bullet {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--amber);
    margin-top: 6px;
    flex-shrink: 0;
}
.remark-text {
    font-size: 12.5px;
    color: #334155;
    line-height: 1.5;
}

/* CITIZEN OVERSIGHT CARD */
.citizen-oversight-card {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border-radius: 14px;
    padding: 20px;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 18px;
    flex-wrap: wrap;
}
.oversight-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    background: rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #f87171;
    flex-shrink: 0;
}
.oversight-body {
    flex: 1;
    min-width: 240px;
}
.oversight-body h5 {
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 4px;
}
.oversight-body p {
    font-size: 12px;
    color: #cbd5e1;
    line-height: 1.5;
    margin: 0;
}
.oversight-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.btn-ask-meo {
    font-family: 'Poppins', sans-serif;
    font-size: 12.5px;
    font-weight: 600;
    background: var(--amber);
    color: #fff;
    text-decoration: none;
    padding: 8px 16px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}
.btn-ask-meo:hover {
    background: #dc2626;
    transform: translateY(-1px);
}
.btn-print-record {
    font-family: 'Poppins', sans-serif;
    font-size: 12px;
    font-weight: 500;
    background: rgba(255,255,255,0.12);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.2);
    padding: 8px 14px;
    border-radius: 8px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}
.btn-print-record:hover {
    background: rgba(255,255,255,0.22);
}

/* MODAL FOOTER */
.modal-footer-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 24px;
    border-top: 1px solid var(--border);
    background: #f8fafc;
    gap: 12px;
    flex-wrap: wrap;
}
.modal-footer-note {
    font-size: 11px;
    color: var(--muted);
}
.modal-btn-close {
    font-family: 'Poppins', sans-serif;
    font-size: 12px;
    font-weight: 600;
    background: var(--steel);
    color: #fff;
    border: none;
    padding: 8px 18px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}
.modal-btn-close:hover {
    background: #000;
}

/* PROJECTS SECTION */
.meo-projects { 
    padding: 56px 0 80px; 
    scroll-margin-top: 110px; /* Offset for fixed header when scrolling to section */
}
.projects-inner { max-width: 1200px; margin: 0 auto; padding: 0 24px; }

/* FOOTER */
.meo-footer { background: var(--steel); color: rgba(255,255,255,0.55); margin-top: 40px; }
.footer-inner { 
    max-width: 1200px; 
    margin: 0 auto; 
    padding: 32px 24px; 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    gap: 24px; 
    flex-wrap: wrap; 
}
.footer-left { display: flex; align-items: center; gap: 12px; }
.footer-logo { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; }
.footer-logo img { width: 100%; height: 100%; object-fit: contain; }
.footer-name { font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.9); }
.footer-sub { font-size: 11px; }
.footer-center { font-size: 12px; text-align: center; line-height: 1.8; }
.footer-right { font-size: 11px; }

/* RESPONSIVE BREAKPOINTS */

/* Tablet and smaller desktops */
@media (max-width: 1024px) {
    .meo-hero {
        min-height: 500px;
        margin-top: 94px;
    }
    .hero-inner {
        padding: 0 24px;
        min-height: 500px;
    }
    .hero-content {
        max-width: 600px;
        padding: 60px 0 40px;
    }
    .hero-logo {
        padding: 60px 0 40px;
    }
    .hero-logo img {
        width: 140px;
    }
    .transparency-kpi-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    .milestones-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .announcements-grid {
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    }
}

/* Tablet portrait */
@media (max-width: 768px) {
    .meo-root {
        overflow-x: hidden;
    }

    .meo-hero {
        margin-top: 64px;
    }

    .meo-announcements,
    .meo-projects {
        scroll-margin-top: 80px;
    }

    .top-bar {
        display: none;
    }
    
    .meo-nav {
        top: 0;
    }

    .nav-inner {
        padding: 0 16px;
        height: 64px;
        width: 100%;
        min-width: 0;
        gap: 12px;
    }

    .meo-nav {
        border-bottom: 1px solid rgba(226, 232, 240, 0.95);
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08);
    }

    .nav-brand {
        min-width: 0;
        max-width: calc(100% - 58px);
        flex: 1 1 auto;
    }
    
    .nav-links { display: none !important; }
    
    .mobile-menu-btn {
        display: flex;
        width: 44px;
        height: 44px;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        flex: 0 0 44px;
        transition: background 0.15s ease;
    }

    .mobile-menu-btn:active,
    .mobile-menu-btn:focus-visible {
        background: var(--light);
        outline: 2px solid rgba(185, 28, 28, 0.25);
        outline-offset: 2px;
    }
    
    .mobile-nav {
        display: flex;
        top: 64px;
        padding: 10px 16px 14px;
        gap: 3px;
        box-shadow: 0 16px 24px rgba(15, 23, 42, 0.12);
        border-radius: 0 0 14px 14px;
    }
    
    .nav-logo {
        width: 40px;
        height: 40px;
        border-radius: 10px;
    }
    
    .nav-title {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 13px;
    }

    .nav-sub {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .meo-hero {
        min-height: auto;
    }
    
    .hero-inner { 
        padding: 60px 16px 40px; 
        flex-direction: column;
        gap: 32px;
        min-height: auto;
    }
    
    .hero-content {
        max-width: 100%;
        padding: 0;
        text-align: center;
    }
    
    .hero-tag {
        margin: 0 auto 16px;
    }
    
    .hero-desc {
        margin: 0 auto 24px;
    }
    
    .hero-logo {
        padding: 0;
        gap: 16px;
    }
    
    .hero-logo img {
        width: 120px;
        height: 120px;
        object-fit: contain;
    }
    
    .hero-bg-slide img,
    .hero-background-fallback img {
        filter: blur(3px) brightness(0.5);
        transform: scale(1.15);
    }
    
    .transparency-kpi-grid { 
        grid-template-columns: repeat(2, 1fr); 
        gap: 10px;
    }
    
    .hero-title {
        font-size: 28px;
    }
    
    .hero-desc {
        font-size: 14px;
        max-width: 100%;
    }
    
    .hero-actions {
        flex-direction: column;
        width: 100%;
    }
    
    .hero-cta,
    .hero-secondary {
        width: 100%;
        justify-content: center;
        text-align: center;
    }
    
    .meo-announcements {
        padding: 40px 0 32px;
    }
    
    .announcements-inner {
        padding: 0 16px;
    }
    
    .announcements-grid {
        grid-template-columns: 1fr;
    }
    
    .section-head {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 20px;
    }
    
    .controls-row {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
        margin-bottom: 14px;
    }
    
    .search-wrap {
        width: 100%;
    }

    .filter-dropdowns {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
    }
    .select-group {
        width: 100%;
        justify-content: space-between;
    }
    .styled-select {
        flex: 1;
    }
    
    .sort-wrap {
        width: 100%;
        justify-content: space-between;
        gap: 8px;
    }
    
    .sort-select {
        flex: 1;
    }
    
    .filter-bar-wrap {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    .filter-bar {
        gap: 4px;
        padding-bottom: 4px;
        flex-wrap: nowrap;
        margin: 0 -16px;
        padding: 0 16px 8px;
        overflow-x: auto;
        scrollbar-width: thin;
    }
    
    .filter-btn {
        font-size: 11px;
        padding: 6px 10px;
    }
    
    .table-shell {
        display: block;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .meo-projects {
        padding: 40px 0 60px;
    }
    
    .projects-inner {
        padding: 0 16px;
    }
    
    .footer-inner { 
        flex-direction: column; 
        align-items: flex-start;
        padding: 24px 16px;
        gap: 16px;
    }
    
    .footer-center { 
        text-align: left; 
        font-size: 11px;
    }

    .transparency-modal-card {
        margin: 8px;
        max-height: 96vh;
    }
    .modal-grid,
    .financial-cards-grid,
    .milestones-grid {
        grid-template-columns: 1fr;
    }
    .citizen-oversight-card {
        flex-direction: column;
        align-items: flex-start;
    }
}

/* Mobile phones */
@media (max-width: 480px) {
    .nav-inner {
        padding: 0 12px;
        height: 60px;
    }

    .meo-hero {
        margin-top: 60px;
    }

    .meo-announcements,
    .meo-projects {
        scroll-margin-top: 76px;
    }

    .mobile-nav {
        top: 60px;
        padding-left: 12px;
        padding-right: 12px;
    }
    
    .nav-brand {
        gap: 8px;
        max-width: calc(100% - 56px);
    }
    
    .nav-logo {
        width: 36px;
        height: 36px;
    }
    
    .nav-title { 
        font-size: 12px; 
        line-height: 1.2;
    }
    
    .nav-sub { font-size: 10px; }
    
    .hero-inner { 
        padding: 28px 12px 24px; 
        gap: 24px;
    }
    
    .hero-logo img {
        width: 85px;
        height: 85px;
        object-fit: contain;
    }
    
    .hero-tag {
        font-size: 10px;
        padding: 4px 10px;
    }
    
    .hero-title {
        font-size: 24px;
    }
    
    .hero-desc {
        font-size: 13px;
    }
    
    .hero-cta,
    .hero-secondary {
        padding: 10px 16px;
        font-size: 13px;
    }
    
    .meo-announcements {
        padding: 32px 0 24px;
    }
    
    .announcements-inner {
        padding: 0 12px;
    }
    
    .section-title { font-size: 18px; }
    
    .section-sub { font-size: 12px; }
    
    .search-wrap {
        padding: 7px 12px;
    }
    
    .search-input {
        font-size: 12px;
    }
    
    .filter-btn {
        font-size: 10px;
        padding: 5px 8px;
    }
    
    .filter-count {
        font-size: 9px;
        padding: 1px 4px;
    }

    .meo-projects {
        padding: 32px 0 48px;
    }
    
    .projects-inner {
        padding: 0 12px;
    }
    
    .footer-inner {
        padding: 20px 12px;
        gap: 12px;
    }
    
    .footer-name { font-size: 12px; }
    .footer-sub { font-size: 10px; }
    .footer-center { font-size: 10px; }
    .footer-right { font-size: 10px; }
    
    .modal-header {
        padding: 16px;
    }
    .modal-title { font-size: 15px; }
    .modal-body {
        padding: 16px;
        gap: 16px;
    }
    .disc-progress-num {
        font-size: 26px;
    }
    .hero-accomplishment-card {
        padding: 14px;
    }
}

/* ACHIEVEMENTS / COMPLETED PROJECTS EXHIBITION GALLERY */
.meo-achievements {
    padding: 42px 0 54px;
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 50%, #f8fafc 100%);
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    position: relative;
    overflow: hidden;
}

.meo-achievements::before {
    content: '';
    position: absolute;
    top: 0;
    left: 20%;
    width: 600px;
    height: 400px;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.05) 0%, transparent 70%);
    pointer-events: none;
}

.achievements-inner {
    max-width: 1240px;
    margin: 0 auto;
    padding: 0 20px;
    position: relative;
    z-index: 2;
}

/* Gallery Header */
.ach-gallery-header {
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin-bottom: 18px;
}

@media (min-width: 768px) {
    .ach-gallery-header {
        flex-direction: row;
        align-items: flex-end;
        justify-content: space-between;
    }
}

.ach-header-left {
    max-width: 680px;
}

.ach-header-left .section-title {
    font-size: 24px;
    line-height: 1.25;
    margin-bottom: 4px;
}

.ach-header-left .section-sub {
    font-size: 13px;
    line-height: 1.5;
    color: #64748b;
}

.section-badge-emerald {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #047857 !important;
    background: #ecfdf5 !important;
    border: 1px solid #a7f3d0 !important;
    padding: 3px 10px;
    border-radius: 9999px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 6px;
}

.ach-live-pulse {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: achPulse 2s infinite;
}

@keyframes achPulse {
    0% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    }
    70% {
        box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
    }
}

.ach-header-controls {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.ach-counter-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.ach-counter-num {
    font-size: 12.5px;
    font-weight: 800;
    color: #047857;
    font-variant-numeric: tabular-nums;
}

.ach-counter-text {
    font-size: 10px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

/* Year Filter Dropdown */
.ach-year-filter-wrapper {
    position: relative;
    display: inline-flex;
    align-items: center;
}

.ach-year-select-box {
    position: relative;
    display: inline-flex;
    align-items: center;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0 20px 0 26px;
    height: 29px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    transition: all 0.2s ease;
}

.ach-year-select-box:hover {
    border-color: #cbd5e1;
}

.ach-year-icon {
    position: absolute;
    left: 8px;
    color: #64748b;
    pointer-events: none;
}

.ach-year-select {
    appearance: none;
    background: transparent;
    border: none;
    font-family: inherit;
    font-size: 11px;
    font-weight: 700;
    color: #0f172a;
    cursor: pointer;
    outline: none;
    padding: 0;
}

.ach-select-arrow {
    position: absolute;
    right: 7px;
    color: #94a3b8;
    pointer-events: none;
}

/* View Switcher */
.ach-view-switcher {
    display: inline-flex;
    padding: 2px;
    background: #e2e8f0;
    border-radius: 10px;
}

.ach-view-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 9px;
    border: none;
    background: transparent;
    color: #64748b;
    border-radius: 8px;
    font-size: 10.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.ach-view-btn:hover {
    color: #0f172a;
}

.ach-view-btn.active {
    background: #ffffff;
    color: #0f172a;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}

/* Category Filter Pill Bar */
.achievements-filter-bar {
    display: flex;
    flex-wrap: nowrap;
    overflow-x: auto;
    gap: 6px;
    padding-bottom: 6px;
    margin-bottom: 18px;
    scrollbar-width: thin;
}

.ach-filter-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    white-space: nowrap;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}

.ach-filter-pill:hover {
    border-color: #cbd5e1;
    color: #0f172a;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
}

.ach-filter-pill.active {
    background: #0f172a;
    border-color: #0f172a;
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.2);
}

.ach-pill-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.ach-pill-count {
    padding: 1px 6px;
    border-radius: 9999px;
    font-size: 9.5px;
    font-weight: 700;
    background: #f1f5f9;
    color: #475569;
    transition: all 0.2s ease;
}

.ach-filter-pill.active .ach-pill-count {
    background: rgba(255, 255, 255, 0.2);
    color: #ffffff;
}

/* Smooth Category Shuffle Transitions */
.gallery-card-enter-active,
.gallery-card-leave-active {
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

.gallery-card-enter-from {
    opacity: 0;
    transform: scale(0.94) translateY(12px);
}

.gallery-card-leave-to {
    opacity: 0;
    transform: scale(0.94) translateY(12px);
}

.gallery-card-leave-active {
    position: absolute;
    pointer-events: none;
    z-index: 0;
}

.gallery-card-move {
    transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

/* Gallery Stage & Card Layouts */
.achievements-gallery-stage {
    display: grid;
    gap: 16px;
    position: relative;
    transition: all 0.3s ease;
}

/* Grid View */
.achievements-gallery-stage.view-grid {
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
}

/* Mosaic View */
.achievements-gallery-stage.view-mosaic {
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
}

@media (min-width: 1024px) {
    .achievements-gallery-stage.view-mosaic {
        grid-template-columns: repeat(3, 1fr);
    }
    .achievements-gallery-stage.view-mosaic .is-hero-card {
        grid-column: span 2;
    }
}

/* Gallery Item Card */
.gallery-item-card {
    position: relative;
    border-radius: 14px;
    overflow: hidden;
    background: #0f172a;
    box-shadow: 0 4px 14px -3px rgba(15, 23, 42, 0.1), 0 1px 3px -1px rgba(0, 0, 0, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    cursor: pointer;
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
    transform: translateY(0);
    will-change: transform, box-shadow;
}

.gallery-item-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: -120%;
    width: 60%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.15),
        transparent
    );
    transform: skewX(-20deg);
    transition: left 0.65s ease;
    pointer-events: none;
    z-index: 5;
}

.gallery-item-card:hover::after {
    left: 150%;
}

.gallery-item-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 14px 28px -6px rgba(15, 23, 42, 0.22), 0 0 0 1px rgba(16, 185, 129, 0.5);
}

.gallery-item-card:active {
    transform: translateY(-1px) scale(0.99);
}

.gallery-item-card.cat-achievement:hover {
    box-shadow: 0 14px 25px -6px rgba(217, 119, 6, 0.2), 0 0 0 1px rgba(245, 158, 11, 0.5);
}

.gallery-item-card.cat-turnover:hover {
    box-shadow: 0 14px 25px -6px rgba(37, 99, 235, 0.2), 0 0 0 1px rgba(59, 130, 246, 0.5);
}

.gallery-item-card.cat-milestone:hover {
    box-shadow: 0 14px 25px -6px rgba(124, 58, 237, 0.2), 0 0 0 1px rgba(139, 92, 246, 0.5);
}

/* Image Container */
.g-img-container {
    position: relative;
    width: 100%;
    height: 220px;
    overflow: hidden;
    background: #090d16;
}

.view-mosaic .is-hero-card .g-img-container {
    height: 260px;
}

@media (max-width: 640px) {
    .g-img-container,
    .view-mosaic .is-hero-card .g-img-container {
        height: 190px;
    }
}

.g-photo {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1), filter 0.5s ease;
    filter: brightness(0.95);
}

.gallery-item-card:hover .g-photo {
    transform: scale(1.06);
    filter: brightness(1.03);
}

/* Ambient Gradient Overlay */
.g-photo-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        180deg,
        rgba(15, 23, 42, 0.3) 0%,
        rgba(15, 23, 42, 0.05) 30%,
        rgba(15, 23, 42, 0.5) 60%,
        rgba(15, 23, 42, 0.95) 100%
    );
    pointer-events: none;
    transition: opacity 0.3s ease;
}

.gallery-item-card:hover .g-photo-overlay {
    background: linear-gradient(
        180deg,
        rgba(15, 23, 42, 0.35) 0%,
        rgba(15, 23, 42, 0.1) 30%,
        rgba(15, 23, 42, 0.6) 60%,
        rgba(15, 23, 42, 0.98) 100%
    );
}

/* Floating Top Bar */
.g-top-bar {
    position: absolute;
    top: 10px;
    left: 10px;
    right: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    z-index: 4;
}

.g-category-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3.5px 8px;
    border-radius: 9999px;
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #ffffff;
    backdrop-filter: blur(8px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.25);
}

.g-tag-emerald { background: linear-gradient(135deg, rgba(5, 150, 105, 0.9), rgba(16, 185, 129, 0.85)); }
.g-tag-gold { background: linear-gradient(135deg, rgba(217, 119, 6, 0.9), rgba(245, 158, 11, 0.85)); }
.g-tag-blue { background: linear-gradient(135deg, rgba(37, 99, 235, 0.9), rgba(59, 130, 246, 0.85)); }
.g-tag-purple { background: linear-gradient(135deg, rgba(124, 58, 237, 0.9), rgba(139, 92, 246, 0.85)); }

.g-tag-dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: #ffffff;
}

.g-year-badge {
    padding: 3px 7px;
    border-radius: 9999px;
    font-size: 9px;
    font-weight: 700;
    color: #f8fafc;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(6px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.15);
}

/* Floating Zoom Action Icon */
.g-zoom-trigger {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) scale(0.7);
    opacity: 0;
    pointer-events: none;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    z-index: 3;
}

.gallery-item-card:hover .g-zoom-trigger {
    opacity: 1;
    transform: translate(-50%, -50%) scale(1);
}

.g-zoom-circle {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.92);
    color: #0f172a;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
    backdrop-filter: blur(6px);
}

/* Bottom Caption Drawer */
.g-caption-drawer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 12px 14px;
    z-index: 4;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.g-location-chip {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 10px;
    font-weight: 600;
    color: #a7f3d0;
    letter-spacing: 0.02em;
}

.g-card-title {
    font-size: 13px;
    font-weight: 700;
    color: #ffffff;
    line-height: 1.3;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
}

.view-mosaic .is-hero-card .g-card-title {
    font-size: 15px;
}

.g-card-caption {
    font-size: 10.5px;
    color: #cbd5e1;
    line-height: 1.4;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    opacity: 0.9;
}

.g-card-footer {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 10px;
    font-weight: 700;
    color: #34d399;
    margin-top: 2px;
    opacity: 0;
    transform: translateY(3px);
    transition: all 0.2s ease;
}

.gallery-item-card:hover .g-card-footer {
    opacity: 1;
    transform: translateY(0);
}

/* Empty State */
.ach-empty-gallery {
    text-align: center;
    padding: 40px 16px;
    background: #ffffff;
    border: 2px dashed #e2e8f0;
    border-radius: 14px;
    margin-top: 8px;
}

.ach-empty-icon {
    width: 48px;
    height: 48px;
    margin: 0 auto 12px;
    border-radius: 50%;
    background: #ecfdf5;
    color: #059669;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ach-empty-gallery h4 {
    font-size: 14px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 4px;
}

.ach-empty-gallery p {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 14px;
}

.ach-reset-filter-btn {
    display: inline-flex;
    align-items: center;
    padding: 7px 16px;
    border-radius: 9999px;
    background: #047857;
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(4, 120, 87, 0.25);
    transition: all 0.2s ease;
}

.ach-reset-filter-btn:hover {
    background: #065f46;
    transform: translateY(-1px);
}

/* ACHIEVEMENTS GALLERY PAGINATION */
.ach-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    margin-top: 18px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    gap: 12px;
    flex-wrap: wrap;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
}

.ach-pagination-info {
    font-size: 11.5px;
    color: #64748b;
}

.ach-pagination-info strong {
    color: #047857;
    font-weight: 700;
}

.ach-pagination-controls {
    display: flex;
    align-items: center;
    gap: 4px;
}

.ach-pg-numbers {
    display: flex;
    align-items: center;
    gap: 3px;
}

.ach-pg-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 30px;
    min-width: 30px;
    padding: 0 8px;
    border-radius: 7px;
    font-family: inherit;
    font-size: 11px;
    font-weight: 600;
    color: #475569;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    user-select: none;
}

.ach-pg-btn:hover:not(:disabled) {
    background: #ecfdf5;
    border-color: #a7f3d0;
    color: #047857;
    transform: translateY(-1px);
}

.ach-pg-btn:active:not(:disabled) {
    transform: translateY(0);
}

.ach-pg-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    background: #f8fafc;
    border-color: #e2e8f0;
}

.ach-pg-nav {
    gap: 4px;
    padding: 0 9px;
}

.ach-pg-num.active {
    background: #047857;
    border-color: #047857;
    color: #ffffff;
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(4, 120, 87, 0.25);
}

.ach-pg-ellipsis {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 30px;
    font-size: 12px;
    font-weight: 700;
    color: #94a3b8;
}

@media (max-width: 640px) {
    .ach-pagination {
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 10px;
        padding: 12px;
    }
    .ach-pagination-controls {
        flex-wrap: wrap;
        justify-content: center;
    }
    .ach-pg-btn {
        height: 28px;
        min-width: 28px;
        font-size: 10.5px;
    }
}

/* FULLSCREEN EXHIBITION CINEMA LIGHTBOX */
.ach-exhibit-backdrop {
    background: rgba(8, 12, 22, 0.9) !important;
    backdrop-filter: blur(16px) !important;
    padding: 16px;
}

.ach-cinema-modal {
    max-width: 1000px;
    width: 100%;
    background: #0f172a;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.6);
    display: flex;
    flex-direction: column;
    max-height: 94vh;
    animation: modalPop 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalPop {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(10px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.ach-cinema-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 22px;
    background: rgba(15, 23, 42, 0.95);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.ach-cinema-agency {
    display: flex;
    align-items: center;
    gap: 8px;
}

.ach-agency-name {
    font-size: 11.5px;
    font-weight: 600;
    color: #94a3b8;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.ach-cinema-top-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.ach-cinema-counter {
    font-size: 11.5px;
    font-weight: 700;
    color: #34d399;
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid rgba(16, 185, 129, 0.3);
    padding: 4px 10px;
    border-radius: 9999px;
}

.ach-cinema-close-btn {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.ach-cinema-close-btn:hover {
    background: rgba(239, 68, 68, 0.85);
    border-color: rgba(239, 68, 68, 1);
    transform: rotate(90deg);
}

/* Lightbox Stage Body */
.ach-cinema-body {
    position: relative;
    width: 100%;
    background: #060911;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    min-height: 320px;
    max-height: 60vh;
}

.ach-cinema-stage {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 12px;
}

.ach-cinema-img {
    max-width: 100%;
    max-height: 56vh;
    object-fit: contain;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

/* Lightbox Photo Slide Transitions */
.cinema-slide-enter-active,
.cinema-slide-leave-active {
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.cinema-slide-enter-from {
    opacity: 0;
    transform: scale(0.95) translateX(20px);
}

.cinema-slide-leave-to {
    opacity: 0;
    transform: scale(0.95) translateX(-20px);
}

/* Arrow Navigation */
.ach-nav-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(15, 23, 42, 0.75);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 10;
    backdrop-filter: blur(10px);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4);
}

.ach-nav-prev { left: 16px; }
.ach-nav-next { right: 16px; }

.ach-nav-arrow:hover {
    background: #10b981;
    border-color: #34d399;
    transform: translateY(-50%) scale(1.1);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5);
}

/* Cinema Info Bottom Panel */
.ach-cinema-info {
    padding: 20px 24px;
    background: #0f172a;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    flex-direction: column;
    gap: 14px;
    overflow-y: auto;
}

.ach-info-tags {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.ach-info-title {
    font-size: 18px;
    font-weight: 700;
    color: #ffffff;
    line-height: 1.35;
    margin: 8px 0 4px;
}

.ach-info-caption {
    font-size: 13.5px;
    color: #cbd5e1;
    line-height: 1.6;
    margin: 0;
}

.ach-info-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 12px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.ach-hint-text {
    font-size: 11px;
    color: #64748b;
    font-weight: 500;
}

.ach-btn-cinema-close {
    padding: 7px 18px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #ffffff;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.ach-btn-cinema-close:hover {
    background: rgba(255, 255, 255, 0.2);
}

@media (max-width: 640px) {
    .ach-cinema-modal {
        max-height: 98vh;
        border-radius: 16px;
    }
    .ach-cinema-body {
        max-height: 48vh;
    }
    .ach-cinema-info {
        padding: 16px;
    }
    .ach-info-title {
        font-size: 15px;
    }
    .ach-nav-arrow {
        width: 36px;
        height: 36px;
    }
    .ach-nav-prev { left: 8px; }
    .ach-nav-next { right: 8px; }
}

/* Very small screens */
@media (max-width: 360px) {
    .nav-sub { display: none; }
    .nav-title { max-width: 145px; }
    .mobile-menu-btn {
        width: 40px;
        height: 40px;
    }
}

/* Touch-friendly improvements */
@media (hover: none) and (pointer: coarse) {
    .nav-link,
    .filter-btn,
    .sort-btn,
    .details-btn,
    .btn-ask-meo,
    .btn-print-record,
    .modal-btn-close {
        min-height: 42px;
        min-width: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    
    .p-row {
        min-height: 44px;
    }
}

/* Print styles */
@media print {
    .meo-nav,
    .top-bar,
    .hero-actions,
    .filter-bar-wrap,
    .controls-row,
    .mobile-menu-btn,
    .mobile-nav,
    .details-btn,
    .btn-ask-meo,
    .btn-print-record,
    .modal-btn-close,
    .modal-close,
    .meo-footer,
    .meo-announcements {
        display: none !important;
    }
    
    .meo-root {
        background: #fff;
        color: #000;
    }
    
    .meo-hero {
        margin-top: 0;
        break-inside: avoid;
        display: none;
    }
    
    .modal-backdrop {
        position: static;
        background: none;
        padding: 0;
    }
    .transparency-modal-card {
        max-width: 100%;
        box-shadow: none;
        border: 1px solid #000;
    }
    
    .table-shell {
        overflow-x: visible;
        display: block !important;
        border: 1px solid #000;
    }
    
    .project-table {
        font-size: 10px;
        min-width: auto;
    }
}
</style>
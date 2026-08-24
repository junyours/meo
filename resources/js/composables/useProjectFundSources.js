import { computed, ref } from 'vue';

export const DEFAULT_FUND_CATEGORIES = {
    national: {
        label: 'National',
        color: 'blue',
        sources: [],
    },
    provincial: {
        label: 'Provincial',
        color: 'green',
        sources: [],
    },
    lgu: {
        label: 'LGU',
        color: 'purple',
        sources: [],
    },
};

export function normalizeFundCategory(category) {
    if (!category) {
        return 'national';
    }

    const key = String(category).toLowerCase();

    return DEFAULT_FUND_CATEGORIES[key] ? key : 'national';
}

export function useProjectFundSources(fundSourcesRoute) {
    const fundSources = ref({});
    const fundCategories = ref(structuredClone(DEFAULT_FUND_CATEGORIES));
    const isLoadingSources = ref(false);

    const fundCategoryOptions = computed(() =>
        Object.keys(DEFAULT_FUND_CATEGORIES).map((key) => ({
            value: key,
            label: DEFAULT_FUND_CATEGORIES[key].label,
        }))
    );

    const mergeGroupedSources = (groupedSources) => {
        const merged = structuredClone(DEFAULT_FUND_CATEGORIES);

        Object.entries(groupedSources).forEach(([category, sources]) => {
            if (!merged[category]) {
                merged[category] = {
                    label: category.charAt(0).toUpperCase() + category.slice(1),
                    color: 'gray',
                    sources: [],
                };
            }

            merged[category].sources = [...new Set(sources)].sort();
        });

        fundCategories.value = merged;
    };

    const loadAllFundSources = async () => {
        isLoadingSources.value = true;

        try {
            const { data } = await window.axios.get(route(fundSourcesRoute));
            const groupedSources = {};

            if (Array.isArray(data.sources)) {
                data.sources.forEach((item) => {
                    if (typeof item === 'string') {
                        return;
                    }

                    if (item.category && item.source) {
                        if (!groupedSources[item.category]) {
                            groupedSources[item.category] = [];
                        }

                        groupedSources[item.category].push(item.source);
                    }
                });
            }

            Object.keys(groupedSources).forEach((category) => {
                groupedSources[category] = [...new Set(groupedSources[category])].sort();
            });

            fundSources.value = groupedSources;
            mergeGroupedSources(groupedSources);
        } catch (error) {
            console.error('Error loading fund sources:', error);
            fundSources.value = {};
            fundCategories.value = structuredClone(DEFAULT_FUND_CATEGORIES);
        } finally {
            isLoadingSources.value = false;
        }
    };

    const fetchFundSources = async (category = null) => {
        isLoadingSources.value = true;

        try {
            const params = category ? { category } : {};
            const { data } = await window.axios.get(route(fundSourcesRoute, params));

            if (!Array.isArray(data.sources)) {
                return [];
            }

            if (category) {
                return data.sources.map((item) => (typeof item === 'string' ? item : item.source)).filter(Boolean);
            }

            return data.sources;
        } catch (error) {
            console.error('Error fetching fund sources:', error);
            return [];
        } finally {
            isLoadingSources.value = false;
        }
    };

    const loadSourcesForCategory = async (category) => {
        const normalizedCategory = normalizeFundCategory(category);
        const sources = await fetchFundSources(normalizedCategory);
        fundSources.value[normalizedCategory] = sources;

        if (fundCategories.value[normalizedCategory]) {
            fundCategories.value[normalizedCategory].sources = [...sources];
        }

        return sources;
    };

    const addLocalFundSource = (category, source) => {
        const normalizedCategory = normalizeFundCategory(category);
        const trimmedSource = source.trim();

        if (!trimmedSource) {
            return false;
        }

        const existingSources = fundSources.value[normalizedCategory] || [];

        if (existingSources.includes(trimmedSource)) {
            return false;
        }

        fundSources.value[normalizedCategory] = [...existingSources, trimmedSource].sort();

        if (fundCategories.value[normalizedCategory]) {
            fundCategories.value[normalizedCategory].sources = [...fundSources.value[normalizedCategory]];
        }

        return true;
    };

    const getSourcesForCategory = (category, currentSource = '', includeCurrent = false) => {
        const normalizedCategory = normalizeFundCategory(category);
        const sources = [...(fundSources.value[normalizedCategory] || [])];

        if (includeCurrent && currentSource && currentSource !== '__custom' && !sources.includes(currentSource)) {
            sources.push(currentSource);
        }

        return sources.sort();
    };

    return {
        fundSources,
        fundCategories,
        isLoadingSources,
        fundCategoryOptions,
        loadAllFundSources,
        fetchFundSources,
        loadSourcesForCategory,
        addLocalFundSource,
        getSourcesForCategory,
    };
}

export function resolveSourceOfFund(sourceOfFund, customSourceInput = '') {
    if (sourceOfFund === '__custom') {
        return customSourceInput.trim();
    }

    return (sourceOfFund || '').trim();
}

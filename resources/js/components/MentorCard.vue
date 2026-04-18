<template>
    <router-link :to="{ name: 'mentorat.mentor', params: { id: mentor.id } }"
        class="block bg-white rounded-2xl border border-slate-100 overflow-hidden hover:border-violet-200 hover:shadow-md transition group">
        <!-- Header gradient -->
        <div class="h-20 bg-gradient-to-br from-violet-500 via-purple-500 to-fuchsia-500 relative">
            <div class="absolute -bottom-8 left-5">
                <div class="w-16 h-16 rounded-full bg-white border-4 border-white shadow-sm flex items-center justify-center text-2xl font-black text-violet-600">
                    {{ initials }}
                </div>
            </div>
            <div v-if="mentor.is_diaspora" class="absolute top-3 right-3 text-xs font-semibold px-2 py-0.5 rounded-md bg-white/90 text-rose-700">
                Diaspora
            </div>
        </div>

        <div class="pt-10 px-5 pb-5">
            <h3 class="font-bold text-lg text-slate-900 group-hover:text-violet-700 transition line-clamp-1">
                {{ mentor.name }}
            </h3>
            <p class="text-sm text-slate-500 mt-0.5">{{ mentor.country }}{{ mentor.city ? ', ' + mentor.city : '' }}</p>
            <p v-if="mentor.bio" class="text-sm text-slate-600 mt-2 line-clamp-2">{{ mentor.bio }}</p>

            <!-- Skills -->
            <div v-if="mentor.skills?.length" class="flex flex-wrap gap-1 mt-3">
                <span v-for="s in mentor.skills.slice(0, 4)" :key="s.id"
                    class="text-[10px] px-2 py-0.5 rounded-full font-medium"
                    :class="levelClass(s.pivot?.level)">
                    {{ s.name }}
                </span>
                <span v-if="mentor.skills.length > 4" class="text-[10px] text-slate-400 self-center">
                    +{{ mentor.skills.length - 4 }}
                </span>
            </div>

            <!-- Stats row -->
            <div class="flex items-center gap-4 mt-4 text-xs text-slate-500">
                <span v-if="mentor.avg_rating > 0" class="flex items-center gap-1">
                    <span class="text-amber-500">★</span>
                    {{ parseFloat(mentor.avg_rating).toFixed(1) }}
                    <span class="text-slate-400">({{ mentor.reviews_count }})</span>
                </span>
                <span v-if="mentor.mentorships_completed_count > 0" class="flex items-center gap-1">
                    ✅ {{ mentor.mentorships_completed_count }} terminé{{ mentor.mentorships_completed_count > 1 ? 's' : '' }}
                </span>
                <span v-if="mentor.mentorships_active_count > 0" class="flex items-center gap-1">
                    🔄 {{ mentor.mentorships_active_count }} en cours
                </span>
            </div>

            <!-- Availability indicator -->
            <div v-if="mentor.mentor_availabilities?.length" class="mt-3 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-xs text-emerald-700 font-medium">Disponible</span>
            </div>
            <div v-else class="mt-3 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-slate-300"></span>
                <span class="text-xs text-slate-400">Créneaux non renseignés</span>
            </div>
        </div>
    </router-link>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({ mentor: { type: Object, required: true } });

const initials = computed(() => {
    const parts = (props.mentor.name || '').split(' ');
    return parts.map(p => p.charAt(0).toUpperCase()).slice(0, 2).join('');
});

function levelClass(level) {
    return {
        expert: 'bg-violet-100 text-violet-700',
        advanced: 'bg-blue-50 text-blue-700',
        intermediate: 'bg-emerald-50 text-emerald-700',
        beginner: 'bg-slate-100 text-slate-600',
    }[level] || 'bg-slate-100 text-slate-600';
}
</script>
